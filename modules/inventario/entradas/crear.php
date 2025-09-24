<?php
// modules/inventario/entradas/crear.php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$flash_ok  = '';
$flash_err = '';

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($isPost) {
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga el formulario.';
    } else {
        // Campos
        $fecha   = trim($_POST['fecha'] ?? '');
        $prov    = trim($_POST['proveedor'] ?? '');
        $fact    = trim($_POST['factura'] ?? '');
        $obs     = trim($_POST['observaciones'] ?? '');

        // Usuario desde la sesión (sin helper)
        $sessionUser = $_SESSION['user']    // típico: ['id'=>..,'username'=>..,'email'=>..]
            ?? $_SESSION['usuario'] // por si usaste otra clave
            ?? [];

        $creado_por = $sessionUser['username']
            ?? $sessionUser['nombre']
            ?? $sessionUser['email']
            ?? 'sistema';


        // Validaciones simples
        $err = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $err['fecha'] = 'Fecha inválida (AAAA-MM-DD).';
        }
        if ($prov === '') {
            $err['proveedor'] = 'El proveedor es obligatorio.';
        }
        if ($fact === '') {
            $err['factura'] = 'La factura es obligatoria.';
        }

        if (!$err) {
            // Insert
            $stmt = mysqli_prepare($cn, "
        INSERT INTO entradas (fecha, proveedor, factura, observaciones, creado_por)
        VALUES (?, ?, ?, ?, ?)
      ");
            mysqli_stmt_bind_param($stmt, 'sssss', $fecha, $prov, $fact, $obs, $creado_por);

            try {
                $ok = mysqli_stmt_execute($stmt);
                if (!$ok) {
                    throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                }
                $id_entrada = mysqli_insert_id($cn);
                mysqli_stmt_close($stmt);

                // CSRF rotate + redirect a la pantalla de edición/detalle de líneas (la haremos luego)
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                header('Location: ' . $BASE . '/modules/inventario/entradas/index.php?created=1');
                exit;
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) {
                    mysqli_stmt_close($stmt);
                }
                $flash_err = 'No se pudo guardar (código ' . (int)$ex->getCode() . ').';
            }
        } else {
            $flash_err = 'Revisa los campos del formulario.';
        }
    }
}

// Valores por defecto del form
$hoy   = date('Y-m-d');
$page_title = 'Inventario · Nueva entrada';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Entradas', 'href' => $BASE . '/modules/inventario/entradas/index.php'],
    ['label' => 'Nueva entrada']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Nueva entrada</h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/index.php') ?>">← Volver</a>
    </div>

    <?php if ($flash_ok): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="crear.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control"
                            value="<?= htmlspecialchars($_POST['fecha'] ?? $hoy) ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" class="form-control"
                            value="<?= htmlspecialchars($_POST['proveedor'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Factura</label>
                        <input type="text" name="factura" class="form-control"
                            value="<?= htmlspecialchars($_POST['factura'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" rows="3" class="form-control"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary">Guardar</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/index.php') ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>