<?php
// Zona horaria del proyecto (preferencia tuya)
date_default_timezone_set('America/Hermosillo');
$hoy = date('Y-m-d');
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Intranet · Inicio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap local -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <!-- Tu hoja de estilos -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* Por si aún no creas style.css, dejamos un mínimo aquí */
        body {
            background: #f6f7f9;
        }

        .card {
            border-radius: 12px;
        }
    </style>
</head>

<body>
    <!-- Navbar simple -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="#">Intranet</a>
        </div>
    </nav>

    <!-- Contenido -->
    <main class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Bienvenido a la Intranet</h1>

                <ul class="mb-3">
                    <li><strong>Ruta del proyecto:</strong> C:\xampp\htdocs\intranet</li>
                    <li><strong>Fecha (Hermosillo):</strong> <?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?></li>
                </ul>

                <a class="btn btn-primary" href="modules/uniformes/">Ir a Uniformes</a>
                <p class="text-muted small mt-2 mb-0">
                    *En el siguiente paso crearemos la página del módulo <em>uniformes</em>.
                </p>
            </div>
        </div>
    </main>

    <!-- Bootstrap local (bundle incluye Popper) -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <!-- Tu JS -->
    <script src="assets/js/app.js"></script>
</body>

</html>