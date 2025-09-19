<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/config.php'; // para BASE_URL

$BASE = rtrim(BASE_URL, '/');

if (!auth_check()) {
  header('Location: ' . $BASE . '/modules/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ($BASE . '/')));
  exit;
}

$toAdmin = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if ($toAdmin) {
  header('Location: ' . $BASE . '/modules/uniformes/index.php');
} else {
  header('Location: ' . $BASE . '/modules/uniformes/catalogo.php');
}
exit;
