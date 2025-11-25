<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
auth_require_login();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
require_once __DIR__ . '/../../../includes/datatables_assets.php';

$q        = trim($_GET['q'] ?? '');
$categ    = trim($_GET['categoria'] ?? '');
$agrupar  = ($_GET['agrupar'] ?? 'producto');  // 'producto' | 'talla'
$mostrar0 = isset($_GET['mostrar0']) && $_GET['mostrar0'] == '1';

// Catálogo de categorías (ajusta si tu tabla difiere)
$cats = db_select("SELECT DISTINCT categoria FROM equipo ORDER BY categoria");

// ===================== Consulta de datos =====================
// Nota: Ajusta nombres de columnas si tu vista v_existencias_netas difiere.
$params = [];
$types  = '';
$where  = " WHERE 1=1 ";

// Búsqueda libre
if ($q !== '') {
    $where .= " AND ( codigo LIKE ? OR descripcion LIKE ? OR categoria LIKE ? OR talla LIKE ? )";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

// Filtro por categoría exacta
if ($categ !== '') {
    $where .= " AND categoria = ?";
    $params[] = $categ;
    $types   .= 's';
}

// Ocultar existencias en cero (si no marcaron el checkbox)
if (!$mostrar0) {
    $where .= " AND existencias <> 0 ";
}

$rows = [];
$cols = [];  // Para pintar el thead según el modo

if ($agrupar === 'producto') {
    // Agrupar por producto (sumar tallas)
    $sql = "
    SELECT
      id_equipo,
      categoria,
      COALESCE(codigo,'')      AS codigo,
      COALESCE(descripcion,'') AS descripcion,
      SUM(existencias)          AS existencia_total
    FROM v_existencias_netas
    $where
    GROUP BY id_equipo, categoria, codigo, descripcion
    ORDER BY categoria ASC, descripcion ASC
  ";
    $rows = db_select($sql, $params, $types);
    $cols = [
        ['key' => 'categoria',        'title' => 'CATEGORIA',   'class' => ''],
        ['key' => 'codigo',           'title' => 'CODIGO',      'class' => ''],
        ['key' => 'descripcion',      'title' => 'PRODUCTO',    'class' => ''],
        ['key' => 'existencia_total', 'title' => 'EXISTENCIA',  'class' => 'text-end'],
    ];
} else {
    // Agrupar por talla (detalle por variante)
    $sql = "
    SELECT
      id_variante,
      id_equipo,
      categoria,
      COALESCE(codigo,'')      AS codigo,
      COALESCE(descripcion,'') AS descripcion,
      COALESCE(talla,'')       AS talla,
      existencias
    FROM v_existencias_netas
    $where
    ORDER BY categoria ASC, descripcion ASC, talla ASC
  ";
    $rows = db_select($sql, $params, $types);
    $cols = [
        ['key' => 'categoria',   'title' => 'Categoría',  'class' => ''],
        ['key' => 'codigo',      'title' => 'Código',     'class' => ''],
        ['key' => 'descripcion', 'title' => 'Producto',   'class' => ''],
        ['key' => 'talla',       'title' => 'Talla',      'class' => ''],
        ['key' => 'existencias',  'title' => 'Existencia', 'class' => 'text-end'],
    ];
}

/*render_breadcrumb([
    ['label' => 'Inventario', 'href' => BASE_URL . 'modules/inventario/'],
    ['label' => 'Existencias (netas)']
]);*/

?>

<div class="container py-3 exist-page"><!-- scope impresión -->
    <h1 class="h4 mb-3">Existencias Netas</h1>

    <!-- Filtros / acciones: NO se imprimen -->
    <section class="exist-filters no-print">
        <form class="row g-2 mb-3" method="get" action="index.php">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control"
                    value="<?= htmlspecialchars($q) ?>"
                    placeholder="Código, descripción, categoría o talla">
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">— Todas —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= htmlspecialchars($c['categoria']) ?>"
                            <?= ($categ === $c['categoria'] ? 'selected' : '') ?>>
                            <?= htmlspecialchars($c['categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Agrupar</label>
                <select name="agrupar" class="form-select">
                    <option value="producto" <?= ($agrupar === 'producto' ? 'selected' : '') ?>>Por producto</option>
                    <option value="talla" <?= ($agrupar === 'talla'    ? 'selected' : '') ?>>Por talla</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label d-block">Opciones</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="mostrar0" name="mostrar0" value="1" <?= ($mostrar0 ? 'checked' : '') ?>>
                    <label class="form-check-label" for="mostrar0">Incluir en cero</label>
                </div>
            </div>
            <div class="col-md-1 d-grid">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-outline-secondary">Filtrar</button>
            </div>
        </form>
    </section>

    <!-- Encabezado de impresión (solo print) -->
    <div class="print-header print-only">
        <div class="ph-left">
            <img class="ph-logo" src="<?= htmlspecialchars(BASE_URL . 'assets/img/logo.png') ?>" alt="Logo" onerror="this.style.display='none'">
        </div>
        <div class="ph-center">
            <h2 class="ph-title">Existencias Netas</h2>
            <div class="ph-sub">
                Agrupación: <?= htmlspecialchars($agrupar === 'producto' ? 'Por producto' : 'Por talla') ?>
            </div>
        </div>
        <div class="ph-right">
            <div class="ph-meta">
                <div><strong>Fecha:</strong> <?= date('Y-m-d H:i') ?></div>
            </div>
        </div>
    </div>

    <!-- Tabla con toolbar de Buttons -->
    <div class="card exist-print">
        <div class="card-header d-flex justify-content-between align-items-center no-print">
            <span class="fw-semibold">Resultados</span>
            <div id="toolbar-exist" class="btn-group btn-group-sm"></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="dt-exist" class="table table-striped table-bordered table-sm table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <?php foreach ($cols as $col): ?>
                                <th class="<?= $col['class'] ?>"><?= htmlspecialchars($col['title']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($cols as $col): ?>
                                    <?php $key = $col['key']; ?>
                                    <?php if ($col['class'] === 'text-end'): ?>
                                        <td class="text-end">
                                            <?= is_numeric($r[$key] ?? null) ? (int)$r[$key] : htmlspecialchars((string)($r[$key] ?? '')) ?>
                                        </td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars((string)($r[$key] ?? '')) ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    <!-- Pie institucional fijo en impresión -->
    <footer class="pie-institucional print-only">
        <div class="pi-inner">
            <img class="pi-logo2" src="<?= htmlspecialchars(BASE_URL . 'assets/img/logo2.png') ?>" alt="Logo 2" onerror="this.style.display='none'">
            <div class="pi-text">
                POLICIA ESTATAL DE SEGURIDAD PUBLICA <br>
                LUIS ENCINAS Y CALLEJON OBREGON, COLONIA EL TORREON <br>
                TEL. +52 (662) 218-9419 Y 218-9420 <br>
                HERMOSILLO, SONORA, MEXICO · www.sonora.gob.mx
            </div>
        </div>
    </footer>
</div>

<?php $isProducto = ($agrupar === 'producto'); ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const isProducto = <?= $isProducto ? 'true' : 'false' ?>;

        // Índice de la columna numérica "Existencia"
        // Por producto: 0:cat,1:cod,2:prod,3:exist_total
        // Por talla   : 0:cat,1:cod,2:prod,3:talla,4:existencias
        const lastNumericCol = isProducto ? 3 : 4;

        const dt = $('#dt-exist').DataTable({
            dom: 't<"row g-2 align-items-center mt-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>',
            responsive: true,
            pageLength: 25,
            order: isProducto ? [
                    [0, 'asc'],
                    [2, 'asc']
                ] // Por producto: ordena por categoría y producto
                :
                [
                    [0, 'asc'],
                    [2, 'asc'],
                    [3, 'asc']
                ], // Por talla: categoría, producto, talla
            language: {
                url: "<?= BASE_URL ?>assets/js/datatables/i18n/es-MX.json"
            },
            columnDefs: [{
                    targets: [lastNumericCol],
                    className: 'text-end' // columna Existencia alineada a la derecha
                }
                // Aquí ya NO ponemos targets 6 ni 4 fijos,
                // porque el número de columnas cambia según el modo.
            ]
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
                    title: 'Inventario'
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)',
                        columns: ':visible'
                    },
                    title: 'Inventario',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    customize: function(doc) {
                        // PDF oficial CEPESP
                        dt_standard_pdf_customization(doc, 'Inventario');
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
                    title: 'Inventario',
                    customize: dt_standard_print_customization
                },*/
                {
                    extend: 'colvis',
                    text: 'Columnas',
                    className: 'btn btn-outline-dark btn-sm'
                }
            ]
        });

        dt.buttons().container().appendTo('#toolbar-exist');
    });
</script>


<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>