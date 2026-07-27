<?php
// coordinador/notificaciones.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';

// Ejecutar el procedimiento que marca solicitudes urgentes (agrega [URGENTE] al motivo)
if (isset($_GET['marcar_urgentes'])) {
    try {
        $pdo->exec("CALL sp_marcar_solicitudes_urgentes()");
        header('Location: notificaciones.php?msg=marcadas');
        exit;
    } catch (Exception $e) {
        header('Location: notificaciones.php?err=1');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'marcadas') {
    $mensaje = 'Solicitudes urgentes actualizadas correctamente.';
}
$error = isset($_GET['err']) ? 'Ocurrió un error al procesar la solicitud.' : '';

// Solicitudes pendientes con más de 3 días (calculado directamente para mostrar en pantalla)
$solicitudes_urgentes = $pdo->query("
    SELECT sol.id_solicitud, sol.fecha_solicitud, sol.turno, sol.motivo,
        DATEDIFF(NOW(), sol.fecha_solicitud) AS dias_esperando,
        CONCAT(u.nombre,' ',u.apellido) AS estudiante,
        a.nombre_area
    FROM solicitud_tutoria sol
    JOIN estudiante e ON sol.id_estudiante = e.id_estudiante
    JOIN usuario u ON e.id_estudiante = u.id_usuario
    JOIN area_academica a ON sol.id_area = a.id_area
    WHERE sol.estado = 'pendiente' AND DATEDIFF(NOW(), sol.fecha_solicitud) > 3
    ORDER BY dias_esperando DESC
")->fetchAll();

// Sesiones de las proximas 24 horas (recordatorio operativo)
$sesiones_proximas = $pdo->query("
    SELECT st.id_sesion, st.fecha, st.hora_inicio, st.modalidad, st.cupo_actual, st.cupo_maximo,
        CONCAT(u.nombre,' ',u.apellido) AS tutor, a.nombre_area
    FROM sesion_tutoria st
    JOIN tutor t ON st.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    JOIN area_academica a ON st.id_area = a.id_area
    WHERE st.estado IN ('programada','en curso')
      AND TIMESTAMP(st.fecha, st.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ORDER BY st.fecha, st.hora_inicio
")->fetchAll();

// Sesiones sin ningun estudiante inscrito (riesgo de cancelacion)
$sesiones_sin_inscritos = $pdo->query("
    SELECT st.id_sesion, st.fecha, st.hora_inicio, a.nombre_area,
        CONCAT(u.nombre,' ',u.apellido) AS tutor
    FROM sesion_tutoria st
    JOIN tutor t ON st.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    JOIN area_academica a ON st.id_area = a.id_area
    WHERE st.estado = 'programada' AND st.cupo_actual = 0 AND st.fecha >= CURDATE()
    ORDER BY st.fecha
")->fetchAll();

// Areas academicas sin ningun tutor asignado, via sp_areas_sin_tutor
$stmt = $pdo->query("CALL sp_areas_sin_tutor()");
$areas_sin_tutor = $stmt->fetchAll();
$stmt->closeCursor();

$total_alertas = count($solicitudes_urgentes) + count($sesiones_sin_inscritos) + count($areas_sin_tutor);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificaciones - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/notificaciones.css">
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

    <a href="asignar_sesion.php">
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

    <a href="notificaciones.php" class="active">
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
        <h2>Notificaciones y Alertas</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="resumen">
        <span><strong><?= $total_alertas ?></strong> alerta(s) que requieren tu atención.</span>
        <a class="btn btn-accion" href="notificaciones.php?marcar_urgentes=1"
           onclick="return confirm('¿Marcar todas las solicitudes con más de 3 días como [URGENTE]?');">
            <i class="fa-solid fa-triangle-exclamation"></i> Marcar solicitudes urgentes
        </a>
    </div>

    <div class="bloque">
        <h3><span class="icono-alerta">⚠️</span> Solicitudes pendientes hace más de 3 días</h3>
        <table>
            <thead><tr><th>Estudiante</th><th>Área</th><th>Turno</th><th>Días esperando</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
                <?php if (count($solicitudes_urgentes) === 0): ?>
                    <tr><td colspan="6" class="vacio">No hay solicitudes vencidas. Todo al día.</td></tr>
                <?php else: ?>
                    <?php foreach ($solicitudes_urgentes as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['estudiante']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['turno']) ?></td>
                        <td><span class="badge <?= $s['dias_esperando'] > 7 ? 'critico' : 'medio' ?>"><?= $s['dias_esperando'] ?> días</span></td>
                        <td><?= htmlspecialchars($s['motivo'] ?: '—') ?></td>
                        <td><a class="btn btn-accion" href="asignar_sesion.php?id_solicitud=<?= $s['id_solicitud'] ?>">Asignar</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bloque">
        <h3><span class="icono-alerta">🕒</span> Sesiones en las próximas 24 horas</h3>
        <table>
            <thead><tr><th>Fecha</th><th>Hora</th><th>Tutor</th><th>Área</th><th>Modalidad</th><th>Cupo</th></tr></thead>
            <tbody>
                <?php if (count($sesiones_proximas) === 0): ?>
                    <tr><td colspan="6" class="vacio">No hay sesiones en las próximas 24 horas.</td></tr>
                <?php else: ?>
                    <?php foreach ($sesiones_proximas as $s): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                        <td><?= substr($s['hora_inicio'],0,5) ?></td>
                        <td><?= htmlspecialchars($s['tutor']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['modalidad']) ?></td>
                        <td><?= $s['cupo_actual'] ?>/<?= $s['cupo_maximo'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bloque">
        <h3><span class="icono-alerta">🚨</span> Sesiones programadas sin estudiantes inscritos</h3>
        <table>
            <thead><tr><th>Fecha</th><th>Hora</th><th>Tutor</th><th>Área</th><th></th></tr></thead>
            <tbody>
                <?php if (count($sesiones_sin_inscritos) === 0): ?>
                    <tr><td colspan="5" class="vacio">Todas las sesiones programadas tienen al menos un inscrito.</td></tr>
                <?php else: ?>
                    <?php foreach ($sesiones_sin_inscritos as $s): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                        <td><?= substr($s['hora_inicio'],0,5) ?></td>
                        <td><?= htmlspecialchars($s['tutor']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><a class="btn btn-accion" href="sesiones.php?estado=programada">Ver</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bloque">
        <h3><span class="icono-alerta">📚</span> Áreas académicas sin tutor asignado</h3>
        <table>
            <thead><tr><th>Área</th></tr></thead>
            <tbody>
                <?php if (count($areas_sin_tutor) === 0): ?>
                    <tr><td class="vacio">Todas las áreas tienen al menos un tutor.</td></tr>
                <?php else: ?>
                    <?php foreach ($areas_sin_tutor as $a): ?>
                    <tr><td><?= htmlspecialchars($a['area']) ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>