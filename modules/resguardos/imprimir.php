<?php
// modules/resguardos/imprimir.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
$cn = db();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');

$id_resguardo = (int)($_GET['id_resguardo'] ?? 0);
$id_salida    = (int)($_GET['id_salida'] ?? 0);

if ($id_resguardo <= 0 && $id_salida <= 0) {
    http_response_code(400);
    exit('Falta id_resguardo o id_salida.');
}

// ---------- Carga cabecera ----------
$R = null;  // datos del resguardo (si aplica)
$S = null;  // datos de la salida

if ($id_resguardo > 0) {
    // Con resguardo: traemos todo linkeado a la salida
    $q = db_select_all("
    SELECT
      r.id_resguardo, r.folio, r.fecha AS fecha_resguardo, r.director, r.lugar, r.creado_por AS resg_creado_por,
      s.id_salida, s.fecha AS fecha_salida, s.observaciones, s.creado_por AS salida_creado_por,
      e.id_empleado, e.no_empleado, e.nombre_completo AS empleado_nombre, e.puesto
    FROM resguardos r
    JOIN salidas s   ON s.id_salida = r.id_salida
    JOIN empleados e ON e.id_empleado = s.id_empleado
    WHERE r.id_resguardo = $id_resguardo
    LIMIT 1
  ");
    if (!$q || isset($q['_error']) || !$q) {
        http_response_code(404);
        exit('Resguardo no encontrado.');
    }
    $R = $q[0];

    // Normalizamos estructura $S para reusar detalle
    $S = [
        'id_salida'       => (int)$R['id_salida'],
        'fecha'           => $R['fecha_salida'],
        'observaciones'   => $R['observaciones'],
        'creado_por'      => $R['salida_creado_por'],
        'id_empleado'     => (int)$R['id_empleado'],
        'no_empleado'     => $R['no_empleado'],
        'empleado_nombre' => $R['empleado_nombre'],
        'puesto'          => $R['puesto'],
    ];
} else {
    // Sin resguardo: imprimimos “tipo resguardo” con datos de la salida
    $q = db_select_all("
    SELECT
      s.id_salida, s.fecha, s.observaciones, s.creado_por,
      e.id_empleado, e.no_empleado, e.nombre_completo AS empleado_nombre, e.puesto
    FROM salidas s
    JOIN empleados e ON e.id_empleado = s.id_empleado
    WHERE s.id_salida = $id_salida
    LIMIT 1
  ");
    if (!$q || isset($q['_error']) || !$q) {
        http_response_code(404);
        exit('Salida no encontrada.');
    }
    $S = $q[0];
}

// ---------- Carga detalle ----------
$id_salida_det = (int)$S['id_salida'];
$DET = db_select_all("
  SELECT
    d.id_detalle_salida,
    d.cantidad,
    v.talla,
    e.codigo,
    e.descripcion,
    e.modelo
  FROM salidas_detalle d
  JOIN item_variantes v ON v.id_variante = d.id_variante
  JOIN equipo e         ON e.id_equipo   = v.id_equipo
  WHERE d.id_salida = $id_salida_det
  ORDER BY e.descripcion ASC, v.talla ASC
");
if (isset($DET['_error'])) $DET = [];

// ---------- Valores de cabecera para el render ----------
$folio_str = $R && !empty($R['folio'])
    ? str_pad((string)$R['folio'], 5, '0', STR_PAD_LEFT)
    : '(pendiente)';

$folio_str = str_pad((string)$R['folio'], 5, '0', STR_PAD_LEFT);
$fecha_doc = $R['fecha_resguardo'];               // usa la fecha del resguardo
$lugar     = $R['lugar'] ?? '';
$director  = $R['director'] ?? '';
$creado_por = $R['creado_por'] ?? ($S['creado_por'] ?? 'sistema');


// ---------- Render plano (sin header/navbar para imprimir limpio) ----------
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Resguardo <?= htmlspecialchars($folio_str) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>../assets/css/style.css">
    <style>
        html,
        body {
            color: var(--fg);
            font: 13px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue",
                Arial, "Noto Sans", "Liberation Sans", "Apple Color Emoji", "Segoe UI Emoji",
                "Segoe UI Symbol";
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
        <button class="btn-back" onclick="history.back()">← Volver</button>
    </div>
    <div class="sheet">
        <header>
            <!-- Ajusta el src si ya tienes un logo en assets/img/logo.png -->
            <img class="logo" src="<?= htmlspecialchars($BASE . '/assets/img/logo.png') ?>" alt="Logo" onerror="this.style.display='none'">
            <div class="hgroup">
                <h1>Policía Estatal de Seguridad Pública</h1>
                <div class="sub">Resguardo de Uniforme</div>
                <div class="folio">
                    <div class="lbl">Folio</div>
                    <div class="val"><?= htmlspecialchars($folio_str) ?></div>
                </div>
            </div>
        </header>
        <br>
        <main class:"contenido">
            <section class="meta">
                <div class="item">
                    <div class="k">Fecha del resguardo:</div>
                    <div class="v"><?= htmlspecialchars($fecha_doc) ?></div>
                </div>
                <div class="item">
                    <div class="k">Lugar de emisión:</div>
                    <div class="v"><?= htmlspecialchars($lugar ?: '—') ?></div>
                </div>
                <div class="item">
                    <div class="k">Nombre:</div>
                    <div class="v"><?= htmlspecialchars($S['empleado_nombre']) ?></div>
                </div>
                <div class="item">
                    <div class="k">Núm. de empleado:</div>
                    <div class="v"><?= htmlspecialchars($S['no_empleado'] ?: '—') ?></div>
                </div>
                <div class="item">
                    <div class="k">Puesto:</div>
                    <div class="v"><?= htmlspecialchars($S['puesto'] ?: '—') ?></div>
                </div>
            </section>
            <br>
            <section class="texto-intro">
                <p>
                    RECIBÍ DE CONFORMIDAD DE <strong><?= htmlspecialchars($director) ?></strong>, DIRECTORA DE ADMINISTRACIÓN DE LA POLICÍA ESTATAL DE SEGURIDAD PÚBLICA, UNIFORME CON LOGOS DE LA POLICÍA ESTATAL.
                </p>
                <p>
                    MISMO QUE SERÁN DESTINADOS A LOS USOS PROPIOS QUE LA INSTITUCIÓN ME ENCOMIENDE, QUEDANDO BAJO MI RESPONSABILIDAD SU BUEN USO Y CONSERVACIÓN DURANTE EL TIEMPO QUE ESTÉ BAJO MI CUSTODIA.
                </p>
                <p><strong>DETALLE DE BIENES:</strong></p>
            </section>

            <table>
                <thead>
                    <tr>
                        <th style="width:18%;">Código</th>
                        <th>Descripción</th>
                        <th style="width:16%;">Modelo</th>
                        <th style="width:12%;">Talla</th>
                        <th class="num" style="width:12%;">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    if ($DET):
                        foreach ($DET as $d):
                            $total += (int)$d['cantidad'];
                    ?>
                            <tr>
                                <td><?= htmlspecialchars($d['codigo']) ?></td>
                                <td><?= htmlspecialchars($d['descripcion']) ?></td>
                                <td><?= htmlspecialchars($d['modelo']) ?></td>
                                <td><span class="chip"><?= htmlspecialchars($d['talla']) ?></span></td>
                                <td class="num"><?= (int)$d['cantidad'] ?></td>
                            </tr>
                        <?php
                        endforeach;
                    else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#777;">No hay partidas en esta salida.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="num">TOTAL DE PIEZAS</td>
                        <td class="num"><?= (int)$total ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if (!empty($S['observaciones'])): ?>
                <div class="note">
                    <strong>Observaciones:</strong><br>
                    <?= nl2br(htmlspecialchars($S['observaciones'])) ?>
                </div>
            <?php endif; ?>

            <section class="signs">
                <div class="sign">
                    <div><strong>Recibí de conformidad</strong></div>
                    <br>
                    <br>
                    <div class="line"><strong><?= htmlspecialchars($S['empleado_nombre']) ?></strong></div>
                    <div style="font-size:11px; color:#555;"></div>
                </div>
                <div class="sign">
                    <div><strong>Entrega</strong></div>
                    <br>
                    <br>
                    <div class="line"><strong><?= htmlspecialchars($director ?: 'Responsable de Almacén') ?></strong></div>
                    <div style="font-size:11px; color:#555;"><strong>Directora Administrativa de la Coordinación Estatal de la Policía Estatal de Seguridad Pública</strong></div>
                </div>
            </section>
        </main>

        <!-- Espacio reservado para que el pie fijo no se empalme con el contenido -->
        <div class="footer-spacer"></div>

        <footer class="pie-institucional">
            <div class="pie-contenido">
                <img class="logo2" src="<?= htmlspecialchars($BASE . '/assets/img/logo2.png') ?>"
                    alt="Logo" onerror="this.style.display='none'">
                <div class="info">
                    <strong>POLICÍA ESTATAL DE SEGURIDAD PÚBLICA</strong><br>
                    LUIS ENCINAS Y CALLEJÓN OBREGÓN, COLONIA EL TORREÓN<br>
                    TEL. +52 (662) 218-9419 Y 218-9420<br>
                    HERMOSILLO, SONORA, MÉXICO —
                    <a href="https://www.sonora.gob.mx" target="_blank">www.sonora.gob.mx</a>
                </div>
            </div>
        </footer>

    </div>
</body>

</html>