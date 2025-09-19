<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: text/html; charset=UTF-8');

$ok = true;
$errores = [];

// 1) Probar conexión
$cn = @db();
if (!$cn) {
    $ok = false;
    $errores[] = 'No se pudo abrir conexión.';
}

// 2) Probar que existen tablas clave (equipo y item_variantes)
$tablas = ['equipo', 'item_variantes'];
$existe = [];

foreach ($tablas as $t) {
    $t_like = mysqli_real_escape_string($cn, $t);
    $sql = "SHOW TABLES LIKE '$t_like'";
    $rows = db_select_all($sql);
    $existe[$t] = !empty($rows) && empty($rows['_error']);
}

// 3) Intentar leer conteos
$conteos = [];
foreach ($tablas as $t) {
    if ($existe[$t]) {
        $rows = db_select_all("SELECT COUNT(*) AS total FROM `$t`");
        $conteos[$t] = (!empty($rows) && empty($rows['_error'])) ? (int)$rows[0]['total'] : 'Error de consulta';
    } else {
        $conteos[$t] = 'No existe';
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Chequeo BD · Uniformes</title>
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
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
    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5 mb-3">Chequeo de Base de Datos</h1>

                <p class="mb-2"><strong>BD:</strong> <?= htmlspecialchars(DB_NAME) ?></p>
                <p class="mb-3"><strong>Estado conexión:</strong>
                    <?php if ($ok): ?>
                        <span class="badge text-bg-success">OK</span>
                    <?php else: ?>
                        <span class="badge text-bg-danger">ERROR</span>
                    <?php endif; ?>
                </p>

                <?php if (!$ok): ?>
                    <div class="alert alert-danger">
                        <?= implode('<br>', array_map('htmlspecialchars', $errores)) ?>
                    </div>
                <?php endif; ?>

                <h2 class="h6 mt-4">Tablas requeridas</h2>
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Tabla</th>
                            <th>Existe</th>
                            <th>Conteo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablas as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t) ?></td>
                                <td>
                                    <?php if ($existe[$t]): ?>
                                        <span class="badge text-bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string)$conteos[$t]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="text-muted small mb-0">
                    Si alguna tabla no existe o marca error, confirmamos el nombre de la BD o importamos el esquema.
                </p>
            </div>
        </div>
    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>