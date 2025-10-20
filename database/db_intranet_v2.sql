-- ====== Roles ======
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,     -- admin, almacen, rrhh, etc.
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====== Usuarios ======
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  department VARCHAR(80) DEFAULT NULL,  -- opcional, útil para el navbar por departamentos
  role_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====== Datos base de roles ======
INSERT IGNORE INTO roles (id, name, description) VALUES
  (1, 'admin', 'Administrador con acceso total'),
  (2, 'inventarios', 'Acceso a módulo de uniformes y gestión de archivos'),
  (3, 'rrhh', 'Recursos Humanos (control de empleados)');
  
  -- ==========================================
-- INTRANET · Equipo táctico (modelo base)
-- ==========================================
-- NOTA: usamos `año` con ñ → rodeamos con backticks (`) para evitar problemas.
-- Charset: utf8mb4 para soportar caracteres latinos.

-- 1) EMPLEADOS --------------------------------
CREATE TABLE IF NOT EXISTS empleados (
  id_empleado   INT AUTO_INCREMENT PRIMARY KEY,
  no_empleado   VARCHAR(20)  NOT NULL UNIQUE,
  curp          VARCHAR(18)  NOT NULL UNIQUE,
  nombre_completo VARCHAR(120) NOT NULL,
  base          VARCHAR(80)  DEFAULT NULL,   -- antes 'departamento'
  puesto        VARCHAR(80)  DEFAULT NULL,
  estatus       TINYINT(1)   NOT NULL DEFAULT 1, -- 1=activo,0=baja
  sexo          ENUM('MASCULINO','FEMENINO') DEFAULT NULL,
  rfc           VARCHAR(13)  DEFAULT NULL,
  cuip          VARCHAR(20)  DEFAULT NULL,
  fecha_alta    DATE         DEFAULT NULL,
  fecha_baja    DATE         DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) EQUIPO + VARIANTES (tallas) --------------
-- Asegúrate de que 'equipo' esté creado antes que 'item_variantes'
CREATE TABLE IF NOT EXISTS equipo (
  id_equipo     INT AUTO_INCREMENT PRIMARY KEY,
  codigo        VARCHAR(50)  NOT NULL UNIQUE, -- p.ej. 'BOTA-12401'
  descripcion   VARCHAR(255) NOT NULL,
  modelo        VARCHAR(50)  NOT NULL,
  categoria     VARCHAR(50)  NOT NULL,
  maneja_talla  TINYINT(1)   NOT NULL DEFAULT 1,
  activo        TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versión de origen con FK en CASCADA y UNIQUE(id_equipo, talla)
CREATE TABLE IF NOT EXISTS item_variantes (
  id_variante INT AUTO_INCREMENT PRIMARY KEY,
  id_equipo   INT         NOT NULL,
  talla       VARCHAR(20) NOT NULL,           -- 'S','M','L','26','ÚNICA', etc.
  activo      TINYINT(1)  NOT NULL DEFAULT 1,

  -- Evita duplicados de talla por producto:
  CONSTRAINT uq_equipo_talla UNIQUE (id_equipo, talla),

  -- Relación con equipo: al eliminar el equipo, se eliminan sus tallas
  CONSTRAINT fk_var_equipo FOREIGN KEY (id_equipo)
    REFERENCES equipo(id_equipo)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) ENTRADAS (cabecera + detalle) -------------
CREATE TABLE IF NOT EXISTS entradas (
  id_entrada    INT AUTO_INCREMENT PRIMARY KEY,
  fecha         DATE         NOT NULL,
  proveedor     VARCHAR(120) NOT NULL,
  factura       VARCHAR(60)  NOT NULL,
  observaciones VARCHAR(255) DEFAULT NULL,
  creado_por    VARCHAR(60)  DEFAULT NULL,
  INDEX idx_ent_fecha (fecha),
  INDEX idx_ent_factura (factura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entradas_detalle (
  id_detalle_entrada INT AUTO_INCREMENT PRIMARY KEY,
  id_entrada         INT NOT NULL,
  id_variante        INT NOT NULL,
  cantidad           INT NOT NULL CHECK (cantidad > 0),
  CONSTRAINT fk_ed_ent FOREIGN KEY (id_entrada)  REFERENCES entradas(id_entrada)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ed_var FOREIGN KEY (id_variante) REFERENCES item_variantes(id_variante)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_ed_var (id_variante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============== SALIDAS (cabecera + detalle) ===================
CREATE TABLE IF NOT EXISTS salidas (
  id_salida     INT AUTO_INCREMENT PRIMARY KEY,
  fecha         DATE         NOT NULL,
  id_empleado   INT          NOT NULL,              -- a quién se entrega
  observaciones VARCHAR(255) DEFAULT NULL,
  creado_por    VARCHAR(60)  DEFAULT NULL,
  CONSTRAINT fk_sal_emp FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_sal_fecha (fecha),
  INDEX idx_sal_emp (id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salidas_detalle (
  id_detalle_salida INT AUTO_INCREMENT PRIMARY KEY,
  id_salida         INT NOT NULL,
  id_variante       INT NOT NULL,
  cantidad          INT NOT NULL CHECK (cantidad > 0),
  CONSTRAINT fk_sd_sal FOREIGN KEY (id_salida)   REFERENCES salidas(id_salida)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sd_var FOREIGN KEY (id_variante) REFERENCES item_variantes(id_variante)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_sd_var (id_variante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `resguardos`
-- --------------------------------------------------------

CREATE TABLE `resguardos` (
  `id_resguardo` INT(11) NOT NULL AUTO_INCREMENT,
  `id_salida` INT(11) NOT NULL,
  `anio` INT(11) NOT NULL,
  `folio` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `lugar` VARCHAR(80) DEFAULT NULL,
  `director` VARCHAR(120) DEFAULT NULL,
  `creado_por` VARCHAR(60) DEFAULT NULL,
  PRIMARY KEY (`id_resguardo`),
  UNIQUE KEY `uq_resguardo_anio_folio` (`anio`, `folio`),
  UNIQUE KEY `uq_resguardo_salida` (`id_salida`),
  KEY `idx_resg_id_salida` (`id_salida`),
  CONSTRAINT `fk_resguardos_salida` FOREIGN KEY (`id_salida`) REFERENCES `salidas` (`id_salida`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Notas:
--  - Cada salida sólo puede tener un resguardo asociado.
--  - El folio es único por año (`anio`, `folio`).
--  - El campo `fecha` guarda la fecha exacta de creación del resguardo.
--  - `creado_por` almacena el usuario (email o identificador) que lo generó.
-- --------------------------------------------------------

-- 5) FOLIOS (reinicio anual) + LOG DE REIMPRESIONES
CREATE TABLE IF NOT EXISTS folio_series (
  id_serie            INT AUTO_INCREMENT PRIMARY KEY,
  serie               VARCHAR(20) NOT NULL,     -- ej. 'RES-ET'
  `año`               INT         NOT NULL,
  ultimo_folio        INT         NOT NULL DEFAULT 0,
  reinicia_anualmente TINYINT(1)  NOT NULL DEFAULT 1,
  prefijo             VARCHAR(20) DEFAULT NULL, -- ej. 'RES-ET'
  longitud            INT         NOT NULL DEFAULT 6, -- ceros a la izquierda
  UNIQUE KEY uq_serie_año (serie, `año`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_logs (
  id_log_impresion INT AUTO_INCREMENT PRIMARY KEY,
  id_resguardo     INT NOT NULL,
  fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario          VARCHAR(60)  DEFAULT NULL,
  motivo           VARCHAR(120) DEFAULT 'Reimpresión',
  CONSTRAINT fk_pl_res FOREIGN KEY (id_resguardo) REFERENCES resguardos(id_resguardo)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_pl_res (id_resguardo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === VISTA: existencias por variante (solo con ENTRADAS) =====================
DROP VIEW IF EXISTS v_existencias;
CREATE VIEW v_existencias AS
SELECT
  v.id_variante,
  e.id_equipo,
  e.codigo,
  e.descripcion,
  e.modelo,
  e.categoria,
  e.maneja_talla,
  v.talla,
  SUM(d.cantidad) AS existencias
FROM entradas_detalle d
JOIN item_variantes v ON v.id_variante = d.id_variante
JOIN equipo e         ON e.id_equipo   = v.id_equipo
GROUP BY
  v.id_variante, e.id_equipo, e.codigo, e.descripcion, e.modelo,
  e.categoria, e.maneja_talla, v.talla;

  -- =============== VISTA: existencias netas (entradas - salidas) ==
DROP VIEW IF EXISTS v_existencias_netas;
CREATE VIEW v_existencias_netas AS
SELECT
  v.id_variante,
  e.id_equipo,
  e.codigo,
  e.descripcion,
  e.modelo,
  e.categoria,
  e.maneja_talla,
  v.talla,
  COALESCE(ent.cant, 0) - COALESCE(sal.cant, 0) AS existencias
FROM item_variantes v
JOIN equipo e ON e.id_equipo = v.id_equipo
LEFT JOIN (
  SELECT d.id_variante, SUM(d.cantidad) AS cant
  FROM entradas_detalle d
  GROUP BY d.id_variante
) ent ON ent.id_variante = v.id_variante
LEFT JOIN (
  SELECT d.id_variante, SUM(d.cantidad) AS cant
  FROM salidas_detalle d
  GROUP BY d.id_variante
) sal ON sal.id_variante = v.id_variante;

INSERT INTO equipo (codigo, descripcion, modelo, categoria, maneja_talla) VALUES
-- Botas
('BOTA-12401', 'Bota táctica color negro marca 5.11', '12401', 'Calzado', 1),

-- Pantalones (caballero)
('PANT-74369', 'Pantalón táctico caballero azul marino', '74369', 'Uniforme', 1),

-- Pantalones (dama)
('PANT-643886', 'Pantalón táctico dama azul marino', '643886', 'Uniforme', 1),

-- Camisas (caballero)
('CAM-72175', 'Camisa táctica 5.11 caballero Dark Navy', '72175', 'Uniforme', 1),

-- Camisas (dama)
('CAM-62070', 'Camisa táctica 5.11 dama Dark Navy', '62070', 'Uniforme', 1),

-- Chamarras
('CHAM-48026', 'Chamarra táctica unisex azul marino', '48026', 'Uniforme', 1),

-- Otros del listado de resguardo
('GORRA-001', 'Gorra (OCAPC)', NULL, 'Uniforme', 1),
('CASCO-001', 'Casco balístico', NULL, 'Protección', 1),
('LAMP-001', 'Lámpara táctica', NULL, 'Accesorio', 0),
('FORN-001', 'Fornitura (5 accesorios)', NULL, 'Accesorio', 0),
('ESP-001', 'Esposas (Smith & Wesson)', NULL, 'Accesorio', 0),
('PORTA-ARMA-L', 'Porta cargador arma larga (pouch)', NULL, 'Accesorio', 0),
('PORTA-ARMA-C', 'Porta cargador arma corta (Milfort)', NULL, 'Accesorio', 0),
('CINT-001', 'Cinturón militar (G&P Outdoor Belt)', NULL, 'Accesorio', 1),
('PASA-001', 'Paga montaña táctico', NULL, 'Accesorio', 0),
('PORTA-ESP-001', 'Porta esposa plástico (Milfort)', NULL, 'Accesorio', 0),
('PORTA-FUS-001', 'Porta fusil táctico 3 puntos', NULL, 'Accesorio', 0),
('CODERA-001', 'Par de coderas tácticas (FX Tactical)', NULL, 'Protección', 1),
('RODILLERA-001', 'Par de rodilleras tácticas (FX Tactical)', NULL, 'Protección', 1),
('GOOGLE-001', 'Google táctico (FX Tactical)', NULL, 'Protección', 1),
('GUANTE-001', 'Guantes tácticos (PMT)', NULL, 'Protección', 1);

/* =========================================
   VARIANTES (tallas) por artículo (item_variantes)
   Requiere: UNIQUE(id_equipo, talla) en item_variantes
   ========================================= */

/* --- BOTAS tácticas 5.11 modelo 12401 (6–10) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '4'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '6'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '6.5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '7' FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '7.5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '8'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '8.5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '9'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '9.5' FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '10'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '10.5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '11'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '11.5'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '12' FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '13'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '14'  FROM equipo WHERE codigo='BOTA-12401';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '15'  FROM equipo WHERE codigo='BOTA-12401';

/* --- PANTALÓN táctico caballero azul marino 74369 (waist*length) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '28*30' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '28*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '28*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '30*30' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '30*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '30*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '32*30' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '32*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '32*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '32*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '34*30' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '34*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '34*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '34*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '36*30' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '36*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '36*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '36*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '38*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '38*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '38*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '40*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '40*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '40*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '42*32' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '42*34' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '42*36' FROM equipo WHERE codigo='PANT-74369';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '44*32' FROM equipo WHERE codigo='PANT-74369';

/* --- PANTALÓN táctico dama azul marino 643886 (waist*length) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '2 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '4 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '6 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '8 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '10 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '12 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '14 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '16 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '18 R' FROM equipo WHERE codigo='PANT-643886';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, '20 R' FROM equipo WHERE codigo='PANT-643886';

/* --- CAMISA táctica 5.11 caballero 72175 (S–XXXL) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'S'    FROM equipo WHERE codigo='CAM-72175';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'M'    FROM equipo WHERE codigo='CAM-72175';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'L'    FROM equipo WHERE codigo='CAM-72175';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XL'   FROM equipo WHERE codigo='CAM-72175';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XXL'  FROM equipo WHERE codigo='CAM-72175';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XXXL' FROM equipo WHERE codigo='CAM-72175';

/* --- CAMISA táctica 5.11 dama 62070 (XS–XL) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XS' FROM equipo WHERE codigo='CAM-62070';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'S'  FROM equipo WHERE codigo='CAM-62070';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'M'  FROM equipo WHERE codigo='CAM-62070';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'L'  FROM equipo WHERE codigo='CAM-62070';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XL' FROM equipo WHERE codigo='CAM-62070';

/* --- CHAMARRA táctica unisex 48026 (S–XL) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'S'  FROM equipo WHERE codigo='CHAM-48026';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'M'  FROM equipo WHERE codigo='CHAM-48026';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'L'  FROM equipo WHERE codigo='CHAM-48026';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'XL' FROM equipo WHERE codigo='CHAM-48026';

/* --- GORRA (OCAPC): talla única (si usas varias, cámbialo a S/M/L) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'ÚNICA' FROM equipo WHERE codigo='GORRA-001';

/* --- CASCO balístico (si por ahora es única) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'ÚNICA' FROM equipo WHERE codigo='CASCO-001';

/* --- LÁMPARA, ESPOSAS, FORNITURA, PORTA CARGADORES, PAGA MONTAÑA, PORTA FUSIL: ÚNICA --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla)
SELECT id_equipo, 'ÚNICA' FROM equipo
WHERE codigo IN ('LAMP-001','ESP-001','FORN-001','PORTA-ARMA-L','PORTA-ARMA-C','PAGA-001','PORTA-FUS-001');

/* --- CINTURÓN militar (si manejas tallas, ajusta; dejo S–L como base) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'S' FROM equipo WHERE codigo='CINT-001';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'M' FROM equipo WHERE codigo='CINT-001';
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'L' FROM equipo WHERE codigo='CINT-001';

/* --- CODERAS / RODILLERAS / GOGGLE / GUANTES (ajustables; dejo S–L, cambia si son únicas) --- */
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'S' FROM equipo WHERE codigo IN ('CODERA-001','RODILLERA-001','GOOGLE-001','GUANTE-001');
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'M' FROM equipo WHERE codigo IN ('CODERA-001','RODILLERA-001','GOOGLE-001','GUANTE-001');
INSERT IGNORE INTO item_variantes (id_equipo, talla) SELECT id_equipo, 'L' FROM equipo WHERE codigo IN ('CODERA-001','RODILLERA-001','GOOGLE-001','GUANTE-001');

/*--- Entrada Inicial ---*/
INSERT INTO `entradas` (`fecha`, `proveedor`, `factura`, `observaciones`, `creado_por`) VALUES
('2025-10-13', 'COMPAÑIA MEXICANA DE PROTECCIÓN S. DE R.L. DE C.V.', 'Acta de Entrega-Recepción', '', 'admin@local');

/*--- Entrada Detalle Inicial ---*/
INSERT INTO `entradas_detalle` (`id_entrada`, `id_variante`, `cantidad`) VALUES
(1, 57, 1),
(1, 20, 39),
(1, 25, 1),
(1, 7, 5),
(1, 8, 20),
(1, 11, 78),
(1, 12, 13),
(1, 14, 15),
(1, 16, 23),
(1, 18, 5);