<?php
// includes/header.php
// Encabezado común: <head>, CSS de Bootstrap local y navbar.

require_once __DIR__ . '/../config/config.php';
$BASE = rtrim(BASE_URL, '/');

if (!empty($USE_DATATABLES)) {
    require_once __DIR__ . '/datatables_assets.php';
}

// Permite personalizar el <title> desde cada página
$title = isset($page_title) ? $page_title : 'Intranet CEPESP';

// (Opcional) añade CSS extra desde la página con $extra_css = ['url1.css', 'url2.css'];
$extra_css = $extra_css ?? [];
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($BASE) ?>/assets/img/logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap LOCAL -->
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/assets/css/style.css">    
    <?php foreach ($extra_css as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
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
    <?php require_once __DIR__ . '/navbar.php'; ?>