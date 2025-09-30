<?php
// modules/resguardos/imprimir.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

// Cabecera: resguardo + salida + empleado
$H = db_select_all("
  SELECT r.id_resguardo, r.folio, r.anio, r.lugar, r.creado_en, r.director,
         s.id_salida, s.fecha, s.id_empleado, s.observaciones,
         e.no_empleado,
         CONCAT(TRIM(COALESCE(e.nombre,'')),' ',TRIM(COALESCE(e.aPaterno,'')),
          CASE WHEN COALESCE(e.aMaterno,'')<>'' THEN CONCAT(' ',TRIM(e.aMaterno)) ELSE '' END
         ) AS empleado_nombre,
         e.puesto
  FROM resguardos r
  JOIN salidas s   ON s.id_salida = r.id_salida
  JOIN empleados e ON e.id_empleado = s.id_empleado
  WHERE r.id_resguardo = $id
  LIMIT 1
");
if (!$H || isset($H['_error'])) {
    http_response_code(404);
    exit('Resguardo no encontrado');
}
$R = $H[0];

// Detalle de la salida
$D = db_select_all("
  SELECT d.cantidad, v.talla, e.descripcion, e.codigo, e.modelo, e.categoria
  FROM salidas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e         ON e.id_equipo   = v.id_equipo
  WHERE d.id_salida = {$R['id_salida']}
  ORDER BY e.descripcion, v.talla
");
if (isset($D['_error'])) $D = [];

$page_title = 'Resguardo · Imprimir';
require_once __DIR__ . '/../../includes/header.php';
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

    .title-res {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: .5px;
    }

    .folio-badge {
        font-size: 1rem;
        font-weight: 700;
    }

    .sign-line {
        border-top: 1px solid #333;
        margin-top: 2.5rem;
        padding-top: .25rem;
        text-align: center;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="h5 mb-0">Resguardo</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">← Volver</a>
            <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div class="title-res">RESGUARDO DE UNIFORME</div>
                <div class="folio-badge">No. <?= htmlspecialchars(str_pad($R['folio'], 5, '0', STR_PAD_LEFT)) ?>/<?= (int)$R['anio'] ?></div>
            </div>

            <div class="row mt-3">
                <div class="col-md-8">
                    <div><strong>Empleado:</strong> <?= htmlspecialchars($R['empleado_nombre']) ?></div>
                    <?php if (!empty($R['no_empleado'])): ?>
                        <div><strong>No. Empleado:</strong> <?= htmlspecialchars($R['no_empleado']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($R['puesto'])): ?>
                        <div><strong>Puesto:</strong> <?= htmlspecialchars($R['puesto']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div><strong>Fecha:</strong> <?= htmlspecialchars($R['fecha']) ?></div>
                    <div><strong>Lugar:</strong> <?= htmlspecialchars($R['lugar']) ?></div>
                </div>
            </div>

            <?php if (!empty($R['observaciones'])): ?>
                <div class="mt-2"><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($R['observaciones'])) ?></div>
            <?php endif; ?>

            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px" class="text-end">Cantidad</th>
                            <th style="width:110px">Talla</th>
                            <th>Descripción</th>
                            <th style="width:140px">Modelo</th>
                            <th style="width:120px">Código</th>
                            <th style="width:140px">Categoría</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($D as $x): ?>
                            <tr>
                                <td class="text-end"><?= (int)$x['cantidad'] ?></td>
                                <td><span class="chip"><?= htmlspecialchars($x['talla']) ?></span></td>
                                <td><?= htmlspecialchars($x['descripcion']) ?></td>
                                <td><?= htmlspecialchars($x['modelo']) ?></td>
                                <td><?= htmlspecialchars($x['codigo']) ?></td>
                                <td><span class="chip"><?= htmlspecialchars($x['categoria']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-5">
                <div class="col-md-6 text-center">
                    <div class="sign-line">RECIBIÓ: <?= htmlspecialchars($R['empleado_nombre']) ?></div>
                </div>
                <div class="col-md-6 text-center">
                    <div class="sign-line">ENTREGÓ: <?= htmlspecialchars($R['director'] ?? 'Director Administrativo') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>