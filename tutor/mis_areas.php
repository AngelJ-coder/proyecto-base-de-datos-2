<?php
// tutor/mis_areas.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

$niveles_validos = ['basico','intermedio','avanzado'];

// --- Asignarse a una nueva área ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar') {
    $id_area = (int) ($_POST['id_area'] ?? 0);
    $nivel = $_POST['nivel_experiencia'] ?? 'basico';

    if (!in_array($nivel, $niveles_validos, true)) {
        $nivel = 'basico';
    }

    if ($id_area <= 0) {
        $error = 'Selecciona un área válida.';
    } else {
        $stmt = $pdo->prepare("SELECT fn_tutor_dicta_area(?, ?) AS ya_dicta");
        $stmt->execute([$id_tutor, $id_area]);
        $ya_dicta = (bool) $stmt->fetchColumn();

        if ($ya_dicta) {
            $error = 'Ya estás asignado a esa área.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tutor_area (id_tutor, id_area, nivel_experiencia) VALUES (?, ?, ?)");
            $stmt->execute([$id_tutor, $id_area, $nivel]);
            $mensaje = 'Área agregada a tu perfil correctamente.';
        }
    }
}

// --- Actualizar nivel de experiencia declarado ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_nivel') {
    $id_area = (int) ($_POST['id_area'] ?? 0);
    $nivel = $_POST['nivel_experiencia'] ?? 'basico';
    if (in_array($nivel, $niveles_validos, true)) {
        $stmt = $pdo->prepare("UPDATE tutor_area SET nivel_experiencia = ? WHERE id_tutor = ? AND id_area = ?");
        $stmt->execute([$nivel, $id_tutor, $id_area]);
        $mensaje = 'Nivel de experiencia actualizado.';
    }
}

// --- Quitar un área ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'quitar') {
    $id_area = (int) ($_POST['id_area'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM tutor_area WHERE id_tutor = ? AND id_area = ?");
    $stmt->execute([$id_tutor, $id_area]);
    $mensaje = 'Área removida de tu perfil.';
}

// Áreas actuales del tutor
$stmt = $pdo->prepare("SELECT id_area FROM tutor_area WHERE id_tutor = ?");
$stmt->execute([$id_tutor]);
$ids_area_tutor = $stmt->fetchAll(PDO::FETCH_COLUMN);

$mis_areas = [];
if ($ids_area_tutor) {
    $stmt_u = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE id_usuario = ?");
    $stmt_u->execute([$id_tutor]);
    $datos_u = $stmt_u->fetch();

    $stmt_v = $pdo->prepare("
        SELECT nombre_area, nivel_experiencia
        FROM vw_tutores_completo
        WHERE nombre = ? AND apellido = ? AND nombre_area = ?
        LIMIT 1
    ");
    $stmt_area = $pdo->prepare("
        SELECT
            a.id_area,
            a.nombre_area,
            a.descripcion,
            c.nombre_carrera
        FROM area_academica a
        JOIN carrera c ON a.id_carrera = c.id_carrera
        WHERE a.id_area = ?
    ");

    foreach ($ids_area_tutor as $id_area) {
        $stmt_area->execute([$id_area]);
        $info_area = $stmt_area->fetch();
        if (!$info_area) continue;

        $stmt_v->execute([$datos_u['nombre'], $datos_u['apellido'], $info_area['nombre_area']]);
        $fila_vista = $stmt_v->fetch();

        $mis_areas[] = [
            'id_area' => $info_area['id_area'],
            'nombre_area' => $info_area['nombre_area'],
            'descripcion' => $info_area['descripcion'],
            'nombre_carrera' => $info_area['nombre_carrera'],
            'nivel_experiencia' => $fila_vista['nivel_experiencia'] ?? null,
        ];
    }
    usort($mis_areas, fn($a, $b) => strcmp($a['nombre_area'], $b['nombre_area']));
}

// Áreas disponibles que el tutor no dicta
$stmt = $pdo->prepare("
    SELECT a.id_area, a.nombre_area, c.nombre_carrera
    FROM area_academica a
    JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE a.id_area NOT IN (SELECT id_area FROM tutor_area WHERE id_tutor = ?)
    ORDER BY c.nombre_carrera, a.nombre_area
");
$stmt->execute([$id_tutor]);
$areas_disponibles = $stmt->fetchAll();

// Total de áreas que dicta el tutor
$stmt = $pdo->prepare("
    SELECT COUNT(ta.id_area) AS total_areas
    FROM tutor t
    LEFT JOIN tutor_area ta ON t.id_tutor = ta.id_tutor
    WHERE t.id_tutor = ?
    GROUP BY t.id_tutor
");
$stmt->execute([$id_tutor]);
$total_areas_dicta = $stmt->fetchColumn();
if ($total_areas_dicta === false) $total_areas_dicta = 0;

// Especialidad principal y modalidad preferida
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, t.especialidad_principal, t.modalidad_preferida
    FROM tutor t
    JOIN usuario u ON t.id_tutor = u.id_usuario
    WHERE t.id_tutor = ?
");
$stmt->execute([$id_tutor]);
$perfil_tutor = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Áreas - Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor_areas.css">
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" >
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="mis_sesiones.php">
        <i class="fa-solid fa-calendar-check"></i><span>Mis Sesiones</span>
    </a>

    <a href="disponibilidad.php">
        <i class="fa-solid fa-clock"></i><span>Disponibilidad</span>
    </a>

    <a href="mis_areas.php" class="active">
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
        <h2>Mis Áreas</h2>
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
            <div class="numero"><?= $total_areas_dicta ?></div>
            <div class="label">Áreas que dicto</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($areas_disponibles) ?></div>
            <div class="label">Áreas disponibles</div>
        </div>
    </div>

    <!-- SECCIONES -->
    <div class="secciones">

        <!-- PERFIL ESPECIALIDAD -->
        <div class="bloque">
            <h3>Mi Perfil de Especialidad</h3>
            <div class="profile-info">
                <div class="info-item">
                    <span class="info-label">
                        <i class="fa-solid fa-graduation-cap"></i> Especialidad Principal
                    </span>
                    <span class="info-value"><?= htmlspecialchars($perfil_tutor['especialidad_principal'] ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">
                        <i class="fa-solid fa-chalkboard-user"></i> Modalidad Preferida
                    </span>
                    <span class="info-value"><?= ucfirst($perfil_tutor['modalidad_preferida'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- AGREGAR ÁREA -->
        <div class="bloque">
            <h3>Agregar Nuevo Área</h3>
            <?php if (count($areas_disponibles) === 0): ?>
                <p class="vacio">
                    <i class="fa-solid fa-check"></i> Ya estás asignado a todas las áreas del catálogo.
                </p>
            <?php else: ?>
                <form method="post" class="form-agregar">
                    <input type="hidden" name="accion" value="asignar">
                    <div class="form-group">
                        <label for="id_area">
                            <i class="fa-solid fa-book"></i> Área Académica
                        </label>
                        <select name="id_area" id="id_area" required>
                            <option value="">Selecciona un área...</option>
                            <?php foreach ($areas_disponibles as $a): ?>
                                <option value="<?= $a['id_area'] ?>">
                                    <?= htmlspecialchars($a['nombre_area']) ?> (<?= htmlspecialchars($a['nombre_carrera']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nivel_experiencia">
                            <i class="fa-solid fa-chart-line"></i> Nivel de Experiencia
                        </label>
                        <select name="nivel_experiencia" id="nivel_experiencia">
                            <option value="basico">Básico</option>
                            <option value="intermedio">Intermedio</option>
                            <option value="avanzado">Avanzado</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fa-solid fa-plus"></i> Agregar Área
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- MIS ÁREAS -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>Áreas que Dicto Actualmente</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th>Carrera</th>
                            <th>Nivel</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($mis_areas) === 0): ?>
                            <tr>
                                <td colspan="4" class="vacio">
                                    <i class="fa-solid fa-inbox"></i> Aún no tienes áreas asignadas
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mis_areas as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($a['nombre_area']) ?></strong>
                                    <?php if ($a['descripcion']): ?>
                                        <div class="desc"><?= htmlspecialchars(substr($a['descripcion'], 0, 50)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['nombre_carrera']) ?></td>
                                <td>
                                    <span class="badge <?= $a['nivel_experiencia'] ?>">
                                        <?= ucfirst($a['nivel_experiencia']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="td-actions">
                                        <form method="post" class="form-nivel">
                                            <input type="hidden" name="accion" value="actualizar_nivel">
                                            <input type="hidden" name="id_area" value="<?= $a['id_area'] ?>">
                                            <select name="nivel_experiencia" class="nivel-inline">
                                                <?php foreach ($niveles_validos as $n): ?>
                                                    <option value="<?= $n ?>" <?= $a['nivel_experiencia'] === $n ? 'selected' : '' ?>>
                                                        <?= ucfirst($n) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-mini guardar">
                                                <i class="fa-solid fa-floppy-disk"></i>
                                            </button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('¿Quitar esta área de tu perfil?');" class="form-eliminar">
                                            <input type="hidden" name="accion" value="quitar">
                                            <input type="hidden" name="id_area" value="<?= $a['id_area'] ?>">
                                            <button type="submit" class="btn-mini eliminar" title="Quitar área">
                                                <i class="fa-solid fa-trash"></i>
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

</div>

</body>
</html>