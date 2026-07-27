<?php
// tutor/disponibilidad.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

$dias_validos = ['lunes','martes','miercoles','jueves','viernes','sabado'];
$turnos_validos = ['mañana','tarde','noche'];
$modalidades_validas = ['presencial','virtual'];

// --- Agregar disponibilidad ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $dia = $_POST['dia_semana'] ?? '';
    $turno = $_POST['turno'] ?? '';
    $modalidad = $_POST['modalidad'] ?? '';

    if (!in_array($dia, $dias_validos, true) || !in_array($turno, $turnos_validos, true) || !in_array($modalidad, $modalidades_validas, true)) {
        $error = 'Selecciona un día, turno y modalidad válidos.';
    } else {
        try {
            $stmt = $pdo->prepare("CALL sp_registrar_disponibilidad(?, ?, ?, ?)");
            $stmt->execute([$id_tutor, $dia, $turno, $modalidad]);
            $mensaje = 'Disponibilidad agregada correctamente.';
        } catch (PDOException $e) {
            if ($e->getCode() == 45000 || str_contains($e->getMessage(), 'ya registrada')) {
                $error = 'Ya tienes registrada esa combinación de día, turno y modalidad.';
            } else {
                $error = 'No se pudo registrar la disponibilidad.';
            }
        }
    }
}

// --- Eliminar disponibilidad ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id_disponibilidad = (int) ($_POST['id_disponibilidad'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM disponibilidad_tutor WHERE id_disponibilidad = ? AND id_tutor = ?");
    $stmt->execute([$id_disponibilidad, $id_tutor]);
    $mensaje = 'Disponibilidad eliminada.';
}

// --- Activar/inactivar disponibilidad ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_estado') {
    $id_disponibilidad = (int) ($_POST['id_disponibilidad'] ?? 0);
    $stmt = $pdo->prepare("SELECT estado FROM disponibilidad_tutor WHERE id_disponibilidad = ? AND id_tutor = ?");
    $stmt->execute([$id_disponibilidad, $id_tutor]);
    $actual = $stmt->fetchColumn();
    if ($actual !== false) {
        $nuevo = $actual === 'activo' ? 'inactivo' : 'activo';
        $stmt = $pdo->prepare("UPDATE disponibilidad_tutor SET estado = ? WHERE id_disponibilidad = ? AND id_tutor = ?");
        $stmt->execute([$nuevo, $id_disponibilidad, $id_tutor]);
        $mensaje = 'Estado actualizado.';
    }
}

// Listar disponibilidad actual del tutor
$stmt = $pdo->prepare("
    SELECT id_disponibilidad, dia_semana, turno, modalidad, estado
    FROM disponibilidad_tutor
    WHERE id_tutor = ?
    ORDER BY FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado'),
             FIELD(turno,'mañana','tarde','noche')
");
$stmt->execute([$id_tutor]);
$mi_disponibilidad = $stmt->fetchAll();

// Total de franjas activas
$stmt = $pdo->prepare("SELECT fn_total_disponibilidades(?) AS total");
$stmt->execute([$id_tutor]);
$total_disponibilidades_activas = $stmt->fetchColumn();

// Disponibilidad agrupada por día (solo activas)
$stmt = $pdo->prepare("
    SELECT dia_semana, COUNT(*) AS total_franjas
    FROM disponibilidad_tutor
    WHERE estado = 'activo' AND id_tutor = ?
    GROUP BY dia_semana
    ORDER BY FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado')
");
$stmt->execute([$id_tutor]);
$resumen_por_dia = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Disponibilidad - Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor_disponibilidad.css">
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

    <a href="disponibilidad.php" class="active">
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
        <h2>Mi Disponibilidad</h2>
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

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="numero"><?= $total_disponibilidades_activas ?></div>
            <div class="label">Franjas Activas</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($mi_disponibilidad) ?></div>
            <div class="label">Total Registradas</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($resumen_por_dia) ?></div>
            <div class="label">Días con disponibilidad</div>
        </div>
    </div>

    <!-- SECCIONES -->
    <div class="secciones">

        <!-- AGREGAR DISPONIBILIDAD -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>Agregar Nueva Disponibilidad</h3>
            <form method="post" class="form-agregar">
                <input type="hidden" name="accion" value="agregar">
                <div class="form-group">
                    <label for="dia_semana">Día de la Semana</label>
                    <select name="dia_semana" id="dia_semana" required>
                        <option value="">Seleccionar día</option>
                        <?php foreach ($dias_validos as $d): ?>
                            <option value="<?= $d ?>"><?= ucfirst($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="turno">Turno</label>
                    <select name="turno" id="turno" required>
                        <option value="">Seleccionar turno</option>
                        <?php foreach ($turnos_validos as $t): ?>
                            <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalidad">Modalidad</label>
                    <select name="modalidad" id="modalidad" required>
                        <option value="">Seleccionar modalidad</option>
                        <?php foreach ($modalidades_validas as $m): ?>
                            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit">
                        <i class="fa-solid fa-plus"></i> Agregar
                    </button>
                </div>
            </form>
        </div>

        <!-- RESUMEN POR DÍA -->
        <div class="bloque">
            <h3>Resumen por Día</h3>
            <table>
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Franjas Activas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($resumen_por_dia) === 0): ?>
                        <tr>
                            <td colspan="2" class="vacio">
                                <i class="fa-solid fa-calendar-days"></i> No tienes franjas activas
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resumen_por_dia as $r): ?>
                        <tr>
                            <td><?= ucfirst($r['dia_semana']) ?></td>
                            <td>
                                <span class="badge basico"><?= $r['total_franjas'] ?> franja<?= $r['total_franjas'] != 1 ? 's' : '' ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- DISPONIBILIDAD SEMANAL COMPLETA -->
        <div class="bloque">
            <h3>Mi Disponibilidad Semanal</h3>
            <table>
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Turno</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($mi_disponibilidad) === 0): ?>
                        <tr>
                            <td colspan="5" class="vacio">
                                <i class="fa-solid fa-inbox"></i> Aún no has registrado disponibilidad
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mi_disponibilidad as $d): ?>
                        <tr>
                            <td><strong><?= ucfirst($d['dia_semana']) ?></strong></td>
                            <td><?= ucfirst($d['turno']) ?></td>
                            <td>
                                <span class="badge">
                                    <i class="fa-solid fa-<?= $d['modalidad'] === 'virtual' ? 'video' : 'location-dot' ?>"></i>
                                    <?= ucfirst($d['modalidad']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $d['estado'] === 'activo' ? 'finalizada' : 'cancelada' ?>">
                                    <?= ucfirst($d['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="td-actions">
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="accion" value="toggle_estado">
                                        <input type="hidden" name="id_disponibilidad" value="<?= $d['id_disponibilidad'] ?>">
                                        <button type="submit" class="btn-mini toggle">
                                            <i class="fa-solid fa-<?= $d['estado'] === 'activo' ? 'pause' : 'play' ?>"></i>
                                            <?= $d['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('¿Eliminar esta disponibilidad?');" style="margin: 0;">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_disponibilidad" value="<?= $d['id_disponibilidad'] ?>">
                                        <button type="submit" class="btn-mini eliminar">
                                            <i class="fa-solid fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
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