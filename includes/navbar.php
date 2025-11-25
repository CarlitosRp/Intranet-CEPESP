<?php
// includes/navbar.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php'; // BASE_URL

$u        = auth_user();
$isLogged = auth_check();

// Activo por “aguja” dentro de la URL actual
function nav_active(string $needle): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return (stripos($uri, $needle) !== false) ? 'active' : '';
}

// Permisos “operativos”
$canOperar = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');

// Atajos
$BASE          = rtrim(BASE_URL, '/');
$URL_CATALOGO  = $BASE . '/modules/uniformes/catalogo.php';
$URL_LISTADO   = $BASE . '/modules/uniformes/index.php';
$URL_LOGIN     = $BASE . '/modules/auth/login.php';
$URL_LOGOUT    = $BASE . '/modules/auth/logout.php';

// Inventario
$URL_ENTRADAS_INDEX = $BASE . '/modules/inventario/entradas/index.php';
$URL_ENTRADAS_NUEVA = $BASE . '/modules/inventario/entradas/crear.php';
$URL_EXISTENCIAS    = $BASE . '/modules/inventario/existencias/index.php';
?>
<nav class="navbar navbar-dark navbar-expand-lg" style="background-color: #0b2965;">
    <div class="container">
        <a class="navbar-brand" href="<?= htmlspecialchars($URL_CATALOGO) ?>">Intranet CEPESP</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <!-- IZQUIERDA -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Uniformes -->
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('/uniformes/catalogo.php') ?>" href="<?= htmlspecialchars($URL_CATALOGO) ?>">
                        Catálogo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('/uniformes/index.php') ?>" href="<?= htmlspecialchars($URL_LISTADO) ?>">
                        Listado
                    </a>
                </li>

                <?php if ($canOperar): ?>
                    <!-- Inventario -->
                    <li class="nav-item dropdown">
                        <?php
                        // Activo si cae en cualquiera de las rutas de inventario
                        $isInvActive = nav_active('/inventario/entradas/')
                            || nav_active('/inventario/existencias/');
                        ?>
                        <a class="nav-link dropdown-toggle <?= $isInvActive ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            Inventario
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <h6 class="dropdown-header">Entradas</h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($URL_ENTRADAS_INDEX) ?>">Listado</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($URL_ENTRADAS_NUEVA) ?>">Nueva entrada</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <h6 class="dropdown-header">Salidas</h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">Listado</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/crear.php') ?>">Nueva salida</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($URL_EXISTENCIAS) ?>">Existencias</a></li>
                        </ul>
                    </li>
                    <!-- Resguardos -->
                    <li class="nav-item dropdown">
                        <?php
                        // Activo si cae en cualquiera de las rutas de inventario
                        $isInvActive = nav_active('/inventario/entradas/')
                            || nav_active('/inventario/existencias/');
                        ?>
                        <a class="nav-link dropdown-toggle <?= $isInvActive ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            Resguardos
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <h6 class="dropdown-header">Resguardos</h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/resguardos/index.php') ?>">Resguardos</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/resguardos/por_empleado.php') ?>">Resguardos por empleado</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/resguardos/reporte_global.php') ?>">Reporte global</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/resguardos/reporte_categoria.php') ?>">Reporte por categoría</a></li>
                        </ul>
                    </li>


                    <li class="nav-item dropdown">
                        <?php
                        // Activo si cae en cualquiera de las rutas de inventario
                        $isInvActive = nav_active('/inventario/entradas/')
                            || nav_active('/inventario/existencias/');
                        ?>
                        <a class="nav-link dropdown-toggle <?= $isInvActive ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            Reportes
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <h6 class="dropdown-header">Reportes</h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($BASE . '/modules/reportes/reportes_uniformes.php') ?>">Reporte Salidas</a></li>                            
                        </ul>
                    </li>


                    <!-- Administración (placeholder futuro) -->
                    <li class="nav-item dropdown">
                        <?php $isAdminActive = nav_active('/users') || nav_active('/roles'); ?>
                        <a class="nav-link dropdown-toggle <?= $isAdminActive ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            Administración
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item disabled" href="#">Usuarios (pendiente)</a></li>
                            <li><a class="dropdown-item disabled" href="#">Roles (pendiente)</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- DERECHA -->
            <ul class="navbar-nav ms-auto">
                <?php if ($isLogged): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-2" style="color: #ffffff;">
                            👋 <?= htmlspecialchars($u['name'] ?? $u['email'] ?? 'Usuario') ?>
                            <small class="">
                                (<?= htmlspecialchars($u['role'] ?? '') ?>)
                            </small>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm" href="<?= htmlspecialchars($URL_LOGOUT) ?>">Salir</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($URL_LOGIN) ?>">Entrar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>