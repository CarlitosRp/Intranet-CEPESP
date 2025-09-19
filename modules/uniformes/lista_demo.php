<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: text/html; charset=UTF-8');

/**
 * Utilidades sencillas
 */
function describe_table(string $table)
{
    return db_select_all("DESCRIBE `$table`");
}
function table_exists(string $table): bool
{
    $cn = db();
    $t_like = mysqli_real_escape_string($cn, $table);
    $rows = db_select_all("SHOW TABLES LIKE '$t_like'");
    return !empty($rows) && empty($rows['_error']);
}
function pick_primary_key(string $table): ?string
{
    $desc = describe_table($table);
    if (!empty($desc) && empty($desc['_error'])) {
        foreach ($desc as $col) {
            if (($col['Key'] ?? '') === 'PRI') return $col['Field'];
        }
    }
    return null;
}
function pick_fk_to_equipo(array $columns): ?string
{
    // candidatos comunes por nombre
    $patterns = ['equipo_id', 'id_equipo', 'fk_equipo', 'equipo', 'equipoID', 'IDEQUIPO'];
    foreach ($columns as $c) {
        $name = $c['Field'] ?? '';
        $low  = strtolower($name);
        if (in_array($low, array_map('strtolower', $patterns), true)) {
            return $name; // respeta mayúsculas reales
        }
        if (str_ends_with($low, '_equipo') || str_ends_with($low, '_equipo_id')) {
            return $name;
        }
    }
    return null;
}
function select_all_limited(string $table, int $limit = 10)
{
    return db_select_all("SELECT * FROM `$table` LIMIT " . intval($limit));
}

/**
 * Verificaciones base
 */
$errors = [];
if (!table_exists('equipo')) {
    $errors[] = "La tabla 'equipo' no existe.";
}
if (!table_exists('item_variantes')) {
    $errors[] = "La tabla 'item_variantes' no existe.";
}

$join_rows = [];
$join_sql  = '';
$fk_used   = null;
$pk_equipo = null;

if (!$errors) {
    // Detectar PK de equipo
    $pk_equipo = pick_primary_key('equipo') ?? 'id'; // fallback común
    // Detectar columna FK en item_variantes
    $desc_var   = describe_table('item_variantes');
    $fk_used    = (!empty($desc_var) && empty($desc_var['_error'])) ? pick_fk_to_equipo($desc_var) : null;

    if ($fk_used) {
        // JOIN genérico (trae hasta 10 filas)
        $join_sql  = "SELECT e.*, v.* 
                      FROM `equipo` e 
                      JOIN `item_variantes` v ON v.`$fk_used` = e.`$pk_equipo`
                      LIMIT 10";
        $join_rows = db_select_all($join_sql);
        if (!empty($join_rows['_error'])) {
            $errors[] = "Error en JOIN: " . $join_rows['_error'];
        }
    } else {
        $errors[] = "No se detectó una columna foránea clara en 'item_variantes' que apunte a 'equipo'.";
    }
}

// Datos de respaldo (para ayudarte a decidir la columna correcta)
$sample_equipo = select_all_limited('equipo', 5);
$sample_vars   = select_all_limited('item_variantes', 5);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Demo JOIN · Uniformes</title>
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

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h1 class="h5 mb-2">JOIN de prueba: <code>equipo</code> + <code>item_variantes</code></h1>
                <p class="mb-1"><strong>PK de equipo detectada:</strong> <code><?= htmlspecialchars((string)$pk_equipo) ?></code></p>
                <p class="mb-0"><strong>FK usada en item_variantes:</strong> <code><?= htmlspecialchars((string)$fk_used) ?: '— (no detectada)' ?></code></p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-warning">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
                <hr class="my-2">
                <div class="small text-muted">
                    Si no se detecta la FK automáticamente, revisa en <code>check_schema.php</code> qué columna de
                    <code>item_variantes</code> referencia a <code>equipo(<?= htmlspecialchars((string)$pk_equipo) ?>)</code> y
                    cámbiala en este archivo (función <code>pick_fk_to_equipo()</code>).
                </div>
            </div>
        <?php endif; ?>

        <?php if ($fk_used && empty($join_rows['_error'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6">Resultado JOIN (máx. 10 filas)</h2>
                    <?php if ($join_rows): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($join_rows[0]) as $col): ?>
                                            <th><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($join_rows as $r): ?>
                                        <tr>
                                            <?php foreach ($r as $val): ?>
                                                <td><?= htmlspecialchars((string)$val) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <details class="mt-2">
                            <summary class="small text-muted">SQL ejecutado</summary>
                            <code><?= htmlspecialchars($join_sql) ?></code>
                        </details>
                    <?php else: ?>
                        <div class="text-muted">Sin filas para mostrar.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Muestra de <code>equipo</code> (5 filas)</h2>
                        <?php if (!empty($sample_equipo) && empty($sample_equipo['_error'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($sample_equipo[0] ?? []) as $c): ?>
                                                <th><?= htmlspecialchars($c) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_equipo as $r): ?>
                                            <tr>
                                                <?php foreach ($r as $v): ?>
                                                    <td><?= htmlspecialchars((string)$v) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No se pudo leer <code>equipo</code>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Muestra de <code>item_variantes</code> (5 filas)</h2>
                        <?php if (!empty($sample_vars) && empty($sample_vars['_error'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($sample_vars[0] ?? []) as $c): ?>
                                                <th><?= htmlspecialchars($c) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_vars as $r): ?>
                                            <tr>
                                                <?php foreach ($r as $v): ?>
                                                    <td><?= htmlspecialchars((string)$v) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No se pudo leer <code>item_variantes</code>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>