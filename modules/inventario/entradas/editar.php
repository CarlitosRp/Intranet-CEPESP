<?php
// modules/inventario/entradas/editar.php
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

// ===== 1) Cargar cabecera de entrada =====
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

// Catálogo para autocompletar (puedes limitar con WHERE activo=1 si quieres)
$catalogo = db_select_all("
  SELECT id_equipo, codigo, descripcion, maneja_talla
  FROM equipo
  ORDER BY descripcion ASC
");
if (isset($catalogo['_error'])) {
    $catalogo = [];
}

// ===== 2) Estado y helpers =====
$flash_ok  = '';
$flash_err = '';

$sessionUser = $_SESSION['user'] ?? $_SESSION['usuario'] ?? [];
$autor = $sessionUser['username'] ?? $sessionUser['nombre'] ?? $sessionUser['email'] ?? 'sistema';

// Normalizadores
$norm_text = fn($s) => trim((string)$s);
$norm_upper = function ($s) {
    $s = trim((string)$s);
    return mb_strtoupper($s, 'UTF-8');
};

// ===== 3) POST acciones (agregar/eliminar partidas, buscar producto) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga la página.';
    } else {

        $accion = $_POST['accion'] ?? '';

        // --- 3.1 Buscar producto por código (postback simple) ---
        if ($accion === 'buscar_prod') {
            $codigo = $norm_text($_POST['codigo'] ?? '');
            if ($codigo === '') {
                $flash_err = 'Escribe un código.';
            } else {
                $codigoEsc = mysqli_real_escape_string($cn, $codigo);
                $prod = db_select_all("SELECT * FROM equipo WHERE codigo = '$codigoEsc' LIMIT 1");
                if (!empty($prod) && empty($prod['_error'])) {
                    $_SESSION['__ent_buscado'] = $prod[0]; // lo guardamos para la vista
                } else {
                    $flash_err = 'Producto no encontrado.';
                    unset($_SESSION['__ent_buscado']);
                }
            }
        }

        // --- 3.2 Agregar partida ---
        if ($accion === 'add_det') {
            $id_equipo  = (int)($_POST['id_equipo'] ?? 0);
            $cantidad   = (int)($_POST['cantidad'] ?? 0);
            $id_variante = isset($_POST['id_variante']) ? (int)$_POST['id_variante'] : 0;

            if ($id_equipo <= 0 || $cantidad <= 0) {
                $flash_err = 'Selecciona producto y una cantidad > 0.';
            } else {
                // traer el producto
                $prod = db_select_all("SELECT id_equipo, maneja_talla FROM equipo WHERE id_equipo = $id_equipo LIMIT 1");
                if (!$prod || isset($prod['_error'])) {
                    $flash_err = 'Producto inválido.';
                } else {
                    $maneja = (int)$prod[0]['maneja_talla'];

                    if ($maneja === 1) {
                        // Validar que la variante exista y pertenezca al producto
                        if ($id_variante <= 0) {
                            $flash_err = 'Selecciona una talla.';
                        } else {
                            $chk = db_select_all("
                SELECT id_variante FROM item_variantes 
                WHERE id_variante = $id_variante AND id_equipo = $id_equipo LIMIT 1
              ");
                            if (!$chk || isset($chk['_error'])) {
                                $flash_err = 'La talla es inválida para este producto.';
                            } else {
                                // Insertar
                                $stmt = mysqli_prepare($cn, "
                  INSERT INTO entradas_detalle (id_entrada, id_variante, cantidad)
                  VALUES (?, ?, ?)
                ");
                                mysqli_stmt_bind_param($stmt, 'iii', $id, $id_variante, $cantidad);
                                try {
                                    $ok = mysqli_stmt_execute($stmt);
                                    mysqli_stmt_close($stmt);
                                    if ($ok) {
                                        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                        header('Location: editar.php?id=' . urlencode((string)$id) . '&d_ok=1');
                                        exit;
                                    } else {
                                        $flash_err = 'No se pudo agregar la partida.';
                                    }
                                } catch (mysqli_sql_exception $ex) {
                                    if (isset($stmt)) {
                                        mysqli_stmt_close($stmt);
                                    }
                                    $flash_err = 'Error al agregar (código ' . (int)$ex->getCode() . ').';
                                }
                            }
                        }
                    } else {
                        // Producto sin tallas: asegurar/crear variante ÚNICA y usarla
                        $var = db_select_all("
              SELECT id_variante FROM item_variantes 
              WHERE id_equipo = $id_equipo AND talla = 'ÚNICA' LIMIT 1
            ");
                        if (!$var || isset($var['_error'])) {
                            // crear ÚNICA
                            $stmtV = mysqli_prepare($cn, "
                INSERT INTO item_variantes (id_equipo, talla, activo) VALUES (?, 'ÚNICA', 1)
              ");
                            mysqli_stmt_bind_param($stmtV, 'i', $id_equipo);
                            try {
                                mysqli_stmt_execute($stmtV);
                                $id_variante = mysqli_insert_id($cn);
                                mysqli_stmt_close($stmtV);
                            } catch (mysqli_sql_exception $ex) {
                                if (isset($stmtV)) {
                                    mysqli_stmt_close($stmtV);
                                }
                                $flash_err = 'No se pudo crear variante ÚNICA.';
                                $id_variante = 0;
                            }
                        } else {
                            $id_variante = (int)$var[0]['id_variante'];
                        }

                        if ($id_variante > 0) {
                            $stmt = mysqli_prepare($cn, "
                INSERT INTO entradas_detalle (id_entrada, id_variante, cantidad)
                VALUES (?, ?, ?)
              ");
                            mysqli_stmt_bind_param($stmt, 'iii', $id, $id_variante, $cantidad);
                            try {
                                $ok = mysqli_stmt_execute($stmt);
                                mysqli_stmt_close($stmt);
                                if ($ok) {
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                    header('Location: editar.php?id=' . urlencode((string)$id) . '&d_ok=1');
                                    exit;
                                } else {
                                    $flash_err = 'No se pudo agregar la partida.';
                                }
                            } catch (mysqli_sql_exception $ex) {
                                if (isset($stmt)) {
                                    mysqli_stmt_close($stmt);
                                }
                                $flash_err = 'Error al agregar (código ' . (int)$ex->getCode() . ').';
                            }
                        }
                    }
                }
            }
        }

        // --- 3.3 Actualizar partida (cantidad y/o talla) ---
        if ($accion === 'upd_det') {
            $id_det     = (int)($_POST['id_detalle_entrada'] ?? 0);
            $cantidad   = (int)($_POST['cantidad'] ?? 0);
            $id_var_new = isset($_POST['id_variante']) ? (int)$_POST['id_variante'] : 0;

            if ($id_det <= 0 || $cantidad <= 0) {
                $flash_err = 'Partida/cantidad inválida.';
            } else {
                // Traer la partida con su producto para validar cambios
                $row = db_select_all("
      SELECT d.id_detalle_entrada, d.id_variante, v.id_equipo, e.maneja_talla
      FROM entradas_detalle d
      JOIN item_variantes v ON v.id_variante = d.id_variante
      JOIN equipo e         ON e.id_equipo   = v.id_equipo
      WHERE d.id_detalle_entrada = $id_det AND d.id_entrada = $id
      LIMIT 1
    ");
                if (!$row || isset($row['_error'])) {
                    $flash_err = 'No se encontró la partida.';
                } else {
                    $id_equipo = (int)$row[0]['id_equipo'];
                    $maneja    = (int)$row[0]['maneja_talla'];

                    if ($maneja === 1) {
                        // Debe venir una variante válida del MISMO producto
                        if ($id_var_new <= 0) {
                            $flash_err = 'Selecciona una talla válida.';
                        } else {
                            $chk = db_select_all("
            SELECT id_variante FROM item_variantes
            WHERE id_variante = $id_var_new AND id_equipo = $id_equipo AND activo = 1
            LIMIT 1
          ");
                            if (!$chk || isset($chk['_error'])) {
                                $flash_err = 'La talla no corresponde al producto o está inactiva.';
                            } else {
                                $stmt = mysqli_prepare($cn, "
              UPDATE entradas_detalle
                 SET id_variante = ?, cantidad = ?
               WHERE id_detalle_entrada = ? AND id_entrada = ?
               LIMIT 1
            ");
                                mysqli_stmt_bind_param($stmt, 'iiii', $id_var_new, $cantidad, $id_det, $id);
                                try {
                                    $ok = mysqli_stmt_execute($stmt);
                                    mysqli_stmt_close($stmt);
                                    if ($ok) {
                                        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                        header('Location: editar.php?id=' . urlencode((string)$id) . '&d_upd=1');
                                        exit;
                                    } else {
                                        $flash_err = 'No se pudo actualizar la partida.';
                                    }
                                } catch (mysqli_sql_exception $ex) {
                                    if (isset($stmt)) {
                                        mysqli_stmt_close($stmt);
                                    }
                                    $flash_err = 'Error al actualizar (código ' . (int)$ex->getCode() . ').';
                                }
                            }
                        }
                    } else {
                        // Producto sin tallas: forzar variante ÚNICA y actualizar solo cantidad
                        $var = db_select_all("
          SELECT id_variante FROM item_variantes
          WHERE id_equipo = $id_equipo AND talla = 'ÚNICA' AND activo = 1
          LIMIT 1
        ");
                        if (!$var || isset($var['_error'])) {
                            $flash_err = 'Variante ÚNICA no disponible.';
                        } else {
                            $id_var_unica = (int)$var[0]['id_variante'];
                            $stmt = mysqli_prepare($cn, "
            UPDATE entradas_detalle
               SET id_variante = ?, cantidad = ?
             WHERE id_detalle_entrada = ? AND id_entrada = ?
             LIMIT 1
          ");
                            mysqli_stmt_bind_param($stmt, 'iiii', $id_var_unica, $cantidad, $id_det, $id);
                            try {
                                $ok = mysqli_stmt_execute($stmt);
                                mysqli_stmt_close($stmt);
                                if ($ok) {
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                    header('Location: editar.php?id=' . urlencode((string)$id) . '&d_upd=1');
                                    exit;
                                } else {
                                    $flash_err = 'No se pudo actualizar la partida.';
                                }
                            } catch (mysqli_sql_exception $ex) {
                                if (isset($stmt)) {
                                    mysqli_stmt_close($stmt);
                                }
                                $flash_err = 'Error al actualizar (código ' . (int)$ex->getCode() . ').';
                            }
                        }
                    }
                }
            }
        }

        // --- 3.4 Eliminar partida ---
        if ($accion === 'del_det') {
            $id_det = (int)($_POST['id_detalle_entrada'] ?? 0);
            if ($id_det <= 0) {
                $flash_err = 'Partida inválida.';
            } else {
                $stmt = mysqli_prepare($cn, "
          DELETE FROM entradas_detalle
          WHERE id_detalle_entrada = ? AND id_entrada = ?
          LIMIT 1
        ");
                mysqli_stmt_bind_param($stmt, 'ii', $id_det, $id);
                try {
                    $ok = mysqli_stmt_execute($stmt);
                    $aff = mysqli_affected_rows($cn);
                    mysqli_stmt_close($stmt);
                    if ($ok && $aff > 0) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                        header('Location: editar.php?id=' . urlencode((string)$id) . '&d_del=1');
                        exit;
                    } else {
                        $flash_err = 'No se pudo eliminar la partida.';
                    }
                } catch (mysqli_sql_exception $ex) {
                    if (isset($stmt)) {
                        mysqli_stmt_close($stmt);
                    }
                    $flash_err = 'Error al eliminar (código ' . (int)$ex->getCode() . ').';
                }
            }
        }
    } // token ok
} // POST

// Mensajes por querystring
if (!empty($_GET['d_ok'])) {
    $flash_ok = 'Partida agregada.';
}
if (!empty($_GET['d_del'])) {
    $flash_ok = 'Partida eliminada.';
}
if (!empty($_GET['created'])) {
    $flash_ok = 'Entrada creada. Ahora agrega partidas.';
}
if (!empty($_GET['h_upd'])) {
    $flash_ok = 'Cabecera actualizada.';
}
if (!empty($_GET['d_upd'])) {
    $flash_ok = 'Partida actualizada.';
}

// ===== 4) Cargar partidas =====
$det = db_select_all("
  SELECT d.id_detalle_entrada, d.cantidad,
         v.id_variante, v.talla,
         e.id_equipo, e.codigo, e.descripcion, e.maneja_talla
  FROM entradas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e          ON e.id_equipo   = v.id_equipo
  WHERE d.id_entrada = $id
  ORDER BY e.descripcion, v.talla
");

$total_pzas = 0;
foreach ($det as $d) {
    $total_pzas += (int)$d['cantidad'];
}

if (isset($det['_error'])) {
    $det = [];
}

// Si hay producto buscado en postback, recupéralo
$buscado = $_SESSION['__ent_buscado'] ?? null;

$page_title = 'Inventario · Editar entrada';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Entradas', 'href' => $BASE . '/modules/inventario/entradas/index.php'],
    ['label' => 'Editar']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Entrada #<?= (int)$E['id_entrada'] ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/index.php') ?>">← Volver</a>
            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/editar_cabecera.php?id=' . (int)$id) ?>">Editar cabecera</a>
            <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/imprimir.php?id=' . (int)$id) ?>">Imprimir</a>
            <!-- tu formulario de Eliminar puede quedar aquí también -->
            <form method="post" action="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/eliminar.php') ?>"
                class="d-inline" onsubmit="return confirm('¿Eliminar por completo esta entrada?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <button class="btn btn-outline-danger btn-sm">Eliminar</button>
            </form>
        </div>
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

    <!-- Cabecera (solo lectura) -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-3">
                    <div class="form-label text-muted">Fecha</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['fecha']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="form-label text-muted">Proveedor</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['proveedor']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="form-label text-muted">Factura</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['factura']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="form-label text-muted">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['creado_por']) ?></div>
                </div>
                <div class="col-12">
                    <div class="form-label text-muted">Observaciones</div>
                    <div><?= nl2br(htmlspecialchars($E['observaciones'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agregar partida -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Agregar partida</h2>

            <!-- Paso 1: seleccionar producto con autocompletar (por descripción) -->
            <form id="frm-buscar-prod" method="post" action="editar.php?id=<?= (int)$id ?>" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="accion" value="buscar_prod">

                <!-- este hidden lo llenamos por JS con el código real que espera el backend -->
                <input type="hidden" name="codigo" id="codigo-seleccionado">

                <div class="col-sm-6">
                    <label class="form-label">Producto</label>
                    <input class="form-control" list="productos-dl" id="producto-input" placeholder="Escribe para buscar por descripción">
                    <datalist id="productos-dl">
                        <?php foreach ($catalogo as $p): ?>
                            <option
                                value="<?= htmlspecialchars($p['descripcion']) ?> (<?= htmlspecialchars($p['codigo']) ?>)"
                                data-code="<?= htmlspecialchars($p['codigo']) ?>"
                                data-id="<?= (int)$p['id_equipo'] ?>"
                                data-mt="<?= (int)$p['maneja_talla'] ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Elige por descripción; se usará el código correspondiente.</div>
                </div>
                <div class="col-sm-3">
                    <button class="btn btn-outline-secondary">Seleccionar</button>
                </div>
            </form>

            <script>
                (function() {
                    const $input = document.getElementById('producto-input');
                    const $hidden = document.getElementById('codigo-seleccionado');
                    const $form = document.getElementById('frm-buscar-prod');
                    const $dl = document.getElementById('productos-dl');

                    function resolverCodigo() {
                        // Busca el option cuyo value coincide con lo que escribió el usuario
                        const val = $input.value.trim();
                        const opts = $dl.options;
                        for (let i = 0; i < opts.length; i++) {
                            if (opts[i].value === val) {
                                $hidden.value = opts[i].dataset.code || '';
                                return true;
                            }
                        }
                        // Si no hubo match exacto, intenta extraer el código entre paréntesis al final: "Desc (COD)"
                        const m = val.match(/\(([^()]+)\)\s*$/);
                        if (m) {
                            $hidden.value = m[1].trim();
                            return true;
                        }
                        $hidden.value = '';
                        return false;
                    }

                    $form.addEventListener('submit', function(e) {
                        if (!resolverCodigo()) {
                            e.preventDefault();
                            alert('Selecciona un producto de la lista (usa alguna de las sugerencias).');
                            $input.focus();
                        }
                    });
                })();
            </script>

            <?php if ($buscado): ?>
                <div class="alert alert-light border mb-3">
                    <div><strong>Producto:</strong> <?= htmlspecialchars($buscado['descripcion']) ?> (<?= htmlspecialchars($buscado['codigo']) ?>)</div>
                    <div><strong>Maneja talla:</strong> <?= ((int)$buscado['maneja_talla'] === 1 ? 'Sí' : 'No') ?></div>
                </div>

                <?php
                // Cargar tallas del producto buscado
                $id_eq_busc = (int)$buscado['id_equipo'];
                $tallas = db_select_all("
            SELECT id_variante, talla, activo
            FROM item_variantes
            WHERE id_equipo = $id_eq_busc
            ORDER BY talla
          ");
                if (isset($tallas['_error'])) $tallas = [];
                ?>

                <!-- Paso 2: elegir talla (si aplica) y cantidad -->
                <form method="post" action="editar.php?id=<?= (int)$id ?>" class="row g-2 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="accion" value="add_det">
                    <input type="hidden" name="id_equipo" value="<?= (int)$buscado['id_equipo'] ?>">

                    <?php if ((int)$buscado['maneja_talla'] === 1): ?>
                        <div class="col-sm-4">
                            <label class="form-label">Talla</label>
                            <select name="id_variante" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($tallas as $t): ?>
                                    <option value="<?= (int)$t['id_variante'] ?>" <?= ((int)$t['activo'] === 1 ? '' : 'disabled') ?>>
                                        <?= htmlspecialchars($t['talla']) ?><?= ((int)$t['activo'] === 1 ? '' : ' (inactiva)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo tallas activas están habilitadas.</div>
                        </div>
                    <?php else: ?>
                        <div class="col-sm-4">
                            <label class="form-label">Talla</label>
                            <input type="text" class="form-control" value="ÚNICA" disabled>
                            <div class="form-text">Se creará/usará la variante “ÚNICA”.</div>
                        </div>
                    <?php endif; ?>

                    <div class="col-sm-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" name="cantidad" class="form-control" min="1" step="1" required>
                    </div>
                    <div class="col-sm-3">
                        <button class="btn btn-primary">Agregar</button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <!-- Partidas existentes -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">Partidas</h2>
            <?php if (!$det): ?>
                <div class="text-muted">Aún no hay partidas en esta entrada.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th style="width:120px">Cantidad</th>
                                <th style="width:160px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1;
                            foreach ($det as $d): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($d['descripcion']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($d['codigo']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($d['talla']) ?></td>
                                    <td><?= (int)$d['cantidad'] ?></td>
                                    <td class="text-nowrap">
                                        <!-- Botón Editar (abre el collapse) -->
                                        <?php $cid = 'edit_det_' . (int)$d['id_detalle_entrada']; ?>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>">
                                            Editar
                                        </button>

                                        <!-- Botón Eliminar (como ya lo tenías) -->
                                        <form method="post" action="editar.php?id=<?= (int)$id ?>"
                                            onsubmit="return confirm('¿Eliminar esta partida?');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="accion" value="del_det">
                                            <input type="hidden" name="id_detalle_entrada" value="<?= (int)$d['id_detalle_entrada'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Fila de edición (collapse) -->
                                <tr class="collapse" id="<?= $cid ?>">
                                    <td colspan="5">
                                        <form method="post" action="editar.php?id=<?= (int)$id ?>" class="row g-2 align-items-end">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="accion" value="upd_det">
                                            <input type="hidden" name="id_detalle_entrada" value="<?= (int)$d['id_detalle_entrada'] ?>">

                                            <?php if ((int)$d['maneja_talla'] === 1): ?>
                                                <?php
                                                // Cargar tallas del producto de esta fila
                                                $id_eq_row = (int)$d['id_equipo'];
                                                $tallas_row = db_select_all("
            SELECT id_variante, talla, activo
            FROM item_variantes
            WHERE id_equipo = $id_eq_row
            ORDER BY talla
          ");
                                                if (isset($tallas_row['_error'])) $tallas_row = [];
                                                ?>
                                                <div class="col-sm-4">
                                                    <label class="form-label">Talla</label>
                                                    <select name="id_variante" class="form-select" required>
                                                        <?php foreach ($tallas_row as $t): ?>
                                                            <option value="<?= (int)$t['id_variante'] ?>"
                                                                <?= ((int)$t['id_variante'] === (int)$d['id_variante'] ? 'selected' : '') ?>
                                                                <?= ((int)$t['activo'] === 1 ? '' : 'disabled') ?>>
                                                                <?= htmlspecialchars($t['talla']) ?><?= ((int)$t['activo'] === 1 ? '' : ' (inactiva)') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php else: ?>
                                                <div class="col-sm-4">
                                                    <label class="form-label">Talla</label>
                                                    <input type="text" class="form-control" value="ÚNICA" disabled>
                                                    <!-- Para productos sin talla NO mandamos id_variante (el backend forzará ÚNICA) -->
                                                </div>
                                            <?php endif; ?>

                                            <div class="col-sm-3">
                                                <label class="form-label">Cantidad</label>
                                                <input type="number" name="cantidad" class="form-control" min="1" step="1"
                                                    value="<?= (int)$d['cantidad'] ?>" required>
                                            </div>
                                            <div class="col-sm-3">
                                                <button class="btn btn-primary">Guardar</button>
                                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($det): ?>
                        <div class="mt-2 text-end">
                            <span class="fw-semibold">Total de piezas:</span> <?= (int)$total_pzas ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
// al final de la vista, ya consumimos el producto buscado
unset($_SESSION['__ent_buscado']);
require_once __DIR__ . '/../../../includes/footer.php';
