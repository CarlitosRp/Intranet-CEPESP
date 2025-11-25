<?php
// ============================================================================
// DB helpers (MySQLi) — Conexión singleton + consultas preparadas
// No se cierra la conexión automáticamente en los helpers.
// ============================================================================
require_once __DIR__ . '/../config/config.php';

$GLOBALS['DB_CONN'] = null;

/**
 * Retorna conexión MySQLi (singleton). Si está cerrada o cayó, reconecta.
 */
function db(): mysqli
{
    $cn = $GLOBALS['DB_CONN'] ?? null;

    // Si ya hay objeto pero está “muerto”, forzar reconexión
    if ($cn instanceof mysqli) {
        // ping() devuelve true si la conexión sigue viva
        if (@$cn->ping()) {
            return $cn;
        } else {
            // Intentar cerrar por si quedó en estado intermedio
            @mysqli_close($cn);
            $GLOBALS['DB_CONN'] = null;
        }
    }

    // Crear nueva conexión
    $cn = mysqli_init();
    if (!$cn) {
        throw new RuntimeException('No se pudo inicializar MySQLi');
    }
    mysqli_options($cn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    if (!@mysqli_real_connect($cn, DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        throw new RuntimeException('Error de conexión MySQL: ' . mysqli_connect_error());
    }

    if (!mysqli_set_charset($cn, DB_CHARSET)) {
        error_log('Aviso: no se pudo fijar charset ' . DB_CHARSET . ' — ' . mysqli_error($cn));
    }

    $GLOBALS['DB_CONN'] = $cn;
    return $cn;
}

/**
 * Cierra explícitamente la conexión global (si quieres hacerlo al final de un script).
 * Importante: los helpers NO llaman a esta función.
 */
function db_close(): void
{
    if ($GLOBALS['DB_CONN'] instanceof mysqli) {
        @mysqli_close($GLOBALS['DB_CONN']);
        $GLOBALS['DB_CONN'] = null;
    }
}

/** Escapa una cadena (para casos puntuales; normalmente usa consultas preparadas). */
function db_escape(string $s): string
{
    return mysqli_real_escape_string(db(), $s);
}

/** Infere tipos para bind_param: i (int), d (float), s (string), b (blob). */
function _db_infer_types(array $params): string
{
    $types = '';
    foreach ($params as $p) {
        if (is_int($p))      $types .= 'i';
        elseif (is_float($p)) $types .= 'd';
        elseif (is_null($p)) $types .= 's'; // bind_param no acepta null sin tipo
        else                 $types .= 's';
    }
    return $types;
}

/**
 * SELECT → todas las filas (array asociativo).
 * $sql con ? y $params (opcional). NO cierra la conexión.
 */
function db_select(string $sql, array $params = [], ?string $types = null): array
{
    $cn = db();
    $stmt = mysqli_prepare($cn, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception('db_select: prepare failed: ' . mysqli_error($cn), mysqli_errno($cn));
    }

    if (!empty($params)) {
        if ($types === null) $types = _db_infer_types($params);
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            $err = mysqli_error($cn);
            mysqli_stmt_close($stmt);
            throw new mysqli_sql_exception('db_select: bind_param failed: ' . $err, mysqli_errno($cn));
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        $code = mysqli_stmt_errno($stmt);
        mysqli_stmt_close($stmt);
        throw new mysqli_sql_exception('db_select: execute failed: ' . $err, $code);
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        mysqli_stmt_close($stmt);
        throw new mysqli_sql_exception('db_select: get_result no disponible (mysqlnd requerido)');
    }

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    return $rows;
}

/** SELECT → una fila (o null). */
function db_select_one(string $sql, array $params = [], ?string $types = null): ?array
{
    $rows = db_select($sql, $params, $types);
    return $rows[0] ?? null;
}

/** SELECT → primer valor (o null). */
function db_select_value(string $sql, array $params = [], ?string $types = null)
{
    $row = db_select_one($sql, $params, $types);
    if ($row === null) return null;
    $vals = array_values($row);
    return $vals[0] ?? null;
}

/** Alias histórico que usas en el proyecto (devuelve todas las filas). */
function db_select_all(string $sql, array $params = [], ?string $types = null): array
{
    return db_select($sql, $params, $types);
}

/**
 * INSERT/UPDATE/DELETE → devuelve filas afectadas (int).
 * NO cierra la conexión.
 */
function db_execute(string $sql, array $params = [], ?string $types = null): int
{
    $cn = db();
    $stmt = mysqli_prepare($cn, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception('db_execute: prepare failed: ' . mysqli_error($cn), mysqli_errno($cn));
    }

    if (!empty($params)) {
        if ($types === null) $types = _db_infer_types($params);
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            $err = mysqli_error($cn);
            mysqli_stmt_close($stmt);
            throw new mysqli_sql_exception('db_execute: bind_param failed: ' . $err, mysqli_errno($cn));
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        $code = mysqli_stmt_errno($stmt);
        mysqli_stmt_close($stmt);
        throw new mysqli_sql_exception('db_execute: execute failed: ' . $err, $code);
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

/** INSERT que devuelve insert_id (int). */
function db_insert(string $sql, array $params = [], ?string $types = null): int
{
    $cn = db();
    $stmt = mysqli_prepare($cn, $sql);
    if (!$stmt) {
        throw new mysqli_sql_exception('db_insert: prepare failed: ' . mysqli_error($cn), mysqli_errno($cn));
    }

    if (!empty($params)) {
        if ($types === null) $types = _db_infer_types($params);
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            $err = mysqli_error($cn);
            mysqli_stmt_close($stmt);
            throw new mysqli_sql_exception('db_insert: bind_param failed: ' . $err, mysqli_errno($cn));
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        $code = mysqli_stmt_errno($stmt);
        mysqli_stmt_close($stmt);
        throw new mysqli_sql_exception('db_insert: execute failed: ' . $err, $code);
    }

    $id = mysqli_insert_id($cn);
    mysqli_stmt_close($stmt);
    return (int)$id;
}
