<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

$cn = db();
$tablas = ['equipo', 'item_variantes'];

/**
 * Devuelve DESCRIBE de una tabla (columnas básicas).
 */
function describe_table($table)
{
    $rows = db_select_all("DESCRIBE `$table`");
    return $rows;
}

/**
 * Devuelve claves (PRIMARY, FOREIGN) desde INFORMATION_SCHEMA si están definidas.
 */
function keys_info($table)
{
    $db = DB_NAME;
    $sql = "
        SELECT
          k.CONSTRAINT_NAME,
          k.TABLE_NAME,
          k.COLUMN_NAME,
          k.REFERENCED_TABLE_NAME,
          k.REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
        WHERE k.TABLE_SCHEMA = '" . mysqli_real_escape_string(db(), $db) . "'
          AND k.TABLE_NAME = '" . mysqli_real_escape_string(db(), $table) . "'
        ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION
    ";
    return db_select_all($sql);
}

/**
 * Heurística simple: propone posibles columnas foráneas que apunten a equipo.
 */
function guess_fk_to_equipo($columns)
{
    $candidates = [];
    $patterns = ['equipo_id', 'id_equipo', 'fk_equipo', 'equipo']; // comunes
    foreach ($columns as $col) {
        $name = strtolower($col['Field'] ?? '');
        foreach ($patterns as $p) {
            if ($name === $p) {
                $candidates[] = $col['Field'];
            }
        }
        // también aceptamos termina_en _equipo o _id
        if (str_ends_with($name, '_equipo') || str_ends_with($name, '_equipo_id')) {
            $candidates[] = $col['Field'];
        }
    }
    // únicos
    return array_values(array_unique($candidates));
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Esquema BD · Uniformes</title>
    <link rel="stylesheet" href="/intranet-CEPESP/assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f6f7f9
        }

        .card {
            border-radius: 12px
        }

        code {
            font-size: .9rem
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5 mb-3">Esquema de tablas</h1>

                <?php foreach ($tablas as $t): ?>
                    <?php $desc = describe_table($t);
                    $keys = keys_info($t); ?>
                    <h2 class="h6 mt-3 mb-2"><?= htmlspecialchars($t) ?></h2>
                    <?php if (!empty($desc) && empty($desc['_error'])): ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Columna</th>
                                    <th>Tipo</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($desc as $col): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($col['Field']) ?></code></td>
                                        <td><?= htmlspecialchars($col['Type']) ?></td>
                                        <td><?= htmlspecialchars($col['Null']) ?></td>
                                        <td><?= htmlspecialchars($col['Key']) ?></td>
                                        <td><?= htmlspecialchars((string)$col['Default']) ?></td>
                                        <td><?= htmlspecialchars($col['Extra']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-warning">No se pudo describir la tabla <?= htmlspecialchars($t) ?>.</div>
                    <?php endif; ?>

                    <details class="mb-3">
                        <summary>Claves (PRIMARY/FOREIGN) detectadas</summary>
                        <?php if (!empty($keys) && empty($keys['_error'])): ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Constraint</th>
                                        <th>Columna</th>
                                        <th>Ref. Tabla</th>
                                        <th>Ref. Columna</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keys as $k): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($k['CONSTRAINT_NAME']) ?></td>
                                            <td><code><?= htmlspecialchars($k['COLUMN_NAME']) ?></code></td>
                                            <td><?= htmlspecialchars($k['REFERENCED_TABLE_NAME'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($k['REFERENCED_COLUMN_NAME'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-muted small">Sin claves registradas en INFORMATION_SCHEMA o sin permisos.</div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>

                <?php
                // Propuesta de FK desde item_variantes hacia equipo (heurística por nombres)
                $desc_item = describe_table('item_variantes');
                $fk_candidates = (!empty($desc_item) && empty($desc_item['_error'])) ? guess_fk_to_equipo($desc_item) : [];
                ?>
                <h2 class="h6 mt-4">Posibles columnas foráneas en <code>item_variantes</code> → <code>equipo</code></h2>
                <?php if ($fk_candidates): ?>
                    <ul>
                        <?php foreach ($fk_candidates as $c): ?>
                            <li><code><?= htmlspecialchars($c) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="text-muted small">
                        Usa una de ellas para el JOIN. En el siguiente paso generamos una consulta de ejemplo con límite de 10 filas.
                    </p>
                <?php else: ?>
                    <div class="alert alert-info">
                        No se detectaron candidatos por nombre. En el próximo paso hacemos un JOIN guiado
                        (te muestro 2-3 plantillas y escogemos la que cuadre según tus columnas).
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>