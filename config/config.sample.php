<?php
// Copia este archivo a config.php y completa tus datos locales.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'intranet');

define('BASE_URL', '/intranet-CEPESP'); // Ajusta a tu carpeta en htdocs

// Entorno
define('APP_DEBUG', true);
if (APP_DEBUG) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', 0);
  error_reporting(0);
}
