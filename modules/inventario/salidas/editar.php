<?php
// modules/inventario/salidas/editar.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

// ================== Entrada ==================
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido.');
}

// ================== Cargar cabecera de la salida ==================
$Srow = db_select_all("
  SELECT
    s.id_salida, s.fecha, s.tipo_resguardo, s.observaciones, s.creado_por,
    e.id_empleado, e.no_empleado, e.nombre_completo AS empleado_nombre
  FROM salidas s
  JOIN empleados e ON e.id_empleado = s.id_empleado
  WHERE s.id_salida = $id
  LIMIT 1
");
if (!$Srow || isset($Srow['_error'])) {
    http_response_code(404);
    exit('Salida no encontrada.');
}
$S = $Srow[0];

$tipo_resguardo = $S['tipo_resguardo'] ?? null;

// Por seguridad, si alguna salida vieja no tiene tipo, asumimos UNIFORMES.
/*if (!$tipo_resguardo) {
    $tipo_resguardo = 'UNIFORMES';
}*/

// Mapeo entre tipo de resguardo y equipo.categoria
switch ($tipo_resguardo) {
    case 'UNIFORME':
        $categoriaFiltro = 'Uniforme';        // 👈 AJUSTA al valor EXACTO en equipo.categoria
        break;

    case 'EQUIPO TACTICO':
        $categoriaFiltro = 'Equipo Táctico';   // 👈 AJUSTA al valor EXACTO en equipo.categoria
        break;

    default:
        $categoriaFiltro = null;
        break;
}

// ================== Helpers de stock ==================
function get_disponible_variante(mysqli $cn, int $id_var): int
{
    $q = db_select_all("
    SELECT
      COALESCE( (SELECT SUM(cantidad) FROM entradas_detalle WHERE id_variante = $id_var), 0) AS ent,
      COALESCE( (SELECT SUM(cantidad) FROM salidas_detalle  WHERE id_variante = $id_var), 0) AS sal
  ");
    if (!$q || isset($q['_error'])) return 0;
    return max(0, ((int)$q[0]['ent']) - ((int)$q[0]['sal']));
}

// ================== Acciones (alta / borrar / actualizar partida) ==================
$flash_ok  = $_SESSION['flash_ok']  ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';



    // ---- Agregar partida ----
    if ($accion === 'add') {
        $id_var   = (int)($_POST['id_variante'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 0);

        if ($id_var <= 0 || $cantidad <= 0) {
            $_SESSION['flash_err'] = 'Selecciona una variante y una cantidad válida.';
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // Valida existencia
        $disp = get_disponible_variante($cn, $id_var);
        if ($cantidad > $disp) {
            $_SESSION['flash_err'] = "No puedes exceder la existencia disponible ({$disp}).";
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // ✅ Validar que la variante corresponde a la categoría del tipo de resguardo
        if (!empty($categoriaFiltro) && $id_var > 0) {
            $rowCat = db_select_all("
          SELECT e.categoria
          FROM item_variantes v
          JOIN equipo e ON e.id_equipo = v.id_equipo
          WHERE v.id_variante = $id_var
          LIMIT 1
        ");
            if (!$rowCat || isset($rowCat['_error'])) {
                $_SESSION['flash_err'] = 'El artículo seleccionado no existe.';
                header('Location: editar.php?id=' . urlencode((string)$id));
                exit;
            }
            if ($rowCat[0]['categoria'] !== $categoriaFiltro) {
                $_SESSION['flash_err'] = 'El artículo no corresponde al tipo de resguardo (' . htmlspecialchars($tipo_resguardo) . ').';
                header('Location: editar.php?id=' . urlencode((string)$id));
                exit;
            }
        }

        // Insertar partida
        $stmt = mysqli_prepare($cn, "INSERT INTO salidas_detalle (id_salida, id_variante, cantidad) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iii', $id, $id_var, $cantidad);
        try {
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
            mysqli_stmt_close($stmt);
            $_SESSION['flash_ok'] = 'Partida agregada.';
        } catch (mysqli_sql_exception $ex) {
            if (isset($stmt)) {
                mysqli_stmt_close($stmt);
            }
            $_SESSION['flash_err'] = 'Error al agregar partida (código ' . (int)$ex->getCode() . ').';
        }

        header('Location: editar.php?id=' . urlencode((string)$id));
        exit;
    }

    // ---- Eliminar partida ----
    if ($accion === 'del') {
        $id_det = (int)($_POST['id_detalle'] ?? 0);
        if ($id_det > 0) {
            // OJO: columna real es id_detalle_salida
            $stmt = mysqli_prepare($cn, "DELETE FROM salidas_detalle WHERE id_detalle_salida = ? AND id_salida = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $id_det, $id);
            try {
                $ok = mysqli_stmt_execute($stmt);
                if (!$ok) throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
                mysqli_stmt_close($stmt);
                $_SESSION['flash_ok'] = 'Partida eliminada.';
            } catch (mysqli_sql_exception $ex) {
                if (isset($stmt)) mysqli_stmt_close($stmt);
                $_SESSION['flash_err'] = 'No se pudo eliminar la partida.';
            }
        }
        header('Location: editar.php?id=' . urlencode((string)$id));
        exit;
    }

    // ---- Actualizar (editar) cantidad y/o variante ----
    if ($accion === 'upd') {
        $id_det       = (int)($_POST['id_detalle'] ?? 0);
        $nueva_cant   = (int)($_POST['cantidad'] ?? 0);
        $id_var_nueva = (int)($_POST['id_variante_new'] ?? 0);

        if ($id_det <= 0 || $nueva_cant <= 0 || $id_var_nueva <= 0) {
            $_SESSION['flash_err'] = 'Datos incompletos al editar partida.';
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // Obtener la partida actual
        $PR = db_select_all("
      SELECT d.id_variante, d.cantidad, v.id_equipo
      FROM salidas_detalle d
      JOIN item_variantes v ON v.id_variante = d.id_variante
      WHERE d.id_detalle_salida = $id_det AND d.id_salida = $id
      LIMIT 1
    ");
        if (!$PR || isset($PR['_error'])) {
            $_SESSION['flash_err'] = 'Partida no encontrada.';
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }
        $id_var_orig = (int)$PR[0]['id_variante'];
        $cant_orig   = (int)$PR[0]['cantidad'];
        $id_equipo   = (int)$PR[0]['id_equipo'];

        // Validar que la nueva variante pertenezca al MISMO equipo (solo cambio de talla)
        $CHK = db_select_all("SELECT id_equipo FROM item_variantes WHERE id_variante = $id_var_nueva LIMIT 1");
        if (!$CHK || isset($CHK['_error']) || (int)$CHK[0]['id_equipo'] !== $id_equipo) {
            $_SESSION['flash_err'] = 'La variante seleccionada no pertenece a este producto.';
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // Evitar duplicados: ¿ya existe otra línea con esa variante en esta salida?
        $DUP = db_select_all("
      SELECT id_detalle_salida
      FROM salidas_detalle
      WHERE id_salida = $id
        AND id_variante = $id_var_nueva
        AND id_detalle_salida <> $id_det
      LIMIT 1
    ");
        if ($DUP && !isset($DUP['_error']) && count($DUP) > 0) {
            $_SESSION['flash_err'] = 'Ya existe otra partida con esa talla en esta salida.';
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // Disponibilidad / Máximo permitido
        if ($id_var_nueva === $id_var_orig) {
            // misma talla → max = disponible + lo ya capturado
            $disp_puro = get_disponible_variante($cn, $id_var_orig);
            $max_ok    = $disp_puro + $cant_orig;
        } else {
            // talla diferente → max = disponible de la nueva talla
            $max_ok = get_disponible_variante($cn, $id_var_nueva);
        }
        if ($nueva_cant > $max_ok) {
            $_SESSION['flash_err'] = "No puedes exceder la existencia permitida (máx. $max_ok).";
            header('Location: editar.php?id=' . urlencode((string)$id));
            exit;
        }

        // Actualizar (variante y cantidad)
        $stmt = mysqli_prepare($cn, "
      UPDATE salidas_detalle
      SET id_variante = ?, cantidad = ?
      WHERE id_detalle_salida = ? AND id_salida = ?
    ");
        mysqli_stmt_bind_param($stmt, 'iiii', $id_var_nueva, $nueva_cant, $id_det, $id);
        try {
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
            mysqli_stmt_close($stmt);
            $_SESSION['flash_ok'] = 'Partida actualizada.';
        } catch (mysqli_sql_exception $ex) {
            if (isset($stmt)) mysqli_stmt_close($stmt);
            $_SESSION['flash_err'] = 'No se pudo actualizar la partida.';
        }

        header('Location: editar.php?id=' . urlencode((string)$id));
        exit;
    }
}

// ================== Catálogo para DATALIST (equipos activos) ==================
$equipos = db_select_all("
  SELECT e.id_equipo, e.codigo, e.descripcion, e.modelo
  FROM equipo e
  WHERE e.activo = 1
    AND e.categoria = '" . $categoriaFiltro . "'
  ORDER BY e.descripcion ASC
");

if (isset($equipos['_error'])) $equipos = [];

// ================== Variantes para ALTA: solo con existencia > 0 ==================
$vars_add = db_select_all("
  SELECT
    v.id_variante,
    v.id_equipo,
    v.talla,
    (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) AS disp
  FROM item_variantes v
  JOIN equipo e ON e.id_equipo = v.id_equipo                 -- 👈 NUEVO JOIN
  LEFT JOIN (
    SELECT id_variante, SUM(cantidad) AS cant
    FROM entradas_detalle
    GROUP BY id_variante
  ) ent ON ent.id_variante = v.id_variante
  LEFT JOIN (
    SELECT id_variante, SUM(cantidad) AS cant
    FROM salidas_detalle
    GROUP BY id_variante
  ) sal ON sal.id_variante = v.id_variante
  WHERE (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) > 0
    AND e.categoria = '" . $categoriaFiltro . "'             -- 👈 FILTRO POR CATEGORÍA
  ORDER BY v.id_equipo ASC, v.talla ASC
");

if (isset($vars_add['_error'])) $vars_add = [];

// ================== Variantes para EDICIÓN: TODAS (aunque disp sea 0) ==================
$vars_all = db_select_all("
  SELECT
    v.id_variante,
    v.id_equipo,
    v.talla,
    (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) AS disp
  FROM item_variantes v
  JOIN equipo e ON e.id_equipo = v.id_equipo                 -- 👈 NUEVO JOIN
  LEFT JOIN (
    SELECT id_variante, SUM(cantidad) AS cant
    FROM entradas_detalle
    GROUP BY id_variante
  ) ent ON ent.id_variante = v.id_variante
  LEFT JOIN (
    SELECT id_variante, SUM(cantidad) AS cant
    FROM salidas_detalle
    GROUP BY id_variante
  ) sal ON sal.id_variante = v.id_variante
  WHERE e.categoria = '" . $categoriaFiltro . "'             -- 👈 SOLO ESA CATEGORÍA
  ORDER BY v.id_equipo ASC, v.talla ASC
");

if (isset($vars_all['_error'])) $vars_all = [];

// ====== Preparar estructuras livianas para JS ======
$equipos_js = [];
foreach ($equipos as $eq) {
    $equipos_js[] = [
        'id_equipo' => (int)$eq['id_equipo'],
        'label'     => $eq['descripcion'] . ' — ' . $eq['modelo'] . ' (' . $eq['codigo'] . ')',
    ];
}
$variantes_add_js = [];
foreach ($vars_add as $v) {
    $variantes_add_js[] = [
        'id_variante' => (int)$v['id_variante'],
        'id_equipo'   => (int)$v['id_equipo'],
        'talla'       => (string)$v['talla'],
        'disp'        => (int)$v['disp'],
    ];
}
$variantes_all_js = [];
foreach ($vars_all as $v) {
    $variantes_all_js[] = [
        'id_variante' => (int)$v['id_variante'],
        'id_equipo'   => (int)$v['id_equipo'],
        'talla'       => (string)$v['talla'],
        'disp'        => (int)$v['disp'],
    ];
}

// ================== Detalle capturado de la salida ==================
$DET = db_select_all("
  SELECT
    d.id_detalle_salida AS id_detalle,   -- alias para usar 'id_detalle' en PHP
    d.id_variante,
    d.cantidad,
    v.talla,
    e.id_equipo,
    e.codigo,
    e.descripcion,
    e.modelo
  FROM salidas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e         ON e.id_equipo   = v.id_equipo
  WHERE d.id_salida = $id
  ORDER BY e.descripcion ASC, v.talla ASC
");
if (isset($DET['_error'])) $DET = [];

// ================== Render ==================
$page_title = 'Uniformes · Editar salida';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';

render_breadcrumb([
    ['label' => 'Inventario', 'href' => $BASE . '/modules/inventario/existencias/index.php'],
    ['label' => 'Salidas',    'href' => $BASE . '/modules/inventario/salidas/index.php'],
    ['label' => 'Editar']
]);

$id_salida = (int)$S['id_salida'];

// ¿Ya existe resguardo para esta salida?
$R = db_select_all("
  SELECT id_resguardo
  FROM resguardos
  WHERE id_salida = {$id_salida}
  LIMIT 1
");
$id_resguardo = ($R && empty($R['_error']) && !empty($R[0]['id_resguardo'])) ? (int)$R[0]['id_resguardo'] : 0;
$href_imprimir = ($id_resguardo > 0)
    ? BASE_URL . '/modules/resguardos/imprimir.php?id_resguardo=' . $id_resguardo
    : '';

// === Resguardo vinculado (si existe) ===
$RES = db_select_all("
  SELECT id_resguardo, anio, folio
  FROM resguardos
  WHERE id_salida = {$id_salida}
  LIMIT 1
");

$has_resguardo = ($RES && empty($RES['_error']) && isset($RES[0]));
$res_id        = 0;
$folio_str     = '';
$href_imprimir = '';

if ($has_resguardo) {
    $res_id        = (int)$RES[0]['id_resguardo'];
    $folio_str     = str_pad((string)$RES[0]['folio'], 5, '0', STR_PAD_LEFT);
    $href_imprimir = BASE_URL . '/modules/resguardos/imprimir.php?id_resguardo=' . $res_id;
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Salida No. <?= htmlspecialchars(str_pad((string)$S['id_salida'], 5, '0', STR_PAD_LEFT)) ?></h1>
        <?php if ($has_resguardo): ?>
            <div class="alert alert-success alert-dismissible fade show auto-hide mt-2" role="alert">
                <span class="me-2">
                    Ya existe resguardo <strong>No. <?= htmlspecialchars($folio_str) ?></strong> para esta salida.
                </span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <?php require_once __DIR__ . '/../../../includes/csrf.php'; ?>
            <div class="d-flex gap-2 align-items-center">
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">← Volver</a>
                <a class="btn btn-primary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar_cabecera.php?id=' . (int)$S['id_salida']) ?>">Editar cabecera</a>
                <?php if ($has_resguardo): ?>
                    <a class="btn btn-outline-primary"
                        href="<?= htmlspecialchars($href_imprimir) ?>" target="_blank">
                        Imprimir resguardo
                    </a>
                    <button class="btn btn-outline-secondary" disabled
                        title="Ya existe resguardo para esta salida">
                        Generar resguardo
                    </button>
                <?php else: ?>
                    <form method="post"
                        action="<?= htmlspecialchars(BASE_URL . '/modules/resguardos/crear.php') ?>"
                        class="d-inline">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id_salida" value="<?= (int)$id_salida ?>">
                        <button class="btn btn-primary" type="submit">
                            Generar resguardo
                        </button>
                    </form>
                <?php endif; ?>
            </div>

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

    <!-- Cabecera -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted">Fecha</div>
                    <div class="fw-semibold"><?= htmlspecialchars($S['fecha']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Empleado</div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($S['empleado_nombre']) ?>
                        <?php if (!empty($S['no_empleado'])): ?>
                            <span class="text-muted"> (<?= htmlspecialchars($S['no_empleado']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($S['creado_por'] ?: 'sistema') ?></div>
                </div>
                <?php if (!empty($S['observaciones'])): ?>
                    <div class="col-12">
                        <div class="text-muted">Observaciones</div>
                        <div><?= nl2br(htmlspecialchars($S['observaciones'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Agregar partida -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Agregar partida</h2>

            <form id="form-add-det" method="post" action="editar.php?id=<?= (int)$S['id_salida'] ?>">
                <input type="hidden" name="accion" value="add">

                <!-- Producto con DATALIST -->
                <div class="mb-3">
                    <label class="form-label">Producto</label>
                    <input
                        type="text"
                        id="dl-equipo"
                        class="form-control"
                        list="equipos-list"
                        placeholder="Escribe y elige un producto (se cargarán sus tallas disponibles)"
                        autocomplete="off">
                    <datalist id="equipos-list">
                        <?php foreach ($equipos_js as $e): ?>
                            <option value="<?= htmlspecialchars($e['label']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Tip: haz clic en el campo para desplegar opciones.</div>
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Variante (solo con existencias)</label>
                        <select name="id_variante" id="id_variante" class="form-select">
                            <option value="">— Selecciona —</option>
                            <!-- Se llenará al elegir el producto -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" id="cant" name="cantidad" class="form-control" min="1" placeholder="Cantidad">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary">Agregar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Detalle -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">Partidas capturadas</h2>

            <?php if (!$DET): ?>
                <div class="text-muted">Aún no hay partidas en esta salida.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Modelo</th>
                                <th style="width:120px">Talla</th>
                                <th class="text-end" style="width:120px">Cantidad</th>
                                <th class="text-nowrap" style="width:200px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($DET as $d): ?>
                                <?php
                                // Calcular máximo si mantiene la MISMA talla: disp + cantidad actual
                                $disp_puro = get_disponible_variante($cn, (int)$d['id_variante']);
                                $max_ok    = (int)$disp_puro + (int)$d['cantidad'];
                                $modalId   = 'mdlEdit' . (int)$d['id_detalle'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['codigo']) ?></td>
                                    <td><?= htmlspecialchars($d['descripcion']) ?></td>
                                    <td><?= htmlspecialchars($d['modelo']) ?></td>
                                    <td><span class="chip"><?= htmlspecialchars($d['talla']) ?></span></td>
                                    <td class="text-end"><?= (int)$d['cantidad'] ?></td>
                                    <td class="text-nowrap">
                                        <!-- Botón editar (abre modal) -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $modalId ?>"
                                            data-equipo="<?= (int)$d['id_equipo'] ?>"
                                            data-var-actual="<?= (int)$d['id_variante'] ?>"
                                            data-cant-actual="<?= (int)$d['cantidad'] ?>">
                                            Editar
                                        </button>
                                        <!-- Eliminar -->
                                        <form method="post" action="editar.php?id=<?= (int)$S['id_salida'] ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta partida?');">
                                            <input type="hidden" name="accion" value="del">
                                            <input type="hidden" name="id_detalle" value="<?= (int)$d['id_detalle'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Modal edición de cantidad/variante -->
                                <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="post" action="editar.php?id=<?= (int)$S['id_salida'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Editar partida · <?= htmlspecialchars($d['descripcion']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="accion" value="upd">
                                                    <input type="hidden" name="id_detalle" value="<?= (int)$d['id_detalle'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Talla (variante)</label>
                                                        <select name="id_variante_new" class="form-select sel-var-edit" required
                                                            data-equipo="<?= (int)$d['id_equipo'] ?>"
                                                            data-var-actual="<?= (int)$d['id_variante'] ?>"
                                                            data-cant-actual="<?= (int)$d['cantidad'] ?>">
                                                            <!-- Se llena al abrir el modal -->
                                                        </select>
                                                        <div class="form-text text-muted">Solo variantes del mismo producto.</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Cantidad</label>
                                                        <input type="number"
                                                            name="cantidad"
                                                            class="form-control inp-cant-edit"
                                                            min="1"
                                                            value="<?= (int)$d['cantidad'] ?>"
                                                            required>
                                                        <div class="form-text txt-max-help">Máximo permitido: <?= (int)$max_ok ?></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button class="btn btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== JS: datalist (alta) y modal (edición de talla/cantidad) ===== -->
<script>
    // Datos precargados desde PHP
    window.EQUIPOS = <?= json_encode($equipos_js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.VARIANTES_ADD = <?= json_encode($variantes_add_js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.VARIANTES_ALL = <?= json_encode($variantes_all_js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // ===== Alta: datalist → llena select de variantes con existencia
        const $input = document.getElementById('dl-equipo');
        const $selVar = document.getElementById('id_variante');
        const $cant = document.getElementById('cant');
        const form = document.getElementById('form-add-det');

        function findEquipoIdByLabel(label) {
            const it = (window.EQUIPOS || []).find(e => e.label === label);
            return it ? it.id_equipo : null;
        }

        function fillVariantsForEquipoAdd(id_equipo) {
            $selVar.innerHTML = '<option value="">— Selecciona —</option>';
            $cant.value = '';
            $cant.placeholder = 'Cantidad';

            const lista = (window.VARIANTES_ADD || []).filter(v => v.id_equipo === id_equipo);
            lista.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id_variante;
                opt.textContent = `${v.talla} (disp: ${v.disp})`;
                opt.dataset.avail = v.disp;
                $selVar.appendChild(opt);
            });

            if (lista.length === 1) {
                $selVar.selectedIndex = 1;
                const avail = parseInt(lista[0].disp || '0', 10);
                if (Number.isFinite(avail) && avail > 0) {
                    $cant.max = String(avail);
                    $cant.placeholder = `Cantidad (máx. ${avail})`;
                }
                $cant.focus();
            } else {
                $selVar.focus();
            }
        }

        $input?.addEventListener('change', () => {
            const id = findEquipoIdByLabel($input.value.trim());
            if (!id) return;
            fillVariantsForEquipoAdd(id);
        });

        $selVar?.addEventListener('change', () => {
            const opt = $selVar.selectedOptions[0];
            if (!opt) return;
            const avail = parseInt(opt.dataset.avail || '0', 10);
            if (Number.isFinite(avail) && avail > 0) {
                $cant.max = String(avail);
                $cant.placeholder = `Cantidad (máx. ${avail})`;
                if ($cant.value && +$cant.value > avail) $cant.value = avail;
            } else {
                $cant.removeAttribute('max');
                $cant.placeholder = 'Cantidad';
            }
        });

        form?.addEventListener('submit', (ev) => {
            const idv = parseInt($selVar.value || '0', 10);
            const val = parseInt($cant.value || '0', 10);
            if (!idv || !Number.isFinite(val) || val <= 0) {
                ev.preventDefault();
                alert('Selecciona una variante y una cantidad válida.');
                return;
            }
            const opt = $selVar.selectedOptions[0];
            if (opt) {
                const avail = parseInt(opt.dataset.avail || '0', 10);
                if (val > avail) {
                    ev.preventDefault();
                    alert(`No puedes exceder la existencia (${avail}).`);
                    $cant.value = avail;
                    $cant.focus();
                }
            }
        });

        // ===== Edición: modal → cambia variante/cantidad dentro del mismo equipo
        const modals = document.querySelectorAll('.modal');
        modals.forEach(mdl => {
            mdl.addEventListener('shown.bs.modal', () => {
                const sel = mdl.querySelector('.sel-var-edit');
                const inp = mdl.querySelector('.inp-cant-edit');
                const help = mdl.querySelector('.txt-max-help');
                if (!sel || !inp || !help) return;

                const idEquipo = parseInt(sel.getAttribute('data-equipo') || '0', 10);
                const varActual = parseInt(sel.getAttribute('data-var-actual') || '0', 10);
                const cantActual = parseInt(sel.getAttribute('data-cant-actual') || '0', 10);

                // Rellena opciones con TODAS las variantes del equipo (aunque disp sea 0)
                sel.innerHTML = '';
                (window.VARIANTES_ALL || []).filter(v => v.id_equipo === idEquipo)
                    .forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id_variante;
                        opt.textContent = `${v.talla} (disp: ${v.disp})`;
                        opt.dataset.avail = v.disp;
                        if (v.id_variante === varActual) opt.selected = true;
                        sel.appendChild(opt);
                    });

                // Calcula y pinta el máximo actual (si misma talla: disp + cantActual; si cambia, luego recalcula)
                const optSel = sel.selectedOptions[0];
                let max = 0;
                if (optSel) {
                    const avail = parseInt(optSel.dataset.avail || '0', 10);
                    const idv = parseInt(optSel.value || '0', 10);
                    max = (idv === varActual) ? (avail + cantActual) : avail;
                }
                if (max > 0) {
                    inp.max = String(max);
                    help.textContent = `Máximo permitido: ${max}`;
                } else {
                    inp.removeAttribute('max');
                    help.textContent = `Máximo permitido: ${max}`;
                }
                // Selecciona todo el input para editar rápido
                setTimeout(() => {
                    inp.select();
                }, 50);
            });

            // cambia la selección de talla dentro del modal
            mdl.addEventListener('change', (ev) => {
                const sel = ev.target.closest('.sel-var-edit');
                if (!sel) return;
                const wrap = sel.closest('.modal');
                const inp = wrap.querySelector('.inp-cant-edit');
                const help = wrap.querySelector('.txt-max-help');

                const idEquipo = parseInt(sel.getAttribute('data-equipo') || '0', 10);
                const varActual = parseInt(sel.getAttribute('data-var-actual') || '0', 10);
                const cantActual = parseInt(sel.getAttribute('data-cant-actual') || '0', 10);

                const optSel = sel.selectedOptions[0];
                if (!optSel) return;

                const avail = parseInt(optSel.dataset.avail || '0', 10);
                const idv = parseInt(optSel.value || '0', 10);
                const max = (idv === varActual) ? (avail + cantActual) : avail;

                if (max > 0) {
                    inp.max = String(max);
                    if (inp.value && +inp.value > max) inp.value = max;
                } else {
                    inp.removeAttribute('max');
                }
                help.textContent = `Máximo permitido: ${max}`;
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>