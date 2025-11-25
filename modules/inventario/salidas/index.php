<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
auth_require_login();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
require_once __DIR__ . '/../../../includes/datatables_assets.php';

$q      = trim($_GET['q'] ?? '');
$desde  = trim($_GET['desde'] ?? '');
$hasta  = trim($_GET['hasta'] ?? '');
$id_emp = (int)($_GET['id_empleado'] ?? 0);

$fmt = 'Y-m-d';
$desde_sql = $desde ? date($fmt, strtotime($desde)) : null;
$hasta_sql = $hasta ? date($fmt, strtotime($hasta)) : null;

$empleados = db_select("
  SELECT id_empleado, nombre_completo, no_empleado
  FROM empleados
  ORDER BY nombre_completo
");

// ===================== Datos (agrupado) =====================
// Mostramos una fila por salida con totales (partidas y piezas) y si ya hay resguardo
$sql = "
SELECT
  s.id_salida,
  s.fecha,
  e.no_empleado,
  e.nombre_completo AS empleado,
  COALESCE(COUNT(DISTINCT sd.id_detalle_salida), 0) AS partidas,
  COALESCE(SUM(sd.cantidad), 0)              AS piezas,
  r.id_resguardo,
  r.anio,
  r.folio
FROM salidas s
JOIN empleados e      ON e.id_empleado = s.id_empleado
LEFT JOIN salidas_detalle sd ON sd.id_salida = s.id_salida
LEFT JOIN resguardos r       ON r.id_salida = s.id_salida
WHERE 1=1
";

$params = [];
$types  = '';

// Texto libre: busca por empleado/no_empleado/observaciones
if ($q !== '') {
    $sql .= " AND (e.nombre_completo LIKE ? OR e.no_empleado LIKE ? OR s.observaciones LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
    $types .= 'sss';
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
// Empleado
if ($id_emp > 0) {
    $sql .= " AND s.id_empleado = ?";
    $params[] = $id_emp;
    $types .= 'i';
}

$sql .= "
GROUP BY
  s.id_salida, s.fecha, e.no_empleado, e.nombre_completo, r.id_resguardo, r.anio, r.folio
ORDER BY s.fecha DESC, s.id_salida DESC
";

$rows = db_select($sql, $params, $types);

/*render_breadcrumb([
    ['label' => 'Inventario', 'href' => BASE_URL . 'modules/inventario/'],
    ['label' => 'Salidas']
]);*/

?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Salidas de Inventario</h1>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(BASE_URL . 'modules/inventario/salidas/crear.php') ?>">
            Nueva salida
        </a>
    </div>

    <!-- Filtros (no imprimibles) -->
    <form class="row g-2 mb-3 no-print d-flex flex-row align-items-center" method="get" action="index.php">
        <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Empleado, No. emp. u observaciones">
        </div>
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Empleado</label>
            <select name="id_empleado" class="form-select">
                <option value="0">— Todos —</option>
                <?php foreach ($empleados as $e): ?>
                    <option value="<?= (int)$e['id_empleado'] ?>" <?= $id_emp === (int)$e['id_empleado'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre_completo']) ?> (<?= htmlspecialchars($e['no_empleado']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-auto d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
    </form>

    <!-- Tabla + toolbar de Buttons -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Resultados</span>
            <div id="toolbar-salidas" class="btn-group btn-group-sm no-print"></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="dt-salidas" class="table table-striped table-bordered table-sm table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>FECHA</th>
                            <th>No. EMPLEADO</th>
                            <th>NOMBRE</th>
                            <th class="text-center">PARTIDAS</th>
                            <th class="text-end">PIEZAS</th>
                            <th>RESGUARDO</th>
                            <th class="no-export">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $tiene_resg = !empty($r['id_resguardo']);
                            $folio_str  = $tiene_resg ? sprintf('%d-%05d', (int)$r['anio'], (int)$r['folio']) : '—';
                            $url_editar = BASE_URL . 'modules/inventario/salidas/editar.php?id=' . (int)$r['id_salida'];
                            $url_genres = BASE_URL . 'modules/inventario/salidas/generar_resguardo.php?id_salida=' . (int)$r['id_salida'];
                            $url_impr   = $tiene_resg
                                ? BASE_URL . 'modules/resguardos/imprimir.php?id_resguardo=' . (int)$r['id_resguardo']
                                : '';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['fecha']) ?></td>
                                <td><?= htmlspecialchars($r['no_empleado']) ?></td>
                                <td><?= htmlspecialchars($r['empleado']) ?></td>
                                <td class="text-center"><?= (int)$r['partidas'] ?></td>
                                <td class="text-end"><?= (int)$r['piezas'] ?></td>
                                <td><?= htmlspecialchars($folio_str) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars($url_editar) ?>">Editar</a>
                                        <?php if ($tiene_resg): ?>
                                            <a class="btn btn-outline-success" href="<?= htmlspecialchars($url_impr) ?>" target="_blank" rel="noopener">
                                                Imprimir
                                            </a>
                                        <?php else: ?>
                                            <a class="btn btn-outline-dark" href="<?= htmlspecialchars($url_genres) ?>">
                                                Generar&nbsp;Resguardo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!--<p class="text-muted small mt-2 no-print">
                    Mostrando <?= count($rows) ?> salidas.
                </p>-->
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dt = $('#dt-salidas').DataTable({
            dom: 't<"row g-2 align-items-center mt-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>',
            responsive: true,
            // pageLength: 25, // si quieres fijar tamaño de página
            order: [
                [0, 'desc'] // Fecha desc
            ],
            language: {
                url: "<?= BASE_URL ?>assets/js/datatables/i18n/es-MX.json"
            },
            columnDefs: [{
                    targets: [3],
                    className: 'text-center' // Partidas
                },
                {
                    targets: [4],
                    className: 'text-end' // Piezas
                },
                {
                    targets: [6],
                    orderable: false,
                    searchable: false
                } // Acciones
            ]
        });

        new $.fn.dataTable.Buttons(dt, {
            buttons: [{
                    extend: 'excelHtml5',
                    text: 'Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {                        
                        columns: ':visible', // se exportan todas (no hay no-export)
                        columns: ':not(.no-export)'
                    },
                    title: 'Salidas de Inventario'
                },
                {
                    extend: "pdfHtml5",
                    text: "PDF",
                    className: "btn btn-danger btn-sm",
                    exportOptions: {
                        columns: ':visible', // ⬅️ EXCLUYE ACCIONES
                        columns: ':not(.no-export)'
                        
                    },
                    title: "Salidas de Inventario",
                    orientation: "portrait",
                    pageSize: "A4",
                    customize: function(doc) {
                        dt_standard_pdf_customization(doc, "Salidas de Inventario");
                    }
                },
                /*{
                    extend: 'print',
                    text: 'Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: {                        
                        columns: ':visible',
                        columns: ':not(.no-export)'
                    },
                    title: 'Salidas de Inventario',
                    customize: dt_standard_print_customization
                },*/
                {
                    extend: 'colvis',
                    text: 'Columnas',
                    className: 'btn btn-outline-dark btn-sm'
                }
            ]
        });

        dt.buttons().container().appendTo('#toolbar-salidas');
    });
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>