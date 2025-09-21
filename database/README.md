# Base de datos · Intranet CEPESP

Este directorio contiene los scripts SQL necesarios para crear la base de datos del proyecto **Intranet CEPESP**.

---

## 📌 Scripts disponibles

- `db_intranet_v2.sql` → Script principal de creación de todas las tablas y relaciones.

---

## 🚀 Cómo importar la base de datos

### Opción 1 · Usando phpMyAdmin (XAMPP)
1. Abre [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Crea una base de datos nueva llamada **intranet**.
3. Selecciona la BD recién creada.
4. Haz clic en la pestaña **Importar**.
5. Elige el archivo `db_intranet_v2.sql` y presiona **Continuar**.

### Opción 2 · Usando la consola MySQL
1. Abre una terminal en la carpeta del proyecto.
2. Ejecuta:
   ```bash
   mysql -u root -p intranet < database/db_intranet_v2.sql
