<?php
// coordinador/dashboard.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

// Solicitudes por estado
$sol_estado = $pdo->query("SELECT estado, COUNT(*) AS total FROM solicitud_tutoria GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$sol_pendientes = $sol_estado['pendiente'] ?? 0;
$sol_asignadas = $sol_estado['asignada'] ?? 0;
$sol_rechazadas = $sol_estado['rechazada'] ?? 0;

// Solicitudes urgentes (pendientes con mas de 3 dias esperando)
$sol_urgentes = $pdo->query("
    SELECT COUNT(*) FROM solicitud_tutoria
    WHERE estado = 'pendiente' AND DATEDIFF(NOW(), fecha_solicitud) > 3
")->fetchColumn();

// Sesiones por estado
$ses_estado = $pdo->query("SELECT estado, COUNT(*) AS total FROM sesion_tutoria GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$ses_programadas = $ses_estado['programada'] ?? 0;
$ses_en_curso = $ses_estado['en curso'] ?? 0;
$ses_completas = $ses_estado['completa'] ?? 0;
$ses_finalizadas = $ses_estado['finalizada'] ?? 0;
$ses_canceladas = $ses_estado['cancelada'] ?? 0;

// Planes activos
$planes_en_curso = $pdo->query("SELECT COUNT(*) FROM plan_tutoria WHERE estado='en curso'")->fetchColumn();
$planes_finalizados = $pdo->query("SELECT COUNT(*) FROM plan_tutoria WHERE estado='finalizado'")->fetchColumn();

// Sesiones de hoy
$sesiones_hoy = $pdo->query("
    SELECT s.id_sesion, s.hora_inicio, s.hora_fin, s.turno, s.modalidad, s.estado,
        CONCAT(u.nombre,' ',u.apellido) AS tutor, a.nombre_area
    FROM sesion_tutoria s
    JOIN tutor t ON s.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE s.fecha = CURDATE()
    ORDER BY s.hora_inicio
")->fetchAll();

// Tutores disponibles
$total_tutores = $pdo->query("SELECT COUNT(*) FROM tutor WHERE disponible='si'")->fetchColumn();

// Top 5 tutores mas solicitados
$top_tutores = $pdo->query("SELECT * FROM vw_tutores_mas_solicitados LIMIT 5")->fetchAll();

// Solicitudes pendientes recientes
$solicitudes_recientes = $pdo->query("
    SELECT sol.id_solicitud, sol.fecha_solicitud, sol.turno, sol.motivo,
        CONCAT(u.nombre,' ',u.apellido) AS estudiante, a.nombre_area
    FROM solicitud_tutoria sol
    JOIN estudiante e ON sol.id_estudiante = e.id_estudiante
    JOIN usuario u ON e.id_estudiante = u.id_usuario
    JOIN area_academica a ON sol.id_area = a.id_area
    WHERE sol.estado = 'pendiente'
    ORDER BY sol.fecha_solicitud DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/coordinador.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" class="active">
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
        <h2>Dashboard</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- TARJETAS ESTADÍSTICAS -->
    <div class="stats-grid">

        <a class="stat-card-link" href="notificaciones.php">
            <div class="stat-card <?= $sol_urgentes > 0 ? 'alerta' : '' ?>">
                <div class="stat-icono rojo"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="stat-numero"><?= $sol_urgentes ?></div>
                    <div class="stat-label">Solicitudes urgentes (&gt;3 días)</div>
                </div>
            </div>
        </a>

        <div class="stat-card">
            <div class="stat-icono naranja"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <div class="stat-numero"><?= $sol_pendientes ?></div>
                <div class="stat-label">Solicitudes pendientes</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono verde"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <div class="stat-numero"><?= $sol_asignadas ?></div>
                <div class="stat-label">Solicitudes asignadas</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono rojo"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <div class="stat-numero"><?= $sol_rechazadas ?></div>
                <div class="stat-label">Solicitudes rechazadas</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono azul"><i class="fa-solid fa-route"></i></div>
            <div>
                <div class="stat-numero"><?= $planes_en_curso ?></div>
                <div class="stat-label">Planes en curso</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono morado"><i class="fa-solid fa-flag-checkered"></i></div>
            <div>
                <div class="stat-numero"><?= $planes_finalizados ?></div>
                <div class="stat-label">Planes finalizados</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono azul"><i class="fa-solid fa-calendar-day"></i></div>
            <div>
                <div class="stat-numero"><?= count($sesiones_hoy) ?></div>
                <div class="stat-label">Sesiones hoy</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icono verde"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="stat-numero"><?= $total_tutores ?></div>
                <div class="stat-label">Tutores disponibles</div>
            </div>
        </div>

    </div>

    <!-- GRID DE BLOQUES -->
    <div class="secciones">

        <div class="bloque">
            <h3><i class="fa-solid fa-calendar-day"></i> Sesiones de hoy</h3>
            <?php if (count($sesiones_hoy) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay sesiones programadas para hoy.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Hora</th><th>Tutor</th><th>Área</th><th>Modalidad</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($sesiones_hoy as $s): ?>
                    <tr>
                        <td><?= substr($s['hora_inicio'],0,5) ?> - <?= substr($s['hora_fin'],0,5) ?></td>
                        <td><?= htmlspecialchars($s['tutor']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['modalidad']) ?></td>
                        <td><span class="badge-estado <?= str_replace(' ','_',$s['estado']) ?>"><?= htmlspecialchars(ucfirst($s['estado'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3><i class="fa-solid fa-inbox"></i> Solicitudes pendientes recientes</h3>
            <?php if (count($solicitudes_recientes) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> No hay solicitudes pendientes.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Estudiante</th><th>Área</th><th>Turno</th></tr></thead>
                <tbody>
                    <?php foreach ($solicitudes_recientes as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['estudiante']) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['turno']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <a class="link-ver-mas" href="solicitudes.php">Ver todas las solicitudes <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="bloque">
            <h3><i class="fa-solid fa-chart-pie"></i> Sesiones por estado</h3>
            <div class="lista-mini">
                <div class="fila-mini"><span>Programadas</span><span class="contador"><?= $ses_programadas ?></span></div>
                <div class="fila-mini"><span>En curso</span><span class="contador"><?= $ses_en_curso ?></span></div>
                <div class="fila-mini"><span>Completas</span><span class="contador"><?= $ses_completas ?></span></div>
                <div class="fila-mini"><span>Finalizadas</span><span class="contador"><?= $ses_finalizadas ?></span></div>
                <div class="fila-mini"><span>Canceladas</span><span class="contador"><?= $ses_canceladas ?></span></div>
            </div>
        </div>

        <div class="bloque">
            <h3><i class="fa-solid fa-ranking-star"></i> Top 5 Tutores más solicitados</h3>
            <?php if (count($top_tutores) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> Aún no hay sesiones registradas.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Tutor</th><th>Sesiones</th></tr></thead>
                <tbody>
                    <?php foreach ($top_tutores as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['tutor']) ?></td>
                        <td><span class="contador"><?= $t['total_sesiones'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

</div>
</body>
</html>