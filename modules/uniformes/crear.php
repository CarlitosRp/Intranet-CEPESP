<?php
// modules/uniformes/crear.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
auth_require_login();

// Permisos: quién puede crear
$canCreate = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if (!$canCreate) {
    http_response_code(403);
    exit('<div style="padding:16px;font-family:system-ui">Sin permiso para crear productos.</div>');
}

// CSRF
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$cn = db();

// Estado del form
$isPost       = ($_SERVER['REQUEST_METHOD'] === 'POST');
$errores      = [];
$flash_ok     = '';
$flash_error  = '';

// Valores
$codigo       = $isPost ? trim($_POST['codigo'] ?? '')      : '';
$descripcion  = $isPost ? trim($_POST['descripcion'] ?? '') : '';
$modelo       = $isPost ? trim($_POST['modelo'] ?? '')      : '';
$categoria    = $isPost ? trim($_POST['categoria'] ?? '')   : '';
$maneja_talla = $isPost ? (isset($_POST['maneja_talla']) ? '1' : '0') : '1';
$talla_inicial = $isPost ? trim($_POST['talla_inicial'] ?? '') : '';

// Envío
if ($isPost) {
    // CSRF
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $errores['general'] = 'Token inválido. Recarga la página e inténtalo de nuevo.';
    }

    // Validaciones básicas
    if ($codigo === '') {
        $errores['codigo']      = 'El código es obligatorio.';
    }
    if ($descripcion === '') {
        $errores['descripcion'] = 'La descripción es obligatoria.';
    }
    if ($modelo === '') {
        $errores['modelo']      = 'El modelo es obligatorio.';
    }
    if ($categoria === '') {
        $errores['categoria']   = 'La categoría es obligatoria.';
    }

    // Normalización simple para talla
    $norm_talla = function (string $t): string {
        $t = trim($t);
        $t = strtoupper($t);
        $t = preg_replace('/\s+/', ' ', $t);
        return $t;
    };
    if ($maneja_talla === '1' && $talla_inicial !== '') {
        $talla_inicial = $norm_talla($talla_inicial);
        if (!preg_match('/^[A-Z0-9ÁÉÍÓÚÜÑ\-\.\/ ]{1,20}$/u', $talla_inicial)) {
            $errores['talla_inicial'] = 'La talla contiene caracteres no permitidos.';
        }
    }

    // Evitar códigos duplicados (si no hay índice único, lo controlamos aquí)
    if (empty($errores)) {
        $codigo_esc = mysqli_real_escape_string($cn, $codigo);
        $dupe = db_select_all("SELECT id_equipo FROM equipo WHERE codigo = '$codigo_esc' LIMIT 1");
        if (!empty($dupe) && empty($dupe['_error'])) {
            $errores['codigo'] = 'Ya existe un producto con ese código.';
        }
    }

    // Insertar
    if (empty($errores)) {
        // INSERT equipo
        $stmt = mysqli_prepare($cn, "INSERT INTO equipo (codigo, descripcion, modelo, categoria, maneja_talla, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $mt = ($maneja_talla === '1') ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'ssssi', $codigo, $descripcion, $modelo, $categoria, $mt);

        try {
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) {
                throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
            }
            $id_equipo = mysqli_insert_id($cn);
            mysqli_stmt_close($stmt);

            // Talla inicial (opcional)
            if ($mt === 1 && $talla_inicial !== '') {
                $stmt2 = mysqli_prepare($cn, "INSERT INTO item_variantes (id_equipo, talla, activo) VALUES (?, ?, 1)");
                mysqli_stmt_bind_param($stmt2, 'is', $id_equipo, $talla_inicial);
                try {
                    $ok2 = mysqli_stmt_execute($stmt2);
                    if (!$ok2) {
                        throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                    }
                    mysqli_stmt_close($stmt2);
                } catch (mysqli_sql_exception $ex2) {
                    // Si hay duplicado por alguna razón, lo ignoramos y seguimos
                    if (isset($stmt2)) {
                        mysqli_stmt_close($stmt2);
                    }
                }
            }

            // OK → redirigir al detalle
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            header('Location: detalle.php?id=' . urlencode((string)$id_equipo) . '&created=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            if (isset($stmt)) {
                mysqli_stmt_close($stmt);
            }
            $flash_error = 'Error al crear el producto (código ' . (int)$ex->getCode() . ').';
        }
    }
}

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Uniformes · Crear producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }

        .form-text {
            font-size: .86rem
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <?php
    require_once __DIR__ . '/../../includes/breadcrumbs.php';
    $BASE = rtrim(BASE_URL, '/');
    $URL_LISTADO  = $BASE . '/modules/uniformes/index.php';
    render_breadcrumb([
        ['label' => 'Listado', 'href' => $URL_LISTADO],
        ['label' => 'Crear']
    ]);
    ?>
    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5 mb-3">Crear producto</h1>

                <?php if (!empty($errores['general'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errores['general']) ?></div>
                <?php endif; ?>
                <?php if ($flash_error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
                <?php endif; ?>

                <form method="post" action="crear.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control <?= isset($errores['codigo']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($codigo) ?>" required>
                            <?php if (isset($errores['codigo'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['codigo']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Ej.: PANT-74369, BOTA-12401</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control <?= isset($errores['modelo']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($modelo) ?>" required>
                            <?php if (isset($errores['modelo'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['modelo']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control <?= isset($errores['descripcion']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($descripcion) ?>" required>
                            <?php if (isset($errores['descripcion'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['descripcion']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="categoria" class="form-control <?= isset($errores['categoria']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($categoria) ?>" required>
                            <?php if (!isset($errores['categoria'])): ?>
                                <div class="form-text">Ej.: Uniforme, Calzado, Accesorio…</div>
                            <?php else: ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['categoria']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkMT" name="maneja_talla" <?= $maneja_talla === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkMT">Maneja talla</label>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Talla inicial (opcional)</label>
                            <input type="text" name="talla_inicial" class="form-control <?= isset($errores['talla_inicial']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($talla_inicial) ?>" maxlength="20" placeholder="Ej.: CH, 26, ÚNICA">
                            <?php if (isset($errores['talla_inicial'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['talla_inicial']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Se normaliza en MAYÚSCULAS.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Crear</button>
                        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>