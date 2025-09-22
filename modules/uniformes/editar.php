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

if ($isPost && !isset($_POST['accion_talla'])) {
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

// --- Acciones de TALLAS (add / delete) -----------------------------
$flash_talla_ok = '';
$flash_talla_err = '';

if ($isPost && isset($_POST['accion_talla'])) {
    // Validar CSRF para acciones de talla
    $token_ok_t = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok_t) {
        $flash_talla_err = 'Token inválido. Recarga la página e inténtalo de nuevo.';
    } else {
        $cn = db();

        // Normalizador sencillo de talla
        $norm_talla = function (string $t): string {
            $t = trim($t);
            $t = strtoupper($t);         // “ch”, “m”, “l” → “CH”, “M”, “L”
            $t = preg_replace('/\s+/', ' ', $t); // espacios múltiples → uno
            return $t;
        };

        // --- Guard para acciones de talla según maneja_talla -----------------
        $mt_row = db_select_all("SELECT maneja_talla FROM equipo WHERE id_equipo = $id LIMIT 1");
        $mt_db  = (isset($mt_row[0]['maneja_talla']) ? (int)$mt_row[0]['maneja_talla'] : 1);

        $bloquear_tallas = ($mt_db === 0);
        // ---------------------------------------------------------------------

        if ($_POST['accion_talla'] === 'add') {
            if ($bloquear_tallas) {
                $flash_talla_err = 'Este producto no maneja tallas. No puedes agregar/editar/eliminar tallas.';
            } else {
                $talla_in = $norm_talla($_POST['talla'] ?? '');
                if ($talla_in === '') {
                    $flash_talla_err = 'La talla es obligatoria.';
                } elseif (!preg_match('/^[A-Z0-9ÁÉÍÓÚÜÑ\-\.\/ ]{1,20}$/u', $talla_in)) {
                    $flash_talla_err = 'La talla contiene caracteres no permitidos.';
                } else {
                    // INSERT con índice único (id_equipo, talla)
                    $stmt = mysqli_prepare($cn, "INSERT INTO item_variantes (id_equipo, talla, activo) VALUES (?, ?, 1)");
                    mysqli_stmt_bind_param($stmt, 'is', $id, $talla_in);

                    try {
                        $ok = mysqli_stmt_execute($stmt);
                        if (!$ok) {
                            throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                        }
                        mysqli_stmt_close($stmt);

                        // ÉXITO → redirect con mensaje
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                        header('Location: editar.php?id=' . urlencode((string)$id) . '&t_status=added');
                        exit;
                    } catch (mysqli_sql_exception $ex) {
                        if (isset($stmt)) {
                            mysqli_stmt_close($stmt);
                        }
                        if ((int)$ex->getCode() === 1062) {
                            $flash_talla_err = 'Esa talla ya existe para este producto.';
                        } else {
                            $flash_talla_err = 'Error al agregar talla (código ' . (int)$ex->getCode() . ').';
                        }
                        $talla_in = ''; // limpiar input al mostrar error
                    }
                }
            }
        }

        if ($_POST['accion_talla'] === 'del') {
            if ($bloquear_tallas) {
                $flash_talla_err = 'Este producto no maneja tallas. No puedes agregar/editar/eliminar tallas.';
            } else {
                $id_var = (int)($_POST['id_variante'] ?? 0);
                if ($id_var <= 0) {
                    $flash_talla_err = 'ID de variante inválido.';
                } else {
                    $stmt = mysqli_prepare($cn, "DELETE FROM item_variantes WHERE id_variante = ? AND id_equipo = ? LIMIT 1");
                    mysqli_stmt_bind_param($stmt, 'ii', $id_var, $id);

                    try {
                        $ok  = mysqli_stmt_execute($stmt);
                        $aff = mysqli_affected_rows($cn);
                        mysqli_stmt_close($stmt);

                        if ($ok && $aff > 0) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                            header('Location: editar.php?id=' . urlencode((string)$id) . '&t_status=deleted');
                            exit;
                        } else {
                            $flash_talla_err = 'No se pudo eliminar (¿ya no existe o no pertenece a este producto?).';
                        }
                    } catch (mysqli_sql_exception $ex) {
                        if (isset($stmt)) {
                            mysqli_stmt_close($stmt);
                        }
                        $flash_talla_err = 'Error al eliminar la talla (código ' . (int)$ex->getCode() . ').';
                    }
                }
            }
        }

        if ($_POST['accion_talla'] === 'upd') {
            if ($bloquear_tallas) {
                $flash_talla_err = 'Este producto no maneja tallas. No puedes agregar/editar/eliminar tallas.';
            } else {
                $id_var   = (int)($_POST['id_variante'] ?? 0);
                $talla_in = $norm_talla($_POST['talla'] ?? '');

                if ($id_var <= 0) {
                    $flash_talla_err = 'ID de variante inválido.';
                } elseif ($talla_in === '') {
                    $flash_talla_err = 'La talla es obligatoria.';
                } elseif (!preg_match('/^[A-Z0-9ÁÉÍÓÚÜÑ\-\.\/ ]{1,20}$/u', $talla_in)) {
                    $flash_talla_err = 'La talla contiene caracteres no permitidos.';
                } else {
                    $stmt = mysqli_prepare($cn, "
                UPDATE item_variantes
                   SET talla = ?
                 WHERE id_variante = ? AND id_equipo = ?
                 LIMIT 1
            ");
                    mysqli_stmt_bind_param($stmt, 'sii', $talla_in, $id_var, $id);

                    try {
                        $ok  = mysqli_stmt_execute($stmt);
                        $aff = mysqli_affected_rows($cn);
                        mysqli_stmt_close($stmt);

                        // Si no cambió (misma talla), $aff puede ser 0; lo consideramos OK.
                        if ($ok && $aff >= 0) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                            header('Location: editar.php?id=' . urlencode((string)$id) . '&t_status=updated');
                            exit;
                        } else {
                            $flash_talla_err = 'No se pudo actualizar (¿no existe o no pertenece a este producto?).';
                        }
                    } catch (mysqli_sql_exception $ex) {
                        if (isset($stmt)) {
                            mysqli_stmt_close($stmt);
                        }
                        if ((int)$ex->getCode() === 1062) {
                            $flash_talla_err = 'Ya existe esa talla para este producto.';
                        } else {
                            $flash_talla_err = 'Error al actualizar la talla (código ' . (int)$ex->getCode() . ').';
                        }
                        $talla_in = '';
                    }
                }
            }
        }
    }
}
// -------------------------------------------------------------------

$flash_talla_ok  = $flash_talla_ok  ?? '';
$flash_talla_err = $flash_talla_err ?? '';

$t_status = $_GET['t_status'] ?? '';
if ($t_status === 'added') {
    $flash_talla_ok = 'Talla agregada correctamente.';
}
if ($t_status === 'updated') {
    $flash_talla_ok = 'Talla actualizada.';
}
if ($t_status === 'deleted') {
    $flash_talla_ok = 'Talla eliminada.';
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

        <!-- Tallas: alta y baja -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Tallas</h2>

                <?php if (!empty($flash_talla_ok)): ?>
                    <div class="alert alert-success alert-dismissible fade show auto-hide" role="alert">
                        <?= htmlspecialchars($flash_talla_ok) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($flash_talla_err)): ?>
                    <div class="alert alert-danger alert-dismissible fade show auto-hide" role="alert">
                        <?= htmlspecialchars($flash_talla_err) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>



                <!-- Alta de talla -->
                <form class="row g-2 mb-3" method="post" action="editar.php?id=<?= urlencode($e['id_equipo']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="accion_talla" value="add">
                    <div class="col-sm-4 col-md-3">
                        <input type="text" name="talla" class="form-control" placeholder="Ej.: CH, M, 26, ÚNICA" maxlength="20" required>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-primary">Agregar talla</button>
                    </div>
                    <div class="col-12">
                        <div class="form-text">Se normaliza en MAYÚSCULAS. No se permiten caracteres especiales raros.</div>
                    </div>
                </form>

                <!-- Listado de tallas existentes -->
                <?php
                // Releer tallas por si se insertó/borró (o usa $vars si prefieres)
                $vars = db_select_all("SELECT id_variante, talla FROM item_variantes WHERE id_equipo = $id ORDER BY talla");
                $hayErrorVars = isset($vars['_error']);
                ?>

                <?php if ($hayErrorVars): ?>
                    <div class="alert alert-danger">Error al consultar tallas: <?= htmlspecialchars($vars['_error']) ?></div>
                <?php elseif (!$vars): ?>
                    <div class="alert alert-info">Este producto no tiene tallas registradas.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Talla</th>
                                    <th style="width:1%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1;
                                foreach ($vars as $v): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <!-- Form de actualización por fila -->
                                            <form method="post" action="editar.php?id=<?= urlencode($e['id_equipo']) ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="accion_talla" value="upd">
                                                <input type="hidden" name="id_variante" value="<?= (int)$v['id_variante'] ?>">
                                                <div class="input-group input-group-sm" style="max-width: 280px;">
                                                    <input type="text" name="talla" class="form-control"
                                                        value="<?= htmlspecialchars($v['talla']) ?>"
                                                        maxlength="20" required>
                                                    <button class="btn btn-success">Guardar</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td>
                                            <!-- Form de eliminación por fila -->
                                            <form method="post" action="editar.php?id=<?= urlencode($e['id_equipo']) ?>"
                                                onsubmit="return confirm('¿Eliminar la talla <?= htmlspecialchars($v['talla']) ?>?');"
                                                class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="accion_talla" value="del">
                                                <input type="hidden" name="id_variante" value="<?= (int)$v['id_variante'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>