<?php
// modules/inventario/salidas/crear.php
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

$page_title = 'Inventario · Nueva salida';
/* 1) Empleados activos
   
*/
$empleados = db_select_all("
  SELECT
    id_empleado,
    no_empleado,
    nombre_completo
  FROM empleados
  WHERE estatus = 1
  ORDER BY nombre_completo ASC
");
if (isset($empleados['_error'])) {
    $empleados = [];
}

// 2) Procesar POST
$flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga el formulario.';
    } else {
        $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
        $id_empleado = (int)($_POST['id_empleado'] ?? 0);
        $tipo_resguardo = trim($_POST['tipo_resguardo'] ?? '');
        $obs   = trim($_POST['observaciones'] ?? '');
        $creado_por = $_SESSION['user']['username'] ?? ($_SESSION['user']['email'] ?? 'sistema');

        // Validaciones sencillas
        $err = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $err['fecha'] = 'Fecha inválida.';
        if ($id_empleado <= 0) $err['empleado'] = 'Selecciona un empleado.';

        // ¿existe empleado activo?
        if ($id_empleado > 0) {
            $chk = db_select_all("SELECT id_empleado FROM empleados WHERE id_empleado = $id_empleado AND estatus = 1 LIMIT 1");
            if (!$chk || isset($chk['_error'])) {
                $err['empleado'] = 'Empleado no válido o inactivo.';
            }
        }

        $tipos_validos = ['UNIFORME', 'EQUIPO TACTICO'];
        if (!in_array($tipo_resguardo, $tipos_validos, true)) {
            $err['tipo_resguardo'] = 'Debe seleccionar un tipo de resguardo válido.';
        }

        if (!$err) {
            $stmt = mysqli_prepare($cn, "
        INSERT INTO salidas (fecha, id_empleado, tipo_resguardo, observaciones, creado_por)
        VALUES (?, ?, ?, ?, ?)
      ");
            mysqli_stmt_bind_param($stmt, 'sisss', $fecha, $id_empleado, $tipo_resguardo, $obs, $creado_por);

            try {
                $ok = mysqli_stmt_execute($stmt);
                if (!$ok) {
                    throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                }
                $id_salida = mysqli_insert_id($cn);
                mysqli_stmt_close($stmt);

                // Rotar CSRF y redirigir a EDITAR para capturar partidas
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                header('Location: ' . $BASE . '/modules/inventario/salidas/editar.php?id=' . $id_salida . '&created=1');
                exit;
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) {
                    mysqli_stmt_close($stmt);
                }
                $flash_err = 'No se pudo crear la salida (código ' . (int)$ex->getCode() . ').';
            }
        } else {
            $flash_err = 'Revisa los campos marcados.';
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
/*render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Salidas', 'href' => $BASE . '/modules/inventario/salidas/index.php'],
    ['label' => 'Nueva salida']
]);*/
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Nueva salida</h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">← Volver</a>
    </div>

    <?php if ($flash_err): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="crear.php" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" required
                        value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Empleado</label>
                    <select name="id_empleado" class="form-select" required>
                        <option value="">— Selecciona —</option>
                        <?php
                        $sel_emp = (int)($_POST['id_empleado'] ?? 0);
                        foreach ($empleados as $e):
                        ?>
                            <option value="<?= (int)$e['id_empleado'] ?>" <?= $sel_emp === (int)$e['id_empleado'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['nombre_completo']) ?>
                                <?php if (!empty($e['no_empleado'])): ?>
                                    (<?= htmlspecialchars($e['no_empleado']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($err['empleado'])): ?>
                        <div class="text-danger small"><?= htmlspecialchars($err['empleado']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="tipo_resguardo" class="form-label">Tipo de resguardo</label>
                    <select name="tipo_resguardo" id="tipo_resguardo" class="form-select" required>
                        <option value="">— Selecciona —</option>
                        <option value="UNIFORME"
                            <?= (isset($tipo_resguardo) && $tipo_resguardo === 'UNIFORME') ? 'selected' : '' ?>>
                            Uniforme
                        </option>
                        <option value="EQUIPO TACTICO"
                            <?= (isset($tipo_resguardo) && $tipo_resguardo === 'EQUIPO TACTICO') ? 'selected' : '' ?>>
                            Equipo Táctico
                        </option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" rows="3" class="form-control"
                        placeholder="Notas sobre esta entrega (opcional)"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Crear y agregar partidas</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>