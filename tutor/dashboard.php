<?php
// tutor/dashboard.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];

// Sesiones de hoy del tutor
$stmt = $pdo->prepare("
    SELECT s.id_sesion, s.hora_inicio, s.hora_fin, s.turno, s.modalidad, s.estado,
        a.nombre_area, s.cupo_actual, s.cupo_maximo
    FROM sesion_tutoria s
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE s.id_tutor = ? AND s.fecha = CURDATE()
    ORDER BY s.hora_inicio
");
$stmt->execute([$id_tutor]);
$sesiones_hoy = $stmt->fetchAll();

// Contador de sesiones por estado (solo de este tutor)
$stmt = $pdo->prepare("SELECT estado, COUNT(*) AS total FROM sesion_tutoria WHERE id_tutor = ? GROUP BY estado");
$stmt->execute([$id_tutor]);
$ses_estado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$ses_programadas = $ses_estado['programada'] ?? 0;
$ses_en_curso   = $ses_estado['en curso'] ?? 0;
$ses_completas  = $ses_estado['completa'] ?? 0;
$ses_finalizadas = $ses_estado['finalizada'] ?? 0;
$ses_canceladas = $ses_estado['cancelada'] ?? 0;

// Áreas que dicta con nivel de experiencia
$stmt = $pdo->prepare("
    SELECT a.nombre_area, ta.nivel_experiencia
    FROM tutor_area ta
    JOIN area_academica a ON ta.id_area = a.id_area
    WHERE ta.id_tutor = ?
    ORDER BY a.nombre_area
");
$stmt->execute([$id_tutor]);
$mis_areas = $stmt->fetchAll();

// Solicitudes pendientes en las áreas que dicta el tutor
$stmt = $pdo->prepare("
    SELECT sol.id_solicitud, sol.fecha_solicitud, sol.turno, sol.motivo,
        CONCAT(u.nombre,' ',u.apellido) AS estudiante, a.nombre_area
    FROM solicitud_tutoria sol
    JOIN estudiante e ON sol.id_estudiante = e.id_estudiante
    JOIN usuario u ON e.id_estudiante = u.id_usuario
    JOIN area_academica a ON sol.id_area = a.id_area
    WHERE sol.estado = 'pendiente'
      AND sol.id_area IN (SELECT id_area FROM tutor_area WHERE id_tutor = ?)
    ORDER BY sol.fecha_solicitud DESC
    LIMIT 5
");
$stmt->execute([$id_tutor]);
$solicitudes_en_mis_areas = $stmt->fetchAll();

// Total de solicitudes pendientes en sus áreas (para la card)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM solicitud_tutoria
    WHERE estado = 'pendiente' AND id_area IN (SELECT id_area FROM tutor_area WHERE id_tutor = ?)
");
$stmt->execute([$id_tutor]);
$total_sol_pendientes = $stmt->fetchColumn();

// Datos del tutor (especialidad / disponibilidad general)
$stmt = $pdo->prepare("
    SELECT t.especialidad_principal, t.grado_academico, t.disponible, t.modalidad_preferida
    FROM tutor t WHERE t.id_tutor = ?
");
$stmt->execute([$id_tutor]);
$datos_tutor = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Tutor</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/tutor_dashboard.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" class="active">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="mis_sesiones.php">
        <i class="fa-solid fa-calendar-check"></i><span>Mis Sesiones</span>
    </a>

    <a href="disponibilidad.php">
        <i class="fa-solid fa-clock"></i><span>Disponibilidad</span>
    </a>

    <a href="mis_areas.php">
        <i class="fa-solid fa-book-open"></i><span>Mis Áreas</span>
    </a>

    <a href="registrar_evaluacion.php">
        <i class="fa-solid fa-star"></i><span>Evaluaciones</span>
    </a>

    <a href="marcar_asistencia.php" >
        <i class="fa-solid fa-clipboard-check"></i><span>Asistencia</span>
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
        <h2>Dashboard - Tutor</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- CARDS -->
    <div class="grid-cards">
        <a class="card-link" href="mis_sesiones.php?estado=programada">
            <div class="card">
                <div class="numero"><?= $ses_programadas ?></div>
                <div class="label">Sesiones programadas</div>
            </div>
        </a>
        <a class="card-link" href="mis_sesiones.php?estado=en curso">
            <div class="card naranja">
                <div class="numero"><?= $ses_en_curso ?></div>
                <div class="label">Sesiones en curso</div>
            </div>
        </a>
        <a class="card-link" href="mis_sesiones.php?estado=finalizada">
            <div class="card verde">
                <div class="numero"><?= $ses_finalizadas ?></div>
                <div class="label">Sesiones finalizadas</div>
            </div>
        </a>
        <div class="card">
            <div class="numero"><?= $ses_completas ?></div>
            <div class="label">Sesiones con cupo completo</div>
        </div>
        <div class="card rojo">
            <div class="numero"><?= $ses_canceladas ?></div>
            <div class="label">Sesiones canceladas</div>
        </div>
        <div class="card">
            <div class="numero"><?= count($sesiones_hoy) ?></div>
            <div class="label">Sesiones hoy</div>
        </div>
        <a class="card-link" href="dashboard.php#solicitudes">
            <div class="card <?= $total_sol_pendientes > 0 ? 'alerta' : '' ?>">
                <div class="numero"><?= $total_sol_pendientes ?></div>
                <div class="label">Solicitudes pendientes en mis áreas</div>
            </div>
        </a>
        <div class="card">
            <div class="numero"><?= count($mis_areas) ?></div>
            <div class="label">Áreas que dicto</div>
        </div>
    </div>

    <div class="secciones">
        <div class="bloque">
            <h3>Mis sesiones de hoy</h3>
            <table>
                <thead><tr><th>Hora</th><th>Área</th><th>Modalidad</th><th>Cupo</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (count($sesiones_hoy) === 0): ?>
                        <tr><td colspan="5" class="vacio">No tienes sesiones programadas para hoy.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sesiones_hoy as $s): ?>
                        <tr>
                            <td><?= substr($s['hora_inicio'],0,5) ?> - <?= substr($s['hora_fin'],0,5) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= ucfirst($s['modalidad']) ?></td>
                            <td><?= $s['cupo_actual'] ?>/<?= $s['cupo_maximo'] ?></td>
                            <td><span class="badge <?= str_replace(' ','_',$s['estado']) ?>"><?= $s['estado'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="btn-link" href="mis_sesiones.php">Ver todas mis sesiones →</a>
        </div>

        <div class="bloque" id="solicitudes">
            <h3>Solicitudes pendientes en mis áreas</h3>
            <table>
                <thead><tr><th>Estudiante</th><th>Área</th><th>Turno</th></tr></thead>
                <tbody>
                    <?php if (count($solicitudes_en_mis_areas) === 0): ?>
                        <tr><td colspan="3" class="vacio">No hay solicitudes pendientes en tus áreas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes_en_mis_areas as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['estudiante']) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= ucfirst($s['turno']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bloque">
            <h3>Mis áreas y nivel de experiencia</h3>
            <table>
                <thead><tr><th>Área</th><th>Nivel</th></tr></thead>
                <tbody>
                    <?php if (count($mis_areas) === 0): ?>
                        <tr><td colspan="2" class="vacio">Aún no tienes áreas asignadas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mis_areas as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                            <td><span class="badge <?= $a['nivel_experiencia'] ?>"><?= ucfirst($a['nivel_experiencia']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="btn-link" href="mis_areas.php">Gestionar mis áreas →</a>
        </div>

        <div class="bloque">
            <h3>Mi perfil</h3>
            <table>
                <tr><td>Especialidad</td><td><?= htmlspecialchars($datos_tutor['especialidad_principal'] ?? '—') ?></td></tr>
                <tr><td>Grado académico</td><td><?= htmlspecialchars($datos_tutor['grado_academico'] ?? '—') ?></td></tr>
                <tr><td>Modalidad preferida</td><td><?= ucfirst($datos_tutor['modalidad_preferida'] ?? '—') ?></td></tr>
                <tr><td>Disponible</td><td><?= $datos_tutor['disponible'] === 'si' ? 'Sí' : 'No' ?></td></tr>
            </table>
            <a class="btn-link" href="perfil.php">Editar mi perfil →</a>
        </div>
    </div>

</div>

</body>
</html>