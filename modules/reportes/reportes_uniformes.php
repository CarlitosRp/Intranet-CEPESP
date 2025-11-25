<?php
// modules/reportes/reportes_uniformes.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';

// ── Seguridad básica
if (!auth_is_logged_in()) {
    header('Location: ' . BASE_URL . 'modules/auth/login.php?next=' . urlencode(BASE_URL . 'modules/reportes/reportes_uniformes.php'));
    exit;
}

// ── Filtros
$q         = trim($_GET['q'] ?? '');
$desde     = trim($_GET['desde'] ?? '');
$hasta     = trim($_GET['hasta'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$id_emp    = (int)($_GET['id_empleado'] ?? 0);

// Normaliza fechas Y-m-d
$fmt = 'Y-m-d';
$desde_sql = $desde ? date($fmt, strtotime($desde)) : null;
$hasta_sql = $hasta ? date($fmt, strtotime($hasta)) : null;

// ── Datos catálogo (categorías, empleados)
$cats = db_select_all("SELECT DISTINCT categoria FROM equipo ORDER BY categoria");
$emps = db_select_all("SELECT id_empleado, nombre_completo, no_empleado FROM empleados ORDER BY nombre_completo");

// ── Query principal
$sql = "
SELECT
  s.fecha,
  e.no_empleado,
  e.nombre_completo AS empleado,
  it.categoria,
  it.descripcion   AS producto,
  v.talla,
  sd.cantidad
FROM salidas_detalle sd
JOIN salidas s          ON s.id_salida = sd.id_salida
JOIN empleados e        ON e.id_empleado = s.id_empleado
JOIN item_variantes v   ON v.id_variante = sd.id_variante
JOIN equipo it          ON it.id_equipo = v.id_equipo
WHERE 1=1
";

$params = [];
$types  = '';

// Filtro texto (en empleado / no_empleado / producto / talla)
if ($q !== '') {
    $sql .= " AND ( e.nombre_completo LIKE ? OR e.no_empleado LIKE ? OR it.descripcion LIKE ? OR v.talla LIKE ? )";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
// Fechas
if ($desde_sql) {
    $sql .= " AND s.fecha >= ?";
    $params[] = $desde_sql;
    $types .= 's';
}
if ($hasta_sql) {
    $sql .= " AND s.fecha <= ?";
    $params[] = $hasta_sql;
    $types .= 's';
}
// Categoría
if ($categoria !== '') {
    $sql .= " AND it.categoria = ?";
    $params[] = $categoria;
    $types .= 's';
}
// Empleado
if ($id_emp > 0) {
    $sql .= " AND e.id_empleado = ?";
    $params[] = $id_emp;
    $types .= 'i';
}

$sql .= " ORDER BY s.fecha DESC, e.nombre_completo ASC, it.categoria ASC, it.descripcion ASC, v.talla ASC";

$rows = db_select($sql, $params, $types); // usa tu helper preparado

// Para “Exportar CSV” simple (opcional):
$qsCsv = http_build_query(['q' => $q, 'desde' => $desde, 'hasta' => $hasta, 'categoria' => $categoria, 'id_empleado' => $id_emp]);

/*render_breadcrumb([
    ['label' => 'Reportes'],
    ['label' => 'Uniformes']
]);*/
?>

<div class="container my-4 exist-page">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Reporte · Entrega de uniformes</h1>
        <small class="text-muted"><?= htmlspecialchars(date('Y-m-d H:i')) ?></small>
    </div>

    <!-- Filtros -->
    <form class="row g-2 mb-3 no-print d-flex flex-row align-items-center" method="get" action="">
        <div class="col-md-3">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control"
                placeholder="Empleado, No. emp., producto o talla">
        </div>
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-select">
                <option value="">— Todas —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= htmlspecialchars($c['categoria']) ?>" <?= $categoria === $c['categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['categoria']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Empleado</label>
            <select name="id_empleado" class="form-select">
                <option value="0">— Todos —</option>
                <?php foreach ($emps as $e): ?>
                    <option value="<?= (int)$e['id_empleado'] ?>" <?= $id_emp === (int)$e['id_empleado'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre_completo']) ?> (<?= htmlspecialchars($e['no_empleado']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-1 col-md-auto d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Ejecutar</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Listado de entregas</span>
            <!-- Toolbar destino de los botones de DataTables (NO duplicar) -->
            <div id="toolbar-uniformes" class="btn-group btn-group-sm no-print" role="group" aria-label="Acciones del reporte"></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="dt-uniformes" class="table table-striped table-bordered table-sm table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>FECHA</th>
                            <th>No. EMPLEADO</th>
                            <th>NOMBRE</th>
                            <th>CATEGORIA</th>
                            <th>DESCRIPCION</th>
                            <th>TALLA</th>
                            <th class="text-end">CANTIDAD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['fecha']) ?></td>
                                <td><?= htmlspecialchars($r['no_empleado']) ?></td>
                                <td><?= htmlspecialchars($r['empleado']) ?></td>
                                <td><?= htmlspecialchars($r['categoria']) ?></td>
                                <td><?= htmlspecialchars($r['producto']) ?></td>
                                <td><?= htmlspecialchars($r['talla']) ?></td>
                                <td class="text-end"><?= (int)$r['cantidad'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Carga los assets locales de DataTables + Buttons (Bootstrap 5)
// Este include debe inyectar CSS/JS: dataTables.bootstrap5.min.css/js,
// buttons.bootstrap5.min.css/js, buttons.html5.min.js, buttons.print.min.js, jszip, pdfmake, vfs_fonts, responsive, etc.
require_once __DIR__ . '/../../includes/datatables_assets.php';
require_once __DIR__ . '/../../includes/pie_institucional.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dt = $('#dt-uniformes').DataTable({
            dom: 't<"row g-2 align-items-center mt-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>',
            responsive: true,
            pageLength: 25,
            order: [
                [0, 'desc'], // Fecha
                [2, 'asc'] // Empleado
            ],
            language: {
                url: "<?= BASE_URL ?>assets/js/datatables/i18n/es-MX.json"
            },
            columnDefs: [{
                targets: [6], // Cantidad
                className: 'text-end' // alineada a la derecha
            }]
        });

        new $.fn.dataTable.Buttons(dt, {
            buttons: [{
                    extend: 'excelHtml5',
                    text: 'Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)',
                        columns: ':visible' // se exportan todas (no hay no-export)
                    },
                    title: 'Reporte Resguardos Uniformes'
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)',
                        columns: ':visible'
                    },
                    title: 'Reporte Resguardos Uniformes',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    customize: function(doc) {
                        // PDF oficial CEPESP
                        dt_standard_pdf_customization(doc, 'Reporte Resguardos Uniformes');
                    }
                },
                /*{
                    extend: 'print',
                    text: 'Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)',
                        columns: ':visible'
                    },
                    title: 'Reporte Resguardos Uniformes',
                    customize: dt_standard_print_customization
                },*/
                {
                    extend: 'colvis',
                    text: 'Columnas',
                    className: 'btn btn-outline-dark btn-sm'
                }
            ]
        });

        dt.buttons().container().appendTo('#toolbar-uniformes');
    });
</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>