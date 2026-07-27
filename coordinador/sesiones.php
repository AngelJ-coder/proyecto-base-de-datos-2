<?php
// coordinador/sesiones.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Cancelar sesion
if (isset($_GET['cancelar'])) {
    $id = (int)$_GET['cancelar'];
    try {
        $stmt = $pdo->prepare("
            UPDATE sesion_tutoria
            SET estado = 'cancelada'
            WHERE id_sesion = ? AND estado IN ('programada','en curso')
        ");
        $stmt->execute([$id]);
        header('Location: sesiones.php?msg=cancelada');
        exit;
    } catch (Exception $e) {
        header('Location: sesiones.php?err=1');
        exit;
    }
}

// Marcar como finalizada
if (isset($_GET['finalizar'])) {
    $id = (int)$_GET['finalizar'];
    try {
        $stmt = $pdo->prepare("
            UPDATE sesion_tutoria
            SET estado = 'finalizada'
            WHERE id_sesion = ? AND estado IN ('programada','en curso','completa')
        ");
        $stmt->execute([$id]);
        header('Location: sesiones.php?msg=finalizada');
        exit;
    } catch (Exception $e) {
        header('Location: sesiones.php?err=1');
        exit;
    }
}

// Cerrar automaticamente sesiones vencidas (fecha pasada, aun programada/en curso)
if (isset($_GET['cerrar_vencidas'])) {
    try {
        $pdo->exec("CALL sp_cerrar_sesiones_vencidas()");
        header('Location: sesiones.php?msg=vencidas_cerradas');
        exit;
    } catch (Exception $e) {
        header('Location: sesiones.php?err=1');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $mapa = [
        'cancelada' => 'Sesión cancelada correctamente.',
        'finalizada' => 'Sesión marcada como finalizada.',
        'vencidas_cerradas' => 'Sesiones vencidas cerradas correctamente.',
    ];
    $mensaje = $mapa[$_GET['msg']] ?? '';
}
if (isset($_GET['err'])) {
    $error = 'Ocurrió un error al procesar la sesión.';
}

// Filtros
$filtro_estado = $_GET['estado'] ?? 'todas';
$filtro_area = $_GET['id_area'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$sql = "
SELECT st.id_sesion, st.fecha, st.hora_inicio, st.hora_fin, st.turno, st.modalidad,
    st.cupo_maximo, st.cupo_actual, st.estado,
    CONCAT(ut.nombre,' ',ut.apellido) AS tutor,
    a.nombre_area, a.id_area,
    dm.aula, dm.edificio, dm.plataforma, dm.enlace
FROM sesion_tutoria st
JOIN tutor t ON st.id_tutor = t.id_tutor
JOIN usuario ut ON t.id_tutor = ut.id_usuario
JOIN area_academica a ON st.id_area = a.id_area
LEFT JOIN detalle_modalidad_sesion dm ON st.id_sesion = dm.id_sesion
WHERE 1=1
";
$params = [];

if ($filtro_estado !== 'todas') {
    $sql .= " AND st.estado = ? ";
    $params[] = $filtro_estado;
}
if ($filtro_area !== '') {
    $sql .= " AND a.id_area = ? ";
    $params[] = $filtro_area;
}
if ($filtro_fecha !== '') {
    $sql .= " AND st.fecha = ? ";
    $params[] = $filtro_fecha;
}
$sql .= " ORDER BY st.fecha DESC, st.hora_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesiones = $stmt->fetchAll();

$areas = $pdo->query("SELECT id_area, nombre_area FROM area_academica ORDER BY nombre_area")->fetchAll();

// Contadores por estado
$conteos = $pdo->query("SELECT estado, COUNT(*) AS total FROM sesion_tutoria GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$c_programada = $conteos['programada'] ?? 0;
$c_en_curso = $conteos['en curso'] ?? 0;
$c_completa = $conteos['completa'] ?? 0;
$c_finalizada = $conteos['finalizada'] ?? 0;
$c_cancelada = $conteos['cancelada'] ?? 0;
$c_todas = array_sum($conteos);

// Sesiones vencidas pendientes de cerrar (para mostrar el aviso/boton)
$vencidas_pendientes = $pdo->query("
    SELECT COUNT(*) FROM sesion_tutoria
    WHERE estado IN ('programada','en curso') AND fecha < CURDATE()
")->fetchColumn();

// ===== NUEVO: paginación para la tabla de sesiones (5 por página) =====
// Se pagina en PHP sobre el array ya obtenido para no tocar la consulta original de arriba.
$pag_ses = isset($_GET['pag_ses']) ? max(1, (int)$_GET['pag_ses']) : 1;
$por_pagina_ses = 5;
$total_ses_filtradas = count($sesiones);
$total_paginas_ses = max(1, (int)ceil($total_ses_filtradas / $por_pagina_ses));
if ($pag_ses > $total_paginas_ses) { $pag_ses = $total_paginas_ses; }
$offset_ses = ($pag_ses - 1) * $por_pagina_ses;
$sesiones_paginadas = array_slice($sesiones, $offset_ses, $por_pagina_ses);

// ===== NUEVO: helper para construir la query string conservando filtros y la página =====
function construir_url_pagina_sesiones($pag_ses_val, $filtro_estado, $filtro_area, $filtro_fecha) {
    $params = [
        'estado' => $filtro_estado,
        'id_area' => $filtro_area,
        'fecha' => $filtro_fecha,
        'pag_ses' => $pag_ses_val,
    ];
    return '?' . http_build_query($params);
}

// Estudiantes inscritos por sesion (para el modal de detalle), solo para las visibles
$inscritos_por_sesion = [];
if (count($sesiones) > 0) {
    $ids = array_column($sesiones, 'id_sesion');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT se.id_sesion, CONCAT(u.nombre,' ',u.apellido) AS estudiante, se.asistencia
        FROM sesion_estudiante se
        JOIN estudiante e ON se.id_estudiante = e.id_estudiante
        JOIN usuario u ON e.id_estudiante = u.id_usuario
        WHERE se.id_sesion IN ($in)
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $inscritos_por_sesion[$row['id_sesion']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sesiones - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/coordinador.css">
<link rel="stylesheet" href="../assets/css/sesiones.css">
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

    <a href="solicitudes.php">
        <i class="fa-solid fa-inbox"></i><span>Solicitudes</span>
    </a>

    <a href="asignar_sesion.php">
        <i class="fa-solid fa-calendar-check"></i><span>Asignar Sesión</span>
    </a>

    <a href="sesiones.php" class="active">
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
        <h2>Sesiones de Tutoría</h2>

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

    <?php if ($vencidas_pendientes > 0): ?>
    <div class="alerta alerta-warning">
        <span><i class="fa-solid fa-clock"></i> Hay <?= $vencidas_pendientes ?> sesión(es) vencida(s) sin cerrar (fecha pasada, aún programada o en curso).</span>
        <a class="btn-mini btn-warning" href="sesiones.php?cerrar_vencidas=1"
           onclick="return confirm('¿Cerrar automáticamente las sesiones vencidas? Se marcarán como finalizadas.');">Cerrar vencidas ahora</a>
    </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <a href="?estado=todas" class="<?= $filtro_estado==='todas'?'activo':'' ?>">Todas (<?= $c_todas ?>)</a>
        <a href="?estado=programada" class="<?= $filtro_estado==='programada'?'activo':'' ?>">Programadas (<?= $c_programada ?>)</a>
        <a href="?estado=en curso" class="<?= $filtro_estado==='en curso'?'activo':'' ?>">En curso (<?= $c_en_curso ?>)</a>
        <a href="?estado=completa" class="<?= $filtro_estado==='completa'?'activo':'' ?>">Completas (<?= $c_completa ?>)</a>
        <a href="?estado=finalizada" class="<?= $filtro_estado==='finalizada'?'activo':'' ?>">Finalizadas (<?= $c_finalizada ?>)</a>
        <a href="?estado=cancelada" class="<?= $filtro_estado==='cancelada'?'activo':'' ?>">Canceladas (<?= $c_cancelada ?>)</a>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filtros">
        <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro_estado) ?>">
        <div class="campo">
            <label>Área</label>
            <select name="id_area" onchange="this.form.submit()">
                <option value="">Todas las áreas</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= $a['id_area'] ?>" <?= $filtro_area == $a['id_area'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nombre_area']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" onchange="this.form.submit()">
        </div>
        <?php if ($filtro_fecha !== '' || $filtro_area !== ''): ?>
            <a href="?estado=<?= urlencode($filtro_estado) ?>" class="btn btn-cancelar">Limpiar filtros</a>
        <?php endif; ?>
    </form>

    <!-- TABLA DE SESIONES -->
    <div class="bloque bloque-ancho tabla-scroll">
        <?php if (count($sesiones) === 0): ?>
            <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay sesiones con este filtro.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th><th>Horario</th><th>Turno</th><th>Tutor</th><th>Área</th>
                    <th>Modalidad</th><th>Lugar/Enlace</th><th>Cupo</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sesiones_paginadas as $s): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($s['fecha'])) ?></td>
                    <td><?= substr($s['hora_inicio'],0,5) ?> - <?= substr($s['hora_fin'],0,5) ?></td>
                    <td><?= ucfirst($s['turno']) ?></td>
                    <td><?= htmlspecialchars($s['tutor']) ?></td>
                    <td><?= htmlspecialchars($s['nombre_area']) ?></td>
                    <td><?= ucfirst($s['modalidad']) ?></td>
                    <td>
                        <?php if ($s['modalidad'] === 'presencial'): ?>
                            <?= htmlspecialchars(trim(($s['aula'] ?? '').' '.($s['edificio'] ? '- '.$s['edificio'] : ''))) ?: '—' ?>
                        <?php else: ?>
                            <?= $s['enlace'] ? '<a href="'.htmlspecialchars($s['enlace']).'" target="_blank">'.htmlspecialchars($s['plataforma'] ?: 'Enlace').'</a>' : '—' ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="cupo <?= $s['cupo_actual'] >= $s['cupo_maximo'] ? 'lleno' : '' ?>">
                            <?= $s['cupo_actual'] ?>/<?= $s['cupo_maximo'] ?>
                        </span>
                    </td>
                    <td><span class="badge-estado <?= str_replace(' ','_',$s['estado']) ?>"><?= ucfirst($s['estado']) ?></span></td>
                    <td class="acciones-col">
                        <button type="button" class="btn-mini btn-ver" onclick="verInscritos(<?= $s['id_sesion'] ?>)">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <?php if (in_array($s['estado'], ['programada','en curso','completa'])): ?>
                            <a class="btn-mini btn-finalizar" href="sesiones.php?finalizar=<?= $s['id_sesion'] ?>&estado=<?= urlencode($filtro_estado) ?>"
                               onclick="return confirm('¿Marcar esta sesión como finalizada?');">
                                <i class="fa-solid fa-check"></i> Finalizar
                            </a>
                        <?php endif; ?>
                        <?php if (in_array($s['estado'], ['programada','en curso'])): ?>
                            <a class="btn-mini btn-rechazar" href="sesiones.php?cancelar=<?= $s['id_sesion'] ?>&estado=<?= urlencode($filtro_estado) ?>"
                               onclick="return confirm('¿Cancelar esta sesión?');">
                                <i class="fa-solid fa-xmark"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- NUEVO: paginación tabla de sesiones -->
        <?php if ($total_paginas_ses > 1): ?>
        <div class="paginacion">
            <?php if ($pag_ses > 1): ?>
                <a href="<?= construir_url_pagina_sesiones($pag_ses - 1, $filtro_estado, $filtro_area, $filtro_fecha) ?>"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_paginas_ses; $i++): ?>
                <?php if ($i === $pag_ses): ?>
                    <span class="pagina-actual"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= construir_url_pagina_sesiones($i, $filtro_estado, $filtro_area, $filtro_fecha) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pag_ses < $total_paginas_ses): ?>
                <a href="<?= construir_url_pagina_sesiones($pag_ses + 1, $filtro_estado, $filtro_area, $filtro_fecha) ?>"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

</div>

<!-- MODAL DE ESTUDIANTES INSCRITOS -->
<div class="modal-overlay" id="modalInscritos">
    <div class="modal-box">
        <span class="modal-close" onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></span>
        <h3><i class="fa-solid fa-users"></i> Estudiantes inscritos</h3>
        <table id="tablaInscritos">
            <thead><tr><th>Estudiante</th><th>Asistencia</th></tr></thead>
            <tbody id="cuerpoInscritos"></tbody>
        </table>
    </div>
</div>

<script>
const inscritos = <?= json_encode($inscritos_por_sesion, JSON_UNESCAPED_UNICODE) ?>;

function verInscritos(idSesion) {
    const cuerpo = document.getElementById('cuerpoInscritos');
    cuerpo.innerHTML = '';
    const lista = inscritos[idSesion] || [];
    if (lista.length === 0) {
        cuerpo.innerHTML = '<tr><td colspan="2" style="color:#888;text-align:center;padding:12px;">Sin estudiantes inscritos.</td></tr>';
    } else {
        lista.forEach(function(e) {
            const fila = document.createElement('tr');
            fila.innerHTML = '<td>' + e.estudiante + '</td><td>' + e.asistencia + '</td>';
            cuerpo.appendChild(fila);
        });
    }
    document.getElementById('modalInscritos').classList.add('abierto');
}

function cerrarModal() {
    document.getElementById('modalInscritos').classList.remove('abierto');
}
</script>
</body>
</html>