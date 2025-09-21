<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

$cn = db();

// 1) Validar parámetro id (id_equipo)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('<div style="padding:16px;font-family:system-ui">Parámetro inválido.</div>');
}

// 2) Leer datos del equipo (cabecera)
$sqlEquipo = "
  SELECT
    e.id_equipo,
    e.codigo,
    e.descripcion,
    e.modelo,
    e.categoria,
    e.maneja_talla
  FROM equipo e
  WHERE e.id_equipo = $id
  LIMIT 1
";
$info = db_select_all($sqlEquipo);
if (isset($info['_error'])) {
    http_response_code(500);
    die('<div style="padding:16px;font-family:system-ui">Error: ' . htmlspecialchars($info['_error']) . '</div>');
}
if (!$info) {
    http_response_code(404);
    die('<div style="padding:16px;font-family:system-ui">No se encontró el equipo solicitado.</div>');
}
$e = $info[0];

// 3) Leer variantes (tallas) del equipo
$sqlVars = "
  SELECT v.talla
  FROM item_variantes v
  WHERE v.id_equipo = $id
  ORDER BY v.talla
";
$vars = db_select_all($sqlVars);
$hayErrorVars = isset($vars['_error']);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Uniformes · Detalle de <?= htmlspecialchars($e['codigo']) ?></title>
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }

        dt {
            width: 160px;
        }

        dd {
            margin-left: 0;
        }

        .badge {
            font-weight: 500;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <?php require_once __DIR__ . '/../../includes/breadcrumbs.php';
    $BASE = rtrim(BASE_URL, '/');
    $URL_CATALOGO = $BASE . '/modules/uniformes/catalogo.php';
    render_breadcrumb([
        ['label' => 'Catálogo', 'href' => $URL_CATALOGO],
        ['label' => 'Detalle']
    ]);
    ?>
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Uniformes · Detalle de producto</h1>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h5 mb-0">Uniformes · Detalle de producto</h1>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="catalogo.php">← Volver al catálogo</a>
                    <a class="btn btn-outline-secondary btn-sm" href="index.php">Listado (no agrupado)</a>
                    <a class="btn btn-primary btn-sm" href="editar.php?id=<?= urlencode($e['id_equipo']) ?>">✏️ Editar</a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide" role="alert">
                Producto creado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Cabecera del producto -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <?php if (!empty($_GET['ok'])): ?>
                    <div class="alert alert-success">Cambios guardados correctamente.</div>
                <?php endif; ?>
                <h2 class="h6 mb-3">Producto</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Código</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($e['codigo']) ?></dd>
                    <dt class="col-sm-3">Descripción</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($e['descripcion']) ?></dd>
                    <dt class="col-sm-3">Modelo</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($e['modelo']) ?></dd>
                    <dt class="col-sm-3">Categoría</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($e['categoria']) ?></dd>
                    <dt class="col-sm-3">Maneja talla</dt>
                    <dd class="col-sm-9">
                        <?php
                        $mt = (string)$e['maneja_talla'] === '1' ? 'Sí' : 'No';
                        echo htmlspecialchars($mt);
                        ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Variantes / Tallas como chips -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Tallas disponibles</h2>

                <?php if ($hayErrorVars): ?>
                    <div class="alert alert-danger">
                        Error al consultar tallas: <?= htmlspecialchars($vars['_error']) ?>
                    </div>
                <?php elseif (!$vars): ?>
                    <div class="alert alert-info">Este producto no tiene tallas registradas.</div>
                <?php else: ?>
                    <div>
                        <?php foreach ($vars as $v): ?>
                            <span class="badge text-bg-primary me-1 mb-1"><?= htmlspecialchars($v['talla']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>