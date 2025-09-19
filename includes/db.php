<?php
// Conexión MySQLi (procedimental) + funciones utilitarias sencillas.

require_once __DIR__ . '/../config/config.php';

/** @var mysqli|null */
$GLOBALS['DB_CONN'] = null;

/**
 * Abre (si hace falta) y retorna la conexión MySQLi.
 * Uso: $cn = db();
 */
function db()
{
    if ($GLOBALS['DB_CONN'] instanceof mysqli) {
        return $GLOBALS['DB_CONN'];
    }

    $cn = mysqli_init();
    if (!$cn) {
        die('Error: no se pudo inicializar MySQLi');
    }

    // Sugerimos tiempo de conexión
    mysqli_options($cn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    if (!mysqli_real_connect($cn, DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        die('Error de conexión MySQL: ' . mysqli_connect_error());
    }

    if (!mysqli_set_charset($cn, DB_CHARSET)) {
        // No detenemos, pero avisamos
        error_log('Aviso: no se pudo fijar charset ' . DB_CHARSET . ' - ' . mysqli_error($cn));
    }

    $GLOBALS['DB_CONN'] = $cn;
    return $cn;
}

/**
 * Cierra la conexión (por si la quieres usar en scripts CLI/test).
 */
function db_close()
{
    if ($GLOBALS['DB_CONN'] instanceof mysqli) {
        mysqli_close($GLOBALS['DB_CONN']);
        $GLOBALS['DB_CONN'] = null;
    }
}

/**
 * Helper seguro para consultas SELECT simples.
 * Retorna array de filas asociativas.
 */
function db_select_all(string $sql)
{
    $cn = db();
    $res = mysqli_query($cn, $sql);
    if (!$res) {
        return ['_error' => mysqli_error($cn), '_sql' => $sql];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_free_result($res);
    return $rows;
}
