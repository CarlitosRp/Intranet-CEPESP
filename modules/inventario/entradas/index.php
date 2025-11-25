<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
auth_require_login();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
require_once __DIR__ . '/../../../includes/datatables_assets.php';

$flash_ok = '';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $flash_ok = 'Entrada eliminada correctamente.';
}

$cn = db();

$q  = trim($_GET['q'] ?? '');  // proveedor o factura
$f1 = trim($_GET['f1'] ?? ''); // fecha desde (YYYY-MM-DD)
$f2 = trim($_GET['f2'] ?? ''); // fecha hasta

$where = '1=1';
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    $where .= " AND (proveedor LIKE '%$qEsc%' OR factura LIKE '%$qEsc%')";
}
if ($f1 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f1)) {
    $where .= " AND fecha >= '" . mysqli_real_escape_string($cn, $f1) . "'";
}
if ($f2 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f2)) {
    $where .= " AND fecha <= '" . mysqli_real_escape_string($cn, $f2) . "'";
}

$sql = "
  SELECT id_entrada, fecha, proveedor, factura, observaciones, creado_por
  FROM entradas
  WHERE $where
  ORDER BY fecha DESC, id_entrada DESC
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) {
    $rows = [];
}

$total = count($rows);

//$page_title = 'Inventario · Entradas';

/*render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Entradas']
]);*/

?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Entradas de Inventario</h1>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(BASE_URL . 'modules/inventario/entradas/crear.php') ?>">
            Nueva entrada
        </a>
    </div>

    <?php if ($flash_ok !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-4">
            <label class="form-label">Proveedor / Factura</label>
            <input type="text" name="q" class="form-control"
                placeholder="Proveedor o factura"
                value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="f1" class="form-control"
                value="<?= htmlspecialchars($f1) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="f2" class="form-control"
                value="<?= htmlspecialchars($f2) ?>">
        </div>
        <div class="col-md-2 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
    </form>

    <?php if ($total === 0): ?>
        <div class="alert alert-light border">Aún no hay entradas.</div>
    <?php else: ?>

        <!-- Tabla + toolbar de Buttons (mismo estilo que salidas) -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Resultados</span>
                <div id="toolbar-entradas" class="btn-group btn-group-sm"></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dt-entradas" class="table table-striped table-bordered table-sm table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>FECHA</th>
                                <th>PROVEEDOR</th>
                                <th>FACTURA</th>
                                <th>OBSERVACIONES</th>
                                <th class="no-export">CREADO POR</th>
                                <th class="no-export">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><?= htmlspecialchars($r['proveedor']) ?></td>
                                    <td><?= htmlspecialchars($r['factura']) ?></td>
                                    <td><?= htmlspecialchars($r['observaciones']) ?></td>
                                    <td><?= htmlspecialchars($r['creado_por']) ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="<?= htmlspecialchars(BASE_URL . 'modules/inventario/entradas/editar.php?id=' . (int)$r['id_entrada']) ?>">
                                            Ver
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary"
                                            href="<?= htmlspecialchars(BASE_URL . 'modules/inventario/entradas/editar.php?id=' . (int)$r['id_entrada']) ?>">
                                            Editar
                                        </a>
                                        <!-- Aquí más adelante puedes agregar botón Eliminar, etc. -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!--<p class="text-muted small mt-2">
                        Mostrando <?= $total ?> entradas.
                    </p>-->
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dt = $('#dt-entradas').DataTable({
            dom: 't<"row g-2 align-items-center mt-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>',
            responsive: true,
            pageLength: 25,
            order: [
                [0, 'desc'], // Fecha desc
                [1, 'asc'] // Proveedor asc (si aplica)
            ],
            language: {
                url: "<?= BASE_URL ?>assets/js/datatables/i18n/es-MX.json"
            },
            columnDefs: [{
                    targets: [2],
                    className: 'text-center' // Factura
                },
                {
                    targets: [5],
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
                        columns: ':visible',
                        columns: ':not(.no-export)' // se exportan todas (no hay no-export)
                    },
                    title: 'Entradas de Inventario'
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: ':visible',
                        columns: ':not(.no-export)'
                    },
                    title: 'Entradas de Inventario',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    customize: function(doc) {
                        // PDF oficial CEPESP
                        dt_standard_pdf_customization(doc, 'Entradas de Inventario');
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
                    title: 'Entradas de Inventario',
                    customize: dt_standard_print_customization
                },*/
                {
                    extend: 'colvis',
                    text: 'Columnas',
                    className: 'btn btn-outline-dark btn-sm'
                }
            ]
        });

        dt.buttons().container().appendTo('#toolbar-entradas');
    });
</script>


<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>