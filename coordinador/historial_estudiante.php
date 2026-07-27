<?php
// coordinador/historial_estudiante.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = isset($_GET['id_estudiante']) ? (int)$_GET['id_estudiante'] : 0;
$buscar = trim($_GET['buscar'] ?? '');

// Buscador de estudiantes (si no se selecciono uno todavia)
$resultados_busqueda = [];
if ($id_estudiante === 0 && $buscar !== '') {
    $stmt = $pdo->prepare("
        SELECT u.id_usuario AS id_estudiante, u.nombre, u.apellido, u.email,
            est.registro_universitario, c.nombre_carrera, est.semestre
        FROM estudiante est
        JOIN usuario u ON est.id_estudiante = u.id_usuario
        JOIN carrera c ON est.id_carrera = c.id_carrera
        WHERE u.nombre LIKE ? OR u.apellido LIKE ? OR est.registro_universitario LIKE ?
        ORDER BY u.nombre
        LIMIT 20
    ");
    $like = "%$buscar%";
    $stmt->execute([$like, $like, $like]);
    $resultados_busqueda = $stmt->fetchAll();
}

$estudiante = null;
$sesiones = [];
$evaluaciones = [];
$solicitudes = [];
$promedio = null;

if ($id_estudiante > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id_usuario AS id_estudiante, u.nombre, u.apellido, u.email, u.telefono,
            est.registro_universitario, est.semestre, c.nombre_carrera
        FROM estudiante est
        JOIN usuario u ON est.id_estudiante = u.id_usuario
        JOIN carrera c ON est.id_carrera = c.id_carrera
        WHERE est.id_estudiante = ?
    ");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch();

    if ($estudiante) {
        // Historial de sesiones
        $stmt = $pdo->prepare("
            SELECT st.id_sesion, st.fecha, st.hora_inicio, st.turno, st.modalidad, st.estado,
                a.nombre_area, CONCAT(ut.nombre,' ',ut.apellido) AS tutor,
                se.asistencia
            FROM sesion_estudiante se
            JOIN sesion_tutoria st ON se.id_sesion = st.id_sesion
            JOIN area_academica a ON st.id_area = a.id_area
            JOIN tutor t ON st.id_tutor = t.id_tutor
            JOIN usuario ut ON t.id_tutor = ut.id_usuario
            WHERE se.id_estudiante = ?
            ORDER BY st.fecha DESC
        ");
        $stmt->execute([$id_estudiante]);
        $sesiones = $stmt->fetchAll();

        // Evaluaciones
        $stmt = $pdo->prepare("
            SELECT ev.calificacion, ev.observaciones, ev.recomendaciones, ev.fecha_evaluacion,
                a.nombre_area, st.fecha AS fecha_sesion
            FROM evaluacion_sesion ev
            JOIN sesion_tutoria st ON ev.id_sesion = st.id_sesion
            JOIN area_academica a ON st.id_area = a.id_area
            WHERE ev.id_estudiante = ?
            ORDER BY ev.fecha_evaluacion DESC
        ");
        $stmt->execute([$id_estudiante]);
        $evaluaciones = $stmt->fetchAll();

        // Promedio general
        $stmt = $pdo->prepare("SELECT AVG(calificacion) FROM evaluacion_sesion WHERE id_estudiante = ?");
        $stmt->execute([$id_estudiante]);
        $promedio = $stmt->fetchColumn();

        // Solicitudes hechas por el estudiante
        $stmt = $pdo->prepare("
            SELECT sol.id_solicitud, sol.fecha_solicitud, sol.turno, sol.motivo, sol.estado, a.nombre_area
            FROM solicitud_tutoria sol
            JOIN area_academica a ON sol.id_area = a.id_area
            WHERE sol.id_estudiante = ?
            ORDER BY sol.fecha_solicitud DESC
        ");
        $stmt->execute([$id_estudiante]);
        $solicitudes = $stmt->fetchAll();
    }
}

$total_sesiones = count($sesiones);
$sesiones_finalizadas = count(array_filter($sesiones, fn($s) => $s['estado'] === 'finalizada'));
$asistencias = count(array_filter($sesiones, fn($s) => $s['asistencia'] === 'presente'));
$ausencias = count(array_filter($sesiones, fn($s) => $s['asistencia'] === 'ausente'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial de Estudiante - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/historial_estudiante.css">
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

    <a href="historial_estudiante.php" class="active">
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
        <h2>Historial de Estudiante</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($id_estudiante > 0 && $estudiante): ?>

        <a class="volver" href="historial_estudiante.php"><i class="fa-solid fa-arrow-left"></i> Buscar otro estudiante</a>

        <div class="ficha">
            <h3><?= htmlspecialchars($estudiante['nombre'].' '.$estudiante['apellido']) ?></h3>
            <div class="datos">
                <span><strong>Registro:</strong> <?= htmlspecialchars($estudiante['registro_universitario']) ?></span>
                <span><strong>Carrera:</strong> <?= htmlspecialchars($estudiante['nombre_carrera']) ?> (Sem. <?= $estudiante['semestre'] ?>)</span>
                <span><strong>Email:</strong> <?= htmlspecialchars($estudiante['email']) ?></span>
                <?php if ($estudiante['telefono']): ?><span><strong>Tel.:</strong> <?= htmlspecialchars($estudiante['telefono']) ?></span><?php endif; ?>
            </div>
        </div>

        <div class="grid-cards">
            <div class="card"><div class="numero"><?= $total_sesiones ?></div><div class="label">Sesiones totales</div></div>
            <div class="card"><div class="numero"><?= $sesiones_finalizadas ?></div><div class="label">Finalizadas</div></div>
            <div class="card"><div class="numero"><?= $asistencias ?></div><div class="label">Asistencias</div></div>
            <div class="card"><div class="numero"><?= $ausencias ?></div><div class="label">Ausencias</div></div>
            <div class="card"><div class="numero"><?= $promedio !== null ? number_format($promedio, 2) : '—' ?></div><div class="label">Promedio general</div></div>
        </div>

        <div class="bloque">
            <h4>Historial de sesiones</h4>
            <table>
                <thead><tr><th>Fecha</th><th>Área</th><th>Tutor</th><th>Turno</th><th>Modalidad</th><th>Asistencia</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (count($sesiones) === 0): ?>
                        <tr><td colspan="7" class="vacio">Este estudiante no tiene sesiones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sesiones as $s): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= htmlspecialchars($s['tutor']) ?></td>
                            <td><?= ucfirst($s['turno']) ?></td>
                            <td><?= ucfirst($s['modalidad']) ?></td>
                            <td><span class="badge <?= $s['asistencia'] ?>"><?= ucfirst($s['asistencia']) ?></span></td>
                            <td><span class="badge <?= str_replace(' ','_',$s['estado']) ?>"><?= ucfirst($s['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bloque">
            <h4>Evaluaciones</h4>
            <table>
                <thead><tr><th>Fecha sesión</th><th>Área</th><th>Calificación</th><th>Observaciones</th><th>Recomendaciones</th></tr></thead>
                <tbody>
                    <?php if (count($evaluaciones) === 0): ?>
                        <tr><td colspan="5" class="vacio">Sin evaluaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($evaluaciones as $e): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($e['fecha_sesion'])) ?></td>
                            <td><?= htmlspecialchars($e['nombre_area']) ?></td>
                            <td><?= number_format($e['calificacion'], 2) ?></td>
                            <td><?= htmlspecialchars($e['observaciones'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($e['recomendaciones'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bloque">
            <h4>Solicitudes de tutoría</h4>
            <table>
                <thead><tr><th>Fecha</th><th>Área</th><th>Turno</th><th>Motivo</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (count($solicitudes) === 0): ?>
                        <tr><td colspan="5" class="vacio">Sin solicitudes registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($s['fecha_solicitud'])) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= ucfirst($s['turno']) ?></td>
                            <td><?= htmlspecialchars($s['motivo'] ?: '—') ?></td>
                            <td><span class="badge <?= $s['estado'] ?>"><?= ucfirst($s['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <form method="GET" class="buscador">
            <input type="text" name="buscar" placeholder="Buscar por nombre, apellido o registro universitario" value="<?= htmlspecialchars($buscar) ?>">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        </form>

        <?php if ($buscar !== ''): ?>
            <table class="lista">
                <thead><tr><th>Nombre</th><th>Registro</th><th>Carrera</th><th>Semestre</th><th>Email</th><th></th></tr></thead>
                <tbody>
                    <?php if (count($resultados_busqueda) === 0): ?>
                        <tr><td colspan="6" class="vacio">No se encontraron estudiantes.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultados_busqueda as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
                            <td><?= htmlspecialchars($r['registro_universitario']) ?></td>
                            <td><?= htmlspecialchars($r['nombre_carrera']) ?></td>
                            <td><?= $r['semestre'] ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><a class="btn btn-ver" href="historial_estudiante.php?id_estudiante=<?= $r['id_estudiante'] ?>">Ver historial</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>