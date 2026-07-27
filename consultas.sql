-- =====================================================================
-- SISTEMA DE GESTION DE TUTORIAS ACADEMICAS
-- Script completo de objetos de base de datos
-- Consultas, Procedimientos, Funciones, Vistas, Cursores y Triggers
-- (incluye los del script base + los generados por cada integrante)
-- =====================================================================

USE tutorias_academicas;

-- #######################################################################
-- BLOQUE BASE (adicionales de integraciones)
-- #######################################################################

-- =========================
-- TRIGGERS BASE
-- =========================


DELIMITER $$
CREATE TRIGGER trg_incrementar_cupo
AFTER INSERT ON sesion_estudiante
FOR EACH ROW
BEGIN
    UPDATE sesion_tutoria
    SET cupo_actual = cupo_actual + 1,
        estado = IF(cupo_actual + 1 >= cupo_maximo, 'completa', estado)
    WHERE id_sesion = NEW.id_sesion;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_decrementar_cupo
AFTER DELETE ON sesion_estudiante
FOR EACH ROW
BEGIN
    UPDATE sesion_tutoria
    SET cupo_actual = GREATEST(cupo_actual - 1, 0),
        estado = IF(estado = 'completa', 'programada', estado)
    WHERE id_sesion = OLD.id_sesion;
END$$
DELIMITER ;





-- #######################################################################
-- INTEGRANTE 1 - MODULO USUARIOS Y SEGURIDAD
-- #######################################################################


-- ===== Procedimientos almacenados (2) =====
DELIMITER $$
CREATE PROCEDURE sp_cambiar_estado_usuario(IN p_id_usuario INT, IN p_nuevo_estado VARCHAR(10))
BEGIN
    UPDATE usuario SET estado = p_nuevo_estado WHERE id_usuario = p_id_usuario;
END$$
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_crear_usuario(IN p_nombre VARCHAR(80), IN p_apellido VARCHAR(80),
    IN p_ci VARCHAR(20), IN p_email VARCHAR(120), IN p_password VARCHAR(255))
BEGIN
    INSERT INTO usuario (nombre, apellido, ci, email, password)
    VALUES (p_nombre, p_apellido, p_ci, p_email, p_password);
END$$
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_nombre_completo(p_id_usuario INT) RETURNS VARCHAR(160)
DETERMINISTIC
BEGIN
    DECLARE v_nombre VARCHAR(160);
    SELECT CONCAT(nombre,' ',apellido) INTO v_nombre FROM usuario WHERE id_usuario = p_id_usuario;
    RETURN v_nombre;
END$$
DELIMITER ;


DELIMITER $$
CREATE FUNCTION fn_usuario_activo(p_id_usuario INT) RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE v_estado VARCHAR(10);
    SELECT estado INTO v_estado FROM usuario WHERE id_usuario = p_id_usuario;
    RETURN v_estado = 'activo';
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====
CREATE VIEW vw_usuarios_por_rol AS
SELECT u.id_usuario, u.nombre, u.apellido, u.email,
    CASE
        WHEN a.id_administrador IS NOT NULL THEN 'Administrador'
        WHEN e.id_estudiante IS NOT NULL THEN 'Estudiante'
        WHEN t.id_tutor IS NOT NULL THEN 'Tutor'
        WHEN c.id_coordinador IS NOT NULL THEN 'Coordinador'
    END AS rol
FROM usuario u
LEFT JOIN administrador a ON u.id_usuario = a.id_administrador
LEFT JOIN estudiante e ON u.id_usuario = e.id_estudiante
LEFT JOIN tutor t ON u.id_usuario = t.id_tutor
LEFT JOIN coordinador c ON u.id_usuario = c.id_coordinador;


CREATE VIEW vw_usuarios_activos AS
SELECT id_usuario, nombre, apellido, email, fecha_registro
FROM usuario WHERE estado = 'activo';

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_listar_usuarios_inactivos_largo()
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_contar_usuarios_por_dominio()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_validar_email
BEFORE INSERT ON usuario
FOR EACH ROW
BEGIN
    IF NEW.email NOT LIKE '%@%.%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Formato de correo invalido';
    END IF;
END$$
DELIMITER ;


DELIMITER $$
CREATE TRIGGER trg_estado_default
BEFORE INSERT ON usuario
FOR EACH ROW
BEGIN
    IF NEW.estado IS NULL THEN
        SET NEW.estado = 'activo';
    END IF;
END$$
DELIMITER ;


-- #######################################################################
-- INTEGRANTE 2 - MODULO ACADEMICO (CARRERA / AREA ACADEMICA)
-- #######################################################################


-- ===== Procedimientos almacenados (2) =====
DELIMITER $$
CREATE PROCEDURE sp_crear_area(IN p_nombre VARCHAR(120), IN p_desc VARCHAR(255), IN p_id_carrera INT)
BEGIN
    INSERT INTO area_academica (nombre_area, descripcion, id_carrera)
    VALUES (p_nombre, p_desc, p_id_carrera);
END$$
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_areas_por_carrera(IN p_id_carrera INT)
BEGIN
    SELECT id_area, nombre_area, descripcion
    FROM area_academica WHERE id_carrera = p_id_carrera;
END$$
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_total_areas_carrera(p_id_carrera INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM area_academica WHERE id_carrera = p_id_carrera;
    RETURN v_total;
END$$
DELIMITER ;


DELIMITER $$
CREATE FUNCTION fn_nombre_carrera(p_id_area INT) RETURNS VARCHAR(120)
DETERMINISTIC
BEGIN
    DECLARE v_nombre VARCHAR(120);
    SELECT c.nombre_carrera INTO v_nombre
    FROM area_academica a JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE a.id_area = p_id_area;
    RETURN v_nombre;
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====
CREATE VIEW vw_areas_por_carrera AS
SELECT c.nombre_carrera, a.id_area, a.nombre_area, a.descripcion
FROM area_academica a JOIN carrera c ON a.id_carrera = c.id_carrera;


CREATE VIEW vw_demanda_por_area AS
SELECT a.nombre_area, COUNT(s.id_solicitud) AS total_solicitudes
FROM area_academica a LEFT JOIN solicitud_tutoria s ON a.id_area = s.id_area
GROUP BY a.id_area ORDER BY total_solicitudes DESC;

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_resumen_areas_por_carrera()
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_areas_sin_tutor()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_area_nombre_mayus
BEFORE INSERT ON area_academica
FOR EACH ROW
BEGIN
    SET NEW.nombre_area = CONCAT(UPPER(LEFT(NEW.nombre_area,1)), SUBSTRING(NEW.nombre_area,2));
END$$
DELIMITER ;


DELIMITER $$
CREATE TRIGGER trg_evitar_area_duplicada
BEFORE INSERT ON area_academica
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM area_academica WHERE nombre_area = NEW.nombre_area AND id_carrera = NEW.id_carrera) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe esta area en la carrera';
    END IF;
END$$
DELIMITER ;


-- #######################################################################
-- INTEGRANTE 3 - MODULO TUTORES Y DISPONIBILIDAD
-- #######################################################################


-- ===== Procedimientos almacenados (2) =====
DELIMITER $$
CREATE PROCEDURE sp_tutores_disponibles(IN p_id_area INT, IN p_turno VARCHAR(10), IN p_modalidad VARCHAR(20))
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_registrar_disponibilidad(IN p_id_tutor INT, IN p_dia VARCHAR(15),
    IN p_turno VARCHAR(10), IN p_modalidad VARCHAR(15))
BEGIN
    INSERT INTO disponibilidad_tutor (id_tutor, dia_semana, turno, modalidad)
    VALUES (p_id_tutor, p_dia, p_turno, p_modalidad);
END$$
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_tutor_dicta_area(p_id_tutor INT, p_id_area INT) RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE v_existe INT;
    SELECT COUNT(*) INTO v_existe FROM tutor_area WHERE id_tutor=p_id_tutor AND id_area=p_id_area;
    RETURN v_existe > 0;
END$$
DELIMITER ;


DELIMITER $$
CREATE FUNCTION fn_total_disponibilidades(p_id_tutor INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM disponibilidad_tutor WHERE id_tutor=p_id_tutor AND estado='activo';
    RETURN v_total;
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====
CREATE VIEW vw_tutores_completo AS
SELECT u.nombre, u.apellido, t.especialidad_principal, t.modalidad_preferida,
       a.nombre_area, ta.nivel_experiencia
FROM tutor t
JOIN usuario u ON t.id_tutor = u.id_usuario
JOIN tutor_area ta ON t.id_tutor = ta.id_tutor
JOIN area_academica a ON ta.id_area = a.id_area;


CREATE VIEW vw_disponibilidad_general AS
SELECT t.id_tutor, u.nombre, u.apellido, d.dia_semana, d.turno, d.modalidad
FROM disponibilidad_tutor d
JOIN tutor t ON d.id_tutor = t.id_tutor
JOIN usuario u ON t.id_tutor = u.id_usuario
WHERE d.estado = 'activo';

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_tutores_carga_horaria()
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_verificar_tutores_sin_area()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_evitar_disponibilidad_duplicada
BEFORE INSERT ON disponibilidad_tutor
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM disponibilidad_tutor
        WHERE id_tutor=NEW.id_tutor AND dia_semana=NEW.dia_semana
        AND turno=NEW.turno AND modalidad=NEW.modalidad) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Disponibilidad ya registrada';
    END IF;
END$$
DELIMITER ;


DELIMITER $$
CREATE TRIGGER trg_actualizar_disponible_tutor
AFTER INSERT ON disponibilidad_tutor
FOR EACH ROW
BEGIN
    UPDATE tutor SET disponible = 'si' WHERE id_tutor = NEW.id_tutor;
END$$
DELIMITER ;


-- #######################################################################
-- INTEGRANTE 4 - MODULO SOLICITUDES Y SESIONES
-- #######################################################################


-- ===== Procedimientos almacenados (2) =====

DELIMITER $$
CREATE PROCEDURE sp_inscribir_estudiante(IN p_id_sesion INT, IN p_id_estudiante INT)
BEGIN
    IF EXISTS (SELECT 1 FROM sesion_estudiante WHERE id_sesion = p_id_sesion AND id_estudiante = p_id_estudiante) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El estudiante ya esta inscrito en esta sesion';
    ELSE
        INSERT INTO sesion_estudiante (id_sesion, id_estudiante)
        VALUES (p_id_sesion, p_id_estudiante);
    END IF;
END$$
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_crear_sesion(IN p_id_plan INT, IN p_id_tutor INT, IN p_id_coordinador INT,
    IN p_id_area INT, IN p_turno VARCHAR(10), IN p_fecha DATE, IN p_hora_inicio TIME,
    IN p_hora_fin TIME, IN p_modalidad VARCHAR(15), IN p_cupo INT)
BEGIN
    INSERT INTO sesion_tutoria (id_plan, id_tutor, id_coordinador, id_area, turno,
        fecha, hora_inicio, hora_fin, modalidad, cupo_maximo)
    VALUES (p_id_plan, p_id_tutor, p_id_coordinador, p_id_area, p_turno,
        p_fecha, p_hora_inicio, p_hora_fin, p_modalidad, p_cupo);

    SELECT LAST_INSERT_ID() AS id_sesion;
END$$
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_cupos_disponibles(p_id_sesion INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_disponibles INT;
    SELECT (cupo_maximo - cupo_actual) INTO v_disponibles
    FROM sesion_tutoria WHERE id_sesion = p_id_sesion;
    RETURN v_disponibles;
END$$
DELIMITER ;


DELIMITER $$
CREATE FUNCTION fn_total_estudiantes_sesion(p_id_sesion INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM sesion_estudiante WHERE id_sesion = p_id_sesion;
    RETURN v_total;
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====
CREATE VIEW vw_reporte_tutorias AS
SELECT
    s.id_sesion,
    CONCAT(ue.nombre,' ',ue.apellido) AS estudiante,
    CONCAT(ut.nombre,' ',ut.apellido) AS tutor,
    a.nombre_area,
    c.nombre_carrera,
    s.fecha,
    s.turno,
    s.modalidad,
    s.estado,
    ev.calificacion
FROM sesion_tutoria s
JOIN sesion_estudiante se ON s.id_sesion = se.id_sesion
JOIN estudiante est ON se.id_estudiante = est.id_estudiante
JOIN usuario ue ON est.id_estudiante = ue.id_usuario
JOIN tutor tu ON s.id_tutor = tu.id_tutor
JOIN usuario ut ON tu.id_tutor = ut.id_usuario
JOIN area_academica a ON s.id_area = a.id_area
JOIN carrera c ON est.id_carrera = c.id_carrera
LEFT JOIN evaluacion_sesion ev ON s.id_sesion = ev.id_sesion AND ev.id_estudiante = est.id_estudiante;



CREATE VIEW vw_sesiones_activas AS
SELECT st.id_sesion, u.nombre AS tutor, a.nombre_area, st.fecha, st.turno,
       st.modalidad, st.cupo_actual, st.cupo_maximo, st.estado
FROM sesion_tutoria st
JOIN tutor t ON st.id_tutor = t.id_tutor
JOIN usuario u ON t.id_tutor = u.id_usuario
JOIN area_academica a ON st.id_area = a.id_area
WHERE st.estado IN ('programada','en curso');

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_marcar_solicitudes_urgentes()
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_cerrar_sesiones_vencidas()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_validar_cupo
BEFORE INSERT ON sesion_estudiante
FOR EACH ROW
BEGIN
    DECLARE v_cupo_max INT;
    DECLARE v_cupo_act INT;
    SELECT cupo_maximo, cupo_actual INTO v_cupo_max, v_cupo_act
    FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;

    IF v_cupo_act >= v_cupo_max THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede inscribir: la sesion alcanzo su cupo maximo';
    END IF;
END$$
DELIMITER ;


DELIMITER $$
CREATE TRIGGER trg_validar_modalidad_detalle
BEFORE INSERT ON detalle_modalidad_sesion
FOR EACH ROW
BEGIN
    DECLARE v_modalidad VARCHAR(15);
    SELECT modalidad INTO v_modalidad FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;
    IF v_modalidad = 'virtual' AND NEW.aula IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sesion virtual no debe tener aula';
    END IF;
    IF v_modalidad = 'presencial' AND NEW.enlace IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sesion presencial no debe tener enlace';
    END IF;
END$$
DELIMITER ;


-- #######################################################################
-- INTEGRANTE 5 - MODULO EVALUACION Y MATERIAL DE APOYO
-- #######################################################################


-- ===== Procedimientos almacenados (2) =====
DELIMITER $$
CREATE PROCEDURE sp_registrar_evaluacion(IN p_id_sesion INT, IN p_id_estudiante INT,
    IN p_calificacion DECIMAL(4,2), IN p_observaciones VARCHAR(255), IN p_recomendaciones VARCHAR(255))
BEGIN
    INSERT INTO evaluacion_sesion (id_sesion, id_estudiante, calificacion, observaciones, recomendaciones)
    VALUES (p_id_sesion, p_id_estudiante, p_calificacion, p_observaciones, p_recomendaciones);
END$$
DELIMITER ;



DELIMITER $$
CREATE PROCEDURE sp_subir_material(
    IN p_id_area INT,
    IN p_id_tutor INT,
    IN p_titulo VARCHAR(150),
    IN p_descripcion VARCHAR(255),
    IN p_ruta VARCHAR(255),
    IN p_tipo VARCHAR(10),
    IN p_tamano_kb INT
)
BEGIN
    INSERT INTO material_apoyo (id_area, id_tutor, titulo, descripcion, ruta_archivo, tipo_archivo, tamano_kb)
    VALUES (p_id_area, p_id_tutor, p_titulo, p_descripcion, p_ruta, p_tipo, p_tamano_kb);
END$$
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_promedio_estudiante(p_id_estudiante INT) RETURNS DECIMAL(4,2)
DETERMINISTIC
BEGIN
    DECLARE v_promedio DECIMAL(4,2);
    SELECT AVG(calificacion) INTO v_promedio
    FROM evaluacion_sesion WHERE id_estudiante = p_id_estudiante;
    RETURN IFNULL(v_promedio, 0);
END$$
DELIMITER ;


DELIMITER $$
CREATE FUNCTION fn_total_materiales_area(p_id_area INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total FROM material_apoyo WHERE id_area = p_id_area AND visible='si';
    RETURN v_total;
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====

CREATE VIEW vw_tutores_mas_solicitados AS
SELECT
    CONCAT(u.nombre,' ',u.apellido) AS tutor,
    COUNT(s.id_sesion) AS total_sesiones
FROM tutor t
JOIN usuario u ON t.id_tutor = u.id_usuario
LEFT JOIN sesion_tutoria s ON t.id_tutor = s.id_tutor
GROUP BY t.id_tutor
ORDER BY total_sesiones DESC;


CREATE VIEW vw_evaluaciones_completo AS
SELECT u.nombre, u.apellido, a.nombre_area, ev.calificacion, ev.fecha_evaluacion
FROM evaluacion_sesion ev
JOIN estudiante e ON ev.id_estudiante = e.id_estudiante
JOIN usuario u ON e.id_estudiante = u.id_usuario
JOIN sesion_tutoria st ON ev.id_sesion = st.id_sesion
JOIN area_academica a ON st.id_area = a.id_area;

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_promedio_por_area()
BEGIN
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
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE sp_ocultar_materiales_antiguos()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_validar_evaluacion
BEFORE INSERT ON evaluacion_sesion
FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20);
    SELECT estado INTO v_estado FROM sesion_tutoria WHERE id_sesion = NEW.id_sesion;
    IF v_estado <> 'finalizada' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede evaluar una sesion que no esta finalizada';
    END IF;
END$$
DELIMITER ;


DELIMITER $$
CREATE TRIGGER trg_validar_tipo_archivo
BEFORE INSERT ON material_apoyo
FOR EACH ROW
BEGIN
    IF NEW.tipo_archivo NOT IN ('pdf','docx','pptx','xlsx') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de archivo no permitido';
    END IF;
END$$
DELIMITER ;

-- #######################################################################
-- INTEGRANTE 6 - OBJETOS ADICIONALES (reportes y automatización)
-- #######################################################################

-- ===== Procedimientos almacenados (2) =====
DELIMITER $$
CREATE PROCEDURE sp_resumen_sesiones_por_modalidad()
BEGIN
    SELECT modalidad, COUNT(*) AS total_sesiones
    FROM sesion_tutoria
    GROUP BY modalidad
    ORDER BY total_sesiones DESC;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_reporte_tutores_sin_sesiones_activas()
BEGIN
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
DELIMITER ;

-- ===== Funciones almacenadas (2) =====
DELIMITER $$
CREATE FUNCTION fn_sesiones_pendientes_estudiante(p_id_estudiante INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total
    FROM sesion_estudiante se
    JOIN sesion_tutoria s ON se.id_sesion = s.id_sesion
    WHERE se.id_estudiante = p_id_estudiante
      AND s.estado IN ('programada','en curso');
    RETURN IFNULL(v_total, 0);
END$$
DELIMITER ;

DELIMITER $$
CREATE FUNCTION fn_total_materiales_visibles_area(p_id_area INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total
    FROM material_apoyo
    WHERE id_area = p_id_area AND visible = 'si';
    RETURN IFNULL(v_total, 0);
END$$
DELIMITER ;

-- ===== Vistas y Reportes (2) =====
CREATE VIEW vw_sesiones_por_estado_y_area AS
SELECT a.nombre_area, s.estado, COUNT(*) AS total_sesiones
FROM sesion_tutoria s
JOIN area_academica a ON s.id_area = a.id_area
GROUP BY a.id_area, s.estado
ORDER BY a.nombre_area, s.estado;

CREATE VIEW vw_tutores_con_sesiones_activas AS
SELECT t.id_tutor, CONCAT(u.nombre,' ',u.apellido) AS tutor, COUNT(s.id_sesion) AS sesiones_activas
FROM tutor t
JOIN usuario u ON t.id_tutor = u.id_usuario
LEFT JOIN sesion_tutoria s ON t.id_tutor = s.id_tutor AND s.estado IN ('programada','en curso')
GROUP BY t.id_tutor, u.nombre, u.apellido;

-- ===== Cursores (2) =====
DELIMITER $$
CREATE PROCEDURE sp_reporte_tutores_sin_sesiones_activas_cursor()
BEGIN
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
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_resumen_sesiones_por_area_cursor()
BEGIN
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
DELIMITER ;

-- ===== Triggers (2) =====
DELIMITER $$
CREATE TRIGGER trg_estado_default_plan
BEFORE INSERT ON plan_tutoria
FOR EACH ROW
BEGIN
    IF NEW.estado IS NULL OR NEW.estado = '' THEN
        SET NEW.estado = 'en curso';
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_estado_default_material
BEFORE INSERT ON material_apoyo
FOR EACH ROW
BEGIN
    IF NEW.visible IS NULL OR NEW.visible = '' THEN
        SET NEW.visible = 'si';
    END IF;
END$$
DELIMITER ;

-- =====================================================================
-- FIN DEL SCRIPT
-- =====================================================================








-- =====================================================================
-- CONSULTAS DEL SCRIPT
-- =====================================================================

-- #######################################################################
-- INTEGRANTE 1 - MODULO USUARIOS Y SEGURIDAD   (usado principalmente por Admin)
-- #######################################################################

-- 1. Listado completo de solicitudes de tutoría
SELECT
    sol.id_solicitud,
    sol.fecha_solicitud,
    sol.turno,
    sol.motivo,
    sol.estado,
    CONCAT(u.nombre,' ',u.apellido) AS estudiante,
    u.email,
    est.semestre,
    c.nombre_carrera,
    a.nombre_area
FROM solicitud_tutoria sol
JOIN estudiante est ON sol.id_estudiante=est.id_estudiante
JOIN usuario u ON est.id_estudiante=u.id_usuario
JOIN carrera c ON est.id_carrera=c.id_carrera
JOIN area_academica a ON sol.id_area=a.id_area
ORDER BY sol.fecha_solicitud DESC;

-- 2. Cantidad de solicitudes por estado
SELECT estado,
COUNT(*) AS total
FROM solicitud_tutoria
GROUP BY estado;

-- 3. Usuarios con teléfono registrado
SELECT
    id_usuario,
    nombre,
    apellido,
    email,
    telefono
FROM usuario
WHERE telefono IS NOT NULL
  AND telefono <> '';

-- 4. Usuarios inactivos
SELECT nombre, apellido, email, fecha_registro
FROM usuario WHERE estado = 'inactivo';

-- 5. Usuarios registrados en el ultimo mes
SELECT nombre, apellido, fecha_registro
FROM usuario
WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 MONTH);

-- 6. Buscar usuario por correo
SELECT id_usuario, nombre, apellido, estado
FROM usuario WHERE email = 'ejemplo@umsa.bo';

-- 7. Coordinadores y su cargo
SELECT u.nombre, u.apellido, c.cargo
FROM coordinador c JOIN usuario u ON c.id_coordinador = u.id_usuario;


-- #######################################################################
-- INTEGRANTE 2 - MODULO ACADEMICO (CARRERA / AREA ACADEMICA)   (usado por Admin y Coordinador)
-- #######################################################################

-- 1. Áreas académicas disponibles para el filtro
SELECT
id_area,
nombre_area
FROM area_academica
ORDER BY nombre_area;

-- 2. Materiales de apoyo por tutor
SELECT
    CONCAT(u.nombre,' ',u.apellido) AS tutor,
    COUNT(m.id_material) AS total_materiales
FROM material_apoyo m
JOIN tutor t ON m.id_tutor = t.id_tutor
JOIN usuario u ON t.id_tutor = u.id_usuario
JOIN area_academica a ON m.id_area = a.id_area
WHERE m.visible = 'si'
  AND a.id_carrera = (
      SELECT id_carrera
      FROM estudiante
      WHERE id_estudiante = ?
  )
GROUP BY t.id_tutor
ORDER BY total_materiales DESC;

-- 3. Materiales visibles por área
SELECT
    m.titulo,
    m.descripcion,
    m.tipo_archivo,
    m.ruta_archivo,
    m.fecha_subida,
    a.nombre_area,
    CONCAT(u.nombre,' ',u.apellido) AS tutor
FROM material_apoyo m
JOIN area_academica a ON m.id_area = a.id_area
JOIN tutor t ON m.id_tutor = t.id_tutor
JOIN usuario u ON t.id_tutor = u.id_usuario
WHERE m.visible = 'si'
AND a.id_carrera = (
    SELECT id_carrera
    FROM estudiante
    WHERE id_estudiante = ?
)
ORDER BY m.fecha_subida DESC;

-- 4. Estudiantes agrupados por carrera
SELECT c.nombre_carrera, COUNT(e.id_estudiante) AS total_estudiantes
FROM carrera c LEFT JOIN estudiante e ON c.id_carrera = e.id_carrera
GROUP BY c.id_carrera;

-- 5. Solicitudes del estudiante
SELECT
    s.id_solicitud,
    s.fecha_solicitud,
    s.turno,
    s.motivo,
    s.estado,
    a.nombre_area,
    (
        SELECT p.id_plan
        FROM plan_tutoria p
        WHERE p.id_solicitud = s.id_solicitud
    ) AS id_plan
FROM solicitud_tutoria s
JOIN area_academica a
ON s.id_area = a.id_area
WHERE s.id_estudiante = ?
ORDER BY s.fecha_solicitud DESC;

-- 6. Buscar area por nombre
SELECT id_area, nombre_area, descripcion FROM area_academica
WHERE nombre_area LIKE '%Base de Datos%';

-- 7. Carreras con su facultad
SELECT nombre_carrera, facultad FROM carrera ORDER BY facultad;


-- #######################################################################
-- INTEGRANTE 3 - MODULO TUTORES Y DISPONIBILIDAD    (usado por Tutor)
-- #######################################################################

-- 1. Tutores con su especialidad y modalidad preferida
SELECT u.nombre, u.apellido, t.especialidad_principal, t.modalidad_preferida
FROM tutor t JOIN usuario u ON t.id_tutor = u.id_usuario;

-- 2. Sesiones disponibles para inscripción
SELECT
    st.id_sesion,
    v.nombre_area,
    v.tutor,
    v.fecha,
    v.turno,
    v.modalidad,
    v.cupo_actual,
    v.cupo_maximo
FROM sesion_tutoria st
JOIN vw_sesiones_activas v
ON v.id_sesion = st.id_sesion
WHERE st.fecha >= CURDATE()
AND st.id_sesion NOT IN
(
    SELECT id_sesion
    FROM sesion_estudiante
    WHERE id_estudiante = ?
)
ORDER BY v.fecha,v.turno;

-- 3. Áreas sin descripción registrada
SELECT
a.nombre_area,
c.nombre_carrera
FROM area_academica a
JOIN carrera c
ON a.id_carrera=c.id_carrera
WHERE a.descripcion IS NULL
OR a.descripcion='';

-- 4. Historial de evaluaciones del estudiante
SELECT
    ev.id_sesion,
    a.nombre_area,
    ev.calificacion,
    ev.observaciones,
    ev.recomendaciones,
    ev.fecha_evaluacion
FROM evaluacion_sesion ev
JOIN sesion_tutoria st
ON ev.id_sesion = st.id_sesion
JOIN area_academica a
ON st.id_area = a.id_area
WHERE ev.id_estudiante = ?
ORDER BY ev.fecha_evaluacion DESC;

-- 5. Cantidad de areas que dicta cada tutor
SELECT u.nombre, u.apellido, COUNT(ta.id_area) AS total_areas
FROM tutor t JOIN usuario u ON t.id_tutor = u.id_usuario
LEFT JOIN tutor_area ta ON t.id_tutor = ta.id_tutor
GROUP BY t.id_tutor;

-- 6. Solicitudes realizadas por el estudiante
SELECT
    s.id_solicitud,
    s.fecha_solicitud,
    a.nombre_area,
    s.turno,
    s.motivo,
    s.estado
FROM solicitud_tutoria s
JOIN area_academica a
ON s.id_area = a.id_area
WHERE s.id_estudiante = ?
ORDER BY s.fecha_solicitud DESC;

-- 7. Disponibilidad agrupada por dia
SELECT dia_semana, COUNT(*) AS total_tutores
FROM disponibilidad_tutor WHERE estado='activo'
GROUP BY dia_semana;


-- #######################################################################
-- INTEGRANTE 4 - MODULO SOLICITUDES Y SESIONES    (usado por Coordinador)
-- #######################################################################

-- 1. Rechazar una solicitud pendiente
UPDATE solicitud_tutoria
SET estado='rechazada'
WHERE id_solicitud=?
AND estado='pendiente';

-- 2. (CAMBIADA) Sesiones programadas del tutor
SELECT s.id_sesion, s.fecha, s.hora_inicio, s.hora_fin, s.turno, s.modalidad, s.estado
FROM sesion_tutoria s
WHERE s.id_tutor = ?
  AND s.estado = 'programada'
ORDER BY s.fecha DESC, s.hora_inicio DESC;

-- 3. (CAMBIADA) Estudiantes inscritos en una sesión
SELECT se.id_sesion, CONCAT(u.nombre,' ',u.apellido) AS estudiante, se.asistencia
FROM sesion_estudiante se
JOIN estudiante e ON se.id_estudiante = e.id_estudiante
JOIN usuario u ON e.id_estudiante = u.id_usuario
WHERE se.id_sesion = ?
ORDER BY u.apellido, u.nombre;

-- 4. Áreas académicas de la carrera del estudiante
SELECT id_area, nombre_area
FROM area_academica
WHERE id_carrera = (
    SELECT id_carrera
    FROM estudiante
    WHERE id_estudiante = ?
)
ORDER BY nombre_area;

-- 5. Datos del perfil del estudiante
SELECT
    u.nombre,
    u.apellido,
    u.ci,
    u.email,
    u.telefono,
    e.registro_universitario,
    e.semestre,
    c.nombre_carrera,
    c.facultad
FROM usuario u
JOIN estudiante e
ON u.id_usuario = e.id_estudiante
JOIN carrera c
ON e.id_carrera = c.id_carrera
WHERE u.id_usuario = ?;

-- 6. (CAMBIADA) Sesiones virtuales con detalle de modalidad
SELECT st.id_sesion, st.fecha, st.turno, dm.plataforma, dm.enlace
FROM sesion_tutoria st
LEFT JOIN detalle_modalidad_sesion dm ON st.id_sesion = dm.id_sesion
WHERE st.modalidad = 'virtual'
ORDER BY st.fecha, st.turno;

-- 7. Historial de sesiones de un estudiante
SELECT st.id_sesion, st.fecha, a.nombre_area, st.estado
FROM sesion_estudiante se
JOIN sesion_tutoria st ON se.id_sesion = st.id_sesion
JOIN area_academica a ON st.id_area = a.id_area
WHERE se.id_estudiante = 1;


-- #######################################################################
-- INTEGRANTE 5 - MODULO EVALUACION Y MATERIAL DE APOYO   (usado por Tutor y Estudiante)
-- #######################################################################

-- 1. (CAMBIADA - antes complementaba sp_registrar_evaluacion) Sesiones finalizadas aun sin evaluacion registrada
SELECT st.id_sesion, st.fecha, a.nombre_area
FROM sesion_tutoria st
JOIN area_academica a ON st.id_area = a.id_area
WHERE st.estado = 'finalizada'
AND st.id_sesion NOT IN (SELECT DISTINCT id_sesion FROM evaluacion_sesion);

-- 2. Reporte de tutorías por rango de fechas
SELECT *
FROM vw_reporte_tutorias
WHERE fecha BETWEEN ? AND ?
ORDER BY fecha DESC;

-- 3. Promedio general del estudiante
SELECT AVG(calificacion) AS promedio
FROM evaluacion_sesion
WHERE id_estudiante = ?;

-- 4. (CAMBIADA) Materiales subidos con días desde la subida
SELECT id_material, titulo, fecha_subida, DATEDIFF(NOW(), fecha_subida) AS dias_desde_subida
FROM material_apoyo
ORDER BY fecha_subida DESC;

-- 5. Sesiones inscritas del estudiante
SELECT
    s.id_sesion,
    s.fecha,
    s.turno,
    s.modalidad,
    a.nombre_area
FROM sesion_estudiante se
JOIN sesion_tutoria s
ON se.id_sesion = s.id_sesion
JOIN area_academica a
ON s.id_area = a.id_area
WHERE se.id_estudiante = ?
ORDER BY s.fecha DESC;

-- 6. (CAMBIADA) Materiales con tipo de archivo y tamaño
SELECT m.id_material, m.titulo, m.tipo_archivo, m.tamano_kb
FROM material_apoyo m
ORDER BY m.fecha_subida DESC;

-- 7. Observaciones y recomendaciones del estudiante
SELECT
    a.nombre_area,
    ev.calificacion,
    ev.observaciones,
    ev.recomendaciones
FROM evaluacion_sesion ev
JOIN sesion_tutoria st
ON ev.id_sesion = st.id_sesion
JOIN area_academica a
ON st.id_area = a.id_area
WHERE ev.id_estudiante = ?
ORDER BY ev.fecha_evaluacion DESC;


-- #######################################################################
-- INTEGRANTE 6 - MODULO CONSULTAS ADICIONALES (consultas directas del proyecto, no incluidas en las 35)
-- #######################################################################

-- 1. Conteo de sesiones por estado
SELECT estado, COUNT(*) AS total
FROM sesion_tutoria
GROUP BY estado;

-- 2. Total de carreras registradas
SELECT COUNT(*) AS total_carreras
FROM carrera;

-- 3. Total de áreas académicas registradas
SELECT COUNT(*) AS total_areas
FROM area_academica;

-- 4. Total de asignaciones tutor-área registradas
SELECT COUNT(*) AS total_asignaciones
FROM tutor_area;

-- 5. Áreas que dicta un tutor específico
SELECT a.id_area, a.nombre_area
FROM tutor_area ta
JOIN area_academica a ON ta.id_area = a.id_area
WHERE ta.id_tutor = ?
ORDER BY a.nombre_area;

-- 6. Disponibilidad registrada de un tutor
SELECT id_disponibilidad, dia_semana, turno, modalidad, estado
FROM disponibilidad_tutor
WHERE id_tutor = ?
ORDER BY FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado'),
         FIELD(turno,'mañana','tarde','noche');

-- 7. Carrera del estudiante para filtrar áreas
SELECT id_carrera
FROM estudiante
WHERE id_estudiante = ?;

-- =====================================================================
-- FIN DEL ARCHIVO
-- =====================================================================