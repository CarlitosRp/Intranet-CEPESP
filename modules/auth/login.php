<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php'; // para BASE_URL si la usas en links
header('Content-Type: text/html; charset=UTF-8');

$err = $err ?? '';
$flash_ok = '';

// Normaliza el "next" y evita open-redirects
$BASE = rtrim(BASE_URL, '/');
$defaultNext = $BASE . '/modules/uniformes/catalogo.php';
$next = $_GET['next'] ?? $defaultNext;
if (strpos($next, $BASE) !== 0) {
    $next = $defaultNext; // si no apunta a nuestra app, fuerza el default
}

// ✅ mensaje post-logout
if (!empty($_GET['logout'])) {
    $flash_ok = 'Has cerrado sesión correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email === '' || $pass === '') {
        $err = 'Email y contraseña son obligatorios.';
    } else {
        if (auth_login($email, $pass)) {
            header('Location: ' . $next);
            exit;
        } else {
            $err = 'Credenciales inválidas o usuario inactivo.';
        }
    }
}
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Intranet · Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSS local -->
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE . '/assets/css/bootstrap.min.css') ?>">
    <style>
        body {
            background: #f6f7f9;
        }

        .card {
            border-radius: 12px;
            max-width: 420px;
            margin: 10vh auto;
        }
    </style>
</head>

<body>
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h5 mb-3 text-center">Intranet · Acceso</h1>

            <?php if (!empty($flash_ok)): ?>
                <div class="alert alert-success alert-dismissible fade show auto-hide">
                    <?= htmlspecialchars($flash_ok) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show auto-hide">
                    <?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php?next=<?= urlencode($next) ?>">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@local" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
    </div>

    <!-- JS local -->
    <script src="<?= htmlspecialchars($BASE . '/assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script>
        // Auto-ocultar alerts a los 4s (como en footer.php), lo dejamos aquí porque login no incluye footer
        document.addEventListener("DOMContentLoaded", () => {
            const alerts = document.querySelectorAll(".alert-dismissible.auto-hide");
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.remove("show");
                    alert.classList.add("fade");
                    setTimeout(() => alert.remove(), 500);
                }, 4000);
            });
        });
    </script>
</body>

</html>