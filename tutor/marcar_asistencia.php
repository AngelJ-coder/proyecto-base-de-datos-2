<?php
// tutor/marcar_asistencia.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

$asistencia_valida = ['presente','ausente','pendiente'];

// --- Guardar asistencia ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $id_sesion = (int) ($_POST['id_sesion'] ?? 0);

    // Verificar que la sesión pertenezca al tutor y ya haya iniciado
    $stmt = $pdo->prepare("SELECT estado FROM sesion_tutoria WHERE id_sesion = ? AND id_tutor = ?");
    $stmt->execute([$id_sesion, $id_tutor]);
    $estado_sesion = $stmt->fetchColumn();

    if ($estado_sesion === false) {
        $error = 'Esa sesión no existe o no te pertenece.';
    } elseif (!in_array($estado_sesion, ['en curso', 'completa', 'finalizada'], true)) {
        $error = 'Solo puedes marcar asistencia en sesiones que ya iniciaron o finalizaron.';
    } else {
        $asistencias = $_POST['asistencia'] ?? []; // [id_estudiante => valor]
        $stmt = $pdo->prepare("
            UPDATE sesion_estudiante SET asistencia = ?
            WHERE id_sesion = ? AND id_estudiante = ?
        ");
        foreach ($asistencias as $id_estudiante => $valor) {
            if (in_array($valor, $asistencia_valida, true)) {
                $stmt->execute([$valor, $id_sesion, (int) $id_estudiante]);
            }
        }
        $mensaje = 'Asistencia guardada correctamente.';
    }
}

// Sesión seleccionada
$id_sesion_sel = isset($_GET['id_sesion']) ? (int) $_GET['id_sesion'] : (isset($_POST['id_sesion']) ? (int) $_POST['id_sesion'] : null);

// Sesiones del tutor que ya iniciaron o finalizaron (única forma de que tenga sentido marcar asistencia)
$stmt = $pdo->prepare("
    SELECT s.id_sesion, s.fecha, s.turno, s.estado, a.nombre_area
    FROM sesion_tutoria s
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE s.id_tutor = ? AND s.estado IN ('en curso','completa','finalizada')
    ORDER BY s.fecha DESC
");
$stmt->execute([$id_tutor]);
$sesiones_disponibles = $stmt->fetchAll();

$sesion_info = null;
$estudiantes = [];
if ($id_sesion_sel) {
    $stmt = $pdo->prepare("
        SELECT s.id_sesion, s.fecha, s.estado, a.nombre_area
        FROM sesion_tutoria s
        JOIN area_academica a ON s.id_area = a.id_area
        WHERE s.id_sesion = ? AND s.id_tutor = ?
    ");
    $stmt->execute([$id_sesion_sel, $id_tutor]);
    $sesion_info = $stmt->fetch();

    if ($sesion_info) {
        $stmt = $pdo->prepare("
            SELECT e.id_estudiante, u.nombre, u.apellido, e.registro_universitario, se.asistencia
            FROM sesion_estudiante se
            JOIN estudiante e ON se.id_estudiante = e.id_estudiante
            JOIN usuario u ON e.id_estudiante = u.id_usuario
            WHERE se.id_sesion = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$id_sesion_sel]);
        $estudiantes = $stmt->fetchAll();
    }
}

// Conteo rápido de asistencia para stats (solo si hay sesión seleccionada)
$total_presentes = count(array_filter($estudiantes, fn($e) => $e['asistencia'] === 'presente'));
$total_ausentes  = count(array_filter($estudiantes, fn($e) => $e['asistencia'] === 'ausente'));
$total_pendientes = count(array_filter($estudiantes, fn($e) => $e['asistencia'] === 'pendiente' || $e['asistencia'] === null));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marcar Asistencia - Tutor</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/tutor_asistencia.css">
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
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

    <a href="marcar_asistencia.php" class="active">
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
        <h2>Marcar Asistencia</h2>

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

    <!-- SELECTOR DE SESIÓN -->
    <div class="bloque">
        <h3><i class="fa-solid fa-list-check"></i> Selecciona una sesión</h3>
        <?php if (count($sesiones_disponibles) === 0): ?>
            <p class="vacio">
                <i class="fa-solid fa-inbox"></i> No tienes sesiones en curso, completas o finalizadas para marcar asistencia.
            </p>
        <?php else: ?>
            <form method="get" class="selector-sesion">
                <div class="form-group full">
                    <label for="id_sesion"><i class="fa-solid fa-calendar-day"></i> Sesión</label>
                    <select name="id_sesion" id="id_sesion" required>
                        <option value="">Selecciona una sesión...</option>
                        <?php foreach ($sesiones_disponibles as $s): ?>
                            <option value="<?= $s['id_sesion'] ?>" <?= $id_sesion_sel == $s['id_sesion'] ? 'selected' : '' ?>>
                                <?= $s['fecha'] ?> — <?= htmlspecialchars($s['nombre_area']) ?> (<?= ucfirst($s['turno']) ?>) — <?= ucfirst($s['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Ver estudiantes
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($id_sesion_sel): ?>

        <?php if (!$sesion_info): ?>
            <div class="bloque">
                <p class="vacio"><i class="fa-solid fa-triangle-exclamation"></i> Esa sesión no existe o no te pertenece.</p>
            </div>
        <?php else: ?>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="numero"><?= count($estudiantes) ?></div>
                    <div class="label">Estudiantes inscritos</div>
                </div>
                <div class="stat-card verde">
                    <div class="numero"><?= $total_presentes ?></div>
                    <div class="label">Presentes</div>
                </div>
                <div class="stat-card rojo">
                    <div class="numero"><?= $total_ausentes ?></div>
                    <div class="label">Ausentes</div>
                </div>
                <div class="stat-card naranja">
                    <div class="numero"><?= $total_pendientes ?></div>
                    <div class="label">Pendientes</div>
                </div>
            </div>

            <div class="bloque">
                <h3>
                    <i class="fa-solid fa-users"></i>
                    <?= htmlspecialchars($sesion_info['nombre_area']) ?> — <?= $sesion_info['fecha'] ?>
                    <span class="badge <?= str_replace(' ','_',$sesion_info['estado']) ?>"><?= ucfirst($sesion_info['estado']) ?></span>
                </h3>

                <?php if (count($estudiantes) === 0): ?>
                    <p class="vacio"><i class="fa-solid fa-user-slash"></i> No hay estudiantes inscritos en esta sesión.</p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id_sesion" value="<?= $id_sesion_sel ?>">
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Registro</th>
                                        <th>Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $e): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?></td>
                                        <td><?= htmlspecialchars($e['registro_universitario']) ?></td>
                                        <td>
                                            <div class="radios">
                                                <?php foreach ($asistencia_valida as $opt): ?>
                                                    <label class="radio-pill <?= $opt ?>">
                                                        <input type="radio" name="asistencia[<?= $e['id_estudiante'] ?>]" value="<?= $opt ?>" <?= $e['asistencia'] === $opt ? 'checked' : '' ?>>
                                                        <?= ucfirst($opt) ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar asistencia
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    <?php endif; ?>

</div>

</body>
</html>