<?php
// estudiante/dashboard.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];

// Nombre completo vía función almacenada
$stmt = $pdo->prepare("SELECT fn_nombre_completo(?) AS nombre_completo");
$stmt->execute([$id_estudiante]);
$nombre_completo = $stmt->fetchColumn();

// Próximas sesiones inscritas (futuras)
$stmt = $pdo->prepare("
    SELECT s.id_sesion, s.fecha, s.hora_inicio, s.hora_fin, s.turno, s.modalidad, s.estado,
        a.nombre_area, CONCAT(u.nombre,' ',u.apellido) AS tutor, se.asistencia
    FROM sesion_estudiante se
    JOIN sesion_tutoria s ON se.id_sesion = s.id_sesion
    JOIN area_academica a ON s.id_area = a.id_area
    JOIN tutor t ON s.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    WHERE se.id_estudiante = ? AND s.fecha >= CURDATE()
    ORDER BY s.fecha, s.hora_inicio
    LIMIT 5
");
$stmt->execute([$id_estudiante]);
$proximas_sesiones = $stmt->fetchAll();

// Contador de solicitudes por estado
$stmt = $pdo->prepare("SELECT estado, COUNT(*) AS total FROM solicitud_tutoria WHERE id_estudiante = ? GROUP BY estado");
$stmt->execute([$id_estudiante]);
$sol_estado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$sol_pendientes = $sol_estado['pendiente'] ?? 0;
$sol_asignadas  = $sol_estado['asignada'] ?? 0;
$sol_rechazadas = $sol_estado['rechazada'] ?? 0;

// Total de sesiones inscritas (histórico)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sesion_estudiante WHERE id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$total_sesiones = $stmt->fetchColumn();

// Sesiones pendientes vía función del Integrante 6
$stmt = $pdo->prepare("SELECT fn_sesiones_pendientes_estudiante(?) AS sesiones_pendientes");
$stmt->execute([$id_estudiante]);
$sesiones_pendientes = (int) $stmt->fetchColumn();

// Promedio general vía función almacenada
$stmt = $pdo->prepare("SELECT fn_promedio_estudiante(?) AS promedio");
$stmt->execute([$id_estudiante]);
$promedio = $stmt->fetchColumn();

// Datos de carrera y semestre
$stmt = $pdo->prepare("
    SELECT c.nombre_carrera, e.semestre, e.registro_universitario
    FROM estudiante e JOIN carrera c ON e.id_carrera = c.id_carrera
    WHERE e.id_estudiante = ?
");
$stmt->execute([$id_estudiante]);
$datos_est = $stmt->fetch();

// Materiales recientes visibles en áreas de su carrera
$stmt = $pdo->prepare("
    SELECT m.titulo, m.tipo_archivo, m.fecha_subida, a.nombre_area
    FROM material_apoyo m
    JOIN area_academica a ON m.id_area = a.id_area
    WHERE a.id_carrera = (SELECT id_carrera FROM estudiante WHERE id_estudiante = ?)
      AND m.visible = 'si'
    ORDER BY m.fecha_subida DESC
    LIMIT 5
");
$stmt->execute([$id_estudiante]);
$materiales_recientes = $stmt->fetchAll();

// Planes de tutoria en curso del estudiante
$stmt = $pdo->prepare("
    SELECT p.id_plan, p.objetivo, p.numero_sesiones_estimadas, p.estado
    FROM plan_tutoria p
    JOIN solicitud_tutoria s ON p.id_solicitud = s.id_solicitud
    WHERE s.id_estudiante = ? AND p.estado = 'en curso'
");
$stmt->execute([$id_estudiante]);
$planes_en_curso = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_dashboard.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" class="active">
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
        <h2>Dashboard - Estudiante</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nombre_completo ?: $_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($nombre_completo ?: $_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- CARDS -->
    <div class="grid-cards">
        <a class="card-link" href="mis_solicitudes.php?estado=pendiente">
            <div class="card <?= $sol_pendientes > 0 ? 'alerta' : '' ?>">
                <div class="numero"><?= $sol_pendientes ?></div>
                <div class="label">Solicitudes pendientes</div>
            </div>
        </a>
        <a class="card-link" href="mis_solicitudes.php?estado=asignada">
            <div class="card verde">
                <div class="numero"><?= $sol_asignadas ?></div>
                <div class="label">Solicitudes asignadas</div>
            </div>
        </a>
        <div class="card rojo">
            <div class="numero"><?= $sol_rechazadas ?></div>
            <div class="label">Solicitudes rechazadas</div>
        </div>
        <a class="card-link" href="mis_sesiones.php">
            <div class="card">
                <div class="numero"><?= $total_sesiones ?></div>
                <div class="label">Sesiones inscritas (total)</div>
            </div>
        </a>
        <div class="card naranja">
            <div class="numero"><?= $sesiones_pendientes ?></div>
            <div class="label">Sesiones pendientes</div>
        </div>
        <a class="card-link" href="mi_historial.php">
            <div class="card <?= $promedio >= 51 ? 'verde' : 'rojo' ?>">
                <div class="numero"><?= $promedio !== null ? number_format($promedio, 2) : '—' ?></div>
                <div class="label">Promedio general</div>
            </div>
        </a>
        <div class="card">
            <div class="numero"><?= htmlspecialchars($datos_est['semestre'] ?? '—') ?></div>
            <div class="label">Semestre actual</div>
        </div>
    </div>

    <div class="secciones">
        <div class="bloque">
            <h3>Próximas sesiones</h3>
            <table>
                <thead><tr><th>Fecha</th><th>Área</th><th>Tutor</th><th>Modalidad</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (count($proximas_sesiones) === 0): ?>
                        <tr><td colspan="5" class="vacio">No tienes sesiones próximas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($proximas_sesiones as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['fecha']) ?><br><small><?= substr($s['hora_inicio'],0,5) ?></small></td>
                            <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                            <td><?= htmlspecialchars($s['tutor']) ?></td>
                            <td><?= ucfirst($s['modalidad']) ?></td>
                            <td><span class="badge <?= str_replace(' ','_',$s['estado']) ?>"><?= $s['estado'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="btn-link" href="mis_sesiones.php">Ver todas mis sesiones →</a>
        </div>

        <div class="bloque">
            <h3>Material de apoyo reciente</h3>
            <table>
                <thead><tr><th>Título</th><th>Área</th><th>Tipo</th></tr></thead>
                <tbody>
                    <?php if (count($materiales_recientes) === 0): ?>
                        <tr><td colspan="3" class="vacio">No hay materiales disponibles todavía.</td></tr>
                    <?php else: ?>
                        <?php foreach ($materiales_recientes as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['titulo']) ?></td>
                            <td><?= htmlspecialchars($m['nombre_area']) ?></td>
                            <td><span class="badge <?= $m['tipo_archivo'] ?>"><?= strtoupper($m['tipo_archivo']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="btn-link" href="material_apoyo.php">Ver todo el material →</a>
        </div>

        <div class="bloque">
            <h3>Mis planes de tutoría en curso</h3>
            <table>
                <thead><tr><th>Objetivo</th><th>Sesiones estimadas</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (count($planes_en_curso) === 0): ?>
                        <tr><td colspan="3" class="vacio">No tienes planes de tutoría en curso.</td></tr>
                    <?php else: ?>
                        <?php foreach ($planes_en_curso as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['objetivo']) ?></td>
                            <td><?= htmlspecialchars($p['numero_sesiones_estimadas']) ?></td>
                            <td><span class="chip-plan"><?= ucfirst($p['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bloque">
            <h3>Mis datos académicos</h3>
            <table>
                <tr><td>Carrera</td><td><?= htmlspecialchars($datos_est['nombre_carrera'] ?? '—') ?></td></tr>
                <tr><td>Registro universitario</td><td><?= htmlspecialchars($datos_est['registro_universitario'] ?? '—') ?></td></tr>
                <tr><td>Semestre</td><td><?= htmlspecialchars($datos_est['semestre'] ?? '—') ?></td></tr>
            </table>
            <a class="btn-link" href="perfil.php">Editar mi perfil →</a>
        </div>

        <div class="bloque">
            <h3>Acciones rápidas</h3>
            <p class="acciones-desc">¿Necesitas ayuda en alguna materia?</p>
            <a class="btn-link" href="solicitar_tutoria.php"><i class="fa-solid fa-plus"></i> Solicitar nueva tutoría →</a>
            <br><br>
            <a class="btn-link" href="sesiones_disponibles.php"><i class="fa-solid fa-plus"></i> Ver sesiones con cupo disponible →</a>
        </div>
    </div>

</div>

</body>
</html>