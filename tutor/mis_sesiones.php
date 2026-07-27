<?php
// tutor/mis_sesiones.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];

// --- Filtros ---
$f_estado = $_GET['estado'] ?? '';
$f_desde  = $_GET['desde'] ?? '';
$f_hasta  = $_GET['hasta'] ?? '';

$estados_validos = ['programada', 'en curso', 'completa', 'finalizada', 'cancelada'];

$where = ["s.id_tutor = ?"];
$params = [$id_tutor];

if ($f_estado !== '' && in_array($f_estado, $estados_validos, true)) {
    $where[] = "s.estado = ?";
    $params[] = $f_estado;
}
if ($f_desde !== '') {
    $where[] = "s.fecha >= ?";
    $params[] = $f_desde;
}
if ($f_hasta !== '') {
    $where[] = "s.fecha <= ?";
    $params[] = $f_hasta;
}

$sql = "
    SELECT s.id_sesion, s.fecha, s.hora_inicio, s.hora_fin, s.turno, s.modalidad,
        s.estado, s.cupo_actual, s.cupo_maximo, a.nombre_area
    FROM sesion_tutoria s
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.fecha DESC, s.hora_inicio DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesiones = $stmt->fetchAll();

// --- Detalle de una sesión puntual (vista expandida) ---
$id_sesion_detalle = isset($_GET['ver']) ? (int) $_GET['ver'] : null;
$detalle_sesion = null;
$estudiantes_inscritos = [];
$detalle_modalidad = null;

if ($id_sesion_detalle) {
    // Verificar que la sesión pertenezca a este tutor
    $stmt = $pdo->prepare("
        SELECT s.*, a.nombre_area
        FROM sesion_tutoria s
        JOIN area_academica a ON s.id_area = a.id_area
        WHERE s.id_sesion = ? AND s.id_tutor = ?
    ");
    $stmt->execute([$id_sesion_detalle, $id_tutor]);
    $detalle_sesion = $stmt->fetch();

    if ($detalle_sesion) {
        $stmt = $pdo->prepare("
            SELECT e.id_estudiante, u.nombre, u.apellido, e.registro_universitario, se.asistencia, se.fecha_inscripcion
            FROM sesion_estudiante se
            JOIN estudiante e ON se.id_estudiante = e.id_estudiante
            JOIN usuario u ON e.id_estudiante = u.id_usuario
            WHERE se.id_sesion = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$id_sesion_detalle]);
        $estudiantes_inscritos = $stmt->fetchAll();

        // Promedio historico de cada estudiante (usa fn_promedio_estudiante del script)
        $stmt_prom = $pdo->prepare("SELECT fn_promedio_estudiante(?) AS promedio");
        foreach ($estudiantes_inscritos as &$est) {
            $stmt_prom->execute([$est['id_estudiante']]);
            $est['promedio_historico'] = $stmt_prom->fetchColumn();
        }
        unset($est);

        // Historial de sesiones anteriores de cada estudiante con este tutor - basado en consulta #7 Integrante 4
        // Esta consulta se tomó del archivo consultar_n.sql como una adaptación directa del proyecto para este módulo.
        $stmt_hist = $pdo->prepare("
            SELECT st.id_sesion, st.fecha, a.nombre_area, st.estado
            FROM sesion_estudiante se
            JOIN sesion_tutoria st ON se.id_sesion = st.id_sesion
            JOIN area_academica a ON st.id_area = a.id_area
            WHERE se.id_estudiante = ? AND st.id_tutor = ?
            ORDER BY st.fecha DESC
        ");
        foreach ($estudiantes_inscritos as &$est) {
            $stmt_hist->execute([$est['id_estudiante'], $id_tutor]);
            $est['historial_conmigo'] = $stmt_hist->fetchAll();
        }
        unset($est);

        $stmt = $pdo->prepare("SELECT * FROM detalle_modalidad_sesion WHERE id_sesion = ?");
        $stmt->execute([$id_sesion_detalle]);
        $detalle_modalidad = $stmt->fetch();

        // Cupos disponibles y total de estudiantes (usan fn_cupos_disponibles y fn_total_estudiantes_sesion del script)
        $stmt = $pdo->prepare("SELECT fn_cupos_disponibles(?) AS cupos_libres");
        $stmt->execute([$id_sesion_detalle]);
        $cupos_disponibles = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT fn_total_estudiantes_sesion(?) AS total");
        $stmt->execute([$id_sesion_detalle]);
        $total_estudiantes_sesion = $stmt->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Sesiones - Tutor</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/tutor_mis_sesiones.css">
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" >
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="mis_sesiones.php" class="active">
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
        <h2>Mis Sesiones</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre'] ?? 'Tutor') ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>
        </div>
    </div>

    <div class="bloque">
        <h3>Filtrar sesiones</h3>
        <form method="get" class="filtros">
            <div>
                <label for="estado">Estado</label>
                <select name="estado" id="estado">
                    <option value="">Todos</option>
                    <?php foreach ($estados_validos as $e): ?>
                        <option value="<?= $e ?>" <?= $f_estado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="desde">Desde</label>
                <input type="date" name="desde" id="desde" value="<?= htmlspecialchars($f_desde) ?>">
            </div>
            <div>
                <label for="hasta">Hasta</label>
                <input type="date" name="hasta" id="hasta" value="<?= htmlspecialchars($f_hasta) ?>">
            </div>
            <div><button type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button></div>
            <?php if ($f_estado || $f_desde || $f_hasta): ?>
                <a class="limpiar" href="mis_sesiones.php">Limpiar filtros</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr><th>Fecha</th><th>Hora</th><th>Área</th><th>Turno</th><th>Modalidad</th><th>Cupo</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (count($sesiones) === 0): ?>
                    <tr><td colspan="8" class="vacio">No se encontraron sesiones con estos filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($sesiones as $s): ?>
                    <tr class="fila-sesion">
                        <td><?= $s['fecha'] ?></td>
                        <td><?= substr($s['hora_inicio'],0,5) ?> - <?= substr($s['hora_fin'],0,5) ?></td>
                        <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                        <td><?= ucfirst($s['turno']) ?></td>
                        <td><?= ucfirst($s['modalidad']) ?></td>
                        <td><?= $s['cupo_actual'] ?>/<?= $s['cupo_maximo'] ?></td>
                        <td><span class="badge <?= str_replace(' ','_',$s['estado']) ?>"><?= $s['estado'] ?></span></td>
                        <td>
                            <a class="btn-ver" href="?<?= http_build_query(array_merge($_GET, ['ver' => $s['id_sesion']])) ?>#detalle">Ver detalle</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($id_sesion_detalle): ?>
    <div class="bloque" id="detalle">
        <h3>Detalle de la sesión</h3>
        <?php if (!$detalle_sesion): ?>
            <p class="vacio">No se encontró esa sesión o no te pertenece.</p>
        <?php else: ?>
            <div class="detalle-grid">
                <div><strong>Área</strong><?= htmlspecialchars($detalle_sesion['nombre_area']) ?></div>
                <div><strong>Fecha</strong><?= $detalle_sesion['fecha'] ?></div>
                <div><strong>Hora</strong><?= substr($detalle_sesion['hora_inicio'],0,5) ?> - <?= substr($detalle_sesion['hora_fin'],0,5) ?></div>
                <div><strong>Turno</strong><?= ucfirst($detalle_sesion['turno']) ?></div>
                <div><strong>Modalidad</strong><?= ucfirst($detalle_sesion['modalidad']) ?></div>
                <div><strong>Cupo</strong><?= $detalle_sesion['cupo_actual'] ?>/<?= $detalle_sesion['cupo_maximo'] ?> (<?= $cupos_disponibles ?> disponibles)</div>
                <div><strong>Estado</strong><span class="badge <?= str_replace(' ','_',$detalle_sesion['estado']) ?>"><?= $detalle_sesion['estado'] ?></span></div>
            </div>

            <?php if ($detalle_modalidad): ?>
                <div class="detalle-caja">
                    <?php if ($detalle_sesion['modalidad'] === 'presencial'): ?>
                        <strong>Ubicación:</strong>
                        Aula <?= htmlspecialchars($detalle_modalidad['aula'] ?? '—') ?>,
                        Edificio <?= htmlspecialchars($detalle_modalidad['edificio'] ?? '—') ?>
                        <?php if ($detalle_modalidad['ubicacion']): ?> — <?= htmlspecialchars($detalle_modalidad['ubicacion']) ?><?php endif; ?>
                    <?php else: ?>
                        <strong>Plataforma:</strong> <?= htmlspecialchars($detalle_modalidad['plataforma'] ?? '—') ?><br>
                        <strong>Enlace:</strong>
                        <?php if ($detalle_modalidad['enlace']): ?>
                            <a href="<?= htmlspecialchars($detalle_modalidad['enlace']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($detalle_modalidad['enlace']) ?></a>
                        <?php else: ?> — <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h3 style="margin-top:18px;">Estudiantes inscritos (<?= $total_estudiantes_sesion ?>)</h3>
            <table>
                <thead><tr><th>Estudiante</th><th>Registro</th><th>Asistencia</th><th>Promedio histórico</th><th>Sesiones conmigo</th><th>Inscrito el</th></tr></thead>
                <tbody>
                    <?php if (count($estudiantes_inscritos) === 0): ?>
                        <tr><td colspan="6" class="vacio">Aún no hay estudiantes inscritos en esta sesión.</td></tr>
                    <?php else: ?>
                        <?php foreach ($estudiantes_inscritos as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?></td>
                            <td><?= htmlspecialchars($e['registro_universitario']) ?></td>
                            <td><span class="badge <?= $e['asistencia'] ?>"><?= ucfirst($e['asistencia']) ?></span></td>
                            <td><?= number_format((float) $e['promedio_historico'], 2) ?></td>
                            <td><?= count($e['historial_conmigo']) ?></td>
                            <td><?= $e['fecha_inscripcion'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($detalle_sesion['estado'] === 'en curso' || $detalle_sesion['estado'] === 'finalizada'): ?>
                <p style="margin-top:12px;">
                    <a class="btn-link" href="marcar_asistencia.php?id_sesion=<?= $detalle_sesion['id_sesion'] ?>">Marcar asistencia →</a>
                </p>
            <?php endif; ?>
            <?php if ($detalle_sesion['estado'] === 'finalizada'): ?>
                <p>
                    <a class="btn-link" href="registrar_evaluacion.php?id_sesion=<?= $detalle_sesion['id_sesion'] ?>">Registrar evaluaciones →</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>