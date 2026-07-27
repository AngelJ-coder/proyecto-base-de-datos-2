<?php
// admin/reportes.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

// Reporte 1: usuarios inactivos (cursor)
$stmt = $pdo->query("CALL sp_listar_usuarios_inactivos_largo()");
$usuarios_inactivos = $stmt->fetchAll();
$stmt->closeCursor();

// Reporte 2: usuarios agrupados por dominio de correo (cursor)
$stmt = $pdo->query("CALL sp_contar_usuarios_por_dominio()");
$dominios = $stmt->fetchAll();
$stmt->closeCursor();

// Reporte 3: total de areas por carrera (cursor)
$stmt = $pdo->query("CALL sp_resumen_areas_por_carrera()");
$resumen_areas = $stmt->fetchAll();
$stmt->closeCursor();

// Reporte 4: tutores sin area asignada (cursor)
$stmt = $pdo->query("CALL sp_verificar_tutores_sin_area()");
$tutores_sin_area = $stmt->fetchAll();
$stmt->closeCursor();

// ===== Reporte 5: Usuarios registrados en el ultimo mes (Consulta 5 - Integrante 1) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para mostrar usuarios registrados en el último mes.
$usuarios_recientes = $pdo->query("
    SELECT nombre, apellido, fecha_registro
    FROM usuario
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
")->fetchAll();

// ===== Reporte 6: Sesiones canceladas (Consulta 4 - Integrante 4) =====
$sesiones_canceladas = $pdo->query("
    SELECT id_sesion, fecha, turno, modalidad
    FROM sesion_tutoria WHERE estado = 'cancelada'
")->fetchAll();

// ===== Reporte 7: Resumen por modalidad (Integrante 6, procedimiento) =====
$stmt = $pdo->query("CALL sp_resumen_sesiones_por_modalidad()");
$resumen_modalidad = $stmt->fetchAll();
$stmt->closeCursor();

// ===== Reporte 8: Sesiones por estado y área (Integrante 6, vista) =====
$sesiones_estado_area = $pdo->query("
    SELECT nombre_area, estado, total_sesiones
    FROM vw_sesiones_por_estado_y_area
    ORDER BY nombre_area, estado
")->fetchAll();

// ===== Reporte 9: Resumen de sesiones por área (Integrante 6, cursor) =====
$stmt = $pdo->query("CALL sp_resumen_sesiones_por_area_cursor()");
$resumen_area_cursor = $stmt->fetchAll();
$stmt->closeCursor();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reportes - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<style>
/* ===== NUEVO: estilos de paginación (JS), sin tocar los CSS originales ===== */
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
    cursor:pointer;
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

    <a href="usuarios.php">
        <i class="fa-solid fa-users"></i><span>Usuarios</span>
    </a>

    <a href="carreras.php">
        <i class="fa-solid fa-school"></i><span>Carreras</span>
    </a>

    <a href="areas.php">
        <i class="fa-solid fa-book-open"></i><span>Áreas</span>
    </a>

    <a href="tutores_areas.php">
        <i class="fa-solid fa-user-graduate"></i><span>Tutores-Áreas</span>
    </a>

    <a href="reportes.php" class="active">
        <i class="fa-solid fa-chart-line"></i><span>Reportes</span>
    </a>

    <a href="mantenimiento.php">
        <i class="fa-solid fa-screwdriver-wrench"></i><span>Mantenimiento</span>
    </a>

    <a href="perfil.php">
        <i class="fa-solid fa-user"></i><span>Perfil</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Reportes</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- GRID DE REPORTES -->
    <div class="secciones">

        <div class="bloque">
            <h3>Usuarios inactivos</h3>
            <?php if (count($usuarios_inactivos) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> No hay usuarios inactivos.</p>
            <?php else: ?>
            <table id="tablaUsuariosInactivos">
                <thead><tr><th>ID</th><th>Nombre</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios_inactivos as $u): ?>
                    <tr>
                        <td><?= $u['id_usuario'] ?></td>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Usuarios por dominio de correo</h3>
            <?php if (count($dominios) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay datos disponibles.</p>
            <?php else: ?>
            <table id="tablaDominios">
                <thead><tr><th>Dominio</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($dominios as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['dominio']) ?></td>
                        <td><span class="contador"><?= $d['total'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Áreas por carrera</h3>
            <?php if (count($resumen_areas) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay carreras registradas.</p>
            <?php else: ?>
            <table id="tablaAreasPorCarrera">
                <thead><tr><th>Carrera</th><th>Total áreas</th></tr></thead>
                <tbody>
                    <?php foreach ($resumen_areas as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['carrera']) ?></td>
                        <td><span class="contador"><?= $r['total_areas'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Tutores sin área asignada</h3>
            <?php if (count($tutores_sin_area) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> Todos los tutores tienen al menos un área asignada.</p>
            <?php else: ?>
            <table id="tablaTutoresSinArea">
                <thead><tr><th>Tutor</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($tutores_sin_area as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['nombre']) ?></td>
                        <td><span class="badge-alerta">Sin área</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Usuarios registrados en el último mes</h3>
            <?php if (count($usuarios_recientes) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay registros en el último mes.</p>
            <?php else: ?>
            <table id="tablaUsuariosRecientes">
                <thead><tr><th>Nombre</th><th>Fecha de registro</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios_recientes as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                        <td><?= htmlspecialchars($u['fecha_registro']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Sesiones canceladas</h3>
            <?php if (count($sesiones_canceladas) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> No hay sesiones canceladas registradas.</p>
            <?php else: ?>
            <table id="tablaSesionesCanceladas">
                <thead><tr><th>ID</th><th>Fecha</th><th>Turno</th><th>Modalidad</th></tr></thead>
                <tbody>
                    <?php foreach ($sesiones_canceladas as $s): ?>
                    <tr>
                        <td><?= $s['id_sesion'] ?></td>
                        <td><?= htmlspecialchars($s['fecha']) ?></td>
                        <td><?= htmlspecialchars($s['turno']) ?></td>
                        <td><span class="badge-cancelada"><?= htmlspecialchars($s['modalidad']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Resumen por modalidad</h3>
            <?php if (count($resumen_modalidad) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay datos de modalidad disponibles.</p>
            <?php else: ?>
            <table id="tablaResumenModalidad">
                <thead><tr><th>Modalidad</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($resumen_modalidad as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['modalidad']) ?></td>
                        <td><span class="contador"><?= $m['total_sesiones'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Sesiones por estado y área</h3>
            <?php if (count($sesiones_estado_area) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay datos de estado por área.</p>
            <?php else: ?>
            <table id="tablaSesionesEstadoArea">
                <thead><tr><th>Área</th><th>Estado</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($sesiones_estado_area as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombre_area']) ?></td>
                        <td><?= htmlspecialchars($e['estado']) ?></td>
                        <td><span class="contador"><?= $e['total_sesiones'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque">
            <h3>Resumen de sesiones por área</h3>
            <?php if (count($resumen_area_cursor) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay datos de resumen por área.</p>
            <?php else: ?>
            <table id="tablaResumenAreaCursor">
                <thead><tr><th>Área</th><th>Total sesiones</th></tr></thead>
                <tbody>
                    <?php foreach ($resumen_area_cursor as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['area']) ?></td>
                        <td><span class="contador"><?= $r['total_sesiones'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
// ===== NUEVO: Paginación en JavaScript (sin recargar la página) =====
function paginarTabla(idTabla, filasPorPagina = 5) {
    const tabla = document.getElementById(idTabla);
    if (!tabla) return;

    const tbody = tabla.querySelector('tbody');
    const filas = Array.from(tbody.querySelectorAll('tr')).filter(f => !f.classList.contains('fila-vacia'));
    const totalFilas = filas.length;
    const totalPaginas = Math.max(1, Math.ceil(totalFilas / filasPorPagina));

    if (totalFilas === 0) return;

    let paginaActual = 1;

    const contenedorPaginacion = document.createElement('div');
    contenedorPaginacion.className = 'paginacion';
    tabla.insertAdjacentElement('afterend', contenedorPaginacion);

    function mostrarPagina(pagina) {
        paginaActual = Math.min(Math.max(1, pagina), totalPaginas);
        const inicio = (paginaActual - 1) * filasPorPagina;
        const fin = inicio + filasPorPagina;

        filas.forEach((fila, i) => {
            fila.style.display = (i >= inicio && i < fin) ? '' : 'none';
        });

        renderControles();
    }

    function renderControles() {
        contenedorPaginacion.innerHTML = '';
        if (totalPaginas <= 1) return;

        if (paginaActual > 1) {
            const btnPrev = document.createElement('a');
            btnPrev.href = '#';
            btnPrev.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
            btnPrev.onclick = (e) => { e.preventDefault(); mostrarPagina(paginaActual - 1); };
            contenedorPaginacion.appendChild(btnPrev);
        }

        for (let i = 1; i <= totalPaginas; i++) {
            if (i === paginaActual) {
                const span = document.createElement('span');
                span.className = 'pagina-actual';
                span.textContent = i;
                contenedorPaginacion.appendChild(span);
            } else {
                const link = document.createElement('a');
                link.href = '#';
                link.textContent = i;
                link.onclick = (e) => { e.preventDefault(); mostrarPagina(i); };
                contenedorPaginacion.appendChild(link);
            }
        }

        if (paginaActual < totalPaginas) {
            const btnNext = document.createElement('a');
            btnNext.href = '#';
            btnNext.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            btnNext.onclick = (e) => { e.preventDefault(); mostrarPagina(paginaActual + 1); };
            contenedorPaginacion.appendChild(btnNext);
        }
    }

    mostrarPagina(1);
}

document.addEventListener('DOMContentLoaded', function () {
    paginarTabla('tablaUsuariosInactivos', 5);
    paginarTabla('tablaDominios', 5);
    paginarTabla('tablaAreasPorCarrera', 5);
    paginarTabla('tablaTutoresSinArea', 5);
    paginarTabla('tablaUsuariosRecientes', 5);
    paginarTabla('tablaSesionesCanceladas', 5);
    paginarTabla('tablaResumenModalidad', 5);
    paginarTabla('tablaSesionesEstadoArea', 5);
    paginarTabla('tablaResumenAreaCursor', 5);
});
</script>
</body>
</html>