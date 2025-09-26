<?php
// modules/uniformes/detalle.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$BASE = rtrim(BASE_URL, '/');

// ------------ Input ------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('<div style="padding:16px;font-family:system-ui">ID inválido.</div>');
}

// ------------ Datos del producto ------------
$cn = db();
$rows = db_select_all("
  SELECT id_equipo, codigo, descripcion, modelo, categoria, maneja_talla, IFNULL(activo,0) AS activo
  FROM equipo
  WHERE id_equipo = $id
  LIMIT 1
");
if (isset($rows['_error'])) {
    http_response_code(500);
    exit('<div style="padding:16px;font-family:system-ui">Error al consultar el producto.</div>');
}
if (!$rows) {
    http_response_code(404);
    exit('<div style="padding:16px;font-family:system-ui">Producto no encontrado.</div>');
}
$e = $rows[0];

// ------------ Tallas (solo lectura aquí) ------------
$vars = db_select_all("
  SELECT id_variante, talla, IFNULL(activo,1) AS activo
  FROM item_variantes
  WHERE id_equipo = $id
  ORDER BY talla
");
if (isset($vars['_error'])) $vars = [];

// ------------ Permisos ------------
$canEdit   = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
$canDelete = $canEdit;
$canToggle = $canEdit;
?>

<?php
$page_title = 'Uniformes · Detalle de producto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';
$URL_CATALOGO = $BASE . '/modules/uniformes/catalogo.php';
$URL_INDEX    = $BASE . '/modules/uniformes/index.php';
render_breadcrumb([
    ['label' => 'Catálogo', 'href' => $URL_CATALOGO],
    ['label' => 'Detalle']
]);
?>
<div class="container py-4">

    <!-- Mensajes (auto-hide) -->
    <?php if (!empty($_GET['created'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">Producto creado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">Producto actualizado.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['toggled'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">Estado del producto actualizado.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Encabezado + acciones -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Uniformes · Detalle de producto</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($URL_CATALOGO) ?>">← Volver al catálogo</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($URL_INDEX) ?>">Listado (no agrupado)</a>

            <?php if ($canEdit): ?>
                <a class="btn btn-primary btn-sm"
                    href="<?= htmlspecialchars($BASE . '/modules/uniformes/editar.php?id=' . urlencode((string)$e['id_equipo'])) ?>">
                    ✏️ Editar
                </a>
            <?php endif; ?>

            <?php if ($canDelete): ?>
                <!-- ⛔ Eliminar (POST + CSRF + confirm) -->
                <form method="post"
                    action="<?= htmlspecialchars($BASE . '/modules/uniformes/eliminar.php') ?>"
                    class="d-inline"
                    onsubmit="return confirm('¿Eliminar definitivamente este producto? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="id_equipo" value="<?= (int)$e['id_equipo'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Título + estado + toggle -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h5 mb-0"><?= htmlspecialchars($e['descripcion']) ?></h2>
            <?php if ((int)$e['activo'] === 1): ?>
                <span class="badge text-bg-success">Activo</span>
            <?php else: ?>
                <span class="badge text-bg-secondary">Inactivo</span>
            <?php endif; ?>
        </div>

        <?php if ($canToggle): ?>
            <!-- 🔁 Activar/Desactivar (POST absoluto) -->
            <form method="post"
                action="<?= htmlspecialchars($BASE . '/modules/uniformes/toggle_equipo.php') ?>"
                class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id_equipo" value="<?= (int)$e['id_equipo'] ?>">
                <?php if ((int)$e['activo'] === 1): ?>
                    <button class="btn btn-outline-secondary btn-sm">Desactivar</button>
                <?php else: ?>
                    <button class="btn btn-success btn-sm">Activar</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- Ficha -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6"><strong>Código</strong>
                    <div><?= htmlspecialchars($e['codigo']) ?></div>
                </div>
                <div class="col-sm-6"><strong>Modelo</strong>
                    <div><?= htmlspecialchars($e['modelo']) ?></div>
                </div>
                <div class="col-12"><strong>Descripción</strong>
                    <div><?= htmlspecialchars($e['descripcion']) ?></div>
                </div>
                <div class="col-sm-6"><strong>Categoría</strong>
                    <div><span class="chip"><?= htmlspecialchars($e['categoria']) ?></span></div>
                </div>
                <div class="col-sm-6"><strong>Maneja talla</strong>
                    <div><?= ((int)$e['maneja_talla'] === 1 ? 'Sí' : 'No') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tallas -->
    <?php if ((int)$e['maneja_talla'] === 1): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="h6">Tallas disponibles</h3>
                <?php if (!$vars): ?>
                    <div class="text-muted">No hay tallas registradas.</div>
                <?php else: ?>
                    <?php foreach ($vars as $v): ?>
                        <?php $inactive = ((int)$v['activo'] === 0); ?>
                        <span class="chip" <?= $inactive ? 'style="opacity:.5"' : '' ?>>
                            <?= htmlspecialchars($v['talla']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="h6 mb-0">Tallas</h3>
                <div class="text-muted">Este producto no maneja tallas.</div>
            </div>
        </div>
    <?php endif; ?>


</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>