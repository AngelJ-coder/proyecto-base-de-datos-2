<?php
// estudiante/mis_sesiones.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';
$filtro_estado = $_GET['estado'] ?? '';

// Cancelar inscripción (solo si la sesión aún no ha ocurrido / no está finalizada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $id_sesion = (int)$_POST['id_sesion'];
    $stmt = $pdo->prepare("SELECT estado, fecha FROM sesion_tutoria WHERE id_sesion = ?");
    $stmt->execute([$id_sesion]);
    $ses = $stmt->fetch();

    if ($ses && !in_array($ses['estado'], ['finalizada', 'cancelada']) && $ses['fecha'] >= date('Y-m-d')) {
        $stmt = $pdo->prepare("DELETE FROM sesion_estudiante WHERE id_sesion = ? AND id_estudiante = ?");
        $stmt->execute([$id_sesion, $id_estudiante]);
        // trg_decrementar_cupo libera el cupo automáticamente
        $mensaje = 'Cancelaste tu inscripción a la sesión.';
    } else {
        $error = 'No puedes cancelar una sesión ya finalizada o en curso.';
    }
}

// Detalle de sesiones del estudiante (con datos de modalidad)
$sql = "
    SELECT s.id_sesion, s.fecha, s.hora_inicio, s.hora_fin, s.turno, s.modalidad, s.estado,
        a.nombre_area, CONCAT(u.nombre,' ',u.apellido) AS tutor, se.asistencia,
        d.aula, d.edificio, d.plataforma, d.enlace
    FROM sesion_estudiante se
    JOIN sesion_tutoria s ON se.id_sesion = s.id_sesion
    JOIN area_academica a ON s.id_area = a.id_area
    JOIN tutor t ON s.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    LEFT JOIN detalle_modalidad_sesion d ON s.id_sesion = d.id_sesion
    WHERE se.id_estudiante = ?
";
$params = [$id_estudiante];
if ($filtro_estado !== '') {
    $sql .= " AND s.estado = ?";
    $params[] = $filtro_estado;
}
$sql .= " ORDER BY s.fecha DESC, s.hora_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesiones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Sesiones - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_mis_sesiones.css">
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

    <a href="mis_sesiones.php" class="active">
        <i class="fa-solid fa-calendar-check"></i><span>Mis Sesiones</span>
    </a>

    <a href="mi_historial.php">
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
        <h2>Mis Sesiones</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($mensaje): ?>
        <div class="alerta-msg ok">
            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta-msg error">
            <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="filtros">
        <a href="mis_sesiones.php" class="<?= $filtro_estado === '' ? 'activo' : '' ?>">Todas</a>
        <a href="mis_sesiones.php?estado=programada" class="<?= $filtro_estado === 'programada' ? 'activo' : '' ?>">Programadas</a>
        <a href="mis_sesiones.php?estado=en curso" class="<?= $filtro_estado === 'en curso' ? 'activo' : '' ?>">En curso</a>
        <a href="mis_sesiones.php?estado=finalizada" class="<?= $filtro_estado === 'finalizada' ? 'activo' : '' ?>">Finalizadas</a>
        <a href="mis_sesiones.php?estado=cancelada" class="<?= $filtro_estado === 'cancelada' ? 'activo' : '' ?>">Canceladas</a>
    </div>

    <div class="bloque">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Área</th><th>Tutor</th><th>Modalidad</th><th>Estado</th><th>Asistencia</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (count($sesiones) === 0): ?>
                        <tr><td colspan="7" class="vacio">No tienes sesiones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sesiones as $s): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($s['fecha']) ?><br>
                                <small class="hora-cell"><?= substr($s['hora_inicio'],0,5) ?> - <?= substr($s['hora_fin'],0,5) ?></small>
                            </td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= htmlspecialchars($s['tutor']) ?></td>
                            <td>
                                <?= ucfirst($s['modalidad']) ?><br>
                                <span class="detalle-modalidad">
                                    <?php if ($s['modalidad'] === 'virtual' && $s['enlace']): ?>
                                        <a href="<?= htmlspecialchars($s['enlace']) ?>" target="_blank"><?= htmlspecialchars($s['plataforma'] ?? 'Enlace') ?></a>
                                    <?php elseif ($s['modalidad'] === 'presencial' && $s['aula']): ?>
                                        Aula <?= htmlspecialchars($s['aula']) ?> - <?= htmlspecialchars($s['edificio']) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><span class="badge <?= str_replace(' ','_',$s['estado']) ?>"><?= ucfirst($s['estado']) ?></span></td>
                            <td><span class="asist asist-<?= $s['asistencia'] ?>"><?= ucfirst($s['asistencia']) ?></span></td>
                            <td>
                                <?php if (in_array($s['estado'], ['programada']) && $s['fecha'] >= date('Y-m-d')): ?>
                                <form method="POST" onsubmit="return confirm('¿Cancelar tu inscripción a esta sesión?');">
                                    <input type="hidden" name="id_sesion" value="<?= $s['id_sesion'] ?>">
                                    <button type="submit" name="cancelar" class="btn-cancelar">
                                        <i class="fa-solid fa-xmark"></i> Cancelar
                                    </button>
                                </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
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