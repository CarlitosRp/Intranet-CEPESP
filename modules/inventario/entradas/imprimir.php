<?php
// modules/inventario/entradas/imprimir.php
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
    exit('ID inválido');
}

// Cabecera
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

// Detalle
$det = db_select_all("
  SELECT d.id_detalle_entrada, d.cantidad,
         v.talla,
         e.codigo, e.descripcion
  FROM entradas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e          ON e.id_equipo   = v.id_equipo
  WHERE d.id_entrada = $id
  ORDER BY e.descripcion, v.talla
");
if (isset($det['_error'])) $det = [];

$total_pzas = 0;
foreach ($det as $d) {
    $total_pzas += (int)$d['cantidad'];
}

$page_title = 'Imprimir entrada';
$extra_css = []; // podrías inyectar extra CSS si quieres
require_once __DIR__ . '/../../../includes/header.php';
?>
<style>
    @media print {

        .navbar,
        .btn,
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
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="h5 mb-0">Entrada #<?= (int)$E['id_entrada'] ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/editar.php?id=' . (int)$id) ?>">← Volver</a>
            <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-3">
                    <div class="text-muted">Fecha</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['fecha']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted">Proveedor</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['proveedor']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted">Factura</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['factura']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($E['creado_por']) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted">Observaciones</div><?= nl2br(htmlspecialchars($E['observaciones'])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">Partidas</h2>
            <?php if (!$det): ?>
                <div class="text-muted">Sin partidas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th style="width:120px">Cantidad</th>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-end"><span class="fw-semibold">Total de piezas:</span> <?= (int)$total_pzas ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>