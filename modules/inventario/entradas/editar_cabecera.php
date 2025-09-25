<?php
// modules/inventario/entradas/editar_cabecera.php
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

// Traer cabecera
$ent = db_select_all("
  SELECT id_entrada, fecha, proveedor, factura, observaciones, creado_por
  FROM entradas
  WHERE id_entrada = $id
  LIMIT 1
");
if (isset($ent['_error']) || !$ent) {
    http_response_code(404);
    exit('Entrada no encontrada');
}
$E = $ent[0];

$flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga el formulario.';
    } else {
        $fecha = trim($_POST['fecha'] ?? '');
        $prov  = trim($_POST['proveedor'] ?? '');
        $fact  = trim($_POST['factura'] ?? '');
        $obs   = trim($_POST['observaciones'] ?? '');

        $err = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $err['fecha'] = 'Fecha inválida.';
        if ($prov === '') $err['proveedor'] = 'Proveedor obligatorio.';
        if ($fact === '') $err['factura'] = 'Factura obligatoria.';

        if (!$err) {
            $stmt = mysqli_prepare($cn, "
        UPDATE entradas
           SET fecha = ?, proveedor = ?, factura = ?, observaciones = ?
         WHERE id_entrada = ?
         LIMIT 1
      ");
            mysqli_stmt_bind_param($stmt, 'ssssi', $fecha, $prov, $fact, $obs, $id);

            try {
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                header('Location: ' . $BASE . '/modules/inventario/entradas/editar.php?id=' . $id . '&h_upd=1');
                exit;
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) {
                    mysqli_stmt_close($stmt);
                }
                $flash_err = 'No se pudo actualizar (código ' . (int)$ex->getCode() . ').';
            }
        } else {
            $flash_err = 'Revisa los campos marcados.';
        }
    }
}

$page_title = 'Inventario · Editar cabecera';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Entradas', 'href' => $BASE . '/modules/inventario/entradas/index.php'],
    ['label' => 'Editar entrada', 'href' => $BASE . '/modules/inventario/entradas/editar.php?id=' . $id],
    ['label' => 'Editar cabecera']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Editar cabecera</h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/editar.php?id=' . (int)$id) ?>">← Volver</a>
    </div>

    <?php if ($flash_err): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="editar_cabecera.php?id=<?= (int)$id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" required
                            value="<?= htmlspecialchars($_POST['fecha'] ?? $E['fecha']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" class="form-control" required
                            value="<?= htmlspecialchars($_POST['proveedor'] ?? $E['proveedor']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Factura</label>
                        <input type="text" name="factura" class="form-control" required
                            value="<?= htmlspecialchars($_POST['factura'] ?? $E['factura']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" rows="3" class="form-control"><?= htmlspecialchars($_POST['observaciones'] ?? $E['observaciones']) ?></textarea>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary">Guardar cambios</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/editar.php?id=' . (int)$id) ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>