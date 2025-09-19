<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

$cn        = db();
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 10;
$offset    = ($page - 1) * $perPage;

// WHERE (búsqueda opcional)
$where = '1=1';
if ($q !== '') {
    $needle = mysqli_real_escape_string($cn, $q);
    $where .= " AND (
        e.codigo      LIKE '%$needle%' OR
        e.descripcion LIKE '%$needle%' OR
        e.modelo      LIKE '%$needle%' OR
        e.categoria   LIKE '%$needle%' OR
        v.talla       LIKE '%$needle%'
    )";
}

// Conteo total
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM equipo e
    JOIN item_variantes v ON v.id_equipo = e.id_equipo
    WHERE $where
";
$countRows = db_select_all($sqlCount);
$totalRows = (!empty($countRows) && empty($countRows['_error'])) ? (int)$countRows[0]['total'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Consulta de página (solo columnas útiles del catálogo)
$sql = "
    SELECT
        e.codigo,
        e.descripcion,
        e.modelo,
        e.categoria,
        v.talla
    FROM equipo e
    JOIN item_variantes v ON v.id_equipo = e.id_equipo
    WHERE $where
    ORDER BY e.codigo, v.talla
    LIMIT $perPage OFFSET $offset
";
$rows = db_select_all($sql);
$hayError = isset($rows['_error']);

// Helper URL
function url_with($params = [])
{
    $base = basename(__FILE__); // index.php
    $query = array_merge($_GET, $params);
    foreach ($query as $k => $v) {
        if ($v === '' || $v === null) unset($query[$k]);
    }
    return $base . (empty($query) ? '' : '?' . http_build_query($query));
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Uniformes · Catálogo</title>
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }

        table th {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <?php require_once __DIR__ . '/../../includes/breadcrumbs.php';
    render_breadcrumb([
        ['label' => 'Listado']
    ]);
    ?>

    <div class="container py-4">

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h1 class="h5 mb-3">Uniformes · Catálogo</h1>

                <!-- Búsqueda -->
                <form class="row g-2 mb-3" method="get" action="index.php">
                    <div class="col-sm-8 col-md-6">
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control"
                            placeholder="Buscar por código, descripción, modelo, categoría o talla">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary">Buscar</button>
                    </div>
                    <?php if ($q !== ''): ?>
                        <div class="col-auto">
                            <a class="btn btn-outline-secondary" href="index.php">Limpiar</a>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="text-muted small mb-3">
                    <?= $q !== '' ? 'Filtro: “' . htmlspecialchars($q) . '” · ' : '' ?>
                    Resultados: <strong><?= (int)$totalRows ?></strong> ·
                    Página <strong><?= (int)$page ?></strong> de <strong><?= (int)$totalPages ?></strong>
                </div>

                <?php if ($hayError): ?>
                    <div class="alert alert-danger">
                        <strong>Error en la consulta:</strong> <?= htmlspecialchars($rows['_error']) ?>
                    </div>
                <?php elseif ($totalRows === 0): ?>
                    <div class="alert alert-warning">No se encontraron resultados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Modelo</th>
                                    <th>Categoría</th>
                                    <th>Talla</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['codigo']) ?></td>
                                        <td><?= htmlspecialchars($r['descripcion']) ?></td>
                                        <td><?= htmlspecialchars($r['modelo']) ?></td>
                                        <td><?= htmlspecialchars($r['categoria']) ?></td>
                                        <td><?= htmlspecialchars($r['talla']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <nav class="d-flex justify-content-between">
                        <a class="btn btn-outline-primary <?= $page <= 1 ? 'disabled' : '' ?>"
                            href="<?= $page <= 1 ? '#' : htmlspecialchars(url_with(['page' => $page - 1])) ?>">« Anterior</a>

                        <a class="btn btn-outline-primary <?= $page >= $totalPages ? 'disabled' : '' ?>"
                            href="<?= $page >= $totalPages ? '#' : htmlspecialchars(url_with(['page' => $page + 1])) ?>">Siguiente »</a>
                    </nav>
                <?php endif; ?>

                <p class="text-muted small mt-3 mb-0">
                    Si mañana quieres volver a mostrar IDs/activos, este es el archivo a ajustar (SELECT y <em>&lt;thead&gt;</em>).
                </p>
            </div>
        </div>

    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>