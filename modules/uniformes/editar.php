<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
auth_require_login(); // exige sesión para entrar a Editar

// ---- Permiso por rol (ajusta si tu rol se llama distinto) ----
$canEdit = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if (!$canEdit) {
    http_response_code(403);
    exit('<div style="padding:16px;font-family:system-ui">Sin permiso para editar.</div>');
}

// ---- Token CSRF simple ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

header('Content-Type: text/html; charset=UTF-8');

$cn = db();

// 1) Validar parámetro id (id_equipo)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('<div style="padding:16px;font-family:system-ui">Parámetro inválido.</div>');
}

// 2) Leer datos actuales del equipo
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

// 3) Leer tallas/variantes para mostrar debajo
$sqlVars = "
  SELECT v.talla
  FROM item_variantes v
  WHERE v.id_equipo = $id
  ORDER BY v.talla
";
$vars = db_select_all($sqlVars);
$hayErrorVars = isset($vars['_error']);

// 4) Preparar valores del formulario (POST o valores actuales)
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$errores = [];

$codigo       = $isPost ? trim($_POST['codigo'] ?? '')       : (string)$e['codigo'];
$descripcion  = $isPost ? trim($_POST['descripcion'] ?? '')  : (string)$e['descripcion'];
$modelo       = $isPost ? trim($_POST['modelo'] ?? '')       : (string)$e['modelo'];
$categoria    = $isPost ? trim($_POST['categoria'] ?? '')    : (string)$e['categoria'];
$maneja_talla = $isPost ? (isset($_POST['maneja_talla']) ? '1' : '0') : ((string)$e['maneja_talla'] === '1' ? '1' : '0');

// 5) Validación + GUARDADO (UPDATE) solo si hay POST
$guardadoOK = false;

if ($isPost) {
    // CSRF
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $errores['general'] = 'Token inválido. Recarga la página e inténtalo de nuevo.';
    }

    // Validaciones
    if ($codigo === '') {
        $errores['codigo'] = 'El código es obligatorio.';
    }
    if ($descripcion === '') {
        $errores['descripcion'] = 'La descripción es obligatoria.';
    }
    if ($modelo === '') {
        $errores['modelo'] = 'El modelo es obligatorio.';
    }
    if ($categoria === '') {
        $errores['categoria'] = 'La categoría es obligatoria.';
    }

    if (empty($errores)) {
        // UPDATE con prepared statement (seguro)
        $cn = db();
        $stmt = mysqli_prepare($cn, "
            UPDATE equipo
               SET codigo = ?, descripcion = ?, modelo = ?, categoria = ?, maneja_talla = ?
             WHERE id_equipo = ?
            LIMIT 1
        ");
        $mt = ($maneja_talla === '1') ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'ssssii', $codigo, $descripcion, $modelo, $categoria, $mt, $id);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            $errores['general'] = 'Error al guardar: ' . mysqli_error($cn);
        } else {
            $guardadoOK = true;
            // regenerar token para evitar reenvíos
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            // Redirigir al detalle con mensaje
            header('Location: detalle.php?id=' . urlencode((string)$id) . '&ok=1');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}



?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Uniformes · Editar <?= htmlspecialchars($codigo ?: $e['codigo']) ?></title>
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }

        .badge {
            font-weight: 500
        }

        .form-text {
            font-size: .86rem
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
        ['label' => 'Detalle',  'href' => 'detalle.php?id=' . urlencode($e['id_equipo'])],
        ['label' => 'Editar']
    ]);
    ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Uniformes · Editar producto</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="detalle.php?id=<?= urlencode($e['id_equipo']) ?>">← Volver al detalle</a>
                <a class="btn btn-outline-secondary btn-sm" href="catalogo.php">Catálogo</a>
            </div>
        </div>

        <!-- Formulario de edición -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Datos del producto</h2>

                <?php if ($isPost && !empty($errores)): ?>
                    <div class="alert alert-danger">Revisa los campos marcados en rojo.</div>
                <?php elseif (!empty($previewOK)): ?>
                    <div class="alert alert-info">
                        <strong>Vista previa:</strong> validación OK. Aún no guardamos cambios (guardado se activará después de configurar permisos por rol).
                    </div>
                <?php endif; ?>

                <form method="post" action="editar.php?id=<?= urlencode($e['id_equipo']) ?>" novalidate>
                    <?php if (!empty($errores['general'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errores['general']) ?></div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control <?= isset($errores['codigo']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($codigo) ?>">
                            <?php if (isset($errores['codigo'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['codigo']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Ej.: PANT-74369, BOTA-12401</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control <?= isset($errores['modelo']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($modelo) ?>">
                            <?php if (isset($errores['modelo'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['modelo']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control <?= isset($errores['descripcion']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($descripcion) ?>">
                            <?php if (isset($errores['descripcion'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['descripcion']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="categoria" class="form-control <?= isset($errores['categoria']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($categoria) ?>">
                            <?php if (isset($errores['categoria'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['categoria']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Ej.: Uniforme, Calzado, Accesorio…</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkMT" name="maneja_talla" <?= $maneja_talla === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkMT">Maneja talla</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Botones: guardar deshabilitado (hasta permisos por rol) -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <a class="btn btn-outline-secondary" href="detalle.php?id=<?= urlencode($e['id_equipo']) ?>">Cancelar</a>
                    </div>

                </form>
            </div>
        </div>

        <!-- Tallas existentes (chips) -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Tallas registradas</h2>
                <?php if ($hayErrorVars): ?>
                    <div class="alert alert-danger">Error al consultar tallas: <?= htmlspecialchars($vars['_error']) ?></div>
                <?php elseif (!$vars): ?>
                    <div class="alert alert-info">Este producto no tiene tallas registradas.</div>
                <?php else: ?>
                    <?php foreach ($vars as $v): ?>
                        <span class="badge text-bg-primary me-1 mb-1"><?= htmlspecialchars($v['talla']) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="text-muted small mt-3 mb-0">
                    *Alta/edición/baja de tallas se habilitará después del módulo de permisos (roles).
                </p>
            </div>
        </div>

    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>