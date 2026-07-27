<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

// ===================== CONSULTAS =====================

// Totales por rol
$roles_count = $pdo->query("
    SELECT rol, COUNT(*) AS total FROM vw_usuarios_por_rol GROUP BY rol
")->fetchAll(PDO::FETCH_KEY_PAIR);

$total_admins = $roles_count['Administrador'] ?? 0;
$total_coords = $roles_count['Coordinador'] ?? 0;
$total_tutores = $roles_count['Tutor'] ?? 0;
$total_estudiantes = $roles_count['Estudiante'] ?? 0;
$total_usuarios = array_sum($roles_count);

// Usuarios activos
$total_activos = $pdo->query("SELECT COUNT(*) FROM vw_usuarios_activos")->fetchColumn();
$total_inactivos = $total_usuarios - $total_activos;

// Sesiones
$sesiones_estado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM sesion_tutoria GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

$sesiones_programadas = $sesiones_estado['programada'] ?? 0;
$sesiones_en_curso = $sesiones_estado['en curso'] ?? 0;
$sesiones_completas = $sesiones_estado['completa'] ?? 0;
$sesiones_finalizadas = $sesiones_estado['finalizada'] ?? 0;
$sesiones_canceladas = $sesiones_estado['cancelada'] ?? 0;

// Solicitudes
$solicitudes_estado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM solicitud_tutoria GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

$solicitudes_pendientes = $solicitudes_estado['pendiente'] ?? 0;
$solicitudes_asignadas = $solicitudes_estado['asignada'] ?? 0;
$solicitudes_rechazadas = $solicitudes_estado['rechazada'] ?? 0;

// Tutorías finalizadas este mes
$tutorias_mes = $pdo->query("
    SELECT COUNT(*) FROM sesion_tutoria
    WHERE estado = 'finalizada'
    AND MONTH(fecha) = MONTH(CURDATE())
    AND YEAR(fecha) = YEAR(CURDATE())
")->fetchColumn();

// Carreras y áreas
$total_carreras = $pdo->query("SELECT COUNT(*) FROM carrera")->fetchColumn();
$total_areas = $pdo->query("SELECT COUNT(*) FROM area_academica")->fetchColumn();
$total_asignaciones = $pdo->query("SELECT COUNT(*) FROM tutor_area")->fetchColumn();

// Top tutores
$top_tutores = $pdo->query("SELECT * FROM vw_tutores_mas_solicitados LIMIT 5")->fetchAll();

// Promedio
$promedio_general = $pdo->query("SELECT AVG(calificacion) FROM evaluacion_sesion")->fetchColumn();
$promedio_general = $promedio_general ? round($promedio_general, 2) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Administrativo</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/dashboard.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" class="active">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="usuarios.php">
        <i class="fa-solid fa-users"></i><span>Usuarios</span>
    </a>

    <a href="carreras.php">
        <i class="fa-solid fa-school"></i><span>Carreras</span>
    </a>

    <a href="areas.php">
        <i class="fa-solid fa-book-open"></i><span>Áreas</span>
    </a>

    <a href="tutores_areas.php">
        <i class="fa-solid fa-user-graduate"></i><span>Tutores-Áreas</span>
    </a>

    <a href="reportes.php">
        <i class="fa-solid fa-chart-line"></i><span>Reportes</span>
    </a>

    <a href="mantenimiento.php">
        <i class="fa-solid fa-screwdriver-wrench"></i><span>Mantenimiento</span>
    </a>

    <a href="perfil.php">
        <i class="fa-solid fa-user"></i><span>Perfil</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Dashboard Administrativo</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=3b82f6&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- CARDS -->
    <div class="cards">

        <div class="card">
            <div class="icon blue"><i class="fa-solid fa-users"></i></div>
            <div class="numero"><?= $total_usuarios ?></div>
            <div class="label">Usuarios Totales</div>
        </div>

        <div class="card">
            <div class="icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="numero"><?= $total_activos ?></div>
            <div class="label">Usuarios Activos</div>
        </div>

        <div class="card">
            <div class="icon red"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="numero"><?= $total_inactivos ?></div>
            <div class="label">Usuarios Inactivos</div>
        </div>

        <div class="card">
            <div class="icon blue"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="numero"><?= $total_tutores ?></div>
            <div class="label">Tutores</div>
        </div>

        <div class="card">
            <div class="icon blue"><i class="fa-solid fa-user"></i></div>
            <div class="numero"><?= $total_estudiantes ?></div>
            <div class="label">Estudiantes</div>
        </div>

        <div class="card">
            <div class="icon blue"><i class="fa-solid fa-school"></i></div>
            <div class="numero"><?= $total_carreras ?></div>
            <div class="label">Carreras</div>
        </div>

        <div class="card">
            <div class="icon orange"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="numero"><?= $tutorias_mes ?></div>
            <div class="label">Tutorías este mes</div>
        </div>

        <div class="card">
            <div class="icon orange"><i class="fa-solid fa-star"></i></div>
            <div class="numero"><?= $promedio_general ?></div>
            <div class="label">Promedio General</div>
        </div>

    </div>

    <!-- GRAFICOS -->
    <div class="grid">

        <div class="box">
            <h3>Estado de Solicitudes</h3>
            <div class="chart-wrapper">
                <canvas id="solicitudesChart"></canvas>
            </div>
        </div>

        <div class="box">
            <h3>Sesiones de Tutoría</h3>
            <div class="chart-wrapper">
                <canvas id="sesionesChart"></canvas>
            </div>
        </div>

    </div>

    <!-- TABLAS -->
    <div class="grid-2">

        <div class="box">
            <h3>Top 5 Tutores más Solicitados</h3>

            <table>
                <thead>
                    <tr>
                        <th>Tutor</th>
                        <th>Sesiones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($top_tutores) === 0): ?>
                        <tr>
                            <td colspan="2">No hay datos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_tutores as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['tutor']) ?></td>
                            <td><strong><?= $t['total_sesiones'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="box">
            <h3>Solicitudes de Tutoría</h3>

            <?php
            $total_sol = max($solicitudes_pendientes + $solicitudes_asignadas + $solicitudes_rechazadas, 1);
            ?>

            <div class="progress">

                <div class="progress-item">
                    <div class="progress-header">
                        <span>Pendientes</span>
                        <span><?= $solicitudes_pendientes ?></span>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill pendiente"
                             style="width:<?= ($solicitudes_pendientes / $total_sol) * 100 ?>%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span>Asignadas</span>
                        <span><?= $solicitudes_asignadas ?></span>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill asignada"
                             style="width:<?= ($solicitudes_asignadas / $total_sol) * 100 ?>%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span>Rechazadas</span>
                        <span><?= $solicitudes_rechazadas ?></span>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill rechazada"
                             style="width:<?= ($solicitudes_rechazadas / $total_sol) * 100 ?>%"></div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

<script>
    // Datos generados por PHP, pasados al JS externo
    const datosDashboard = {
        solicitudes: {
            pendientes: <?= $solicitudes_pendientes ?>,
            asignadas: <?= $solicitudes_asignadas ?>,
            rechazadas: <?= $solicitudes_rechazadas ?>
        },
        sesiones: {
            programadas: <?= $sesiones_programadas ?>,
            enCurso: <?= $sesiones_en_curso ?>,
            completas: <?= $sesiones_completas ?>,
            finalizadas: <?= $sesiones_finalizadas ?>,
            canceladas: <?= $sesiones_canceladas ?>
        }
    };
</script>
<script src="../assets/js/dashboard.js"></script>

</body>
</html>