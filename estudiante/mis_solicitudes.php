<?php
// estudiante/mis_solicitudes.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$filtro_estado = $_GET['estado'] ?? '';
$mensaje = '';
$error = '';

// Cancelar solicitud (solo si sigue pendiente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $id_solicitud = (int)$_POST['id_solicitud'];
    $stmt = $pdo->prepare("SELECT estado FROM solicitud_tutoria WHERE id_solicitud = ? AND id_estudiante = ?");
    $stmt->execute([$id_solicitud, $id_estudiante]);
    $estado_actual = $stmt->fetchColumn();

    if ($estado_actual === 'pendiente') {
        $stmt = $pdo->prepare("UPDATE solicitud_tutoria SET estado = 'rechazada', motivo = CONCAT(motivo, ' [Cancelada por el estudiante]') WHERE id_solicitud = ?");
        $stmt->execute([$id_solicitud]);
        $mensaje = 'Solicitud cancelada correctamente.';
    } else {
        $error = 'Solo puedes cancelar solicitudes que aún están pendientes.';
    }
}

// Total de solicitudes pendientes del estudiante
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM solicitud_tutoria s
    WHERE s.id_estudiante = ? AND s.estado = 'pendiente'
");
$stmt->execute([$id_estudiante]);
$total_pendientes = $stmt->fetchColumn();

// Listado de solicitudes con filtro opcional por estado
$sql = "
    SELECT s.id_solicitud, s.fecha_solicitud, s.turno, s.motivo, s.estado, a.nombre_area,
        (SELECT p.id_plan FROM plan_tutoria p WHERE p.id_solicitud = s.id_solicitud) AS id_plan
    FROM solicitud_tutoria s
    JOIN area_academica a ON s.id_area = a.id_area
    WHERE s.id_estudiante = ?
";
$params = [$id_estudiante];
if (in_array($filtro_estado, ['pendiente','asignada','rechazada'])) {
    $sql .= " AND s.estado = ?";
    $params[] = $filtro_estado;
}
$sql .= " ORDER BY s.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Solicitudes - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_solicitudes.css">
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

    <a href="mis_solicitudes.php" class="active">
        <i class="fa-solid fa-file-lines"></i><span>Mis Solicitudes</span>
    </a>

    <a href="sesiones_disponibles.php">
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
        <h2>Mis Solicitudes</h2>

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
        <div class="stat-card naranja">
            <div class="numero"><?= $total_pendientes ?></div>
            <div class="label">Solicitudes pendientes</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros">
        <a href="mis_solicitudes.php" class="<?= $filtro_estado === '' ? 'activo' : '' ?>">Todas</a>
        <a href="mis_solicitudes.php?estado=pendiente" class="<?= $filtro_estado === 'pendiente' ? 'activo' : '' ?>">Pendientes</a>
        <a href="mis_solicitudes.php?estado=asignada" class="<?= $filtro_estado === 'asignada' ? 'activo' : '' ?>">Asignadas</a>
        <a href="mis_solicitudes.php?estado=rechazada" class="<?= $filtro_estado === 'rechazada' ? 'activo' : '' ?>">Rechazadas</a>
    </div>

    <div class="bloque">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Área</th><th>Turno</th><th>Motivo</th><th>Estado</th><th>Plan</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (count($solicitudes) === 0): ?>
                        <tr><td colspan="7" class="vacio">No tienes solicitudes registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['fecha_solicitud']))) ?></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= ucfirst($s['turno']) ?></td>
                            <td class="motivo-cell"><?= htmlspecialchars($s['motivo']) ?></td>
                            <td><span class="badge <?= $s['estado'] ?>"><?= ucfirst($s['estado']) ?></span></td>
                            <td>
                                <?php if ($s['id_plan']): ?>
                                    <span class="chip-plan">Plan #<?= $s['id_plan'] ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['estado'] === 'pendiente'): ?>
                                <form method="POST" onsubmit="return confirm('¿Cancelar esta solicitud?');">
                                    <input type="hidden" name="id_solicitud" value="<?= $s['id_solicitud'] ?>">
                                    <button type="submit" name="cancelar" class="btn-cancelar">
                                        <i class="fa-solid fa-xmark"></i> Cancelar
                                    </button>
                                </form>
                                <?php elseif ($s['estado'] === 'asignada'): ?>
                                    <button type="button" class="btn-detalle" onclick="verDetalle(<?= $s['id_solicitud'] ?>)">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
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

<!-- MODAL NUEVO: detalle de tutoría -->
<div class="modal-overlay" id="modal-detalle">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-chalkboard-user"></i> Detalle de la tutoría</h3>
            <button type="button" class="modal-cerrar" onclick="cerrarDetalle()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="modal-body-contenido">
            <p class="vacio">Cargando...</p>
        </div>
    </div>
</div>

<script>
async function verDetalle(idSolicitud){
    const overlay = document.getElementById('modal-detalle');
    const body = document.getElementById('modal-body-contenido');
    overlay.classList.add('activo');
    body.innerHTML = '<p class="vacio">Cargando...</p>';

    try{
        const resp = await fetch(`ajax_detalle_solicitud.php?id_solicitud=${encodeURIComponent(idSolicitud)}`);
        const data = await resp.json();

        if(data.error){
            body.innerHTML = `<p class="vacio">${data.error}</p>`;
            return;
        }

        let html = `
            <div class="detalle-seccion">
                <h4>Solicitud</h4>
                <p><strong>Área:</strong> ${data.solicitud.nombre_area}</p>
                <p><strong>Turno:</strong> ${data.solicitud.turno}</p>
                <p><strong>Motivo:</strong> ${data.solicitud.motivo}</p>
            </div>
        `;

        if(data.plan){
            html += `
            <div class="detalle-seccion">
                <h4>Plan de tutoría</h4>
                <p><strong>Objetivo:</strong> ${data.plan.objetivo ?? 'No especificado'}</p>
                <p><strong>Inicio:</strong> ${data.plan.fecha_inicio ?? '—'}</p>
                <p><strong>Fin estimado:</strong> ${data.plan.fecha_fin_estimada ?? '—'}</p>
                <p><strong>Sesiones estimadas:</strong> ${data.plan.numero_sesiones_estimadas}</p>
                <p><strong>Estado del plan:</strong> ${data.plan.estado}</p>
            </div>`;
        }

        if(data.sesiones && data.sesiones.length > 0){
            html += `<div class="detalle-seccion"><h4>Sesiones</h4>`;
            data.sesiones.forEach(s => {
                html += `
                <div class="sesion-item">
                    <p><strong>Tutor:</strong> ${s.tutor}</p>
                    <p><strong>Fecha:</strong> ${s.fecha} · ${s.hora_inicio} - ${s.hora_fin}</p>
                    <p><strong>Modalidad:</strong> ${s.modalidad}</p>
                    ${s.modalidad === 'presencial'
                        ? `<p><strong>Ubicación:</strong> ${s.aula ?? ''} ${s.edificio ?? ''} ${s.ubicacion ?? ''}</p>`
                        : `<p><strong>Enlace:</strong> ${s.enlace ?? 'Aún no disponible'}</p>`
                    }
                    <p><strong>Estado:</strong> ${s.estado}</p>
                    ${s.calificacion ? `<p><strong>Calificación:</strong> ${s.calificacion}</p>` : ''}
                    ${s.observaciones ? `<p><strong>Observaciones:</strong> ${s.observaciones}</p>` : ''}
                </div>`;
            });
            html += `</div>`;
        } else {
            html += `<div class="detalle-seccion"><p class="vacio">Aún no hay sesiones programadas.</p></div>`;
        }

        body.innerHTML = html;
    } catch(e){
        body.innerHTML = '<p class="vacio">No se pudo cargar el detalle.</p>';
    }
}

function cerrarDetalle(){
    document.getElementById('modal-detalle').classList.remove('activo');
}
</script>

</body>
</html>