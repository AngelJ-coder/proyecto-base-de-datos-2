<?php
// tutor/registrar_evaluacion.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

// --- Guardar evaluación ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $id_sesion = (int) ($_POST['id_sesion'] ?? 0);
    $id_estudiante = (int) ($_POST['id_estudiante'] ?? 0);
    $calificacion = $_POST['calificacion'] !== '' ? (float) $_POST['calificacion'] : null;
    $observaciones = trim($_POST['observaciones'] ?? '');
    $recomendaciones = trim($_POST['recomendaciones'] ?? '');

    // Verificar que la sesión sea del tutor y esté finalizada
    $stmt = $pdo->prepare("SELECT estado FROM sesion_tutoria WHERE id_sesion = ? AND id_tutor = ?");
    $stmt->execute([$id_sesion, $id_tutor]);
    $estado_sesion = $stmt->fetchColumn();

    if ($estado_sesion === false) {
        $error = 'Esa sesión no existe o no te pertenece.';
    } elseif ($estado_sesion !== 'finalizada') {
        $error = 'Solo puedes evaluar sesiones que ya están finalizadas.';
    } else {
        // Evitar duplicado (uq_eval: id_sesion + id_estudiante)
        $stmt = $pdo->prepare("SELECT id_evaluacion FROM evaluacion_sesion WHERE id_sesion = ? AND id_estudiante = ?");
        $stmt->execute([$id_sesion, $id_estudiante]);
        $existe = $stmt->fetchColumn();

        try {
            if ($existe) {
                $stmt = $pdo->prepare("
                    UPDATE evaluacion_sesion
                    SET calificacion = ?, observaciones = ?, recomendaciones = ?
                    WHERE id_evaluacion = ?
                ");
                $stmt->execute([$calificacion, $observaciones, $recomendaciones, $existe]);
                $mensaje = 'Evaluación actualizada correctamente.';
            } else {
                $stmt = $pdo->prepare("CALL sp_registrar_evaluacion(?, ?, ?, ?, ?)");
                $stmt->execute([$id_sesion, $id_estudiante, $calificacion, $observaciones, $recomendaciones]);
                $mensaje = 'Evaluación registrada correctamente.';
            }
        } catch (PDOException $e) {
            $error = 'No se pudo guardar la evaluación (la sesión debe estar finalizada).';
        }
    }
}

// Sesión seleccionada
$id_sesion_sel = isset($_GET['id_sesion']) ? (int) $_GET['id_sesion'] : (isset($_POST['id_sesion']) ? (int) $_POST['id_sesion'] : null);

// Listado de sesiones finalizadas del tutor
$stmt = $pdo->prepare("
    SELECT s.id_sesion, s.fecha, s.turno, a.nombre_area
    FROM sesion_tutoria s
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE s.id_tutor = ? AND s.estado = 'finalizada'
    ORDER BY s.fecha DESC
");
$stmt->execute([$id_tutor]);
$sesiones_finalizadas = $stmt->fetchAll();

// Sesiones finalizadas sin evaluar
$stmt = $pdo->prepare("
    SELECT st.id_sesion, st.fecha, a.nombre_area
    FROM sesion_tutoria st
    JOIN area_academica a ON st.id_area = a.id_area
    WHERE st.estado = 'finalizada'
      AND st.id_tutor = ?
      AND st.id_sesion NOT IN (SELECT DISTINCT id_sesion FROM evaluacion_sesion)
    ORDER BY st.fecha DESC
");
$stmt->execute([$id_tutor]);
$sesiones_sin_evaluar = $stmt->fetchAll();

// Estudiantes inscritos + evaluación existente
$estudiantes = [];
$sesion_info = null;
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
            SELECT e.id_estudiante, u.nombre, u.apellido, se.asistencia,
                ev.calificacion, ev.observaciones, ev.recomendaciones
            FROM sesion_estudiante se
            JOIN estudiante e ON se.id_estudiante = e.id_estudiante
            JOIN usuario u ON e.id_estudiante = u.id_usuario
            LEFT JOIN evaluacion_sesion ev ON ev.id_sesion = se.id_sesion AND ev.id_estudiante = se.id_estudiante
            WHERE se.id_sesion = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$id_sesion_sel]);
        $estudiantes = $stmt->fetchAll();

        // Promedio histórico de cada estudiante
        $stmt_prom = $pdo->prepare("SELECT fn_promedio_estudiante(?) AS promedio");
        foreach ($estudiantes as &$est) {
            $stmt_prom->execute([$est['id_estudiante']]);
            $est['promedio_historico'] = $stmt_prom->fetchColumn();
        }
        unset($est);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Evaluación - Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor_evaluacion.css">
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

    <a href="registrar_evaluacion.php" class="active">
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
        <h2>Registrar Evaluación</h2>
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
            <div class="numero"><?= count($sesiones_sin_evaluar) ?></div>
            <div class="label">Sesiones sin evaluar</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($sesiones_finalizadas) ?></div>
            <div class="label">Sesiones finalizadas</div>
        </div>
    </div>

    <!-- SECCIONES -->
    <div class="secciones">

        <!-- PENDIENTES -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>
                <i class="fa-solid fa-clock"></i> Sesiones Pendientes de Evaluar
                <span class="badge-count"><?= count($sesiones_sin_evaluar) ?></span>
            </h3>
            <?php if (count($sesiones_sin_evaluar) === 0): ?>
                <p class="vacio">
                    <i class="fa-solid fa-check"></i> No tienes sesiones finalizadas sin evaluar. ¡Buen trabajo!
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Área</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sesiones_sin_evaluar as $s): ?>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-calendar"></i>
                                    <?= $s['fecha'] ?>
                                </td>
                                <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                                <td>
                                    <a class="btn-mini evaluar" href="?id_sesion=<?= $s['id_sesion'] ?>">
                                        <i class="fa-solid fa-edit"></i> Evaluar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- SELECTOR SESIÓN -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>
                <i class="fa-solid fa-hand-pointer"></i> Selecciona una Sesión Finalizada
            </h3>
            <?php if (count($sesiones_finalizadas) === 0): ?>
                <p class="vacio">
                    <i class="fa-solid fa-inbox"></i> Todavía no tienes sesiones finalizadas para evaluar.
                </p>
            <?php else: ?>
                <form method="get" class="form-selector">
                    <select name="id_sesion" required>
                        <option value="">Selecciona una sesión...</option>
                        <?php foreach ($sesiones_finalizadas as $s): ?>
                            <option value="<?= $s['id_sesion'] ?>" <?= $id_sesion_sel == $s['id_sesion'] ? 'selected' : '' ?>>
                                <?= $s['fecha'] ?> — <?= htmlspecialchars($s['nombre_area']) ?> (<?= ucfirst($s['turno']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">
                        <i class="fa-solid fa-arrow-right"></i> Ver Estudiantes
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- TABLA DE EVALUACIÓN -->
        <?php if ($id_sesion_sel): ?>
            <div class="bloque" style="grid-column: 1 / -1;">
                <?php if (!$sesion_info): ?>
                    <p class="vacio">
                        <i class="fa-solid fa-warning"></i> Esa sesión no existe o no te pertenece.
                    </p>
                <?php elseif ($sesion_info['estado'] !== 'finalizada'): ?>
                    <p class="vacio">
                        <i class="fa-solid fa-info-circle"></i> Esta sesión aún no está finalizada, no se puede evaluar todavía.
                    </p>
                <?php else: ?>
                    <h3>
                        <i class="fa-solid fa-file-invoice"></i> Evaluar: <?= htmlspecialchars($sesion_info['nombre_area']) ?>
                        <span class="fecha-badge"><?= $sesion_info['fecha'] ?></span>
                    </h3>
                    <?php if (count($estudiantes) === 0): ?>
                        <p class="vacio">
                            <i class="fa-solid fa-users"></i> No hay estudiantes inscritos en esta sesión.
                        </p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="eval-table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Asistencia</th>
                                        <th>Promedio Histórico</th>
                                        <th>Calificación</th>
                                        <th>Observaciones</th>
                                        <th>Recomendaciones</th>
                                        <th>Guardar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $e): ?>
                                    <tr class="eval-row">
                                        <form method="post" class="eval-form">
                                            <input type="hidden" name="accion" value="guardar">
                                            <input type="hidden" name="id_sesion" value="<?= $id_sesion_sel ?>">
                                            <input type="hidden" name="id_estudiante" value="<?= $e['id_estudiante'] ?>">

                                            <td class="student-name">
                                                <i class="fa-solid fa-user-circle"></i>
                                                <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $e['asistencia'] ?>">
                                                    <i class="fa-solid fa-<?= $e['asistencia'] === 'presente' ? 'check' : 'times' ?>"></i>
                                                    <?= ucfirst($e['asistencia']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="promedio">
                                                    <?= number_format((float) $e['promedio_historico'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" max="100" name="calificacion" class="calif" placeholder="0.00" value="<?= htmlspecialchars($e['calificacion'] ?? '') ?>">
                                            </td>
                                            <td>
                                                <textarea name="observaciones" class="obs" placeholder="Observaciones..."><?= htmlspecialchars($e['observaciones'] ?? '') ?></textarea>
                                            </td>
                                            <td>
                                                <textarea name="recomendaciones" class="obs" placeholder="Recomendaciones..."><?= htmlspecialchars($e['recomendaciones'] ?? '') ?></textarea>
                                            </td>
                                            <td>
                                                <button type="submit" class="btn-save">
                                                    <i class="fa-solid fa-<?= $e['calificacion'] !== null ? 'sync' : 'save' ?>"></i>
                                                    <?= $e['calificacion'] !== null ? 'Actualizar' : 'Guardar' ?>
                                                </button>
                                            </td>
                                        </form>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>