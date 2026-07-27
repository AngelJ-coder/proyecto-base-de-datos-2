<?php
// coordinador/asignar_sesion.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$id_coordinador = $_SESSION['id_usuario'];
$error = '';
$mensaje = '';

$id_solicitud = isset($_GET['id_solicitud']) ? (int)$_GET['id_solicitud'] : 0;

// Procesar asignacion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_solicitud   = (int)($_POST['id_solicitud'] ?? 0);
    $id_tutor       = (int)($_POST['id_tutor'] ?? 0);
    $id_area        = (int)($_POST['id_area'] ?? 0);
    $turno          = $_POST['turno'] ?? '';
    $fecha          = $_POST['fecha'] ?? '';
    $hora_inicio    = $_POST['hora_inicio'] ?? '';
    $hora_fin       = $_POST['hora_fin'] ?? '';
    $modalidad      = $_POST['modalidad'] ?? '';
    $cupo_maximo    = (int)($_POST['cupo_maximo'] ?? 5);
    $objetivo       = trim($_POST['objetivo'] ?? '');
    $fecha_fin_est  = $_POST['fecha_fin_estimada'] ?? null;
    $num_sesiones   = (int)($_POST['numero_sesiones_estimadas'] ?? 1);
    $aula       = trim($_POST['aula'] ?? '');
    $edificio   = trim($_POST['edificio'] ?? '');
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $plataforma = trim($_POST['plataforma'] ?? '');
    $enlace     = trim($_POST['enlace'] ?? '');

    if ($id_solicitud <= 0 || $id_tutor <= 0 || $id_area <= 0 || $turno === '' ||
        $fecha === '' || $hora_inicio === '' || $hora_fin === '' || $modalidad === '') {
        $error = 'Por favor completa todos los campos obligatorios.';
    } elseif ($hora_fin <= $hora_inicio) {
        $error = 'La hora de fin debe ser posterior a la hora de inicio.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id_estudiante, estado FROM solicitud_tutoria WHERE id_solicitud = ? FOR UPDATE");
            $stmt->execute([$id_solicitud]);
            $sol = $stmt->fetch();

            if (!$sol || $sol['estado'] !== 'pendiente') {
                throw new Exception('La solicitud ya no está pendiente.');
            }

            // Este insert activa el trigger trg_estado_default_plan del Integrante 6.
            $stmt = $pdo->prepare("
                INSERT INTO plan_tutoria (id_solicitud, objetivo, fecha_inicio, fecha_fin_estimada, numero_sesiones_estimadas)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_solicitud, $objetivo ?: null, $fecha, $fecha_fin_est ?: null, $num_sesiones]);
            $id_plan = $pdo->lastInsertId();

            $stmt = $pdo->prepare("CALL sp_crear_sesion(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_plan, $id_tutor, $id_coordinador, $id_area, $turno, $fecha, $hora_inicio, $hora_fin, $modalidad, $cupo_maximo]);

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_sesion = $fila['id_sesion'] ?? null;
            $stmt->closeCursor();

            if (!$id_sesion) {
                throw new Exception('No se pudo obtener el ID de la sesión creada.');
            }

            if ($modalidad === 'presencial') {
                $stmt = $pdo->prepare("
                    INSERT INTO detalle_modalidad_sesion (id_sesion, aula, edificio, ubicacion)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id_sesion, $aula ?: null, $edificio ?: null, $ubicacion ?: null]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO detalle_modalidad_sesion (id_sesion, plataforma, enlace)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$id_sesion, $plataforma ?: null, $enlace ?: null]);
            }

            $stmt = $pdo->prepare("INSERT INTO sesion_estudiante (id_sesion, id_estudiante) VALUES (?, ?)");
            $stmt->execute([$id_sesion, $sol['id_estudiante']]);

            $stmt = $pdo->prepare("UPDATE solicitud_tutoria SET estado = 'asignada' WHERE id_solicitud = ?");
            $stmt->execute([$id_solicitud]);

            $pdo->commit();
            header('Location: solicitudes.php?msg=asignada');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ocurrió un error al asignar la sesión: ' . $e->getMessage();
        }
    }
}

// ===== NUEVO: Procesar inscripción directa de un estudiante en una sesión ya en curso =====
// Este bloque es adicional y no modifica el flujo POST original de arriba.
$msg_inscripcion = '';
$error_inscripcion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'inscribir_existente') {
    $id_sesion_ins = (int)($_POST['id_sesion_ins'] ?? 0);
    $id_solicitud_ins = (int)($_POST['id_solicitud_ins'] ?? 0);

    if ($id_sesion_ins <= 0 || $id_solicitud_ins <= 0) {
        $error_inscripcion = 'Datos inválidos para la inscripción.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id_estudiante, estado FROM solicitud_tutoria WHERE id_solicitud = ? FOR UPDATE");
            $stmt->execute([$id_solicitud_ins]);
            $sol_ins = $stmt->fetch();

            if (!$sol_ins || $sol_ins['estado'] !== 'pendiente') {
                throw new Exception('La solicitud ya no está pendiente.');
            }

            $stmt = $pdo->prepare("CALL sp_inscribir_estudiante(?, ?)");
            $stmt->execute([$id_sesion_ins, $sol_ins['id_estudiante']]);
            $stmt->closeCursor();

            $stmt = $pdo->prepare("UPDATE solicitud_tutoria SET estado = 'asignada' WHERE id_solicitud = ?");
            $stmt->execute([$id_solicitud_ins]);

            $pdo->commit();
            header('Location: asignar_sesion.php?msg_ins=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_inscripcion = 'Ocurrió un error al inscribir al estudiante: ' . $e->getMessage();
        }
    }
}
if (isset($_GET['msg_ins']) && $_GET['msg_ins'] == '1') {
    $msg_inscripcion = 'Estudiante inscrito correctamente en la sesión existente.';
}

$solicitud = null;
if ($id_solicitud > 0) {
    $stmt = $pdo->prepare("
        SELECT sol.id_solicitud, sol.turno, sol.motivo, sol.fecha_solicitud, sol.id_area,
            CONCAT(u.nombre,' ',u.apellido) AS estudiante, u.email, est.semestre,
            c.nombre_carrera, a.nombre_area
        FROM solicitud_tutoria sol
        JOIN estudiante est ON sol.id_estudiante = est.id_estudiante
        JOIN usuario u ON est.id_estudiante = u.id_usuario
        JOIN carrera c ON est.id_carrera = c.id_carrera
        JOIN area_academica a ON sol.id_area = a.id_area
        WHERE sol.id_solicitud = ? AND sol.estado = 'pendiente'
    ");
    $stmt->execute([$id_solicitud]);
    $solicitud = $stmt->fetch();
    if (!$solicitud) {
        $error = 'La solicitud indicada no existe o ya no está pendiente.';
    }
}

// ===== NUEVO: paginación para "Solicitudes pendientes por asignar" (5 por página) =====
$pag_sol = isset($_GET['pag_sol']) ? max(1, (int)$_GET['pag_sol']) : 1;
$por_pagina_sol = 5;
$offset_sol = ($pag_sol - 1) * $por_pagina_sol;

$total_sol = (int)$pdo->query("SELECT COUNT(*) FROM solicitud_tutoria WHERE estado = 'pendiente'")->fetchColumn();
$total_paginas_sol = max(1, (int)ceil($total_sol / $por_pagina_sol));

$stmt = $pdo->prepare("
    SELECT sol.id_solicitud, sol.turno, sol.fecha_solicitud,
        CONCAT(u.nombre,' ',u.apellido) AS estudiante, a.nombre_area
    FROM solicitud_tutoria sol
    JOIN estudiante est ON sol.id_estudiante = est.id_estudiante
    JOIN usuario u ON est.id_estudiante = u.id_usuario
    JOIN area_academica a ON sol.id_area = a.id_area
    WHERE sol.estado = 'pendiente'
    ORDER BY sol.fecha_solicitud ASC
    LIMIT $por_pagina_sol OFFSET $offset_sol
");
$stmt->execute();
$solicitudes_pendientes = $stmt->fetchAll();

$turno_filtro = $_GET['turno_filtro'] ?? ($solicitud['turno'] ?? '');
$modalidad_filtro = $_GET['modalidad_filtro'] ?? '';

$tutores = [];

if ($solicitud && $turno_filtro !== '' && $modalidad_filtro !== '') {
    $stmt = $pdo->prepare("
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            ta.nivel_experiencia,
            dt.dia_semana,
            dt.modalidad
        FROM tutor t
        JOIN usuario u 
            ON t.id_tutor = u.id_usuario
        JOIN tutor_area ta 
            ON t.id_tutor = ta.id_tutor
        JOIN disponibilidad_tutor dt 
            ON t.id_tutor = dt.id_tutor
        WHERE ta.id_area = ?
          AND dt.turno = CONVERT(? USING utf8mb4) COLLATE utf8mb4_spanish_ci
          AND dt.modalidad = CONVERT(? USING utf8mb4) COLLATE utf8mb4_spanish_ci
          AND dt.estado = 'activo'
          AND u.estado = 'activo'
    ");

    $stmt->execute([
        $solicitud['id_area'],
        $turno_filtro,
        $modalidad_filtro
    ]);

    $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$areas = $pdo->query("SELECT id_area, nombre_area FROM area_academica ORDER BY nombre_area")->fetchAll();

// ===== Planes de tutoria en curso (Consulta 5 - Integrante 4) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para listar los planes de tutoría que siguen en curso.
// ===== NUEVO: paginación para "Planes de tutoría en curso" (5 por página) =====
$pag_plan = isset($_GET['pag_plan']) ? max(1, (int)$_GET['pag_plan']) : 1;
$por_pagina_plan = 5;
$offset_plan = ($pag_plan - 1) * $por_pagina_plan;

$total_planes = (int)$pdo->query("SELECT COUNT(*) FROM plan_tutoria WHERE estado = 'en curso'")->fetchColumn();
$total_paginas_plan = max(1, (int)ceil($total_planes / $por_pagina_plan));

$stmt = $pdo->prepare("
    SELECT p.id_plan, p.objetivo, p.numero_sesiones_estimadas, p.estado
    FROM plan_tutoria p WHERE p.estado = 'en curso'
    ORDER BY p.id_plan ASC
    LIMIT $por_pagina_plan OFFSET $offset_plan
");
$stmt->execute();
$planes_en_curso = $stmt->fetchAll();

// ===== NUEVO: Sesiones en curso/programadas con cupo disponible, para inscribir estudiantes
// que tienen solicitudes pendientes que coinciden en área, turno y modalidad =====
$pag_ins = isset($_GET['pag_ins']) ? max(1, (int)$_GET['pag_ins']) : 1;
$por_pagina_ins = 5;
$offset_ins = ($pag_ins - 1) * $por_pagina_ins;

$sql_ins_base = "
    FROM sesion_tutoria st
    JOIN area_academica a ON st.id_area = a.id_area
    JOIN tutor t ON st.id_tutor = t.id_tutor
    JOIN usuario ut ON t.id_tutor = ut.id_usuario
    JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE st.estado IN ('programada','en curso')
      AND st.cupo_actual < st.cupo_maximo
      AND EXISTS (
            SELECT 1 FROM solicitud_tutoria sol2
            WHERE sol2.estado = 'pendiente'
              AND sol2.id_area = st.id_area
              AND CONVERT(sol2.turno USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(st.turno USING utf8mb4) COLLATE utf8mb4_general_ci
      )
";

$total_ins = (int)$pdo->query("SELECT COUNT(*) " . $sql_ins_base)->fetchColumn();
$total_paginas_ins = max(1, (int)ceil($total_ins / $por_pagina_ins));

$stmt = $pdo->prepare("
    SELECT st.id_sesion, st.fecha, st.turno, st.modalidad, st.cupo_maximo, st.cupo_actual,
           a.nombre_area, a.id_area, c.nombre_carrera,
           CONCAT(ut.nombre,' ',ut.apellido) AS tutor
    " . $sql_ins_base . "
    ORDER BY st.fecha ASC
    LIMIT $por_pagina_ins OFFSET $offset_ins
");
$stmt->execute();
$sesiones_para_inscribir = $stmt->fetchAll();

// Para cada sesión, obtenemos las solicitudes pendientes compatibles (misma área y turno)
$solicitudes_compatibles_por_sesion = [];
if (count($sesiones_para_inscribir) > 0) {
   $stmt_comp = $pdo->prepare("
        SELECT sol.id_solicitud, sol.motivo,
            CONCAT(u.nombre,' ',u.apellido) AS estudiante,
            c.nombre_carrera, est.semestre
        FROM solicitud_tutoria sol
        JOIN estudiante est ON sol.id_estudiante = est.id_estudiante
        JOIN usuario u ON est.id_estudiante = u.id_usuario
        JOIN carrera c ON est.id_carrera = c.id_carrera
        WHERE sol.estado = 'pendiente'
          AND sol.id_area = ?
          AND CONVERT(sol.turno USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
        ORDER BY sol.fecha_solicitud ASC
    ");
    foreach ($sesiones_para_inscribir as $s_ins) {
        $stmt_comp->execute([$s_ins['id_area'], $s_ins['turno']]);
        $solicitudes_compatibles_por_sesion[$s_ins['id_sesion']] = $stmt_comp->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asignar Sesión - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/coordinador.css">
<style>
/* ===== NUEVO: estilos para paginación y el nuevo apartado, sin tocar los archivos CSS originales ===== */
.paginacion{
    display:flex;
    gap:6px;
    align-items:center;
    justify-content:flex-end;
    margin-top:14px;
    flex-wrap:wrap;
}
.paginacion a, .paginacion span{
    padding:6px 12px;
    border-radius:8px;
    font-size:.78rem;
    font-weight:700;
    text-decoration:none;
    border:1px solid var(--border, #e6e9f2);
    color:var(--muted, #6b7280);
}
.paginacion a:hover{
    background:var(--accent-soft, #eef1ff);
    color:var(--accent, #4f6df5);
}
.paginacion .pagina-actual{
    background:linear-gradient(135deg,var(--accent, #4f6df5),#6d5ff5);
    color:#fff;
    border-color:transparent;
}
.subsesion-card{
    border:1px solid var(--border, #e6e9f2);
    border-radius:12px;
    padding:14px 16px;
    margin-bottom:12px;
}
.subsesion-card .cabecera{
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:8px;
    font-size:.85rem;
    margin-bottom:10px;
}
.subsesion-card .cabecera strong{ color:var(--text,#111827); }
.tabla-mini-inscripcion td, .tabla-mini-inscripcion th{
    font-size:.8rem;
}
.btn-inscribir-mini{
    background:linear-gradient(135deg,var(--green,#12b76a),#0e9c5a);
    color:#fff;
    border:none;
    padding:5px 12px;
    border-radius:999px;
    font-size:.72rem;
    font-weight:700;
    cursor:pointer;
}
.btn-inscribir-mini:hover{ opacity:.9; }
</style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" >
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitudes.php">
        <i class="fa-solid fa-inbox"></i><span>Solicitudes</span>
    </a>

    <a href="asignar_sesion.php" class="active">
        <i class="fa-solid fa-calendar-check"></i><span>Asignar Sesión</span>
    </a>

    <a href="sesiones.php">
        <i class="fa-solid fa-calendar-days"></i><span>Sesiones</span>
    </a>

    <a href="tutores.php">
        <i class="fa-solid fa-chalkboard-user"></i><span>Tutores</span>
    </a>

    <a href="historial_estudiante.php">
        <i class="fa-solid fa-clock-rotate-left"></i><span>Historial Estudiante</span>
    </a>

    <a href="notificaciones.php">
        <i class="fa-solid fa-bell"></i><span>Notificaciones</span>
    </a>

    <a href="reportes.php">
        <i class="fa-solid fa-chart-line"></i><span>Reportes</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Asignar Sesión de Tutoría</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($msg_inscripcion): ?>
        <div class="alerta alerta-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_inscripcion) ?></div>
    <?php endif; ?>

    <?php if ($error_inscripcion): ?>
        <div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_inscripcion) ?></div>
    <?php endif; ?>

    <?php if (!$solicitud): ?>

        <!-- LISTA DE SOLICITUDES PENDIENTES -->
        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-inbox"></i> Solicitudes pendientes por asignar</h3>
            <?php if (count($solicitudes_pendientes) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> No hay solicitudes pendientes por asignar.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Estudiante</th><th>Área</th><th>Turno</th><th>Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_pendientes as $s): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
                        <td><?= htmlspecialchars($s['estudiante']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['turno']) ?></td>
                        <td><a class="btn-mini btn-asignar" href="asignar_sesion.php?id_solicitud=<?= $s['id_solicitud'] ?>">Asignar</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- NUEVO: paginación solicitudes pendientes -->
            <?php if ($total_paginas_sol > 1): ?>
            <div class="paginacion">
                <?php if ($pag_sol > 1): ?>
                    <a href="?pag_sol=<?= $pag_sol - 1 ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $pag_ins ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas_sol; $i++): ?>
                    <?php if ($i === $pag_sol): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pag_sol=<?= $i ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $pag_ins ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pag_sol < $total_paginas_sol): ?>
                    <a href="?pag_sol=<?= $pag_sol + 1 ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $pag_ins ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- NUEVO: Inscribir estudiantes con solicitud pendiente en tutorías ya en curso -->
        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-user-plus"></i> Inscribir en tutorías ya en curso (misma área, carrera y horario)</h3>
            <?php if (count($sesiones_para_inscribir) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay sesiones en curso con cupo disponible que coincidan con solicitudes pendientes.</p>
            <?php else: ?>
                <?php foreach ($sesiones_para_inscribir as $s_ins): ?>
                <div class="subsesion-card">
                    <div class="cabecera">
                        <span><strong>Sesión #<?= $s_ins['id_sesion'] ?></strong> — <?= htmlspecialchars($s_ins['nombre_area']) ?> (<?= htmlspecialchars($s_ins['nombre_carrera']) ?>)</span>
                        <span>Tutor: <strong><?= htmlspecialchars($s_ins['tutor']) ?></strong></span>
                        <span><?= date('d/m/Y', strtotime($s_ins['fecha'])) ?> — <?= ucfirst($s_ins['turno']) ?> / <?= ucfirst($s_ins['modalidad']) ?></span>
                        <span>Cupo: <?= $s_ins['cupo_actual'] ?>/<?= $s_ins['cupo_maximo'] ?></span>
                    </div>

                    <?php $comp = $solicitudes_compatibles_por_sesion[$s_ins['id_sesion']] ?? []; ?>
                    <?php if (count($comp) === 0): ?>
                        <p class="vacio texto-muted">No hay solicitudes pendientes compatibles con esta sesión.</p>
                    <?php else: ?>
                    <table class="tabla-mini-inscripcion">
                        <thead>
                            <tr><th>Estudiante</th><th>Carrera</th><th>Semestre</th><th>Motivo</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comp as $c_sol): ?>
                            <tr>
                                <td><?= htmlspecialchars($c_sol['estudiante']) ?></td>
                                <td><?= htmlspecialchars($c_sol['nombre_carrera']) ?></td>
                                <td><?= $c_sol['semestre'] ?></td>
                                <td><?= htmlspecialchars($c_sol['motivo'] ?: '—') ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="inscribir_existente">
                                        <input type="hidden" name="id_sesion_ins" value="<?= $s_ins['id_sesion'] ?>">
                                        <input type="hidden" name="id_solicitud_ins" value="<?= $c_sol['id_solicitud'] ?>">
                                        <button type="submit" class="btn-inscribir-mini" <?= ($s_ins['cupo_actual'] >= $s_ins['cupo_maximo']) ? 'disabled' : '' ?>>
                                            <i class="fa-solid fa-user-plus"></i> Inscribir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- NUEVO: paginación del apartado de inscripción -->
            <?php if ($total_paginas_ins > 1): ?>
            <div class="paginacion">
                <?php if ($pag_ins > 1): ?>
                    <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $pag_ins - 1 ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas_ins; $i++): ?>
                    <?php if ($i === $pag_ins): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pag_ins < $total_paginas_ins): ?>
                    <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $pag_plan ?>&pag_ins=<?= $pag_ins + 1 ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PLANES EN CURSO -->
        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-route"></i> Planes de tutoría en curso</h3>
            <?php if (count($planes_en_curso) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay planes de tutoría en curso actualmente.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>ID Plan</th><th>Objetivo</th><th>N.º sesiones estimadas</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($planes_en_curso as $p): ?>
                    <tr>
                        <td><?= $p['id_plan'] ?></td>
                        <td><?= htmlspecialchars($p['objetivo'] ?: '—') ?></td>
                        <td><?= $p['numero_sesiones_estimadas'] ?></td>
                        <td><span class="badge-estado programada"><?= htmlspecialchars($p['estado']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- NUEVO: paginación planes en curso -->
            <?php if ($total_paginas_plan > 1): ?>
            <div class="paginacion">
                <?php if ($pag_plan > 1): ?>
                    <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $pag_plan - 1 ?>&pag_ins=<?= $pag_ins ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas_plan; $i++): ?>
                    <?php if ($i === $pag_plan): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $i ?>&pag_ins=<?= $pag_ins ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pag_plan < $total_paginas_plan): ?>
                    <a href="?pag_sol=<?= $pag_sol ?>&pag_plan=<?= $pag_plan + 1 ?>&pag_ins=<?= $pag_ins ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <!-- INFO DE LA SOLICITUD -->
        <div class="info-solicitud">
            <h4>Solicitud #<?= $solicitud['id_solicitud'] ?></h4>
            <div class="fila">
                <span><strong>Estudiante:</strong> <?= htmlspecialchars($solicitud['estudiante']) ?></span>
                <span><strong>Email:</strong> <?= htmlspecialchars($solicitud['email']) ?></span>
                <span><strong>Carrera:</strong> <?= htmlspecialchars($solicitud['nombre_carrera']) ?> (Sem. <?= $solicitud['semestre'] ?>)</span>
            </div>
            <div class="fila">
                <span><strong>Área:</strong> <?= htmlspecialchars($solicitud['nombre_area']) ?></span>
                <span><strong>Turno solicitado:</strong> <?= ucfirst($solicitud['turno']) ?></span>
                <span><strong>Motivo:</strong> <?= htmlspecialchars($solicitud['motivo'] ?: '—') ?></span>
            </div>
        </div>

        <!-- BUSCADOR DE TUTORES -->
        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-magnifying-glass"></i> Buscar tutores disponibles</h3>
            <form method="GET" class="filtros">
                <input type="hidden" name="id_solicitud" value="<?= $solicitud['id_solicitud'] ?>">
                <div class="campo">
                    <label>Turno a buscar</label>
                    <select name="turno_filtro">
                        <option value="">-- Selecciona --</option>
                        <option value="mañana" <?= $turno_filtro==='mañana'?'selected':'' ?>>Mañana</option>
                        <option value="tarde" <?= $turno_filtro==='tarde'?'selected':'' ?>>Tarde</option>
                        <option value="noche" <?= $turno_filtro==='noche'?'selected':'' ?>>Noche</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Modalidad a buscar</label>
                    <select name="modalidad_filtro">
                        <option value="">-- Selecciona --</option>
                        <option value="presencial" <?= $modalidad_filtro==='presencial'?'selected':'' ?>>Presencial</option>
                        <option value="virtual" <?= $modalidad_filtro==='virtual'?'selected':'' ?>>Virtual</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primario">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                </button>
            </form>
        </div>

        <!-- FORMULARIO DE ASIGNACIÓN -->
        <div class="bloque bloque-ancho">
            <form method="POST" id="formAsignar">
                <input type="hidden" name="id_solicitud" value="<?= $solicitud['id_solicitud'] ?>">
                <input type="hidden" name="id_area" value="<?= $solicitud['id_area'] ?>">
                <div class="form-grid">

                    <div class="seccion-titulo"><i class="fa-solid fa-route"></i> Plan de tutoría</div>
                    <div class="campo full">
                        <label>Objetivo del plan</label>
                        <textarea name="objetivo" rows="2" placeholder="Ej. Reforzar normalización de bases de datos"></textarea>
                    </div>
                    <div class="campo">
                        <label>N.º de sesiones estimadas</label>
                        <input type="number" name="numero_sesiones_estimadas" min="1" value="1">
                    </div>
                    <div class="campo">
                        <label>Fecha fin estimada del plan</label>
                        <input type="date" name="fecha_fin_estimada">
                    </div>

                    <div class="seccion-titulo"><i class="fa-solid fa-calendar-check"></i> Datos de la sesión</div>
                    <div class="campo">
                        <label>Tutor *</label>
                        <select name="id_tutor" required>
                            <option value="">-- Selecciona un tutor --</option>
                            <?php if ($turno_filtro === '' || $modalidad_filtro === ''): ?>
                                <option value="" disabled>Elige turno y modalidad arriba para buscar tutores</option>
                            <?php elseif (count($tutores) === 0): ?>
                                <option value="" disabled>No hay tutores disponibles con ese turno y modalidad</option>
                            <?php endif; ?>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id_usuario'] ?>">
                                    <?= htmlspecialchars($t['nombre'].' '.$t['apellido']) ?>
                                    (<?= ucfirst($t['nivel_experiencia']) ?>)
                                    — <?= ucfirst($t['dia_semana']) ?> / <?= ucfirst($t['modalidad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Turno *</label>
                        <select name="turno" required>
                            <option value="">-- Selecciona --</option>
                            <option value="mañana" <?= $solicitud['turno']==='mañana'?'selected':'' ?>>Mañana</option>
                            <option value="tarde" <?= $solicitud['turno']==='tarde'?'selected':'' ?>>Tarde</option>
                            <option value="noche" <?= $solicitud['turno']==='noche'?'selected':'' ?>>Noche</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Fecha *</label>
                        <input type="date" name="fecha" required>
                    </div>
                    <div class="campo">
                        <label>Modalidad *</label>
                        <select name="modalidad" id="modalidad" required onchange="toggleModalidad()">
                            <option value="">-- Selecciona --</option>
                            <option value="presencial">Presencial</option>
                            <option value="virtual">Virtual</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Hora inicio *</label>
                        <input type="time" name="hora_inicio" required>
                    </div>
                    <div class="campo">
                        <label>Hora fin *</label>
                        <input type="time" name="hora_fin" required>
                    </div>
                    <div class="campo">
                        <label>Cupo máximo</label>
                        <input type="number" name="cupo_maximo" min="1" value="5">
                    </div>

                    <div id="bloque_presencial" class="modalidad-bloque">
                        <div class="campo">
                            <label>Aula</label>
                            <input type="text" name="aula" placeholder="Ej. Aula 305">
                        </div>
                        <div class="campo">
                            <label>Edificio</label>
                            <input type="text" name="edificio" placeholder="Ej. Módulo B">
                        </div>
                        <div class="campo full">
                            <label>Ubicación / referencia</label>
                            <input type="text" name="ubicacion" placeholder="Ej. Planta baja, junto a biblioteca">
                        </div>
                    </div>

                    <div id="bloque_virtual" class="modalidad-bloque">
                        <div class="campo">
                            <label>Plataforma</label>
                            <input type="text" name="plataforma" placeholder="Ej. Zoom, Google Meet">
                        </div>
                        <div class="campo full">
                            <label>Enlace</label>
                            <input type="text" name="enlace" placeholder="https://...">
                        </div>
                    </div>

                    <div class="campo full acciones-form">
                        <button type="submit" class="btn btn-primario">
                            <i class="fa-solid fa-check"></i> Confirmar asignación
                        </button>
                        <a href="solicitudes.php" class="btn btn-cancelar">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>

    <?php endif; ?>

</div>

<script>
function toggleModalidad() {
    const val = document.getElementById('modalidad').value;
    document.getElementById('bloque_presencial').classList.toggle('activo', val === 'presencial');
    document.getElementById('bloque_virtual').classList.toggle('activo', val === 'virtual');
}
</script>
</body>
</html>