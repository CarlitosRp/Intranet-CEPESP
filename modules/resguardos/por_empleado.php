<?php
// modules/resguardos/por_empleado.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Resguardos · Reporte por empleado';

// ====== catálogo de empleados activos ======
$empleados = db_select_all("
  SELECT
    id_empleado,
    no_empleado,
    nombre_completo          
  FROM empleados
  WHERE estatus = 1
  ORDER BY nombre_completo ASC
");
if (isset($empleados['_error'])) $empleados = [];

// ====== filtros ======
$id_empleado = (int)($_GET['id_empleado'] ?? 0);
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$w = "1=1";
if ($id_empleado > 0) {
    $w .= " AND s.id_empleado = $id_empleado";
}
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $w .= " AND s.fecha >= '" . mysqli_real_escape_string($cn, $desde) . "'";
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $w .= " AND s.fecha <= '" . mysqli_real_escape_string($cn, $hasta) . "'";
}

// ====== datos del empleado (si está seleccionado) ======
$Emp = null;
if ($id_empleado > 0) {
    $EmpR = db_select_all("
    SELECT
      id_empleado,
      no_empleado,
      nombre_completo,
      puesto
    FROM empleados
    WHERE id_empleado = $id_empleado
    LIMIT 1
  ");
    if ($EmpR && !isset($EmpR['_error'])) {
        $Emp = $EmpR[0];
    }
}

// ====== consulta principal: resguardos por empleado ======
$rows = [];
$total_pzas_global = 0;

if ($id_empleado > 0) {
    $rows = db_select_all("
    SELECT
      r.id_resguardo, r.folio, r.anio, r.lugar, r.director,
      s.id_salida, s.fecha,
      COALESCE(t.total_pzas,0) AS total_pzas
    FROM resguardos r
    JOIN salidas s ON s.id_salida = r.id_salida
    LEFT JOIN (
      SELECT id_salida, SUM(cantidad) AS total_pzas
      FROM salidas_detalle
      GROUP BY id_salida
    ) t ON t.id_salida = s.id_salida
    WHERE $w
    ORDER BY s.fecha DESC, r.anio DESC, CAST(r.folio AS UNSIGNED) DESC
  ");
    if (isset($rows['_error'])) $rows = [];

    foreach ($rows as $r) {
        $total_pzas_global += (int)$r['total_pzas'];
    }
}

// ====== breakdown opcional: piezas por producto/talla del periodo ======
$break = [];
if ($id_empleado > 0) {
    $break = db_select_all("
    SELECT
      e.descripcion,
      v.talla,
      SUM(d.cantidad) AS piezas
    FROM resguardos r
    JOIN salidas s         ON s.id_salida = r.id_salida
    JOIN salidas_detalle d ON d.id_salida = s.id_salida
    JOIN item_variantes v  ON v.id_variante = d.id_variante
    JOIN equipo e          ON e.id_equipo   = v.id_equipo
    WHERE $w
    GROUP BY e.descripcion, v.talla
    ORDER BY e.descripcion ASC, v.talla ASC
  ");
    if (isset($break['_error'])) $break = [];
}

// ====== export CSV ======
if ($id_empleado > 0 && !empty($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'resguardos_empleado_' . $id_empleado . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    // Encabezado empleado
    fputcsv($out, ['Empleado', $Emp['nombre_completo'] ?? '', 'No. Empleado', $Emp['no_empleado'] ?? '', 'Puesto', $Emp['puesto'] ?? '']);
    fputcsv($out, []); // línea en blanco

    // Resguardos
    fputcsv($out, ['Fecha', 'Folio', 'Piezas', 'Lugar', 'Creado en']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['fecha'],
            sprintf('%05d', (int)$r['folio']) . '/' . (int)$r['anio'],
            (int)$r['total_pzas'],
            $r['lugar'],
            $r['creado_en']
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Total piezas', $total_pzas_global]);

    // Breakdown
    if ($break) {
        fputcsv($out, []);
        fputcsv($out, ['Detalle por producto/talla']);
        fputcsv($out, ['Descripción', 'Talla', 'Piezas']);
        foreach ($break as $b) {
            fputcsv($out, [$b['descripcion'], $b['talla'], (int)$b['piezas']]);
        }
    }
    fclose($out);
    exit;
}

// ====== render ======
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Resguardos', 'href' => $BASE . '/modules/resguardos/index.php'],
    ['label' => 'Reporte por empleado']
]);
?>
<style>
    @media print {

        .navbar,
        .no-print {
            display: none !important;
        }

        body {
            background: #fff;
        }

        .card {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Reporte por empleado</h1>
        <a class="btn btn-outline-secondary btn-sm no-print" href="<?= htmlspecialchars($BASE . '/modules/resguardos/index.php') ?>">← Resguardos</a>
    </div>

    <!-- Filtros -->
    <form method="get" action="por_empleado.php" class="row g-2 mb-3 no-print">
        <div class="col-md-5">
            <label class="form-label">Empleado</label>
            <select name="id_empleado" class="form-select" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($empleados as $e): ?>
                    <option value="<?= (int)$e['id_empleado'] ?>" <?= ($id_empleado === (int)$e['id_empleado'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($e['nombre_completo']) ?>
                        <?php if (!empty($e['no_empleado'])): ?>
                            (<?= htmlspecialchars($e['no_empleado']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-outline-secondary">Aplicar</button>
            <?php
            if ($id_empleado > 0) {
                $qs = ['id_empleado' => $id_empleado];
                if ($desde !== '') $qs['desde'] = $desde;
                if ($hasta !== '') $qs['hasta'] = $hasta;
                $qsc = http_build_query(array_merge($qs, ['export' => 'csv']));
            ?>
                <a class="btn btn-outline-success" href="por_empleado.php?<?= htmlspecialchars($qsc) ?>">Exportar Excel (CSV)</a>
                <button class="btn btn-outline-primary" type="button" onclick="window.print()">Imprimir / PDF</button>
            <?php } ?>
        </div>
    </form>

    <?php if ($id_empleado <= 0): ?>
        <div class="alert alert-info">Selecciona un empleado para ver el reporte.</div>
    <?php else: ?>
        <!-- Datos del empleado -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div><strong>Empleado:</strong> <?= htmlspecialchars($Emp['nombre_completo'] ?? '') ?></div>
                        <?php if (!empty($Emp['no_empleado'])): ?>
                            <div><strong>No. Empleado:</strong> <?= htmlspecialchars($Emp['no_empleado']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($Emp['puesto'])): ?>
                            <div><strong>Puesto:</strong> <?= htmlspecialchars($Emp['puesto']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($desde !== ''): ?>
                            <div><strong>Desde:</strong> <?= htmlspecialchars($desde) ?></div>
                        <?php endif; ?>
                        <?php if ($hasta !== ''): ?>
                            <div><strong>Hasta:</strong> <?= htmlspecialchars($hasta) ?></div>
                        <?php endif; ?>
                        <div><strong>Total piezas (periodo):</strong> <?= (int)$total_pzas_global ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de resguardos -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Resguardos del periodo</h2>
                <?php if (!$rows): ?>
                    <div class="text-muted">Sin resguardos en el periodo seleccionado.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:110px">Fecha</th>
                                    <th>Folio</th>
                                    <th class="text-end" style="width:120px">Piezas</th>
                                    <th style="width:200px" class="text-nowrap">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['fecha']) ?></td>
                                        <td><span class="chip">No. <?= htmlspecialchars(str_pad($r['folio'], 5, '0', STR_PAD_LEFT)) ?>/<?= (int)$r['anio'] ?></span></td>
                                        <td class="text-end"><?= (int)$r['total_pzas'] ?></td>
                                        <td class="text-nowrap">
                                            <a class="btn btn-sm btn-success" target="_blank"
                                                href="<?= htmlspecialchars($BASE . '/modules/resguardos/imprimir.php?id=' . (int)$r['id_resguardo']) ?>">
                                                Imprimir
                                            </a>
                                            <a class="btn btn-sm btn-outline-secondary"
                                                href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$r['id_salida']) ?>">
                                                Ver salida
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Breakdown por producto/talla -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Detalle por producto y talla (periodo)</h2>
                <?php if (!$break): ?>
                    <div class="text-muted">No hay detalle para el periodo.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th style="width:120px">Talla</th>
                                    <th class="text-end" style="width:140px">Piezas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($break as $b): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($b['descripcion']) ?></td>
                                        <td><span class="chip"><?= htmlspecialchars($b['talla']) ?></span></td>
                                        <td class="text-end"><?= (int)$b['piezas'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>