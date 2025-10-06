<?php
// modules/inventario/salidas/editar_cabecera.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido.');
}

// ====== Cargar cabecera actual ======
$R = db_select_all("
  SELECT
    s.id_salida, s.fecha, s.observaciones, s.creado_por,
    e.id_empleado, e.no_empleado, e.nombre_completo AS empleado_nombre
  FROM salidas s
  JOIN empleados e ON e.id_empleado = s.id_empleado
  WHERE s.id_salida = $id
  LIMIT 1
");
if (!$R || isset($R['_error'])) {
    http_response_code(404);
    exit('Salida no encontrada.');
}
$S = $R[0];

// ====== Catálogo de empleados activos ======
$empleados = db_select_all("
  SELECT id_empleado, no_empleado, nombre_completo
  FROM empleados
  WHERE estatus = 1
  ORDER BY nombre_completo ASC
");
if (isset($empleados['_error'])) $empleados = [];

// ====== Mensajes ======
$flash_ok  = $_SESSION['flash_ok']  ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// ====== Guardar cambios ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = trim($_POST['fecha'] ?? '');
    $id_empleado = (int)($_POST['id_empleado'] ?? 0);
    $obs   = trim($_POST['observaciones'] ?? '');

    // Validaciones simples
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $flash_err = 'La fecha es obligatoria y debe tener formato AAAA-MM-DD.';
    } elseif ($id_empleado <= 0) {
        $flash_err = 'Selecciona un empleado válido.';
    } else {
        // Verificar que el empleado exista y esté activo
        $chk = db_select_all("SELECT id_empleado FROM empleados WHERE id_empleado = $id_empleado AND estatus = 1 LIMIT 1");
        if (!$chk || isset($chk['_error']) || !$chk) {
            $flash_err = 'El empleado seleccionado no existe o está inactivo.';
        } else {
            // Actualizar cabecera
            $stmt = mysqli_prepare($cn, "UPDATE salidas SET fecha = ?, id_empleado = ?, observaciones = ? WHERE id_salida = ?");
            mysqli_stmt_bind_param($stmt, 'sisi', $fecha, $id_empleado, $obs, $id);
            try {
                $ok = mysqli_stmt_execute($stmt);
                if (!$ok) throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                mysqli_stmt_close($stmt);
                $_SESSION['flash_ok'] = 'Cabecera actualizada.';
                header('Location: editar.php?id=' . urlencode((string)$id));
                exit;
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) mysqli_stmt_close($stmt);
                $flash_err = 'No se pudo actualizar (código ' . (int)$ex->getCode() . ').';
            }
        }
    }
}

// ====== Render ======
$page_title = 'Salidas · Editar cabecera';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';

render_breadcrumb([
    ['label' => 'Inventario', 'href' => $BASE . '/modules/inventario/existencias/index.php'],
    ['label' => 'Salidas',    'href' => $BASE . '/modules/inventario/salidas/index.php'],
    ['label' => 'Editar cabecera']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Editar cabecera · Salida No. <?= htmlspecialchars(str_pad((string)$S['id_salida'], 5, '0', STR_PAD_LEFT)) ?></h1>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$S['id_salida']) ?>">← Volver a la salida</a>
        </div>
    </div>

    <?php if ($flash_ok): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide" role="alert">
            <?= htmlspecialchars($flash_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-hide" role="alert">
            <?= htmlspecialchars($flash_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="editar_cabecera.php?id=<?= (int)$S['id_salida'] ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($S['fecha']) ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Empleado</label>
                    <select name="id_empleado" class="form-select" required>
                        <option value="">— Selecciona —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int)$e['id_empleado'] ?>" <?= ($S['id_empleado'] === (int)$e['id_empleado'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($e['nombre_completo']) ?>
                                <?php if (!empty($e['no_empleado'])): ?>
                                    (<?= htmlspecialchars($e['no_empleado']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Opcional"><?= htmlspecialchars($S['observaciones'] ?? '') ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Guardar cambios</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$S['id_salida']) ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>