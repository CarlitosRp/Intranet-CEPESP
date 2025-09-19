<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

$cn        = db();
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 10;
$offset    = ($page - 1) * $perPage;

/* ---------------------------
   WHERE (búsqueda opcional)
   Buscamos en: código, descripción, modelo, categoría y talla
----------------------------*/
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

/* ---------------------------
   Conteo TOTAL de grupos (productos)
   Contamos DISTINCT por el equipo (una fila por producto)
----------------------------*/
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM (
      SELECT e.id_equipo
      FROM equipo e
      JOIN item_variantes v ON v.id_equipo = e.id_equipo
      WHERE $where
      GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria
    ) t
";
$countRows = db_select_all($sqlCount);
$totalRows = (!empty($countRows) && empty($countRows['_error'])) ? (int)$countRows[0]['total'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ---------------------------
   Consulta paginada (AGRUPADA)
   GROUP_CONCAT de tallas ordenadas y distintas
----------------------------*/
$sql = "
  SELECT
    e.id_equipo,
    e.codigo,
    e.descripcion,
    e.modelo,
    e.categoria,
    GROUP_CONCAT(DISTINCT v.talla ORDER BY v.talla SEPARATOR ', ') AS tallas
  FROM equipo e
  JOIN item_variantes v ON v.id_equipo = e.id_equipo
  WHERE $where
  GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria
  ORDER BY e.codigo
  LIMIT $perPage OFFSET $offset
";
$rows = db_select_all($sql);
$hayError = isset($rows['_error']);

/* Helper URL (mantiene ?q= al paginar) */
function url_with($params = [])
{
    $base = basename(__FILE__); // catalogo.php
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
    <title>Uniformes · Catálogo (agrupado)</title>
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
        ['label' => 'Catálogo'] // solo punto actual
    ]);
    ?>

    <div class="container py-4">

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h1 class="h5 mb-3">Uniformes · Catálogo (agrupado por producto)</h1>

                <!-- Búsqueda -->
                <form class="row g-2 mb-3" method="get" action="catalogo.php">
                    <div class="col-sm-8 col-md-6">
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control"
                            placeholder="Buscar por código, descripción, modelo, categoría o talla">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary">Buscar</button>
                    </div>
                    <?php if ($q !== ''): ?>
                        <div class="col-auto">
                            <a class="btn btn-outline-secondary" href="catalogo.php">Limpiar</a>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="text-muted small mb-3">
                    <?= $q !== '' ? 'Filtro: “' . htmlspecialchars($q) . '” · ' : '' ?>
                    Productos: <strong><?= (int)$totalRows ?></strong> ·
                    Página <strong><?= (int)$page ?></strong> de <strong><?= (int)$totalPages ?></strong>
                </div>

                <?php if ($hayError): ?>
                    <div class="alert alert-danger">
                        <strong>Error en la consulta:</strong> <?= htmlspecialchars($rows['_error']) ?>
                    </div>
                <?php elseif ($totalRows === 0): ?>
                    <div class="alert alert-warning">No se encontraron productos.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Modelo</th>
                                    <th>Categoría</th>
                                    <th>Tallas</th>
                                    <th style="width:1%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['codigo']) ?></td>
                                        <td><?= htmlspecialchars($r['descripcion']) ?></td>
                                        <td><?= htmlspecialchars($r['modelo']) ?></td>
                                        <td><?= htmlspecialchars($r['categoria']) ?></td>
                                        <td>
                                            <?php
                                            $tallas = array_filter(array_map('trim', explode(',', (string)($r['tallas'] ?? ''))));
                                            foreach ($tallas as $t) {
                                                echo '<span class="badge text-bg-primary me-1 mb-1">' . htmlspecialchars($t) . '</span>';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <a class="btn btn-sm btn-primary"
                                                href="detalle.php?id=<?= urlencode($r['id_equipo']) ?>">
                                                Ver detalle
                                            </a>
                                        </td>
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
                    Tip: este SELECT agrupa por <code>id_equipo</code> (un producto). Las tallas vienen con <code>GROUP_CONCAT</code>.
                </p>
            </div>
        </div>

    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>