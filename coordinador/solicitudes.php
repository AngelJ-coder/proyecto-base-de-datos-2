<?php
// coordinador/solicitudes.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Rechazar solicitud
if (isset($_GET['rechazar'])) {
    $id = (int)$_GET['rechazar'];
    try {
        $stmt = $pdo->prepare("UPDATE solicitud_tutoria SET estado = 'rechazada' WHERE id_solicitud = ? AND estado = 'pendiente'");
        $stmt->execute([$id]);
        header('Location: solicitudes.php?msg=rechazada');
        exit;
    } catch (Exception $e) {
        header('Location: solicitudes.php?err=1');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'rechazada') {
    $mensaje = 'Solicitud rechazada correctamente.';
}
if (isset($_GET['err'])) {
    $error = 'Ocurrió un error al procesar la solicitud.';
}

// Filtros
$filtro_estado = $_GET['estado'] ?? 'pendiente';
$filtro_area = $_GET['id_area'] ?? '';

$sql = "
SELECT sol.id_solicitud, sol.fecha_solicitud, sol.turno, sol.motivo, sol.estado,
    CONCAT(u.nombre,' ',u.apellido) AS estudiante, u.email AS email_estudiante,
    est.semestre, c.nombre_carrera,
    a.id_area, a.nombre_area
FROM solicitud_tutoria sol
JOIN estudiante est ON sol.id_estudiante = est.id_estudiante
JOIN usuario u ON est.id_estudiante = u.id_usuario
JOIN carrera c ON est.id_carrera = c.id_carrera
JOIN area_academica a ON sol.id_area = a.id_area
WHERE 1=1
";
$params = [];

if ($filtro_estado !== 'todas') {
    $sql .= " AND sol.estado = ? ";
    $params[] = $filtro_estado;
}
if ($filtro_area !== '') {
    $sql .= " AND a.id_area = ? ";
    $params[] = $filtro_area;
}
$sql .= " ORDER BY sol.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();
 
// Areas para el filtro
$areas = $pdo->query("SELECT id_area, nombre_area FROM area_academica ORDER BY nombre_area")->fetchAll();

// Contadores para las pestañas
$conteos = $pdo->query("SELECT estado, COUNT(*) AS total FROM solicitud_tutoria GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$c_pendiente = $conteos['pendiente'] ?? 0;
$c_asignada = $conteos['asignada'] ?? 0;
$c_rechazada = $conteos['rechazada'] ?? 0;
$c_todas = $c_pendiente + $c_asignada + $c_rechazada;

// ===== Areas con descripcion vacia o nula (Consulta 3 - Integrante 2) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para mostrar áreas sin descripción registrada.
$areas_sin_descripcion = $pdo->query("
    SELECT a.nombre_area, c.nombre_carrera
    FROM area_academica a JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE a.descripcion IS NULL OR a.descripcion = ''
")->fetchAll();

// ===== NUEVO: paginación para la tabla principal de solicitudes (5 por página) =====
// Se pagina en PHP sobre el array ya obtenido para no tocar la consulta original de arriba.
$pag_sol = isset($_GET['pag_sol']) ? max(1, (int)$_GET['pag_sol']) : 1;
$por_pagina_sol = 5;
$total_sol_filtradas = count($solicitudes);
$total_paginas_sol = max(1, (int)ceil($total_sol_filtradas / $por_pagina_sol));
if ($pag_sol > $total_paginas_sol) { $pag_sol = $total_paginas_sol; }
$offset_sol = ($pag_sol - 1) * $por_pagina_sol;
$solicitudes_paginadas = array_slice($solicitudes, $offset_sol, $por_pagina_sol);

// ===== NUEVO: paginación para "Áreas con descripción pendiente" (5 por página) =====
$pag_desc = isset($_GET['pag_desc']) ? max(1, (int)$_GET['pag_desc']) : 1;
$por_pagina_desc = 5;
$total_desc = count($areas_sin_descripcion);
$total_paginas_desc = max(1, (int)ceil($total_desc / $por_pagina_desc));
if ($pag_desc > $total_paginas_desc) { $pag_desc = $total_paginas_desc; }
$offset_desc = ($pag_desc - 1) * $por_pagina_desc;
$areas_sin_descripcion_paginadas = array_slice($areas_sin_descripcion, $offset_desc, $por_pagina_desc);

// ===== NUEVO: helper para construir la query string conservando filtros y otras páginas =====
function construir_url_pagina_solicitudes($pag_sol_val, $pag_desc_val, $filtro_estado, $filtro_area) {
    $params = [
        'estado' => $filtro_estado,
        'id_area' => $filtro_area,
        'pag_sol' => $pag_sol_val,
        'pag_desc' => $pag_desc_val,
    ];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes de Tutoría - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/coordinador.css">
<style>
/* ===== NUEVO: estilos de paginación, sin tocar los CSS originales ===== */
.paginacion{
    display:flex;
    gap:6px;
    align-items:center;
    justify-content:flex-end;
    margin-top:14px;
    flex-wrap:wrap;
}
.paginacion a, .paginacion span{
    padding:6px 12px;
    border-radius:8px;
    font-size:.78rem;
    font-weight:700;
    text-decoration:none;
    border:1px solid var(--border, #e6e9f2);
    color:var(--muted, #6b7280);
}
.paginacion a:hover{
    background:var(--accent-soft, #eef1ff);
    color:var(--accent, #4f6df5);
}
.paginacion .pagina-actual{
    background:linear-gradient(135deg,var(--accent, #4f6df5),#6d5ff5);
    color:#fff;
    border-color:transparent;
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitudes.php" class="active">
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

    <a href="historial_estudiante.php">
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
        <h2>Solicitudes de Tutoría</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta alerta-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <a href="?estado=pendiente" class="<?= $filtro_estado==='pendiente'?'activo':'' ?>">Pendientes (<?= $c_pendiente ?>)</a>
        <a href="?estado=asignada" class="<?= $filtro_estado==='asignada'?'activo':'' ?>">Asignadas (<?= $c_asignada ?>)</a>
        <a href="?estado=rechazada" class="<?= $filtro_estado==='rechazada'?'activo':'' ?>">Rechazadas (<?= $c_rechazada ?>)</a>
        <a href="?estado=todas" class="<?= $filtro_estado==='todas'?'activo':'' ?>">Todas (<?= $c_todas ?>)</a>
    </div>

    <!-- FILTRO POR ÁREA -->
    <form method="GET" class="filtros">
        <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro_estado) ?>">
        <label>Filtrar por área</label>
        <select name="id_area" onchange="this.form.submit()">
            <option value="">Todas las áreas</option>
            <?php foreach ($areas as $a): ?>
                <option value="<?= $a['id_area'] ?>" <?= $filtro_area == $a['id_area'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['nombre_area']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- TABLA DE SOLICITUDES -->
    <div class="bloque bloque-ancho">
        <?php if (count($solicitudes) === 0): ?>
            <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay solicitudes con este filtro.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th><th>Estudiante</th><th>Carrera/Sem.</th><th>Área</th><th>Turno</th><th>Motivo</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_paginadas as $s): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
                    <td><?= htmlspecialchars($s['estudiante']) ?><br><small class="texto-muted"><?= htmlspecialchars($s['email_estudiante']) ?></small></td>
                    <td><?= htmlspecialchars($s['nombre_carrera']) ?> - Sem. <?= $s['semestre'] ?></td>
                    <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                    <td><?= ucfirst($s['turno']) ?></td>
                    <td><?= htmlspecialchars($s['motivo']) ?></td>
                    <td><span class="badge-estado <?= $s['estado'] ?>"><?= ucfirst($s['estado']) ?></span></td>
                    <td class="acciones">
                        <?php if ($s['estado'] === 'pendiente'): ?>
                            <a class="btn-mini btn-asignar" href="asignar_sesion.php?id_solicitud=<?= $s['id_solicitud'] ?>">Asignar</a>
                            <a class="btn-mini btn-rechazar" href="solicitudes.php?rechazar=<?= $s['id_solicitud'] ?>&estado=<?= $filtro_estado ?>"
                               onclick="return confirm('¿Rechazar esta solicitud?');">Rechazar</a>
                        <?php else: ?>
                            <span class="texto-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- NUEVO: paginación tabla de solicitudes -->
        <?php if ($total_paginas_sol > 1): ?>
        <div class="paginacion">
            <?php if ($pag_sol > 1): ?>
                <a href="<?= construir_url_pagina_solicitudes($pag_sol - 1, $pag_desc, $filtro_estado, $filtro_area) ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_paginas_sol; $i++): ?>
                <?php if ($i === $pag_sol): ?>
                    <span class="pagina-actual"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= construir_url_pagina_solicitudes($i, $pag_desc, $filtro_estado, $filtro_area) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pag_sol < $total_paginas_sol): ?>
                <a href="<?= construir_url_pagina_solicitudes($pag_sol + 1, $pag_desc, $filtro_estado, $filtro_area) ?>"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- ÁREAS SIN DESCRIPCIÓN -->
    <div class="bloque bloque-ancho">
        <h3><i class="fa-solid fa-circle-exclamation"></i> Áreas con descripción pendiente de completar</h3>
        <?php if (count($areas_sin_descripcion) === 0): ?>
            <p class="vacio"><i class="fa-solid fa-circle-check"></i> Todas las áreas tienen descripción registrada.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Área</th><th>Carrera</th></tr></thead>
            <tbody>
                <?php foreach ($areas_sin_descripcion_paginadas as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                    <td><?= htmlspecialchars($a['nombre_carrera']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- NUEVO: paginación áreas sin descripción -->
        <?php if ($total_paginas_desc > 1): ?>
        <div class="paginacion">
            <?php if ($pag_desc > 1): ?>
                <a href="<?= construir_url_pagina_solicitudes($pag_sol, $pag_desc - 1, $filtro_estado, $filtro_area) ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_paginas_desc; $i++): ?>
                <?php if ($i === $pag_desc): ?>
                    <span class="pagina-actual"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= construir_url_pagina_solicitudes($pag_sol, $i, $filtro_estado, $filtro_area) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pag_desc < $total_paginas_desc): ?>
                <a href="<?= construir_url_pagina_solicitudes($pag_sol, $pag_desc + 1, $filtro_estado, $filtro_area) ?>"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

</div>
</body>
</html>