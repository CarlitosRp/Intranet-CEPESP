# Intranet CEPESP

Proyecto de intranet modular desarrollado en **PHP + MySQL + Bootstrap**, pensado para la gestión interna de la empresa.

Actualmente incluye el **módulo de Uniformes**, con catálogo, listado, detalle y edición (con control de acceso por roles).

---

## 🚀 Requisitos

- PHP 8.x
- MySQL/MariaDB
- XAMPP (o servidor LAMP/WAMP similar)
- Composer (opcional, futuro uso)
- Git (para clonar y versionar)

---

## 📂 Estructura del proyecto

intranet-CEPESP/
├─ config/
│ └─ config.php # Configuración base (BASE_URL, DB, etc.)
├─ includes/
│ ├─ db.php # Conexión a la base de datos
│ ├─ auth.php # Manejo de login/logout, roles
│ ├─ navbar.php # Navbar dinámico por rol
│ ├─ breadcrumbs.php # Migas de pan reutilizables
├─ modules/
│ ├─ auth/
│ │ ├─ login.php
│ │ └─ logout.php
│ └─ uniformes/
│ ├─ catalogo.php
│ ├─ index.php
│ ├─ detalle.php
│ └─ editar.php
├─ assets/
│ ├─ css/bootstrap.min.css
│ └─ js/bootstrap.bundle.min.js
├─ uploads/ # Carpeta protegida para archivos
│ └─ .htaccess
├─ index.php # Redirección según rol
└─ README.md # Este archivo


---

## 🛠 Instalación

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/tuusuario/intranet-CEPESP.git

2. Importar la base de datos:

    Crear BD intranet en phpMyAdmin.

    Importar el archivo db_intranet.sql.

3. Configurar acceso a BD en config/config.php:
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'intranet');
    define('BASE_URL', '/intranet-CEPESP'); // ruta del proyecto

 4. Crear usuario administrador (si no existe):

    Ejecutar una sola vez tools/seed_admin.php.

    Usuario inicial:

        Email: admin@local

        Password: admin123

5. Abrir en el navegador:
    http://localhost/intranet-CEPESP/
    

🔑 Roles

    Actualmente definidos en la tabla roles:

        admin → acceso total (CRUD, administración)

        inventarios / almacen → gestión de uniformes

        lector → solo consulta (catálogo)

    Los menús y accesos se ajustan automáticamente según el rol del usuario.

    ✨ Funcionalidades actuales

    ✅ Login y logout con sesiones PHP.

    ✅ Control de acceso por roles.

    ✅ Navbar dinámico según usuario logueado.

    ✅ Breadcrumbs para navegación clara.

    ✅ Catálogo agrupado de uniformes.

    ✅ Listado con búsqueda y paginación.

    ✅ Detalle con tallas en chips.

    ✅ Edición de producto (segura, con CSRF y roles).

    ✅ Redirección en index.php según rol.

    🛣 Roadmap

    Uniformes

    Catálogo, listado y detalle

    Edición con control por rol

    Alta y baja de tallas

    Subida de imágenes por producto

    Exportar a PDF/Excel

    Usuarios y roles

    CRUD de usuarios

    CRUD de roles

    Restablecer contraseña

    UI/UX

    Navbar dinámico

    Breadcrumbs

    Dashboard de inicio con indicadores

    Tema claro/oscuro

    📄 Notas de seguridad

    El archivo tools/seed_admin.php debe eliminarse después de crear el admin.

    La carpeta uploads/ contiene .htaccess para evitar ejecución de PHP.

    .gitignore incluye archivos sensibles (config local, logs, IDE).

    📌 Convenciones de commits

    Este proyecto usa un estilo inspirado en Conventional Commits
    :

    <tipo>(<área>): <resumen corto>

    <explicación opcional más detallada>


    Ejemplos:

    feat(uniformes): catálogo agrupado con GROUP_CONCAT

    fix(db): corregido JOIN en item_variantes

    style(ui): tallas como chips

    docs: agregar guía de commits

    👨‍💻 Autor

    Desarrollado paso a paso con fines de aprendizaje y uso interno.