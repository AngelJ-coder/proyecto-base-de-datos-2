-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-07-2026 a las 22:45:53
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tutorias_academicas`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_areas_por_carrera` (IN `p_id_carrera` INT)   BEGIN
    SELECT id_area, nombre_area, descripcion
    FROM area_academica WHERE id_carrera = p_id_carrera;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_areas_sin_tutor` ()   BEGIN
    DECLARE v_id_area INT; DECLARE v_nombre VARCHAR(120); DECLARE v_count INT; DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_area, nombre_area FROM area_academica;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_sin_tutor;
    CREATE TEMPORARY TABLE tmp_sin_tutor (area VARCHAR(120));
    OPEN cur;
    loop2: LOOP
        FETCH cur INTO v_id_area, v_nombre;
        IF done THEN LEAVE loop2; END IF;
        SELECT COUNT(*) INTO v_count FROM tutor_area WHERE id_area = v_id_area;
        IF v_count = 0 THEN
            INSERT INTO tmp_sin_tutor VALUES (v_nombre);
        END IF;
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_sin_tutor;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cambiar_estado_usuario` (IN `p_id_usuario` INT, IN `p_nuevo_estado` VARCHAR(10))   BEGIN
    UPDATE usuario SET estado = p_nuevo_estado WHERE id_usuario = p_id_usuario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cerrar_sesiones_vencidas` ()   BEGIN
    DECLARE v_id_sesion INT; DECLARE v_fecha DATE; DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_sesion, fecha FROM sesion_tutoria WHERE estado IN ('programada','en curso');
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    OPEN cur;
    loop2: LOOP
        FETCH cur INTO v_id_sesion, v_fecha;
        IF done THEN LEAVE loop2; END IF;
        IF v_fecha < CURDATE() THEN
            UPDATE sesion_tutoria SET estado = 'finalizada' WHERE id_sesion = v_id_sesion;
        END IF;
    END LOOP;
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_contar_usuarios_por_dominio` ()   BEGIN
    DECLARE v_email VARCHAR(120); DECLARE v_dominio VARCHAR(80); DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT email FROM usuario;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_dominios;
    CREATE TEMPORARY TABLE tmp_dominios (dominio VARCHAR(80), total INT DEFAULT 1);
    OPEN cur;
    loop2: LOOP
        FETCH cur INTO v_email;
        IF done THEN LEAVE loop2; END IF;
        SET v_dominio = SUBSTRING_INDEX(v_email, '@', -1);
        IF EXISTS (SELECT 1 FROM tmp_dominios WHERE dominio = v_dominio) THEN
            UPDATE tmp_dominios SET total = total + 1 WHERE dominio = v_dominio;
        ELSE
            INSERT INTO tmp_dominios (dominio) VALUES (v_dominio);
        END IF;
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_dominios;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_area` (IN `p_nombre` VARCHAR(120), IN `p_desc` VARCHAR(255), IN `p_id_carrera` INT)   BEGIN
    INSERT INTO area_academica (nombre_area, descripcion, id_carrera)
    VALUES (p_nombre, p_desc, p_id_carrera);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_sesion` (IN `p_id_plan` INT, IN `p_id_tutor` INT, IN `p_id_coordinador` INT, IN `p_id_area` INT, IN `p_turno` VARCHAR(10), IN `p_fecha` DATE, IN `p_hora_inicio` TIME, IN `p_hora_fin` TIME, IN `p_modalidad` VARCHAR(15), IN `p_cupo` INT)   BEGIN
    INSERT INTO sesion_tutoria (id_plan, id_tutor, id_coordinador, id_area, turno,
        fecha, hora_inicio, hora_fin, modalidad, cupo_maximo)
    VALUES (p_id_plan, p_id_tutor, p_id_coordinador, p_id_area, p_turno,
        p_fecha, p_hora_inicio, p_hora_fin, p_modalidad, p_cupo);

    SELECT LAST_INSERT_ID() AS id_sesion;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_usuario` (IN `p_nombre` VARCHAR(80), IN `p_apellido` VARCHAR(80), IN `p_ci` VARCHAR(20), IN `p_email` VARCHAR(120), IN `p_password` VARCHAR(255))   BEGIN
    INSERT INTO usuario (nombre, apellido, ci, email, password)
    VALUES (p_nombre, p_apellido, p_ci, p_email, p_password);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_inscribir_estudiante` (IN `p_id_sesion` INT, IN `p_id_estudiante` INT)   BEGIN
    IF EXISTS (SELECT 1 FROM sesion_estudiante WHERE id_sesion = p_id_sesion AND id_estudiante = p_id_estudiante) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El estudiante ya esta inscrito en esta sesion';
    ELSE
        INSERT INTO sesion_estudiante (id_sesion, id_estudiante)
        VALUES (p_id_sesion, p_id_estudiante);
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_usuarios_inactivos_largo` ()   BEGIN
    DECLARE v_id INT; DECLARE v_nombre VARCHAR(80); DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_usuario, nombre FROM usuario WHERE estado='inactivo';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_inactivos;
    CREATE TEMPORARY TABLE tmp_inactivos (id_usuario INT, nombre VARCHAR(80));
    OPEN cur;
    loop1: LOOP
        FETCH cur INTO v_id, v_nombre;
        IF done THEN LEAVE loop1; END IF;
        INSERT INTO tmp_inactivos VALUES (v_id, v_nombre);
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_inactivos;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_solicitudes_urgentes` ()   BEGIN
    DECLARE v_id_solicitud INT; DECLARE v_fecha DATETIME; DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_solicitud, fecha_solicitud FROM solicitud_tutoria WHERE estado='pendiente';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    OPEN cur;
    loop1: LOOP
        FETCH cur INTO v_id_solicitud, v_fecha;
        IF done THEN LEAVE loop1; END IF;
        IF DATEDIFF(NOW(), v_fecha) > 3 THEN
            UPDATE solicitud_tutoria SET motivo = CONCAT(motivo,' [URGENTE]')
            WHERE id_solicitud = v_id_solicitud AND motivo NOT LIKE '%[URGENTE]%';
        END IF;
    END LOOP;
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ocultar_materiales_antiguos` ()   BEGIN
    DECLARE v_id_material INT; DECLARE v_fecha DATETIME; DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_material, fecha_subida FROM material_apoyo WHERE visible='si';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    OPEN cur;
    loop2: LOOP
        FETCH cur INTO v_id_material, v_fecha;
        IF done THEN LEAVE loop2; END IF;
        IF DATEDIFF(NOW(), v_fecha) > 365 THEN
            UPDATE material_apoyo SET visible = 'no' WHERE id_material = v_id_material;
        END IF;
    END LOOP;
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_promedio_por_area` ()   BEGIN
    DECLARE v_id_area INT; DECLARE v_nombre VARCHAR(120); DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_area, nombre_area FROM area_academica;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_prom_area;
    CREATE TEMPORARY TABLE tmp_prom_area (area VARCHAR(120), promedio DECIMAL(4,2));
    OPEN cur;
    loop1: LOOP
        FETCH cur INTO v_id_area, v_nombre;
        IF done THEN LEAVE loop1; END IF;
        INSERT INTO tmp_prom_area
        SELECT v_nombre, IFNULL(AVG(ev.calificacion),0)
        FROM evaluacion_sesion ev
        JOIN sesion_tutoria st ON ev.id_sesion = st.id_sesion
        WHERE st.id_area = v_id_area;
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_prom_area;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_disponibilidad` (IN `p_id_tutor` INT, IN `p_dia` VARCHAR(15), IN `p_turno` VARCHAR(10), IN `p_modalidad` VARCHAR(15))   BEGIN
    INSERT INTO disponibilidad_tutor (id_tutor, dia_semana, turno, modalidad)
    VALUES (p_id_tutor, p_dia, p_turno, p_modalidad);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_evaluacion` (IN `p_id_sesion` INT, IN `p_id_estudiante` INT, IN `p_calificacion` DECIMAL(4,2), IN `p_observaciones` VARCHAR(255), IN `p_recomendaciones` VARCHAR(255))   BEGIN
    INSERT INTO evaluacion_sesion (id_sesion, id_estudiante, calificacion, observaciones, recomendaciones)
    VALUES (p_id_sesion, p_id_estudiante, p_calificacion, p_observaciones, p_recomendaciones);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_tutores_sin_sesiones_activas` ()   BEGIN
    DECLARE v_id_tutor INT;
    DECLARE v_nombre VARCHAR(80);
    DECLARE v_total INT;
    DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT t.id_tutor, u.nombre
        FROM tutor t
        JOIN usuario u ON t.id_tutor = u.id_usuario;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_tutores_sin_sesiones;
    CREATE TEMPORARY TABLE tmp_tutores_sin_sesiones (nombre VARCHAR(80));

    OPEN cur;
    loop_tutores: LOOP
        FETCH cur INTO v_id_tutor, v_nombre;
        IF done THEN LEAVE loop_tutores; END IF;

        SELECT COUNT(*) INTO v_total
        FROM sesion_tutoria
        WHERE id_tutor = v_id_tutor
          AND estado IN ('programada','en curso');

        IF v_total = 0 THEN
            INSERT INTO tmp_tutores_sin_sesiones VALUES (v_nombre);
        END IF;
    END LOOP;
    CLOSE cur;

    SELECT * FROM tmp_tutores_sin_sesiones ORDER BY nombre;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_tutores_sin_sesiones_activas_cursor` ()   BEGIN
    DECLARE v_id_tutor INT;
    DECLARE v_nombre VARCHAR(80);
    DECLARE v_total INT;
    DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT t.id_tutor, u.nombre
        FROM tutor t
        JOIN usuario u ON t.id_tutor = u.id_usuario;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_tutores_sin_sesiones_cursor;
    CREATE TEMPORARY TABLE tmp_tutores_sin_sesiones_cursor (nombre VARCHAR(80));

    OPEN cur;
    loop_tutores: LOOP
        FETCH cur INTO v_id_tutor, v_nombre;
        IF done THEN LEAVE loop_tutores; END IF;

        SELECT COUNT(*) INTO v_total
        FROM sesion_tutoria
        WHERE id_tutor = v_id_tutor
          AND estado IN ('programada','en curso');

        IF v_total = 0 THEN
            INSERT INTO tmp_tutores_sin_sesiones_cursor VALUES (v_nombre);
        END IF;
    END LOOP;
    CLOSE cur;

    SELECT * FROM tmp_tutores_sin_sesiones_cursor ORDER BY nombre;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resumen_areas_por_carrera` ()   BEGIN
    DECLARE v_id_carrera INT; DECLARE v_nombre VARCHAR(120); DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT id_carrera, nombre_carrera FROM carrera;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_resumen;
    CREATE TEMPORARY TABLE tmp_resumen (carrera VARCHAR(120), total_areas INT);
    OPEN cur;
    loop1: LOOP
        FETCH cur INTO v_id_carrera, v_nombre;
        IF done THEN LEAVE loop1; END IF;
        INSERT INTO tmp_resumen VALUES (v_nombre, fn_total_areas_carrera(v_id_carrera));
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_resumen;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resumen_sesiones_por_area_cursor` ()   BEGIN
    DECLARE v_id_area INT;
    DECLARE v_nombre_area VARCHAR(120);
    DECLARE v_total INT;
    DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT id_area, nombre_area FROM area_academica;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_resumen_areas_cursor;
    CREATE TEMPORARY TABLE tmp_resumen_areas_cursor (area VARCHAR(120), total_sesiones INT);

    OPEN cur;
    loop_areas: LOOP
        FETCH cur INTO v_id_area, v_nombre_area;
        IF done THEN LEAVE loop_areas; END IF;

        SELECT COUNT(*) INTO v_total
        FROM sesion_tutoria
        WHERE id_area = v_id_area;

        INSERT INTO tmp_resumen_areas_cursor VALUES (v_nombre_area, v_total);
    END LOOP;
    CLOSE cur;

    SELECT * FROM tmp_resumen_areas_cursor ORDER BY total_sesiones DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resumen_sesiones_por_modalidad` ()   BEGIN
    SELECT modalidad, COUNT(*) AS total_sesiones
    FROM sesion_tutoria
    GROUP BY modalidad
    ORDER BY total_sesiones DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_subir_material` (IN `p_id_area` INT, IN `p_id_tutor` INT, IN `p_titulo` VARCHAR(150), IN `p_descripcion` VARCHAR(255), IN `p_ruta` VARCHAR(255), IN `p_tipo` VARCHAR(10), IN `p_tamano_kb` INT)   BEGIN
    INSERT INTO material_apoyo (id_area, id_tutor, titulo, descripcion, ruta_archivo, tipo_archivo, tamano_kb)
    VALUES (p_id_area, p_id_tutor, p_titulo, p_descripcion, p_ruta, p_tipo, p_tamano_kb);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tutores_carga_horaria` ()   BEGIN
    DECLARE v_id_tutor INT; DECLARE v_nombre VARCHAR(80); DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT t.id_tutor, u.nombre FROM tutor t JOIN usuario u ON t.id_tutor=u.id_usuario;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_carga;
    CREATE TEMPORARY TABLE tmp_carga (id_tutor INT, nombre VARCHAR(80), total_sesiones INT);
    OPEN cur;
    loop1: LOOP
        FETCH cur INTO v_id_tutor, v_nombre;
        IF done THEN LEAVE loop1; END IF;
        INSERT INTO tmp_carga
        SELECT v_id_tutor, v_nombre, COUNT(*) FROM sesion_tutoria WHERE id_tutor = v_id_tutor;
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_carga ORDER BY total_sesiones DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tutores_disponibles` (IN `p_id_area` INT, IN `p_turno` VARCHAR(10), IN `p_modalidad` VARCHAR(20))   BEGIN
    SELECT u.id_usuario, u.nombre, u.apellido, ta.nivel_experiencia, dt.dia_semana, dt.modalidad
    FROM tutor t
    JOIN usuario u ON t.id_tutor = u.id_usuario
    JOIN tutor_area ta ON t.id_tutor = ta.id_tutor
    JOIN disponibilidad_tutor dt ON t.id_tutor = dt.id_tutor
    WHERE ta.id_area = p_id_area
      AND dt.turno = p_turno
      AND dt.modalidad = p_modalidad
      AND dt.estado = 'activo'
      AND u.estado = 'activo';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_verificar_tutores_sin_area` ()   BEGIN
    DECLARE v_id_tutor INT; DECLARE v_nombre VARCHAR(80); DECLARE v_count INT; DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT t.id_tutor, u.nombre FROM tutor t JOIN usuario u ON t.id_tutor=u.id_usuario;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    DROP TEMPORARY TABLE IF EXISTS tmp_sin_area;
    CREATE TEMPORARY TABLE tmp_sin_area (nombre VARCHAR(80));
    OPEN cur;
    loop2: LOOP
        FETCH cur INTO v_id_tutor, v_nombre;
        IF done THEN LEAVE loop2; END IF;
        SELECT COUNT(*) INTO v_count FROM tutor_area WHERE id_tutor = v_id_tutor;
        IF v_count = 0 THEN INSERT INTO tmp_sin_area VALUES (v_nombre); END IF;
    END LOOP;
    CLOSE cur;
    SELECT * FROM tmp_sin_area;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cupos_disponibles` (`p_id_sesion` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_disponibles INT;
    SELECT (cupo_maximo - cupo_actual) INTO v_disponibles
    FROM sesion_tutoria WHERE id_sesion = p_id_sesion;
    RETURN v_disponibles;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_nombre_carrera` (`p_id_area` INT) RETURNS VARCHAR(120) CHARSET utf8mb4 COLLATE utf8mb4_spanish_ci DETERMINISTIC BEGIN
    DECLARE v_nombre VARCHAR(120);
    SELECT c.nombre_carrera INTO v_nombre
    FROM area_academica a JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE a.id_area = p_id_area;
    RETURN v_nombre;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_nombre_completo` (`p_id_usuario` INT) RETURNS VARCHAR(160) CHARSET utf8mb4 COLLATE utf8mb4_spanish_ci DETERMINISTIC BEGIN
    DECLARE v_nombre VARCHAR(160);
    SELECT CONCAT(nombre,' ',apellido) INTO v_nombre FROM usuario WHERE id_usuario = p_id_usuario;
    RETURN v_nombre;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_promedio_estudiante` (`p_id_estudiante` INT) RETURNS DECIMAL(4,2) DETERMINISTIC BEGIN
    DECLARE v_promedio DECIMAL(4,2);
    SELECT AVG(calificacion) INTO v_promedio
    FROM evaluacion_sesion WHERE id_estudiante = p_id_estudiante;
    RETURN IFNULL(v_promedio, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_sesiones_pendientes_estudiante` (`p_id_estudiante` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total
    FROM sesion_estudiante se
    JOIN sesion_tutoria s ON se.id_sesion = s.id_sesion
    WHERE se.id_estudiante = p_id_estudiante
      AND s.estado IN ('programada','en curso');
    RETURN IFNULL(v_total, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_areas_carrera` (`p_id_carrera` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM area_academica WHERE id_carrera = p_id_carrera;
    RETURN v_total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_disponibilidades` (`p_id_tutor` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM disponibilidad_tutor WHERE id_tutor=p_id_tutor AND estado='activo';
    RETURN v_total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_estudiantes_sesion` (`p_id_sesion` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM sesion_estudiante WHERE id_sesion = p_id_sesion;
    RETURN v_total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_materiales_area` (`p_id_area` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM material_apoyo WHERE id_area = p_id_area AND visible='si';
    RETURN v_total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_materiales_visibles_area` (`p_id_area` INT) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total
    FROM material_apoyo
    WHERE id_area = p_id_area AND visible = 'si';
    RETURN IFNULL(v_total, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_tutor_dicta_area` (`p_id_tutor` INT, `p_id_area` INT) RETURNS TINYINT(1) DETERMINISTIC BEGIN
    DECLARE v_existe INT;
    SELECT COUNT(*) INTO v_existe FROM tutor_area WHERE id_tutor=p_id_tutor AND id_area=p_id_area;
    RETURN v_existe > 0;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_usuario_activo` (`p_id_usuario` INT) RETURNS TINYINT(1) DETERMINISTIC BEGIN
    DECLARE v_estado VARCHAR(10);
    SELECT estado INTO v_estado FROM usuario WHERE id_usuario = p_id_usuario;
    RETURN v_estado = 'activo';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administrador`
--

CREATE TABLE `administrador` (
  `id_administrador` int(11) NOT NULL,
  `nivel_acceso` enum('total','soporte') NOT NULL DEFAULT 'total'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `administrador`
--

INSERT INTO `administrador` (`id_administrador`, `nivel_acceso`) VALUES
(1, 'total');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `area_academica`
--

CREATE TABLE `area_academica` (
  `id_area` int(11) NOT NULL,
  `nombre_area` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `id_carrera` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `area_academica`
--

INSERT INTO `area_academica` (`id_area`, `nombre_area`, `descripcion`, `id_carrera`) VALUES
(1, 'Base de Datos', 'Modelado, SQL y administracion de bases de datos', 1),
(2, 'Programacion I', 'Fundamentos de programacion', 1),
(3, 'Calculo I', 'Calculo diferencial e integral', 2),
(4, 'Redes de Computadoras', 'Fundamentos de redes y protocolos', 3),
(5, 'Sistemas Operativas', 'Administracion y fundamentos de sistemas operativos', 2),
(6, 'Algebra Lineal', 'Espacios vectoriales, matrices y transformaciones', 1),
(7, 'Estructuras de Datos', 'Listas, arboles, grafos y algoritmos', 1),
(8, 'Fisica I', 'Mecanica clasica y cinematica', 2),
(9, 'Fisica II', 'Electromagnetismo basico', 2),
(10, 'Calculo II', 'Calculo en varias variables', 2),
(11, 'Resistencia de Materiales', 'Analisis de esfuerzos y deformaciones', 6),
(12, 'Circuitos Electricos', 'Analisis de circuitos DC y AC', 7),
(13, 'Termodinamica', 'Principios de energia y calor', 9),
(14, 'Derecho Civil', 'Obligaciones y contratos', 11),
(15, 'Anatomia', 'Estructura del cuerpo humano', 12),
(16, 'Microeconomia', 'Teoria del consumidor y del productor', 15),
(17, 'Macroeconomia', 'Analisis economico agregado', 15),
(18, 'Contabilidad General', 'Registro y analisis contable', 17),
(19, 'Psicologia General', 'Fundamentos de la psicologia', 18),
(20, 'Redaccion Periodistica', 'Tecnicas de redaccion para medios', 19),
(21, 'Fundamentos Digitales', NULL, 1);

--
-- Disparadores `area_academica`
--
DELIMITER $$
CREATE TRIGGER `trg_area_nombre_mayus` BEFORE INSERT ON `area_academica` FOR EACH ROW BEGIN
    SET NEW.nombre_area = CONCAT(UPPER(LEFT(NEW.nombre_area,1)), SUBSTRING(NEW.nombre_area,2));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_evitar_area_duplicada` BEFORE INSERT ON `area_academica` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM area_academica WHERE nombre_area = NEW.nombre_area AND id_carrera = NEW.id_carrera) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe esta area en la carrera';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrera`
--

CREATE TABLE `carrera` (
  `id_carrera` int(11) NOT NULL,
  `nombre_carrera` varchar(120) NOT NULL,
  `facultad` varchar(120) DEFAULT NULL,
  `codigo_carrera` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `carrera`
--

INSERT INTO `carrera` (`id_carrera`, `nombre_carrera`, `facultad`, `codigo_carrera`) VALUES
(1, 'Informatica', 'Ciencias Puras y Naturales', 'INF'),
(2, 'Fisica', 'Ciencias Puras y Naturales', 'FIS'),
(3, 'Ingenieria', '', NULL),
(4, 'Matematica', 'Ciencias Puras y Naturales', 'Mat'),
(5, 'Ingenieria de Sistemas', 'Ingenieria', 'ISI'),
(6, 'Ingenieria Civil', 'Ingenieria', 'CIV'),
(7, 'Ingenieria Electrica', 'Ingenieria', 'ELE'),
(8, 'Ingenieria Industrial', 'Ingenieria', 'IND'),
(9, 'Ingenieria Quimica', 'Ingenieria', 'QUI'),
(10, 'Arquitectura', 'Arquitectura, Artes, Diseno y Urbanismo', 'ARQ'),
(11, 'Derecho', 'Ciencias Juridicas y Politicas', 'DER'),
(12, 'Medicina', 'Ciencias de la Salud', 'MED'),
(13, 'Odontologia', 'Ciencias de la Salud', 'ODO'),
(14, 'Enfermeria', 'Ciencias de la Salud', 'ENF'),
(15, 'Economia', 'Ciencias Economicas y Financieras', 'ECO'),
(16, 'Administracion de Empresas', 'Ciencias Economicas y Financieras', 'ADM'),
(17, 'Contaduria Publica', 'Ciencias Economicas y Financieras', 'CPU'),
(18, 'Psicologia', 'Humanidades y Ciencias de la Educacion', 'PSI'),
(19, 'Comunicacion Social', 'Humanidades y Ciencias de la Educacion', 'COM'),
(20, 'Biologia', 'Ciencias Puras y Naturales', 'BIO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coordinador`
--

CREATE TABLE `coordinador` (
  `id_coordinador` int(11) NOT NULL,
  `cargo` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `coordinador`
--

INSERT INTO `coordinador` (`id_coordinador`, `cargo`) VALUES
(2, 'Coordinador Academico');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_modalidad_sesion`
--

CREATE TABLE `detalle_modalidad_sesion` (
  `id_detalle` int(11) NOT NULL,
  `id_sesion` int(11) NOT NULL,
  `aula` varchar(50) DEFAULT NULL,
  `edificio` varchar(50) DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `plataforma` varchar(50) DEFAULT NULL,
  `enlace` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `detalle_modalidad_sesion`
--

INSERT INTO `detalle_modalidad_sesion` (`id_detalle`, `id_sesion`, `aula`, `edificio`, `ubicacion`, `plataforma`, `enlace`) VALUES
(2, 2, 'piso 2 aula A5', 'Edificio antiguo', 'Umsa-Monoblock', NULL, NULL),
(3, 3, NULL, NULL, NULL, 'google meet', 'https://meet.google.com/uog-saaz-xhf');

--
-- Disparadores `detalle_modalidad_sesion`
--
DELIMITER $$
CREATE TRIGGER `trg_validar_modalidad_detalle` BEFORE INSERT ON `detalle_modalidad_sesion` FOR EACH ROW BEGIN
    DECLARE v_modalidad VARCHAR(15);
    SELECT modalidad INTO v_modalidad FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;
    IF v_modalidad = 'virtual' AND NEW.aula IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sesion virtual no debe tener aula';
    END IF;
    IF v_modalidad = 'presencial' AND NEW.enlace IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sesion presencial no debe tener enlace';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_tutor`
--

CREATE TABLE `disponibilidad_tutor` (
  `id_disponibilidad` int(11) NOT NULL,
  `id_tutor` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado') NOT NULL,
  `turno` enum('mañana','tarde','noche') NOT NULL,
  `modalidad` enum('presencial','virtual') NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `disponibilidad_tutor`
--

INSERT INTO `disponibilidad_tutor` (`id_disponibilidad`, `id_tutor`, `dia_semana`, `turno`, `modalidad`, `estado`) VALUES
(1, 12, 'lunes', 'noche', '', 'activo'),
(2, 3, 'miercoles', 'tarde', 'presencial', 'activo'),
(4, 3, 'lunes', 'tarde', 'virtual', 'activo'),
(5, 1, 'lunes', 'mañana', 'presencial', 'activo'),
(6, 1, 'martes', 'tarde', 'virtual', 'activo'),
(7, 2, 'miercoles', 'noche', 'virtual', 'activo'),
(8, 2, 'jueves', 'mañana', 'presencial', 'activo'),
(9, 4, 'viernes', 'tarde', 'presencial', 'activo'),
(10, 4, 'lunes', 'noche', 'virtual', 'activo'),
(11, 5, 'martes', 'mañana', 'presencial', 'activo'),
(12, 5, 'sabado', 'tarde', 'virtual', 'activo'),
(13, 28, 'miercoles', 'mañana', 'virtual', 'activo'),
(14, 28, 'jueves', 'tarde', 'presencial', 'activo'),
(15, 3, 'viernes', 'mañana', 'virtual', 'activo'),
(16, 3, 'sabado', 'noche', 'presencial', 'activo'),
(17, 12, 'martes', 'tarde', 'virtual', 'activo'),
(18, 12, 'jueves', 'noche', 'presencial', 'activo'),
(19, 1, 'sabado', 'mañana', 'virtual', 'activo');

--
-- Disparadores `disponibilidad_tutor`
--
DELIMITER $$
CREATE TRIGGER `trg_actualizar_disponible_tutor` AFTER INSERT ON `disponibilidad_tutor` FOR EACH ROW BEGIN
    UPDATE tutor SET disponible = 'si' WHERE id_tutor = NEW.id_tutor;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_evitar_disponibilidad_duplicada` BEFORE INSERT ON `disponibilidad_tutor` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM disponibilidad_tutor
        WHERE id_tutor=NEW.id_tutor AND dia_semana=NEW.dia_semana
        AND turno=NEW.turno AND modalidad=NEW.modalidad) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Disponibilidad ya registrada';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiante`
--

CREATE TABLE `estudiante` (
  `id_estudiante` int(11) NOT NULL,
  `registro_universitario` varchar(30) NOT NULL,
  `id_carrera` int(11) NOT NULL,
  `semestre` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `estudiante`
--

INSERT INTO `estudiante` (`id_estudiante`, `registro_universitario`, `id_carrera`, `semestre`) VALUES
(4, '2021100001', 1, 5),
(5, '2021100002', 1, 3),
(11, '1855489', 1, 4),
(13, '2022200001', 1, 2),
(14, '2022200002', 1, 6),
(15, '2022200003', 2, 4),
(16, '2022200004', 5, 3),
(17, '2022200005', 6, 5),
(18, '2022200006', 7, 2),
(19, '2022200007', 9, 4),
(20, '2022200008', 11, 6),
(21, '2022200009', 12, 1),
(22, '2022200010', 15, 3),
(23, '2022200011', 16, 5),
(24, '2022200012', 17, 4),
(25, '2022200013', 18, 2),
(26, '2022200014', 3, 1),
(27, '2022200015', 4, 3),
(29, '18554891', 15, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluacion_sesion`
--

CREATE TABLE `evaluacion_sesion` (
  `id_evaluacion` int(11) NOT NULL,
  `id_sesion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `calificacion` decimal(4,2) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `recomendaciones` varchar(255) DEFAULT NULL,
  `fecha_evaluacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `evaluacion_sesion`
--

INSERT INTO `evaluacion_sesion` (`id_evaluacion`, `id_sesion`, `id_estudiante`, `calificacion`, `observaciones`, `recomendaciones`, `fecha_evaluacion`) VALUES
(1, 2, 4, 85.50, 'Buen manejo de bases de datos', 'Practicar mas consultas complejas', '2026-07-22 22:11:43'),
(2, 2, 11, 78.00, 'Necesita reforzar triggers', 'Repasar procedimientos almacenados', '2026-07-22 22:11:43'),
(3, 20, 5, 90.00, 'Excelente comprension de limites', 'Continuar con integrales', '2026-07-22 22:11:43'),
(4, 27, 4, 82.25, 'Buen manejo de joins', 'Practicar subconsultas', '2026-07-22 22:11:43');

--
-- Disparadores `evaluacion_sesion`
--
DELIMITER $$
CREATE TRIGGER `trg_validar_evaluacion` BEFORE INSERT ON `evaluacion_sesion` FOR EACH ROW BEGIN
    DECLARE v_estado VARCHAR(20);
    SELECT estado INTO v_estado FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;
    IF v_estado <> 'finalizada' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede evaluar una sesion que no esta finalizada';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `material_apoyo`
--

CREATE TABLE `material_apoyo` (
  `id_material` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `id_tutor` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` enum('pdf','docx','pptx','xlsx') NOT NULL,
  `tamano_kb` int(11) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `visible` enum('si','no') NOT NULL DEFAULT 'si'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `material_apoyo`
--

INSERT INTO `material_apoyo` (`id_material`, `id_area`, `id_tutor`, `titulo`, `descripcion`, `ruta_archivo`, `tipo_archivo`, `tamano_kb`, `fecha_subida`, `visible`) VALUES
(3, 1, 12, 'WB8-PROG PROCEDURAL - TRIGGERS', NULL, '../uploads/mat_6a6048346b2b5.pdf', 'pdf', 427, '2026-07-22 00:33:56', 'si'),
(4, 1, 12, 'prueba', '...', '../uploads/mat_6a604995195fc.docx', 'docx', 62, '2026-07-22 00:39:49', 'no');

--
-- Disparadores `material_apoyo`
--
DELIMITER $$
CREATE TRIGGER `trg_estado_default_material` BEFORE INSERT ON `material_apoyo` FOR EACH ROW BEGIN
    IF NEW.visible IS NULL OR NEW.visible = '' THEN
        SET NEW.visible = 'si';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validar_tipo_archivo` BEFORE INSERT ON `material_apoyo` FOR EACH ROW BEGIN
    IF NEW.tipo_archivo NOT IN ('pdf','docx','pptx','xlsx') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de archivo no permitido';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_tutoria`
--

CREATE TABLE `plan_tutoria` (
  `id_plan` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `objetivo` varchar(255) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin_estimada` date DEFAULT NULL,
  `numero_sesiones_estimadas` int(11) DEFAULT 1,
  `estado` enum('en curso','finalizado') NOT NULL DEFAULT 'en curso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `plan_tutoria`
--

INSERT INTO `plan_tutoria` (`id_plan`, `id_solicitud`, `objetivo`, `fecha_inicio`, `fecha_fin_estimada`, `numero_sesiones_estimadas`, `estado`) VALUES
(2, 1, NULL, '2026-07-23', '2026-07-31', 4, 'en curso'),
(3, 2, NULL, '2026-07-22', '2026-07-24', 3, 'en curso'),
(19, 4, 'Mejorar logica de programacion', '2026-07-25', '2026-08-10', 4, 'en curso'),
(20, 5, 'Dominar arboles binarios', '2026-07-26', '2026-08-12', 5, 'en curso'),
(21, 6, 'Repasar calculo diferencial', '2026-07-27', '2026-08-05', 3, 'finalizado'),
(22, 7, 'Resolver operaciones con matrices', '2026-07-28', '2026-08-15', 4, 'en curso'),
(23, 9, 'Comprender cinematica', '2026-07-29', '2026-08-10', 3, 'en curso'),
(24, 10, 'Reforzar termodinamica', '2026-07-30', '2026-08-14', 4, 'en curso'),
(25, 11, 'Redactar contratos civiles', '2026-07-31', '2026-08-20', 5, 'en curso'),
(26, 12, 'Estudiar anatomia osea', '2026-08-01', '2026-08-18', 3, 'en curso'),
(27, 13, 'Reforzar modelado ER', '2026-08-02', '2026-08-16', 4, 'finalizado'),
(28, 14, 'Practicar integrales', '2026-08-03', '2026-08-20', 3, 'en curso'),
(29, 15, 'Reforzar estructuras de control', '2026-08-04', '2026-08-19', 4, 'en curso'),
(30, 16, 'Analizar teoria del consumidor', '2026-08-05', '2026-08-21', 3, 'en curso'),
(31, 17, 'Registrar asientos contables', '2026-08-06', '2026-08-22', 4, 'finalizado'),
(32, 18, 'Fundamentos de psicologia aplicada', '2026-08-07', '2026-08-25', 3, 'en curso'),
(33, 8, 'Configuracion de subredes basicas', '2026-08-08', '2026-08-28', 2, 'en curso');

--
-- Disparadores `plan_tutoria`
--
DELIMITER $$
CREATE TRIGGER `trg_estado_default_plan` BEFORE INSERT ON `plan_tutoria` FOR EACH ROW BEGIN
    IF NEW.estado IS NULL OR NEW.estado = '' THEN
        SET NEW.estado = 'en curso';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesion_estudiante`
--

CREATE TABLE `sesion_estudiante` (
  `id_sesion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `asistencia` enum('presente','ausente','pendiente') NOT NULL DEFAULT 'pendiente',
  `fecha_inscripcion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `sesion_estudiante`
--

INSERT INTO `sesion_estudiante` (`id_sesion`, `id_estudiante`, `asistencia`, `fecha_inscripcion`) VALUES
(2, 4, 'presente', '2026-07-22 00:22:07'),
(2, 11, 'ausente', '2026-07-22 00:15:15'),
(3, 5, 'pendiente', '2026-07-22 22:01:09'),
(3, 11, 'pendiente', '2026-07-22 21:58:37'),
(18, 13, 'pendiente', '2026-07-22 22:11:43'),
(18, 14, 'pendiente', '2026-07-22 22:11:43'),
(19, 15, 'pendiente', '2026-07-22 22:11:43'),
(19, 16, 'pendiente', '2026-07-22 22:11:43'),
(20, 5, 'presente', '2026-07-22 22:11:43'),
(21, 17, 'pendiente', '2026-07-22 22:11:43'),
(22, 18, 'pendiente', '2026-07-22 22:11:43'),
(23, 19, 'pendiente', '2026-07-22 22:11:43'),
(23, 20, 'pendiente', '2026-07-22 22:11:43'),
(24, 21, 'pendiente', '2026-07-22 22:11:43'),
(25, 20, 'pendiente', '2026-07-22 22:27:43'),
(25, 22, 'pendiente', '2026-07-22 22:11:43'),
(26, 23, 'pendiente', '2026-07-22 22:11:43'),
(27, 4, 'presente', '2026-07-22 22:11:43'),
(28, 25, 'pendiente', '2026-07-22 22:11:43'),
(29, 26, 'pendiente', '2026-07-22 22:11:43'),
(30, 22, 'pendiente', '2026-07-22 22:27:13');

--
-- Disparadores `sesion_estudiante`
--
DELIMITER $$
CREATE TRIGGER `trg_decrementar_cupo` AFTER DELETE ON `sesion_estudiante` FOR EACH ROW BEGIN
    UPDATE sesion_tutoria
    SET cupo_actual = GREATEST(cupo_actual - 1, 0),
        estado = IF(estado = 'completa', 'programada', estado)
    WHERE id_sesion = OLD.id_sesion;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_incrementar_cupo` AFTER INSERT ON `sesion_estudiante` FOR EACH ROW BEGIN
    UPDATE sesion_tutoria
    SET cupo_actual = cupo_actual + 1,
        estado = IF(cupo_actual + 1 >= cupo_maximo, 'completa', estado)
    WHERE id_sesion = NEW.id_sesion;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validar_cupo` BEFORE INSERT ON `sesion_estudiante` FOR EACH ROW BEGIN
    DECLARE v_cupo_max INT;
    DECLARE v_cupo_act INT;
    SELECT cupo_maximo, cupo_actual INTO v_cupo_max, v_cupo_act
    FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;

    IF v_cupo_act >= v_cupo_max THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede inscribir: la sesion alcanzo su cupo maximo';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesion_tutoria`
--

CREATE TABLE `sesion_tutoria` (
  `id_sesion` int(11) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `id_tutor` int(11) NOT NULL,
  `id_coordinador` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `turno` enum('mañana','tarde','noche') NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `modalidad` enum('presencial','virtual') NOT NULL,
  `cupo_maximo` int(11) NOT NULL DEFAULT 5,
  `cupo_actual` int(11) NOT NULL DEFAULT 0,
  `estado` enum('programada','en curso','completa','finalizada','cancelada') NOT NULL DEFAULT 'programada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sesion_tutoria`
--

INSERT INTO `sesion_tutoria` (`id_sesion`, `id_plan`, `id_tutor`, `id_coordinador`, `id_area`, `turno`, `fecha`, `hora_inicio`, `hora_fin`, `modalidad`, `cupo_maximo`, `cupo_actual`, `estado`) VALUES
(2, 2, 12, 2, 1, 'noche', '2026-07-22', '14:00:00', '16:00:00', 'presencial', 5, 2, 'finalizada'),
(3, 3, 3, 2, 2, 'tarde', '2026-07-22', '14:00:00', '16:00:00', 'virtual', 5, 2, 'programada'),
(18, 19, 1, 2, 2, 'mañana', '2026-07-28', '08:00:00', '10:00:00', 'presencial', 6, 2, 'programada'),
(19, 20, 1, 2, 7, 'tarde', '2026-07-29', '14:00:00', '16:00:00', 'virtual', 5, 2, 'programada'),
(20, 21, 2, 2, 3, 'noche', '2026-07-30', '19:00:00', '21:00:00', 'virtual', 4, 1, 'finalizada'),
(21, 22, 3, 2, 6, 'mañana', '2026-07-31', '09:00:00', '11:00:00', 'presencial', 5, 1, 'programada'),
(22, 33, 4, 2, 4, 'tarde', '2026-08-01', '15:00:00', '17:00:00', 'presencial', 6, 1, 'programada'),
(23, 23, 5, 2, 8, 'noche', '2026-08-02', '18:00:00', '20:00:00', 'presencial', 5, 2, 'programada'),
(24, 24, 5, 2, 13, 'mañana', '2026-08-03', '08:30:00', '10:30:00', 'virtual', 5, 1, 'programada'),
(25, 25, 12, 2, 14, 'tarde', '2026-08-04', '13:00:00', '15:00:00', 'virtual', 6, 2, 'programada'),
(26, 26, 28, 2, 15, 'noche', '2026-08-05', '19:00:00', '21:00:00', 'presencial', 5, 1, 'programada'),
(27, 27, 3, 2, 1, 'tarde', '2026-08-06', '14:00:00', '16:00:00', 'presencial', 5, 1, 'finalizada'),
(28, 28, 2, 2, 3, 'mañana', '2026-08-07', '09:00:00', '11:00:00', 'virtual', 4, 1, 'programada'),
(29, 29, 1, 2, 2, 'noche', '2026-08-08', '18:00:00', '20:00:00', 'presencial', 6, 1, 'programada'),
(30, 30, 28, 2, 16, 'tarde', '2026-08-09', '14:00:00', '16:00:00', 'virtual', 5, 1, 'programada'),
(31, 32, 12, 2, 1, 'noche', '2026-08-11', '19:00:00', '21:00:00', 'presencial', 5, 0, 'programada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_tutoria`
--

CREATE TABLE `solicitud_tutoria` (
  `id_solicitud` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `turno` enum('mañana','tarde','noche') NOT NULL,
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','asignada','rechazada') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `solicitud_tutoria`
--

INSERT INTO `solicitud_tutoria` (`id_solicitud`, `id_estudiante`, `id_area`, `turno`, `fecha_solicitud`, `motivo`, `estado`) VALUES
(1, 11, 1, 'noche', '2026-07-22 00:07:29', '...', 'asignada'),
(2, 11, 2, 'tarde', '2026-07-22 20:16:45', 'Mejorar la logica de programacion', 'asignada'),
(3, 5, 2, 'tarde', '2026-07-22 22:00:37', '.... [Cancelada por el estudiante]', 'rechazada'),
(4, 13, 2, 'mañana', '2026-07-22 22:07:20', 'Necesito reforzar logica de programacion', 'rechazada'),
(5, 14, 7, 'tarde', '2026-07-22 22:07:20', 'Dudas con arboles binarios', 'pendiente'),
(6, 15, 3, 'noche', '2026-07-22 22:07:20', 'Repaso de limites y derivadas', 'asignada'),
(7, 16, 6, 'mañana', '2026-07-22 22:07:20', 'Problemas con matrices', 'pendiente'),
(8, 17, 4, 'tarde', '2026-07-22 22:07:20', 'Configuracion de subredes', 'rechazada'),
(9, 18, 8, 'noche', '2026-07-22 22:07:20', 'Cinematica y dinamica', 'pendiente'),
(10, 19, 13, 'mañana', '2026-07-22 22:07:20', 'Termodinamica basica', 'asignada'),
(11, 20, 14, 'tarde', '2026-07-22 22:07:20', 'Contratos civiles', 'asignada'),
(12, 21, 15, 'noche', '2026-07-22 22:07:20', 'Anatomia del sistema oseo', 'pendiente'),
(13, 4, 1, 'tarde', '2026-07-22 22:07:20', 'Modelado entidad relacion', 'asignada'),
(14, 5, 3, 'mañana', '2026-07-22 22:07:20', 'Integrales definidas', 'pendiente'),
(15, 11, 2, 'noche', '2026-07-22 22:07:20', 'Estructuras de control', 'pendiente'),
(16, 22, 16, 'tarde', '2026-07-22 22:07:20', 'Teoria del consumidor', 'asignada'),
(17, 23, 18, 'mañana', '2026-07-22 22:07:20', 'Registro contable inicial', 'asignada'),
(18, 24, 19, 'noche', '2026-07-22 22:07:20', 'Fundamentos de psicologia', 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor`
--

CREATE TABLE `tutor` (
  `id_tutor` int(11) NOT NULL,
  `especialidad_principal` varchar(120) DEFAULT NULL,
  `grado_academico` varchar(80) DEFAULT NULL,
  `disponible` enum('si','no') NOT NULL DEFAULT 'si',
  `modalidad_preferida` enum('presencial','virtual','ambas') NOT NULL DEFAULT 'ambas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `tutor`
--

INSERT INTO `tutor` (`id_tutor`, `especialidad_principal`, `grado_academico`, `disponible`, `modalidad_preferida`) VALUES
(1, 'Programacion', 'Licenciatura', 'si', 'ambas'),
(2, 'Calculo', 'Maestria', 'si', 'virtual'),
(3, 'Base de Datos', 'Licenciatura', 'si', 'presencial'),
(4, 'Redes', 'Licenciatura', 'si', 'presencial'),
(5, 'Fisica', 'Licenciatura', 'si', 'ambas'),
(12, 'Matematica Discreta', 'Doctorado', 'si', 'ambas'),
(28, 'Algebra Lineal', 'Maestria', 'si', 'virtual');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tutor_area`
--

CREATE TABLE `tutor_area` (
  `id_tutor` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `nivel_experiencia` enum('basico','intermedio','avanzado') NOT NULL DEFAULT 'basico'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `tutor_area`
--

INSERT INTO `tutor_area` (`id_tutor`, `id_area`, `nivel_experiencia`) VALUES
(1, 2, 'avanzado'),
(1, 5, 'intermedio'),
(1, 7, 'avanzado'),
(2, 3, 'avanzado'),
(2, 8, 'basico'),
(2, 10, 'intermedio'),
(3, 1, 'intermedio'),
(3, 2, 'intermedio'),
(3, 6, 'intermedio'),
(4, 4, 'avanzado'),
(4, 12, 'intermedio'),
(5, 8, 'intermedio'),
(5, 9, 'basico'),
(5, 13, 'basico'),
(12, 1, 'intermedio'),
(12, 5, 'basico'),
(28, 6, 'avanzado'),
(28, 10, 'intermedio');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `ci` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `ci`, `email`, `password`, `telefono`, `fecha_registro`, `estado`) VALUES
(1, 'Administrador', '', '10000001', 'admin@gmail.com', '$2y$10$s7JpPNPyF/qmpSMwgsEB2uRQUgsBfIdR2Hi4mNb8MCIpfl6D3OigW', '70000001', '2026-07-21 23:46:36', 'activo'),
(2, 'Coodinador', '', '10000002', 'coordinador@gmail.com', '$2y$10$IGjJb4jHORpnWfxC3VpcsumeLOWk9wQnjhXC5jGNKpMaTH/.QhlQG', '70000002', '2026-07-21 23:46:36', 'activo'),
(3, 'Estudiante', '', '10000003', 'estudiante@gmail.com', '$2y$10$KBAMwYY6EnYoINJMGpWyfONxsiNQRFaOERCGovVtY8sOnlylpn93.', '70000003', '2026-07-21 23:46:36', 'activo'),
(4, 'Angel Joel', 'Coaquira Condori', '10000004', 'angelcoaquira@tutorias.com', '$2y$10$X5bSWwCjWqWsjMgtEL.DUep3YssFbK8RLfgAxEo1XcoY7hNcFIOry', '70000004', '2026-07-21 23:46:36', 'activo'),
(5, 'Alan Sergio', 'Yupanqui Corini', '10000005', 'alanyupanqui@tutorias.com', '$2y$10$ziABqMqpNAvTXqMe/ONX2.IDkA8vr4AIqqoM4YyrDKEkDGbGTyqu6', '70000005', '2026-07-21 23:46:36', 'activo'),
(11, 'josue', 'condori', '1122334455', 'josucon@gmail.com', '$2y$10$KDrnsCsNbYQl1MouFSmlduRFNAW5Smvlnlywzc423cFrpjrIuyaEm', '73051971', '2026-07-22 00:02:16', 'activo'),
(12, 'miguel', 'Lopez', '987654321', 'miki@umsa.bo', '$2y$10$Vw2J/2agIZlLPtweEcT3WOt2zPIE5laOw064UgvK5ub4SwSSpyfRG', '65432198', '2026-07-22 00:03:54', 'activo'),
(13, 'Maria Fernanda', 'Quispe Rojas', '20000001', 'mariaquispe@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100001', '2026-07-22 22:07:20', 'activo'),
(14, 'Carlos Andres', 'Flores Vargas', '20000002', 'carlosflores@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100002', '2026-07-22 22:07:20', 'activo'),
(15, 'Daniela Sofia', 'Torrez Aguilar', '20000003', 'danielatorrez@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100003', '2026-07-22 22:07:20', 'activo'),
(16, 'Bryan Steve', 'Choque Huanca', '20000004', 'bryanchoque@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100004', '2026-07-22 22:07:20', 'activo'),
(17, 'Valeria Nicole', 'Apaza Mamani', '20000005', 'valeriaapaza@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100005', '2026-07-22 22:07:20', 'activo'),
(18, 'Rodrigo Alejandro', 'Poma Ticona', '20000006', 'rodrigopoma@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100006', '2026-07-22 22:07:20', 'activo'),
(19, 'Camila Alejandra', 'Vargas Soliz', '20000007', 'camilavargas@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100007', '2026-07-22 22:07:20', 'activo'),
(20, 'Diego Fernando', 'Mendoza Rios', '20000008', 'diegomendoza@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100008', '2026-07-22 22:07:20', 'activo'),
(21, 'Paola Andrea', 'Fernandez Luna', '20000009', 'paolafernandez@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100009', '2026-07-22 22:07:20', 'activo'),
(22, 'Sergio Ivan', 'Calle Nina', '20000010', 'sergiocalle@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100010', '2026-07-22 22:07:20', 'activo'),
(23, 'Fabiola Gabriela', 'Rocha Escobar', '20000011', 'fabiolarocha@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100011', '2026-07-22 22:07:20', 'inactivo'),
(24, 'Marcelo Ariel', 'Guzman Paco', '20000012', 'marceloguzman@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100012', '2026-07-22 22:07:20', 'activo'),
(25, 'Andrea Belen', 'Zambrana Cruz', '20000013', 'andreazambrana@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100013', '2026-07-22 22:07:20', 'activo'),
(26, 'Jose Luis', 'Villca Ramos', '20000014', 'joseluisvillca@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100014', '2026-07-22 22:07:20', 'activo'),
(27, 'Gabriela Estefania', 'Machaca Colque', '20000015', 'gabrielamachaca@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100015', '2026-07-22 22:07:20', 'activo'),
(28, 'Kevin Alexander', 'Huanca Sirpa', '20000016', 'kevinhuanca@tutorias.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrst', '71100016', '2026-07-22 22:07:20', 'activo'),
(29, 'jose miguel', 'pairumani', '11254786', 'josepairumani@gmail.com', '$2y$10$bYJOP2F9afPBLtGHl.eDFeA3D8vyq8iKLmAGRMfmubiPWwQdOU3L2', '730514684', '2026-07-22 23:06:06', 'activo');

--
-- Disparadores `usuario`
--
DELIMITER $$
CREATE TRIGGER `trg_estado_default` BEFORE INSERT ON `usuario` FOR EACH ROW BEGIN
    IF NEW.estado IS NULL THEN
        SET NEW.estado = 'activo';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validar_email` BEFORE INSERT ON `usuario` FOR EACH ROW BEGIN
    IF NEW.email NOT LIKE '%@%.%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Formato de correo invalido';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_areas_por_carrera`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_areas_por_carrera` (
`nombre_carrera` varchar(120)
,`id_area` int(11)
,`nombre_area` varchar(120)
,`descripcion` varchar(255)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_demanda_por_area`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_demanda_por_area` (
`nombre_area` varchar(120)
,`total_solicitudes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_disponibilidad_general`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_disponibilidad_general` (
`id_tutor` int(11)
,`nombre` varchar(80)
,`apellido` varchar(80)
,`dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado')
,`turno` enum('mañana','tarde','noche')
,`modalidad` enum('presencial','virtual')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_evaluaciones_completo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_evaluaciones_completo` (
`nombre` varchar(80)
,`apellido` varchar(80)
,`nombre_area` varchar(120)
,`calificacion` decimal(4,2)
,`fecha_evaluacion` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_reporte_tutorias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_reporte_tutorias` (
`id_sesion` int(11)
,`estudiante` varchar(161)
,`tutor` varchar(161)
,`nombre_area` varchar(120)
,`nombre_carrera` varchar(120)
,`fecha` date
,`turno` enum('mañana','tarde','noche')
,`modalidad` enum('presencial','virtual')
,`estado` enum('programada','en curso','completa','finalizada','cancelada')
,`calificacion` decimal(4,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_sesiones_activas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_sesiones_activas` (
`id_sesion` int(11)
,`tutor` varchar(80)
,`nombre_area` varchar(120)
,`fecha` date
,`turno` enum('mañana','tarde','noche')
,`modalidad` enum('presencial','virtual')
,`cupo_actual` int(11)
,`cupo_maximo` int(11)
,`estado` enum('programada','en curso','completa','finalizada','cancelada')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_sesiones_por_estado_y_area`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_sesiones_por_estado_y_area` (
`nombre_area` varchar(120)
,`estado` enum('programada','en curso','completa','finalizada','cancelada')
,`total_sesiones` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_tutores_completo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_tutores_completo` (
`nombre` varchar(80)
,`apellido` varchar(80)
,`especialidad_principal` varchar(120)
,`modalidad_preferida` enum('presencial','virtual','ambas')
,`nombre_area` varchar(120)
,`nivel_experiencia` enum('basico','intermedio','avanzado')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_tutores_con_sesiones_activas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_tutores_con_sesiones_activas` (
`id_tutor` int(11)
,`tutor` varchar(161)
,`sesiones_activas` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_tutores_mas_solicitados`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_tutores_mas_solicitados` (
`tutor` varchar(161)
,`total_sesiones` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_usuarios_activos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_usuarios_activos` (
`id_usuario` int(11)
,`nombre` varchar(80)
,`apellido` varchar(80)
,`email` varchar(120)
,`fecha_registro` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_usuarios_por_rol`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_usuarios_por_rol` (
`id_usuario` int(11)
,`nombre` varchar(80)
,`apellido` varchar(80)
,`email` varchar(120)
,`rol` varchar(13)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_areas_por_carrera`
--
DROP TABLE IF EXISTS `vw_areas_por_carrera`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_areas_por_carrera`  AS SELECT `c`.`nombre_carrera` AS `nombre_carrera`, `a`.`id_area` AS `id_area`, `a`.`nombre_area` AS `nombre_area`, `a`.`descripcion` AS `descripcion` FROM (`area_academica` `a` join `carrera` `c` on(`a`.`id_carrera` = `c`.`id_carrera`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_demanda_por_area`
--
DROP TABLE IF EXISTS `vw_demanda_por_area`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_demanda_por_area`  AS SELECT `a`.`nombre_area` AS `nombre_area`, count(`s`.`id_solicitud`) AS `total_solicitudes` FROM (`area_academica` `a` left join `solicitud_tutoria` `s` on(`a`.`id_area` = `s`.`id_area`)) GROUP BY `a`.`id_area` ORDER BY count(`s`.`id_solicitud`) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_disponibilidad_general`
--
DROP TABLE IF EXISTS `vw_disponibilidad_general`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_disponibilidad_general`  AS SELECT `t`.`id_tutor` AS `id_tutor`, `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `d`.`dia_semana` AS `dia_semana`, `d`.`turno` AS `turno`, `d`.`modalidad` AS `modalidad` FROM ((`disponibilidad_tutor` `d` join `tutor` `t` on(`d`.`id_tutor` = `t`.`id_tutor`)) join `usuario` `u` on(`t`.`id_tutor` = `u`.`id_usuario`)) WHERE `d`.`estado` = 'activo' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_evaluaciones_completo`
--
DROP TABLE IF EXISTS `vw_evaluaciones_completo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_evaluaciones_completo`  AS SELECT `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `a`.`nombre_area` AS `nombre_area`, `ev`.`calificacion` AS `calificacion`, `ev`.`fecha_evaluacion` AS `fecha_evaluacion` FROM ((((`evaluacion_sesion` `ev` join `estudiante` `e` on(`ev`.`id_estudiante` = `e`.`id_estudiante`)) join `usuario` `u` on(`e`.`id_estudiante` = `u`.`id_usuario`)) join `sesion_tutoria` `st` on(`ev`.`id_sesion` = `st`.`id_sesion`)) join `area_academica` `a` on(`st`.`id_area` = `a`.`id_area`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_reporte_tutorias`
--
DROP TABLE IF EXISTS `vw_reporte_tutorias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_reporte_tutorias`  AS SELECT `s`.`id_sesion` AS `id_sesion`, concat(`ue`.`nombre`,' ',`ue`.`apellido`) AS `estudiante`, concat(`ut`.`nombre`,' ',`ut`.`apellido`) AS `tutor`, `a`.`nombre_area` AS `nombre_area`, `c`.`nombre_carrera` AS `nombre_carrera`, `s`.`fecha` AS `fecha`, `s`.`turno` AS `turno`, `s`.`modalidad` AS `modalidad`, `s`.`estado` AS `estado`, `ev`.`calificacion` AS `calificacion` FROM ((((((((`sesion_tutoria` `s` join `sesion_estudiante` `se` on(`s`.`id_sesion` = `se`.`id_sesion`)) join `estudiante` `est` on(`se`.`id_estudiante` = `est`.`id_estudiante`)) join `usuario` `ue` on(`est`.`id_estudiante` = `ue`.`id_usuario`)) join `tutor` `tu` on(`s`.`id_tutor` = `tu`.`id_tutor`)) join `usuario` `ut` on(`tu`.`id_tutor` = `ut`.`id_usuario`)) join `area_academica` `a` on(`s`.`id_area` = `a`.`id_area`)) join `carrera` `c` on(`est`.`id_carrera` = `c`.`id_carrera`)) left join `evaluacion_sesion` `ev` on(`s`.`id_sesion` = `ev`.`id_sesion` and `ev`.`id_estudiante` = `est`.`id_estudiante`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_sesiones_activas`
--
DROP TABLE IF EXISTS `vw_sesiones_activas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_sesiones_activas`  AS SELECT `st`.`id_sesion` AS `id_sesion`, `u`.`nombre` AS `tutor`, `a`.`nombre_area` AS `nombre_area`, `st`.`fecha` AS `fecha`, `st`.`turno` AS `turno`, `st`.`modalidad` AS `modalidad`, `st`.`cupo_actual` AS `cupo_actual`, `st`.`cupo_maximo` AS `cupo_maximo`, `st`.`estado` AS `estado` FROM (((`sesion_tutoria` `st` join `tutor` `t` on(`st`.`id_tutor` = `t`.`id_tutor`)) join `usuario` `u` on(`t`.`id_tutor` = `u`.`id_usuario`)) join `area_academica` `a` on(`st`.`id_area` = `a`.`id_area`)) WHERE `st`.`estado` in ('programada','en curso') ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_sesiones_por_estado_y_area`
--
DROP TABLE IF EXISTS `vw_sesiones_por_estado_y_area`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_sesiones_por_estado_y_area`  AS SELECT `a`.`nombre_area` AS `nombre_area`, `s`.`estado` AS `estado`, count(0) AS `total_sesiones` FROM (`sesion_tutoria` `s` join `area_academica` `a` on(`s`.`id_area` = `a`.`id_area`)) GROUP BY `a`.`id_area`, `s`.`estado` ORDER BY `a`.`nombre_area` ASC, `s`.`estado` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_tutores_completo`
--
DROP TABLE IF EXISTS `vw_tutores_completo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_tutores_completo`  AS SELECT `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `t`.`especialidad_principal` AS `especialidad_principal`, `t`.`modalidad_preferida` AS `modalidad_preferida`, `a`.`nombre_area` AS `nombre_area`, `ta`.`nivel_experiencia` AS `nivel_experiencia` FROM (((`tutor` `t` join `usuario` `u` on(`t`.`id_tutor` = `u`.`id_usuario`)) join `tutor_area` `ta` on(`t`.`id_tutor` = `ta`.`id_tutor`)) join `area_academica` `a` on(`ta`.`id_area` = `a`.`id_area`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_tutores_con_sesiones_activas`
--
DROP TABLE IF EXISTS `vw_tutores_con_sesiones_activas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_tutores_con_sesiones_activas`  AS SELECT `t`.`id_tutor` AS `id_tutor`, concat(`u`.`nombre`,' ',`u`.`apellido`) AS `tutor`, count(`s`.`id_sesion`) AS `sesiones_activas` FROM ((`tutor` `t` join `usuario` `u` on(`t`.`id_tutor` = `u`.`id_usuario`)) left join `sesion_tutoria` `s` on(`t`.`id_tutor` = `s`.`id_tutor` and `s`.`estado` in ('programada','en curso'))) GROUP BY `t`.`id_tutor`, `u`.`nombre`, `u`.`apellido` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_tutores_mas_solicitados`
--
DROP TABLE IF EXISTS `vw_tutores_mas_solicitados`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_tutores_mas_solicitados`  AS SELECT concat(`u`.`nombre`,' ',`u`.`apellido`) AS `tutor`, count(`s`.`id_sesion`) AS `total_sesiones` FROM ((`tutor` `t` join `usuario` `u` on(`t`.`id_tutor` = `u`.`id_usuario`)) left join `sesion_tutoria` `s` on(`t`.`id_tutor` = `s`.`id_tutor`)) GROUP BY `t`.`id_tutor` ORDER BY count(`s`.`id_sesion`) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_usuarios_activos`
--
DROP TABLE IF EXISTS `vw_usuarios_activos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_usuarios_activos`  AS SELECT `usuario`.`id_usuario` AS `id_usuario`, `usuario`.`nombre` AS `nombre`, `usuario`.`apellido` AS `apellido`, `usuario`.`email` AS `email`, `usuario`.`fecha_registro` AS `fecha_registro` FROM `usuario` WHERE `usuario`.`estado` = 'activo' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_usuarios_por_rol`
--
DROP TABLE IF EXISTS `vw_usuarios_por_rol`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_usuarios_por_rol`  AS SELECT `u`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre`, `u`.`apellido` AS `apellido`, `u`.`email` AS `email`, CASE WHEN `a`.`id_administrador` is not null THEN 'Administrador' WHEN `e`.`id_estudiante` is not null THEN 'Estudiante' WHEN `t`.`id_tutor` is not null THEN 'Tutor' WHEN `c`.`id_coordinador` is not null THEN 'Coordinador' END AS `rol` FROM ((((`usuario` `u` left join `administrador` `a` on(`u`.`id_usuario` = `a`.`id_administrador`)) left join `estudiante` `e` on(`u`.`id_usuario` = `e`.`id_estudiante`)) left join `tutor` `t` on(`u`.`id_usuario` = `t`.`id_tutor`)) left join `coordinador` `c` on(`u`.`id_usuario` = `c`.`id_coordinador`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id_administrador`);

--
-- Indices de la tabla `area_academica`
--
ALTER TABLE `area_academica`
  ADD PRIMARY KEY (`id_area`),
  ADD KEY `fk_area_carrera` (`id_carrera`);

--
-- Indices de la tabla `carrera`
--
ALTER TABLE `carrera`
  ADD PRIMARY KEY (`id_carrera`),
  ADD UNIQUE KEY `codigo_carrera` (`codigo_carrera`);

--
-- Indices de la tabla `coordinador`
--
ALTER TABLE `coordinador`
  ADD PRIMARY KEY (`id_coordinador`);

--
-- Indices de la tabla `detalle_modalidad_sesion`
--
ALTER TABLE `detalle_modalidad_sesion`
  ADD PRIMARY KEY (`id_detalle`),
  ADD UNIQUE KEY `id_sesion` (`id_sesion`);

--
-- Indices de la tabla `disponibilidad_tutor`
--
ALTER TABLE `disponibilidad_tutor`
  ADD PRIMARY KEY (`id_disponibilidad`),
  ADD KEY `fk_disp_tutor` (`id_tutor`);

--
-- Indices de la tabla `estudiante`
--
ALTER TABLE `estudiante`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `registro_universitario` (`registro_universitario`),
  ADD KEY `fk_est_carrera` (`id_carrera`);

--
-- Indices de la tabla `evaluacion_sesion`
--
ALTER TABLE `evaluacion_sesion`
  ADD PRIMARY KEY (`id_evaluacion`),
  ADD UNIQUE KEY `uq_eval` (`id_sesion`,`id_estudiante`),
  ADD KEY `fk_eval_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `material_apoyo`
--
ALTER TABLE `material_apoyo`
  ADD PRIMARY KEY (`id_material`),
  ADD KEY `fk_mat_area` (`id_area`),
  ADD KEY `fk_mat_tutor` (`id_tutor`);

--
-- Indices de la tabla `plan_tutoria`
--
ALTER TABLE `plan_tutoria`
  ADD PRIMARY KEY (`id_plan`),
  ADD UNIQUE KEY `id_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `sesion_estudiante`
--
ALTER TABLE `sesion_estudiante`
  ADD PRIMARY KEY (`id_sesion`,`id_estudiante`),
  ADD KEY `fk_se_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `sesion_tutoria`
--
ALTER TABLE `sesion_tutoria`
  ADD PRIMARY KEY (`id_sesion`),
  ADD KEY `fk_ses_plan` (`id_plan`),
  ADD KEY `fk_ses_tutor` (`id_tutor`),
  ADD KEY `fk_ses_coordinador` (`id_coordinador`),
  ADD KEY `fk_ses_area` (`id_area`);

--
-- Indices de la tabla `solicitud_tutoria`
--
ALTER TABLE `solicitud_tutoria`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `fk_sol_estudiante` (`id_estudiante`),
  ADD KEY `fk_sol_area` (`id_area`);

--
-- Indices de la tabla `tutor`
--
ALTER TABLE `tutor`
  ADD PRIMARY KEY (`id_tutor`);

--
-- Indices de la tabla `tutor_area`
--
ALTER TABLE `tutor_area`
  ADD PRIMARY KEY (`id_tutor`,`id_area`),
  ADD KEY `fk_ta_area` (`id_area`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `area_academica`
--
ALTER TABLE `area_academica`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `carrera`
--
ALTER TABLE `carrera`
  MODIFY `id_carrera` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `detalle_modalidad_sesion`
--
ALTER TABLE `detalle_modalidad_sesion`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `disponibilidad_tutor`
--
ALTER TABLE `disponibilidad_tutor`
  MODIFY `id_disponibilidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `evaluacion_sesion`
--
ALTER TABLE `evaluacion_sesion`
  MODIFY `id_evaluacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `material_apoyo`
--
ALTER TABLE `material_apoyo`
  MODIFY `id_material` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `plan_tutoria`
--
ALTER TABLE `plan_tutoria`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `sesion_tutoria`
--
ALTER TABLE `sesion_tutoria`
  MODIFY `id_sesion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `solicitud_tutoria`
--
ALTER TABLE `solicitud_tutoria`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`id_administrador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `area_academica`
--
ALTER TABLE `area_academica`
  ADD CONSTRAINT `fk_area_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`);

--
-- Filtros para la tabla `coordinador`
--
ALTER TABLE `coordinador`
  ADD CONSTRAINT `fk_coord_usuario` FOREIGN KEY (`id_coordinador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_modalidad_sesion`
--
ALTER TABLE `detalle_modalidad_sesion`
  ADD CONSTRAINT `fk_det_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesion_tutoria` (`id_sesion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `disponibilidad_tutor`
--
ALTER TABLE `disponibilidad_tutor`
  ADD CONSTRAINT `fk_disp_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutor` (`id_tutor`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estudiante`
--
ALTER TABLE `estudiante`
  ADD CONSTRAINT `fk_est_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`),
  ADD CONSTRAINT `fk_est_usuario` FOREIGN KEY (`id_estudiante`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `evaluacion_sesion`
--
ALTER TABLE `evaluacion_sesion`
  ADD CONSTRAINT `fk_eval_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eval_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesion_tutoria` (`id_sesion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `material_apoyo`
--
ALTER TABLE `material_apoyo`
  ADD CONSTRAINT `fk_mat_area` FOREIGN KEY (`id_area`) REFERENCES `area_academica` (`id_area`),
  ADD CONSTRAINT `fk_mat_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutor` (`id_tutor`) ON DELETE CASCADE;

--
-- Filtros para la tabla `plan_tutoria`
--
ALTER TABLE `plan_tutoria`
  ADD CONSTRAINT `fk_plan_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitud_tutoria` (`id_solicitud`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sesion_estudiante`
--
ALTER TABLE `sesion_estudiante`
  ADD CONSTRAINT `fk_se_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_se_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesion_tutoria` (`id_sesion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sesion_tutoria`
--
ALTER TABLE `sesion_tutoria`
  ADD CONSTRAINT `fk_ses_area` FOREIGN KEY (`id_area`) REFERENCES `area_academica` (`id_area`),
  ADD CONSTRAINT `fk_ses_coordinador` FOREIGN KEY (`id_coordinador`) REFERENCES `coordinador` (`id_coordinador`),
  ADD CONSTRAINT `fk_ses_plan` FOREIGN KEY (`id_plan`) REFERENCES `plan_tutoria` (`id_plan`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ses_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutor` (`id_tutor`);

--
-- Filtros para la tabla `solicitud_tutoria`
--
ALTER TABLE `solicitud_tutoria`
  ADD CONSTRAINT `fk_sol_area` FOREIGN KEY (`id_area`) REFERENCES `area_academica` (`id_area`),
  ADD CONSTRAINT `fk_sol_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tutor`
--
ALTER TABLE `tutor`
  ADD CONSTRAINT `fk_tutor_usuario` FOREIGN KEY (`id_tutor`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tutor_area`
--
ALTER TABLE `tutor_area`
  ADD CONSTRAINT `fk_ta_area` FOREIGN KEY (`id_area`) REFERENCES `area_academica` (`id_area`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ta_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutor` (`id_tutor`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
