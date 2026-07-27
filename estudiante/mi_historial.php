<?php
// estudiante/mi_historial.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];

// Promedio general vía función almacenada
$stmt = $pdo->prepare("SELECT fn_promedio_estudiante(?) AS promedio");
$stmt->execute([$id_estudiante]);
$promedio = $stmt->fetchColumn();

// Historial completo de evaluaciones vía vw_evaluaciones_completo, filtrado por nombre+apellido
// (la vista no expone id_estudiante, así que cruzamos por el nombre completo del usuario logueado)
$stmt = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE id_usuario = ?");
$stmt->execute([$id_estudiante]);
$datos_usuario = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT nombre_area, calificacion, fecha_evaluacion
    FROM vw_evaluaciones_completo
    WHERE nombre = ? AND apellido = ?
    ORDER BY fecha_evaluacion DESC
");
$stmt->execute([$datos_usuario['nombre'], $datos_usuario['apellido']]);
$evaluaciones = $stmt->fetchAll();

// Observaciones y recomendaciones detalladas (la vista no las incluye, se consulta aparte)
$stmt = $pdo->prepare("
    SELECT ev.id_sesion, a.nombre_area, ev.calificacion, ev.observaciones, ev.recomendaciones, ev.fecha_evaluacion
    FROM evaluacion_sesion ev
    JOIN sesion_tutoria st ON ev.id_sesion = st.id_sesion
    JOIN area_academica a ON st.id_area = a.id_area
    WHERE ev.id_estudiante = ?
    ORDER BY ev.fecha_evaluacion DESC
");
$stmt->execute([$id_estudiante]);
$evaluaciones_detalle = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Historial - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_historial.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitar_tutoria.php">
        <i class="fa-solid fa-user-graduate"></i><span>Solicitar Tutoría</span>
    </a>

    <a href="mis_solicitudes.php">
        <i class="fa-solid fa-file-lines"></i><span>Mis Solicitudes</span>
    </a>

    <a href="sesiones_disponibles.php">
        <i class="fa-solid fa-calendar-plus"></i><span>Sesiones Disponibles</span>
    </a>

    <a href="mis_sesiones.php">
        <i class="fa-solid fa-calendar-check"></i><span>Mis Sesiones</span>
    </a>

    <a href="mi_historial.php" class="active">
        <i class="fa-solid fa-clock-rotate-left"></i><span>Mi Historial</span>
    </a>

    <a href="material_apoyo.php">
        <i class="fa-solid fa-folder-open"></i><span>Material de Apoyo</span>
    </a>

    <a href="perfil.php">
        <i class="fa-solid fa-user"></i><span>Mi Perfil</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Mi Historial de Tutorías</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card <?= $promedio >= 51 ? 'verde' : 'rojo' ?>">
            <div class="numero"><?= $promedio !== null ? number_format($promedio, 2) : '—' ?></div>
            <div class="label">Promedio general</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($evaluaciones) ?></div>
            <div class="label">Sesiones evaluadas</div>
        </div>
    </div>

    <div class="bloque">
        <h3><i class="fa-solid fa-chart-line"></i> Resumen de calificaciones por sesión</h3>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Área</th><th>Calificación</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php if (count($evaluaciones) === 0): ?>
                        <tr><td colspan="3" class="vacio">Aún no tienes evaluaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($evaluaciones as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['nombre_area']) ?></td>
                            <td>
                                <span class="calif <?= $e['calificacion'] >= 51 ? 'calif-alta' : 'calif-baja' ?>">
                                    <?= number_format($e['calificacion'], 2) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($e['fecha_evaluacion']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bloque">
        <h3><i class="fa-solid fa-comment-dots"></i> Observaciones y recomendaciones del tutor</h3>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Área</th><th>Calificación</th><th>Observaciones</th><th>Recomendaciones</th></tr></thead>
                <tbody>
                    <?php if (count($evaluaciones_detalle) === 0): ?>
                        <tr><td colspan="4" class="vacio">No hay observaciones registradas aún.</td></tr>
                    <?php else: ?>
                        <?php foreach ($evaluaciones_detalle as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['nombre_area']) ?></td>
                            <td>
                                <span class="calif <?= $e['calificacion'] >= 51 ? 'calif-alta' : 'calif-baja' ?>">
                                    <?= number_format($e['calificacion'], 2) ?>
                                </span>
                            </td>
                            <td class="obs"><?= htmlspecialchars($e['observaciones'] ?: '—') ?></td>
                            <td class="rec"><?= htmlspecialchars($e['recomendaciones'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>