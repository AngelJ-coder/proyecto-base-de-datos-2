<?php
// coordinador/reportes.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

// Rango de fechas (por defecto: ultimos 30 dias)
$fecha_ini = $_GET['fecha_ini'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$filtro_carrera = $_GET['id_carrera'] ?? '';

$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carrera ORDER BY nombre_carrera")->fetchAll();

// 1. Demanda por area (usa vista vw_demanda_por_area)
$demanda_area = $pdo->query("SELECT * FROM vw_demanda_por_area")->fetchAll();

// 2. Tutores mas solicitados (usa vista vw_tutores_mas_solicitados)
$top_tutores = $pdo->query("SELECT * FROM vw_tutores_mas_solicitados LIMIT 10")->fetchAll();

// 3. Sesiones por estado en el rango de fechas
$stmt = $pdo->prepare("
    SELECT estado, COUNT(*) AS total
    FROM sesion_tutoria
    WHERE fecha BETWEEN ? AND ?
    GROUP BY estado
");
$stmt->execute([$fecha_ini, $fecha_fin]);
$sesiones_estado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. Sesiones por modalidad en el rango
$stmt = $pdo->prepare("
    SELECT modalidad, COUNT(*) AS total
    FROM sesion_tutoria
    WHERE fecha BETWEEN ? AND ?
    GROUP BY modalidad
");
$stmt->execute([$fecha_ini, $fecha_fin]);
$sesiones_modalidad = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 5. Promedio de calificaciones por area (usa sp_promedio_por_area, cursor)
$stmt = $pdo->query("CALL sp_promedio_por_area()");
$promedio_por_area = $stmt->fetchAll();
$stmt->closeCursor();

// Filtrar solo areas con evaluaciones registradas y ordenar por promedio descendente
$promedio_por_area = array_filter($promedio_por_area, fn($p) => $p['promedio'] > 0);
usort($promedio_por_area, fn($a, $b) => $b['promedio'] <=> $a['promedio']);
$promedio_por_area = array_values($promedio_por_area);

// 5b. Tutores con sesiones activas (vista Integrante 6)
$tutores_sesiones_activas = $pdo->query("SELECT * FROM vw_tutores_con_sesiones_activas ORDER BY sesiones_activas DESC LIMIT 10")->fetchAll();

// 5d. Tutores sin sesiones activas (cursor Integrante 6)
$stmt = $pdo->query("CALL sp_reporte_tutores_sin_sesiones_activas_cursor()");
$tutores_sin_sesiones_activas = $stmt->fetchAll();
$stmt->closeCursor();

// 6. Solicitudes por estado (global)
$solicitudes_estado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM solicitud_tutoria GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

// 7. Reporte detallado (usa vista vw_reporte_tutorias) en el rango de fechas, filtrable por carrera
$sql_detalle = "SELECT * FROM vw_reporte_tutorias WHERE fecha BETWEEN ? AND ?";
$params_detalle = [$fecha_ini, $fecha_fin];
if ($filtro_carrera !== '') {
    $sql_detalle .= " AND nombre_carrera = (SELECT nombre_carrera FROM carrera WHERE id_carrera = ?)";
    $params_detalle[] = $filtro_carrera;
}
$sql_detalle .= " ORDER BY fecha DESC LIMIT 100";
$stmt = $pdo->prepare($sql_detalle);
$stmt->execute($params_detalle);
$detalle = $stmt->fetchAll();

// 8. Estudiantes con bajo desempeño (calificacion < 51), filtrable por carrera
$sql_bajo = "
    SELECT CONCAT(u.nombre,' ',u.apellido) AS estudiante, a.nombre_area, ev.calificacion, st.fecha
    FROM evaluacion_sesion ev
    JOIN estudiante e ON ev.id_estudiante = e.id_estudiante
    JOIN usuario u ON e.id_estudiante = u.id_usuario
    JOIN sesion_tutoria st ON ev.id_sesion = st.id_sesion
    JOIN area_academica a ON st.id_area = a.id_area
    WHERE ev.calificacion < 51 AND st.fecha BETWEEN ? AND ?
";
$params_bajo = [$fecha_ini, $fecha_fin];
if ($filtro_carrera !== '') {
    $sql_bajo .= " AND e.id_carrera = ? ";
    $params_bajo[] = $filtro_carrera;
}
$sql_bajo .= " ORDER BY ev.calificacion ASC";
$stmt = $pdo->prepare($sql_bajo);
$stmt->execute($params_bajo);
$bajo_desempeno = $stmt->fetchAll();

// 9. Estudiantes por carrera (util cuando se filtra por carrera)
$sql_est_carrera = "
    SELECT c.nombre_carrera, COUNT(DISTINCT e.id_estudiante) AS total_estudiantes
    FROM carrera c
    LEFT JOIN estudiante e ON c.id_carrera = e.id_carrera
";
if ($filtro_carrera !== '') {
    $sql_est_carrera .= " WHERE c.id_carrera = ? GROUP BY c.id_carrera";
    $stmt = $pdo->prepare($sql_est_carrera);
    $stmt->execute([$filtro_carrera]);
} else {
    $sql_est_carrera .= " GROUP BY c.id_carrera";
    $stmt = $pdo->query($sql_est_carrera);
}
$estudiantes_por_carrera = $stmt->fetchAll();

$total_sesiones_rango = array_sum($sesiones_estado);

// ================================================================
// ===== NUEVO: PAGINACIÓN GENÉRICA (5 por página) PARA TODAS LAS TABLAS =====
// ================================================================
// Cada tabla tiene su propio parámetro de página en la URL para que
// paginar una no afecte a las demás (pag_demanda, pag_tutores, pag_estado,
// pag_activas, pag_inactivas, pag_modalidad, pag_carrera, pag_promedio,
// pag_bajo, pag_detalle).

function paginar_array($array, $param_pagina, $por_pagina = 5) {
    $pagina_actual = isset($_GET[$param_pagina]) ? max(1, (int)$_GET[$param_pagina]) : 1;
    $total = count($array);
    $total_paginas = max(1, (int)ceil($total / $por_pagina));
    if ($pagina_actual > $total_paginas) { $pagina_actual = $total_paginas; }
    $offset = ($pagina_actual - 1) * $por_pagina;
    $datos_pagina = array_slice($array, $offset, $por_pagina);
    return [
        'datos' => $datos_pagina,
        'pagina_actual' => $pagina_actual,
        'total_paginas' => $total_paginas,
    ];
}

// Construye la URL conservando TODOS los filtros y TODAS las páginas actuales,
// cambiando solo el parámetro de página de la tabla indicada.
function construir_url_pagina($param_pagina, $nueva_pagina, $fecha_ini, $fecha_fin, $id_carrera) {
    $params = [
        'fecha_ini'  => $fecha_ini,
        'fecha_fin'  => $fecha_fin,
        'id_carrera' => $id_carrera,
        'pag_demanda'   => $_GET['pag_demanda']   ?? 1,
        'pag_tutores'   => $_GET['pag_tutores']   ?? 1,
        'pag_estado'    => $_GET['pag_estado']    ?? 1,
        'pag_activas'   => $_GET['pag_activas']   ?? 1,
        'pag_inactivas' => $_GET['pag_inactivas'] ?? 1,
        'pag_modalidad' => $_GET['pag_modalidad'] ?? 1,
        'pag_carrera'   => $_GET['pag_carrera']   ?? 1,
        'pag_promedio'  => $_GET['pag_promedio']  ?? 1,
        'pag_bajo'      => $_GET['pag_bajo']      ?? 1,
        'pag_detalle'   => $_GET['pag_detalle']   ?? 1,
    ];
    $params[$param_pagina] = $nueva_pagina;
    return '?' . http_build_query($params);
}

// Convertir el array de estados/modalidad (que es KEY_PAIR: estado => total)
// a un array de filas para poder paginarlo igual que los demás.
$sesiones_estado_filas = [];
foreach ($sesiones_estado as $estado => $total) {
    $sesiones_estado_filas[] = ['estado' => $estado, 'total' => $total];
}
// Aseguramos el orden esperado (igual que en la tabla original)
$orden_estados = ['programada','en curso','completa','finalizada','cancelada'];
usort($sesiones_estado_filas, function($a, $b) use ($orden_estados) {
    return array_search($a['estado'], $orden_estados) <=> array_search($b['estado'], $orden_estados);
});

$sesiones_modalidad_filas = [];
foreach (['presencial','virtual'] as $mod) {
    $sesiones_modalidad_filas[] = ['modalidad' => $mod, 'total' => $sesiones_modalidad[$mod] ?? 0];
}

// Aplicar paginación a cada tabla
$p_demanda   = paginar_array($demanda_area, 'pag_demanda');
$p_tutores   = paginar_array($top_tutores, 'pag_tutores');
$p_estado    = paginar_array($sesiones_estado_filas, 'pag_estado');
$p_activas   = paginar_array($tutores_sesiones_activas, 'pag_activas');
$p_inactivas = paginar_array($tutores_sin_sesiones_activas, 'pag_inactivas');
$p_modalidad = paginar_array($sesiones_modalidad_filas, 'pag_modalidad');
$p_carrera   = paginar_array($estudiantes_por_carrera, 'pag_carrera');
$p_promedio  = paginar_array($promedio_por_area, 'pag_promedio');
$p_bajo      = paginar_array($bajo_desempeno, 'pag_bajo');
$p_detalle   = paginar_array($detalle, 'pag_detalle');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reportes - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes_c.css">
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

    <a href="dashboard.php" >
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitudes.php">
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

    <a href="reportes.php" class="active">
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
        <h2>Reportes y Estadísticas</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filtros">
        <label>Desde:</label>
        <input type="date" name="fecha_ini" value="<?= htmlspecialchars($fecha_ini) ?>">
        <label>Hasta:</label>
        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
        <label>Carrera:</label>
        <select name="id_carrera">
            <option value="">Todas las carreras</option>
            <?php foreach ($carreras as $c): ?>
                <option value="<?= $c['id_carrera'] ?>" <?= $filtro_carrera == $c['id_carrera'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre_carrera']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>

    <!-- CARDS RESUMEN -->
    <div class="grid-cards">
        <div class="card">
            <div class="numero"><?= $total_sesiones_rango ?></div>
            <div class="label">Sesiones en el rango</div>
        </div>
        <div class="card">
            <div class="numero"><?= $sesiones_estado['finalizada'] ?? 0 ?></div>
            <div class="label">Sesiones finalizadas</div>
        </div>
        <div class="card">
            <div class="numero"><?= $sesiones_estado['cancelada'] ?? 0 ?></div>
            <div class="label">Sesiones canceladas</div>
        </div>
        <div class="card">
            <div class="numero"><?= $solicitudes_estado['pendiente'] ?? 0 ?></div>
            <div class="label">Solicitudes pendientes (global)</div>
        </div>
        <div class="card">
            <div class="numero"><?= count($bajo_desempeno) ?></div>
            <div class="label">Estudiantes bajo desempeño</div>
        </div>
    </div>

    <div class="secciones">
        <div class="bloque">
            <h3>Demanda por área</h3>
            <table>
                <thead><tr><th>Área</th><th>Solicitudes</th></tr></thead>
                <tbody>
                    <?php if (count($demanda_area) === 0): ?>
                        <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
                    <?php else: ?>
                        <?php $max = max(array_column($demanda_area, 'total_solicitudes')) ?: 1; ?>
                        <?php foreach ($p_demanda['datos'] as $d): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($d['nombre_area']) ?>
                                <div class="barra-fondo"><div class="barra-relleno" style="width:<?= ($d['total_solicitudes']/$max*100) ?>%"></div></div>
                            </td>
                            <td><?= $d['total_solicitudes'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_demanda['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_demanda['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_demanda', $p_demanda['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_demanda['total_paginas']; $i++): ?>
                    <?php if ($i === $p_demanda['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_demanda', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_demanda['pagina_actual'] < $p_demanda['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_demanda', $p_demanda['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Top tutores más solicitados</h3>
            <table>
                <thead><tr><th>Tutor</th><th>Sesiones</th></tr></thead>
                <tbody>
                    <?php if (count($top_tutores) === 0): ?>
                        <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_tutores['datos'] as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['tutor']) ?></td>
                            <td><?= $t['total_sesiones'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_tutores['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_tutores['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_tutores', $p_tutores['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_tutores['total_paginas']; $i++): ?>
                    <?php if ($i === $p_tutores['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_tutores', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_tutores['pagina_actual'] < $p_tutores['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_tutores', $p_tutores['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Sesiones por estado (rango seleccionado)</h3>
            <table>
                <tbody>
                    <?php foreach ($p_estado['datos'] as $fila): ?>
                    <tr>
                        <td><?= ucfirst($fila['estado']) ?></td>
                        <td><?= $fila['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($p_estado['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_estado['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_estado', $p_estado['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_estado['total_paginas']; $i++): ?>
                    <?php if ($i === $p_estado['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_estado', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_estado['pagina_actual'] < $p_estado['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_estado', $p_estado['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Tutores con sesiones activas (Integrante 6)</h3>
            <table>
                <thead><tr><th>Tutor</th><th>Activas</th></tr></thead>
                <tbody>
                    <?php if (count($tutores_sesiones_activas) === 0): ?>
                        <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_activas['datos'] as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['tutor']) ?></td>
                            <td><?= $t['sesiones_activas'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_activas['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_activas['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_activas', $p_activas['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_activas['total_paginas']; $i++): ?>
                    <?php if ($i === $p_activas['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_activas', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_activas['pagina_actual'] < $p_activas['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_activas', $p_activas['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Tutores sin sesiones activas (Integrante 6)</h3>
            <table>
                <thead><tr><th>Tutor</th></tr></thead>
                <tbody>
                    <?php if (count($tutores_sin_sesiones_activas) === 0): ?>
                        <tr><td class="vacio">No hay tutores sin sesiones activas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_inactivas['datos'] as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['nombre']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_inactivas['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_inactivas['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_inactivas', $p_inactivas['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_inactivas['total_paginas']; $i++): ?>
                    <?php if ($i === $p_inactivas['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_inactivas', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_inactivas['pagina_actual'] < $p_inactivas['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_inactivas', $p_inactivas['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Sesiones por modalidad (rango seleccionado)</h3>
            <table>
                <tbody>
                    <?php foreach ($p_modalidad['datos'] as $fila): ?>
                    <tr>
                        <td><?= ucfirst($fila['modalidad']) ?></td>
                        <td><?= $fila['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($p_modalidad['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_modalidad['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_modalidad', $p_modalidad['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_modalidad['total_paginas']; $i++): ?>
                    <?php if ($i === $p_modalidad['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_modalidad', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_modalidad['pagina_actual'] < $p_modalidad['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_modalidad', $p_modalidad['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Estudiantes por carrera</h3>
            <table>
                <thead><tr><th>Carrera</th><th>Estudiantes</th></tr></thead>
                <tbody>
                    <?php if (count($estudiantes_por_carrera) === 0): ?>
                        <tr><td colspan="2" class="vacio">Sin datos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_carrera['datos'] as $ec): ?>
                        <tr>
                            <td><?= htmlspecialchars($ec['nombre_carrera']) ?></td>
                            <td><?= $ec['total_estudiantes'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_carrera['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_carrera['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_carrera', $p_carrera['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_carrera['total_paginas']; $i++): ?>
                    <?php if ($i === $p_carrera['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_carrera', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_carrera['pagina_actual'] < $p_carrera['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_carrera', $p_carrera['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Promedio de calificación por área</h3>
            <table>
                <thead><tr><th>Área</th><th>Promedio</th></tr></thead>
                <tbody>
                    <?php if (count($promedio_por_area) === 0): ?>
                        <tr><td colspan="2" class="vacio">Aún no hay evaluaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_promedio['datos'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['area']) ?></td>
                            <td><?= number_format($p['promedio'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_promedio['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_promedio['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_promedio', $p_promedio['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_promedio['total_paginas']; $i++): ?>
                    <?php if ($i === $p_promedio['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_promedio', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_promedio['pagina_actual'] < $p_promedio['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_promedio', $p_promedio['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Estudiantes con bajo desempeño (&lt; 51)</h3>
            <table>
                <thead><tr><th>Estudiante</th><th>Área</th><th>Calif.</th></tr></thead>
                <tbody>
                    <?php if (count($bajo_desempeno) === 0): ?>
                        <tr><td colspan="3" class="vacio">Sin casos en el rango seleccionado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($p_bajo['datos'] as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['estudiante']) ?></td>
                            <td><?= htmlspecialchars($b['nombre_area']) ?></td>
                            <td><span class="badge baja"><?= number_format($b['calificacion'], 2) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($p_bajo['total_paginas'] > 1): ?>
            <div class="paginacion">
                <?php if ($p_bajo['pagina_actual'] > 1): ?>
                    <a href="<?= construir_url_pagina('pag_bajo', $p_bajo['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $p_bajo['total_paginas']; $i++): ?>
                    <?php if ($i === $p_bajo['pagina_actual']): ?>
                        <span class="pagina-actual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= construir_url_pagina('pag_bajo', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($p_bajo['pagina_actual'] < $p_bajo['total_paginas']): ?>
                    <a href="<?= construir_url_pagina('pag_bajo', $p_bajo['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bloque full">
        <h3>
            Detalle de tutorías (<?= htmlspecialchars($fecha_ini) ?> a <?= htmlspecialchars($fecha_fin) ?>)
            <a class="btn-exportar" href="exportar_reporte.php?fecha_ini=<?= urlencode($fecha_ini) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&id_carrera=<?= urlencode($filtro_carrera) ?>">
                <i class="fa-solid fa-file-csv"></i> Exportar CSV
            </a>
        </h3>
        <table>
            <thead>
                <tr><th>Fecha</th><th>Estudiante</th><th>Tutor</th><th>Área</th><th>Carrera</th><th>Turno</th><th>Modalidad</th><th>Estado</th><th>Calificación</th></tr>
            </thead>
            <tbody>
                <?php if (count($detalle) === 0): ?>
                    <tr><td colspan="9" class="vacio">No hay registros en el rango seleccionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($p_detalle['datos'] as $d): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
                        <td><?= htmlspecialchars($d['estudiante']) ?></td>
                        <td><?= htmlspecialchars($d['tutor']) ?></td>
                        <td><?= htmlspecialchars($d['nombre_area']) ?></td>
                        <td><?= htmlspecialchars($d['nombre_carrera']) ?></td>
                        <td><?= ucfirst($d['turno']) ?></td>
                        <td><?= ucfirst($d['modalidad']) ?></td>
                        <td><?= ucfirst($d['estado']) ?></td>
                        <td><?= $d['calificacion'] !== null ? number_format($d['calificacion'], 2) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($p_detalle['total_paginas'] > 1): ?>
        <div class="paginacion">
            <?php if ($p_detalle['pagina_actual'] > 1): ?>
                <a href="<?= construir_url_pagina('pag_detalle', $p_detalle['pagina_actual'] - 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $p_detalle['total_paginas']; $i++): ?>
                <?php if ($i === $p_detalle['pagina_actual']): ?>
                    <span class="pagina-actual"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= construir_url_pagina('pag_detalle', $i, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($p_detalle['pagina_actual'] < $p_detalle['total_paginas']): ?>
                <a href="<?= construir_url_pagina('pag_detalle', $p_detalle['pagina_actual'] + 1, $fecha_ini, $fecha_fin, $filtro_carrera) ?>"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>