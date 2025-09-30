<?php
// modules/inventario/salidas/editar.php
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

// ======= Traer cabecera de la salida + empleado =======
$head = db_select_all("
  SELECT s.id_salida, s.fecha, s.id_empleado, s.observaciones, s.creado_por,
         e.no_empleado,
         CONCAT(TRIM(COALESCE(e.nombre,'')), ' ', TRIM(COALESCE(e.aPaterno,'')),
                CASE WHEN COALESCE(e.aMaterno,'')<>'' THEN CONCAT(' ', TRIM(e.aMaterno)) ELSE '' END
         ) AS empleado_nombre
  FROM salidas s
  JOIN empleados e ON e.id_empleado = s.id_empleado
  WHERE s.id_salida = $id
  LIMIT 1
");
if (!$head || isset($head['_error'])) {
    http_response_code(404);
    exit('Salida no encontrada');
}
$S = $head[0];

// ======= Filtro para el selector de variantes (buscar producto/talla) =======
$q = trim($_GET['q'] ?? '');     // texto libre
$solo_pos = true;                // siempre usamos >0 para agregar
$where = "1=1";
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    $where .= " AND (
    v.descripcion LIKE '%$qEsc%' OR
    v.codigo LIKE '%$qEsc%' OR
    v.modelo LIKE '%$qEsc%' OR
    v.categoria LIKE '%$qEsc%' OR
    v.talla LIKE '%$qEsc%'
  )";
}
$where .= " AND v.existencias > 0";

// Lista de variantes disponibles (usamos v_existencias_netas)
$vars = db_select_all("
  SELECT v.id_variante,
         v.codigo, v.descripcion, v.modelo, v.categoria, v.talla, v.existencias
  FROM v_existencias_netas v
  WHERE $where
  ORDER BY v.descripcion ASC, v.talla ASC
");
if (isset($vars['_error'])) {
    $vars = [];
}

// ======= POST: agregar / eliminar / actualizar partida =======
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$token_ok) {
        $flash_err = 'Token inválido. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        // --- Agregar partida ---
        if ($accion === 'add_det') {
            $id_var = (int)($_POST['id_variante'] ?? 0);
            $cant   = (int)($_POST['cantidad'] ?? 0);

            if ($id_var <= 0 || $cant <= 0) {
                $flash_err = 'Selecciona una variante y cantidad válidas.';
            } else {
                // Validar existencias netas actuales
                $chk = db_select_all("
          SELECT existencias FROM v_existencias_netas WHERE id_variante = $id_var LIMIT 1
        ");
                if (!$chk || isset($chk['_error'])) {
                    $flash_err = 'No se pudo validar existencias.';
                } else {
                    $disp = (int)$chk[0]['existencias'];
                    if ($cant > $disp) {
                        $flash_err = "No hay existencias suficientes. Disponibles: $disp.";
                    } else {
                        // Insertar partida
                        $stmt = mysqli_prepare($cn, "
              INSERT INTO salidas_detalle (id_salida, id_variante, cantidad)
              VALUES (?, ?, ?)
            ");
                        mysqli_stmt_bind_param($stmt, 'iii', $id, $id_var, $cant);
                        try {
                            $ok = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            if ($ok) {
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                header('Location: editar.php?id=' . urlencode((string)$id) . '&d_ok=1&q=' . urlencode($q));
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

        // --- Eliminar partida ---
        if ($accion === 'del_det') {
            $id_det = (int)($_POST['id_detalle_salida'] ?? 0);
            if ($id_det <= 0) {
                $flash_err = 'Partida inválida.';
            } else {
                $stmt = mysqli_prepare($cn, "
          DELETE FROM salidas_detalle
           WHERE id_detalle_salida = ? AND id_salida = ?
           LIMIT 1
        ");
                mysqli_stmt_bind_param($stmt, 'ii', $id_det, $id);
                try {
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    if ($ok) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                        header('Location: editar.php?id=' . urlencode((string)$id) . '&d_del=1&q=' . urlencode($q));
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

        // --- Actualizar cantidad ---
        if ($accion === 'upd_det') {
            $id_det = (int)($_POST['id_detalle_salida'] ?? 0);
            $cant   = (int)($_POST['cantidad'] ?? 0);

            if ($id_det <= 0 || $cant <= 0) {
                $flash_err = 'Partida/cantidad inválida.';
            } else {
                // Traer la partida (para conocer su variante)
                $row = db_select_all("
          SELECT d.id_detalle_salida, d.id_variante
          FROM salidas_detalle d
          WHERE d.id_detalle_salida = $id_det AND d.id_salida = $id
          LIMIT 1
        ");
                if (!$row || isset($row['_error'])) {
                    $flash_err = 'No se encontró la partida.';
                } else {
                    $id_var = (int)$row[0]['id_variante'];

                    // Validar existencias netas: hay que considerar que esta misma salida ya tiene una cantidad reservada.
                    // Para evitar complejidad, recalculamos: existencias netas + cantidad actual de esta partida
                    // y verificamos contra la nueva cantidad.
                    $res = db_select_all("
            SELECT
              (SELECT existencias FROM v_existencias_netas WHERE id_variante = $id_var) AS netas,
              (SELECT cantidad FROM salidas_detalle WHERE id_detalle_salida = $id_det LIMIT 1) AS actual
          ");
                    if (!$res || isset($res['_error'])) {
                        $flash_err = 'No se pudo validar existencias.';
                    } else {
                        $netas  = (int)($res[0]['netas'] ?? 0);
                        $actual = (int)($res[0]['actual'] ?? 0);
                        $disp_recalculado = $netas + $actual; // lo disponible “liberando” la partida actual

                        if ($cant > $disp_recalculado) {
                            $flash_err = "No hay existencias suficientes. Disponibles: $disp_recalculado.";
                        } else {
                            $stmt = mysqli_prepare($cn, "
                UPDATE salidas_detalle
                   SET cantidad = ?
                 WHERE id_detalle_salida = ? AND id_salida = ?
                 LIMIT 1
              ");
                            mysqli_stmt_bind_param($stmt, 'iii', $cant, $id_det, $id);
                            try {
                                $ok = mysqli_stmt_execute($stmt);
                                mysqli_stmt_close($stmt);
                                if ($ok) {
                                    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                                    header('Location: editar.php?id=' . urlencode((string)$id) . '&d_upd=1&q=' . urlencode($q));
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
    }
}

// ======= Traer partidas capturadas de esta salida =======
$det = db_select_all("
  SELECT d.id_detalle_salida, d.id_variante, d.cantidad,
         e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria,
         v.talla,
         COALESCE(n.existencias,0) AS exist_disp
  FROM salidas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e         ON e.id_equipo   = v.id_equipo
  LEFT JOIN v_existencias_netas n ON n.id_variante = v.id_variante
  WHERE d.id_salida = $id
  ORDER BY e.descripcion, v.talla
");
if (isset($det['_error'])) {
    $det = [];
}

// ======= Totales =======
$total_pzas = 0;
foreach ($det as $r) {
    $total_pzas += (int)$r['cantidad'];
}

// ======= Mensajes flash por GET =======
if (!empty($_GET['created'])) {
    $flash_ok = 'Salida creada. Ahora agrega partidas.';
}
if (!empty($_GET['d_ok'])) {
    $flash_ok = 'Partida agregada.';
}
if (!empty($_GET['d_del'])) {
    $flash_ok = 'Partida eliminada.';
}
if (!empty($_GET['d_upd'])) {
    $flash_ok = 'Partida actualizada.';
}

// ======= Render =======
$page_title = 'Inventario · Editar salida';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Salidas', 'href' => $BASE . '/modules/inventario/salidas/index.php'],
    ['label' => 'Editar salida']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Salida No. <?= str_pad((int)$S['id_salida'], 5, '0', STR_PAD_LEFT) ?></h1>
        <div class="d-flex gap-2">
            <?php
            // Después de calcular $det y si quieres saber si ya existe resguardo:
            $R = db_select_all("SELECT id_resguardo FROM resguardos WHERE id_salida = $id LIMIT 1");
            $ya_tiene = ($R && !isset($R['_error']) && count($R) > 0);
            $totPart = (int)$total_pzas;
            ?>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">← Volver</a>
                <?php if (!$ya_tiene && $totPart > 0): ?>
                    <form method="post" action="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/generar_resguardo.php') ?>" class="d-inline"
                        onsubmit="return confirm('¿Generar resguardo para esta salida?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="id_salida" value="<?= (int)$id ?>">
                        <button class="btn btn-primary btn-sm">Generar resguardo</button>
                    </form>
                <?php endif; ?>
                <?php if ($ya_tiene): ?>
                    <a class="btn btn-success btn-sm" target="_blank"
                        href="<?= htmlspecialchars($BASE . '/modules/resguardos/imprimir.php?id=' . (int)$R[0]['id_resguardo']) ?>">
                        Imprimir
                    </a>
                <?php endif; ?>
            </div>
            <!-- (Opcional) Botón Editar cabecera -->
            <!-- <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar_cabecera.php?id=' . (int)$id) ?>">Editar cabecera</a> -->
            <!-- (Futuro) Generar resguardo -->
            <!-- <a class="btn btn-primary btn-sm disabled" href="#">Generar resguardo</a> -->
        </div>
    </div>

    <?php if ($flash_ok !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_err !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-hide">
            <?= htmlspecialchars($flash_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cabecera -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-3">
                    <div class="text-muted">Fecha</div>
                    <div class="fw-semibold"><?= htmlspecialchars($S['fecha']) ?></div>
                </div>
                <div class="col-sm-5">
                    <div class="text-muted">Empleado</div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($S['empleado_nombre']) ?>
                        <?php if (!empty($S['no_empleado'])): ?>
                            <span class="text-muted small">(<?= htmlspecialchars($S['no_empleado']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($S['creado_por']) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted">Observaciones</div>
                    <?= nl2br(htmlspecialchars($S['observaciones'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Agregar partida -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="editar.php" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <div class="col-md-6">
                    <label class="form-label">Buscar producto/talla</label>
                    <input type="text" name="q" class="form-control" placeholder="Código, descripción, modelo, categoría o talla" value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary">Filtrar</button>
                </div>
            </form>

            <form method="post" action="editar.php?id=<?= (int)$id ?>" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="accion" value="add_det">

                <div class="col-md-8">
                    <label class="form-label">Variante (solo con existencias)</label>
                    <select name="id_variante" class="form-select" required>
                        <option value="">— Selecciona —</option>
                        <?php foreach ($vars as $v): ?>
                            <option value="<?= (int)$v['id_variante'] ?>">
                                <?= htmlspecialchars($v['descripcion']) ?> · <?= htmlspecialchars($v['talla']) ?>
                                — <?= htmlspecialchars($v['codigo']) ?> (disp: <?= (int)$v['existencias'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($q !== ''): ?>
                        <div class="form-text">Filtro aplicado: <strong><?= htmlspecialchars($q) ?></strong></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="cantidad" class="form-control" min="1" step="1" required>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Partidas -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">Partidas</h2>
            <?php if (!$det): ?>
                <div class="text-muted">Aún no hay partidas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th class="text-end" style="width:140px">Cantidad</th>
                                <th class="text-end" style="width:180px">Existencias disp.</th>
                                <th class="text-nowrap" style="width:220px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1;
                            foreach ($det as $d): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($d['descripcion']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($d['codigo']) ?> · <?= htmlspecialchars($d['modelo']) ?> · <span class="chip"><?= htmlspecialchars($d['categoria']) ?></span></div>
                                    </td>
                                    <td><span class="chip"><?= htmlspecialchars($d['talla']) ?></span></td>
                                    <td class="text-end"><?= (int)$d['cantidad'] ?></td>
                                    <td class="text-end"><?= (int)$d['exist_disp'] ?></td>
                                    <td class="text-nowrap">
                                        <?php $cid = 'ed_' . (int)$d['id_detalle_salida']; ?>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>">Editar</button>

                                        <form method="post" action="editar.php?id=<?= (int)$id ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta partida?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="accion" value="del_det">
                                            <input type="hidden" name="id_detalle_salida" value="<?= (int)$d['id_detalle_salida'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Fila de edición (collapse) -->
                                <tr class="collapse" id="<?= $cid ?>">
                                    <td colspan="6">
                                        <form method="post" action="editar.php?id=<?= (int)$id ?>" class="row g-2 align-items-end">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="accion" value="upd_det">
                                            <input type="hidden" name="id_detalle_salida" value="<?= (int)$d['id_detalle_salida'] ?>">

                                            <div class="col-sm-3">
                                                <label class="form-label">Cantidad</label>
                                                <input type="number" name="cantidad" class="form-control" min="1" step="1" value="<?= (int)$d['cantidad'] ?>" required>
                                                <div class="form-text">Disponible (aprox): <?= (int)$d['exist_disp'] ?>*</div>
                                            </div>

                                            <div class="col-sm-3">
                                                <button class="btn btn-primary">Guardar</button>
                                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>">Cancelar</button>
                                            </div>
                                            <div class="col-12">
                                                <small class="text-muted">* El disponible se calcula al vuelo; al guardar volvemos a validar contra existencias netas reales.</small>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-end">
                    <span class="fw-semibold">Total de piezas:</span> <?= (int)$total_pzas ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>