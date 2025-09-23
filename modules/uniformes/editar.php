<?php
// modules/uniformes/editar.php

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

// -------------------- INPUT --------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('<div style="padding:16px;font-family:system-ui">ID inválido.</div>');
}

// -------------------- PERMISOS --------------------
$canEdit   = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if (!$canEdit) {
    http_response_code(403);
    exit('<div style="padding:16px;font-family:system-ui">Sin permiso.</div>');
}

// -------------------- DATOS DEL PRODUCTO --------------------
$cn = db();
$row = db_select_all("
  SELECT id_equipo, codigo, descripcion, modelo, categoria, maneja_talla, IFNULL(activo,0) AS activo
  FROM equipo
  WHERE id_equipo = $id
  LIMIT 1
");
if (isset($row['_error'])) {
    http_response_code(500);
    exit('<div style="padding:16px;font-family:system-ui">Error al consultar el producto.</div>');
}
if (!$row) {
    http_response_code(404);
    exit('<div style="padding:16px;font-family:system-ui">Producto no encontrado.</div>');
}
$e = $row[0];

// -------------------- VARIANTES (TALLAS) --------------------
$vars = db_select_all("
  SELECT id_variante,
         talla,
         IFNULL(activo, 1) AS activo
  FROM item_variantes
  WHERE id_equipo = $id
  ORDER BY talla
");
if (isset($vars['_error'])) {
    $vars = [];
}

// -------------------- ESTADO FORM / FLASH --------------------
$isPost        = ($_SERVER['REQUEST_METHOD'] === 'POST');
$flash_ok      = '';
$flash_err     = '';
$flash_talla_ok  = '';
$flash_talla_err = '';

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
if ($t_status === 'vtoggled') {
    $flash_talla_ok = 'Estado de la talla actualizado.';
}

// Mostrar alert para actualización/creación del PRODUCTO (no tallas)
if (!empty($_GET['updated'])) { 
    $flash_ok = 'Producto actualizado.'; 
}
if (!empty($_GET['created'])) { 
    $flash_ok = 'Producto creado correctamente.'; 
}

// -------------------- HELPERS --------------------
$norm_text = function (string $s): string {
    return trim($s);
};

$norm_upper = function (string $s): string {
    $s = trim($s);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
};

// -------------------- GUARDAR PRODUCTO (si no es acción de talla) --------------------
if ($isPost && !isset($_POST['accion_talla'])) {

    // CSRF
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga la página e inténtalo de nuevo.';
    } else {
        // Valores
        $codigo       = $norm_text($_POST['codigo'] ?? '');
        $descripcion  = $norm_text($_POST['descripcion'] ?? '');
        $modelo       = $norm_text($_POST['modelo'] ?? '');
        $categoria    = $norm_text($_POST['categoria'] ?? '');
        $maneja_talla = isset($_POST['maneja_talla']) ? 1 : 0;

        // Validaciones
        $err = [];
        if ($codigo === '') {
            $err['codigo'] = 'El código es obligatorio.';
        }
        if ($descripcion === '') {
            $err['descripcion'] = 'La descripción es obligatoria.';
        }
        if ($modelo === '') {
            $err['modelo'] = 'El modelo es obligatorio.';
        }
        if ($categoria === '') {
            $err['categoria'] = 'La categoría es obligatoria.';
        }

        // Duplicado de código (excluyendo el propio id)
        if (!$err) {
            $cod = mysqli_real_escape_string($cn, $codigo);
            $dup = db_select_all("SELECT id_equipo FROM equipo WHERE codigo = '$cod' AND id_equipo <> $id LIMIT 1");
            if (!empty($dup) && empty($dup['_error'])) {
                $err['codigo'] = 'Ya existe otro producto con ese código.';
            }
        }

        if ($err) {
            // Repintar con errores
            $flash_err = 'Revisa los campos marcados.';
            // Reemplazamos $e con lo capturado para que el form muestre valores
            $e['codigo']       = $codigo;
            $e['descripcion']  = $descripcion;
            $e['modelo']       = $modelo;
            $e['categoria']    = $categoria;
            $e['maneja_talla'] = $maneja_talla;
        } else {
            // UPDATE
            $stmt = mysqli_prepare($cn, "
        UPDATE equipo
           SET codigo = ?, descripcion = ?, modelo = ?, categoria = ?, maneja_talla = ?
         WHERE id_equipo = ? LIMIT 1
      ");
            mysqli_stmt_bind_param($stmt, 'ssssii', $codigo, $descripcion, $modelo, $categoria, $maneja_talla, $id);

            try {
                $ok  = mysqli_stmt_execute($stmt);
                $aff = mysqli_affected_rows($cn);
                mysqli_stmt_close($stmt);

                if ($ok && $aff >= 0) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                    header('Location: editar.php?id=' . urlencode((string)$id) . '&updated=1');
                    exit;
                } else {
                    $flash_err = 'No se pudo guardar (¿sin cambios?).';
                }
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) {
                    mysqli_stmt_close($stmt);
                }
                $flash_err = 'Error al guardar (código ' . (int)$ex->getCode() . ').';
            }
        }
    }
}

// -------------------- ACCIONES DE TALLA --------------------
if ($isPost && isset($_POST['accion_talla'])) {

    // CSRF
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_talla_err = 'Token inválido. Recarga la página e inténtalo de nuevo.';
    } else {

        // Guard según maneja_talla en BD (si está en 0, se bloquea)
        $mt_row = db_select_all("SELECT maneja_talla FROM equipo WHERE id_equipo = $id LIMIT 1");
        $mt_db  = (isset($mt_row[0]['maneja_talla']) ? (int)$mt_row[0]['maneja_talla'] : 1);
        $bloquear_tallas = ($mt_db === 0);

        // Normalizador talla
        $norm_talla = function (string $t): string {
            $t = trim($t);
            $t = mb_strtoupper($t, 'UTF-8');
            $t = preg_replace('/\s+/', ' ', $t);
            return $t;
        };

        // ---------- ADD ----------
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
                    $stmt = mysqli_prepare($cn, "INSERT INTO item_variantes (id_equipo, talla, activo) VALUES (?, ?, 1)");
                    mysqli_stmt_bind_param($stmt, 'is', $id, $talla_in);

                    try {
                        $ok = mysqli_stmt_execute($stmt);
                        if (!$ok) {
                            throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                        }
                        mysqli_stmt_close($stmt);

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
                    }
                }
            }
        }

        // ---------- UPD ----------
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
                    }
                }
            }
        }

        // ---------- DEL ----------
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

        // ---------- TOGGLE ACTIVO ----------
        if ($_POST['accion_talla'] === 'toggle') {
            if ($bloquear_tallas) {
                $flash_talla_err = 'Este producto no maneja tallas. No puedes activar/desactivar tallas.';
            } else {
                $id_var = (int)($_POST['id_variante'] ?? 0);
                if ($id_var <= 0) {
                    $flash_talla_err = 'ID de variante inválido.';
                } else {
                    $stmt = mysqli_prepare($cn, "
            UPDATE item_variantes
               SET activo = 1 - activo
             WHERE id_variante = ? AND id_equipo = ?
             LIMIT 1
          ");
                    mysqli_stmt_bind_param($stmt, 'ii', $id_var, $id);

                    try {
                        $ok  = mysqli_stmt_execute($stmt);
                        $aff = mysqli_affected_rows($cn);
                        mysqli_stmt_close($stmt);

                        if ($ok && $aff > 0) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                            header('Location: editar.php?id=' . urlencode((string)$id) . '&t_status=vtoggled');
                            exit;
                        } else {
                            $flash_talla_err = 'No se pudo cambiar el estado (¿no existe o no pertenece a este producto?).';
                        }
                    } catch (mysqli_sql_exception $ex) {
                        if (isset($stmt)) {
                            mysqli_stmt_close($stmt);
                        }
                        $flash_talla_err = 'Error al cambiar estado de la talla (código ' . (int)$ex->getCode() . ').';
                    }
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Uniformes · Editar producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <?php
    require_once __DIR__ . '/../../includes/breadcrumbs.php';
    $URL_DETALLE = $BASE . '/modules/uniformes/detalle.php?id=' . urlencode((string)$e['id_equipo']);
    render_breadcrumb([
        ['label' => 'Detalle', 'href' => $URL_DETALLE],
        ['label' => 'Editar']
    ]);
    ?>

    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Uniformes · Editar producto</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($URL_DETALLE) ?>">← Volver al detalle</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/uniformes/catalogo.php') ?>">Catálogo</a>
            </div>
        </div>

        <?php if ($flash_ok): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide"><?= htmlspecialchars($flash_ok) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_err): ?>
            <div class="alert alert-danger alert-dismissible fade show auto-hide"><?= htmlspecialchars($flash_err) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- ======= FORM PRODUCTO ======= -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="alert alert-light border mb-3">
                    Revisa los campos marcados en rojo.
                </div>

                <form method="post" action="editar.php?id=<?= urlencode((string)$e['id_equipo']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control"
                                value="<?= htmlspecialchars($e['codigo']) ?>" required>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control"
                                value="<?= htmlspecialchars($e['modelo']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control"
                                value="<?= htmlspecialchars($e['descripcion']) ?>" required>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="categoria" class="form-control"
                                value="<?= htmlspecialchars($e['categoria']) ?>" required>
                        </div>

                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkMT" name="maneja_talla"
                                    <?= ((int)$e['maneja_talla'] === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkMT">Maneja talla</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary">Guardar</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($URL_DETALLE) ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======= TALLAS ======= -->
        <?php if ($flash_talla_ok): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide"><?= htmlspecialchars($flash_talla_ok) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_talla_err): ?>
            <div class="alert alert-danger alert-dismissible fade show auto-hide"><?= htmlspecialchars($flash_talla_err) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ((int)$e['maneja_talla'] === 1): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Tallas</h2>

                    <!-- Agregar talla -->
                    <form method="post" action="editar.php?id=<?= urlencode((string)$e['id_equipo']) ?>" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="accion_talla" value="add">
                        <div class="input-group" style="max-width:420px;">
                            <input type="text" name="talla" class="form-control" placeholder="Ej.: CH, M, 26, ÚNICA" maxlength="20">
                            <button class="btn btn-primary">Agregar talla</button>
                        </div>
                        <div class="form-text">Se normaliza en MAYÚSCULAS. No se permiten caracteres especiales raros.</div>
                    </form>

                    <!-- Tabla de tallas -->
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Talla</th>
                                    <th style="width:260px">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1;
                                foreach ($vars as $v): ?>
                                    <?php
                                    $activoVar      = (int)($v['activo'] ?? 1);
                                    $rowClass       = ($activoVar === 1) ? '' : ' class="text-muted"';
                                    $toggleLabel    = ($activoVar === 1) ? 'Desactivar' : 'Activar';
                                    $toggleBtnClass = ($activoVar === 1) ? 'btn-outline-secondary' : 'btn-success';
                                    ?>
                                    <tr<?= $rowClass ?>>
                                        <td><?= $i++ ?></td>

                                        <!-- Actualizar talla -->
                                        <td>
                                            <form method="post" action="editar.php?id=<?= urlencode((string)$e['id_equipo']) ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="accion_talla" value="upd">
                                                <input type="hidden" name="id_variante" value="<?= (int)$v['id_variante'] ?>">
                                                <div class="input-group input-group-sm" style="max-width:280px;">
                                                    <input type="text" name="talla" class="form-control"
                                                        value="<?= htmlspecialchars($v['talla']) ?>" maxlength="20" required>
                                                    <button class="btn btn-success">Guardar</button>
                                                </div>
                                            </form>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="text-nowrap">
                                            <!-- Toggle -->
                                            <form method="post" action="editar.php?id=<?= urlencode((string)$e['id_equipo']) ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="accion_talla" value="toggle">
                                                <input type="hidden" name="id_variante" value="<?= (int)$v['id_variante'] ?>">
                                                <button class="btn btn-sm <?= $toggleBtnClass ?>" title="<?= $toggleLabel ?> talla">
                                                    <?= $toggleLabel ?>
                                                </button>
                                            </form>

                                            <!-- Eliminar -->
                                            <form method="post"
                                                action="editar.php?id=<?= urlencode((string)$e['id_equipo']) ?>"
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

                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Tallas</h2>
                    <div class="alert alert-info mb-0">
                        Este producto <strong>no maneja tallas</strong>. Para habilitar esta sección,
                        marca “Maneja talla” y guarda los cambios del producto.
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>