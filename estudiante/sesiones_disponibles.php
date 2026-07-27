<?php
// estudiante/sesiones_disponibles.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

// Áreas de su carrera para el filtro
$stmt = $pdo->prepare("
    SELECT id_area, nombre_area FROM area_academica
    WHERE id_carrera = (SELECT id_carrera FROM estudiante WHERE id_estudiante = ?)
    ORDER BY nombre_area
");
$stmt->execute([$id_estudiante]);
$areas = $stmt->fetchAll();

$filtro_area = $_GET['id_area'] ?? '';
$filtro_turno = $_GET['turno'] ?? '';

// Inscripción mediante el procedimiento almacenado sp_inscribir_estudiante
// (el SP valida duplicados; el trigger trg_validar_cupo valida el cupo maximo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribir'])) {
    $id_sesion = (int)$_POST['id_sesion'];
    try {
        $stmt = $pdo->prepare("CALL sp_inscribir_estudiante(?, ?)");
        $stmt->execute([$id_sesion, $id_estudiante]);
        $mensaje = 'Te inscribiste correctamente a la sesión.';
    } catch (PDOException $e) {
        $error = 'No se pudo completar la inscripción: la sesión ya está completa o ya estás inscrito.';
    }
}

// Sesiones activas con cupo, cruzando vw_sesiones_activas por id_sesion real
// y usando fn_cupos_disponibles() para el calculo de cupos libres
$sql = "
    SELECT st.id_sesion, v.nombre_area, v.tutor, v.fecha, v.turno, v.modalidad,
           v.cupo_actual, v.cupo_maximo, fn_cupos_disponibles(st.id_sesion) AS cupos_libres
    FROM sesion_tutoria st
    JOIN vw_sesiones_activas v ON v.id_sesion = st.id_sesion
    WHERE st.fecha >= CURDATE()
      AND st.id_sesion NOT IN (
          SELECT id_sesion FROM sesion_estudiante WHERE id_estudiante = ?
      )
";
$params = [$id_estudiante];
if ($filtro_area !== '') {
    $sql .= " AND st.id_area = ?";
    $params[] = $filtro_area;
}
if ($filtro_turno !== '') {
    $sql .= " AND st.turno = ?";
    $params[] = $filtro_turno;
}
$sql .= " ORDER BY v.fecha, v.turno";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesiones = $stmt->fetchAll();

// Stats rápidas
$total_disponibles = count($sesiones);
$total_con_cupo = count(array_filter($sesiones, fn($s) => $s['cupos_libres'] > 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sesiones Disponibles - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_sesiones_disponibles.css">
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

    <a href="sesiones_disponibles.php" class="active">
        <i class="fa-solid fa-calendar-plus"></i><span>Sesiones Disponibles</span>
    </a>

    <a href="mis_sesiones.php">
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
        <h2>Sesiones Disponibles</h2>

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
            <div class="numero"><?= $total_disponibles ?></div>
            <div class="label">Sesiones disponibles</div>
        </div>
        <div class="stat-card verde">
            <div class="numero"><?= $total_con_cupo ?></div>
            <div class="label">Con cupo libre</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="bloque filtros-bloque">
        <form method="GET" class="filtros-form">
            <div class="form-group">
                <label for="id_area"><i class="fa-solid fa-bookmark"></i> Área</label>
                <select name="id_area" id="id_area">
                    <option value="">Todas las áreas</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id_area'] ?>" <?= $filtro_area == $a['id_area'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre_area']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="turno"><i class="fa-solid fa-clock"></i> Turno</label>
                <select name="turno" id="turno">
                    <option value="">Todos</option>
                    <option value="mañana" <?= $filtro_turno === 'mañana' ? 'selected' : '' ?>>Mañana</option>
                    <option value="tarde" <?= $filtro_turno === 'tarde' ? 'selected' : '' ?>>Tarde</option>
                    <option value="noche" <?= $filtro_turno === 'noche' ? 'selected' : '' ?>>Noche</option>
                </select>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
        </form>
    </div>

    <div class="bloque">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Área</th><th>Tutor</th><th>Turno</th><th>Modalidad</th><th>Cupo</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (count($sesiones) === 0): ?>
                        <tr><td colspan="7" class="vacio">No hay sesiones disponibles con estos filtros.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sesiones as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['fecha']) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= htmlspecialchars($s['tutor']) ?></td>
                            <td><?= ucfirst($s['turno']) ?></td>
                            <td><?= ucfirst($s['modalidad']) ?></td>
                            <td>
                                <span class="badge <?= $s['cupos_libres'] > 0 ? 'cupo-ok' : 'cupo-lleno' ?>">
                                    <?= $s['cupo_actual'] ?>/<?= $s['cupo_maximo'] ?>
                                    (<?= $s['cupos_libres'] > 0 ? $s['cupos_libres'] . ' libres' : 'completo' ?>)
                                </span>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="id_sesion" value="<?= $s['id_sesion'] ?>">
                                    <button type="submit" name="inscribir" class="btn-inscribir" <?= $s['cupos_libres'] <= 0 ? 'disabled' : '' ?>>
                                        <i class="fa-solid fa-user-plus"></i> Inscribirme
                                    </button>
                                </form>
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