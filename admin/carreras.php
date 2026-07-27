<?php
// admin/carreras.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Crear carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre_carrera']);
    $facultad = trim($_POST['facultad']);
    $codigo = trim($_POST['codigo_carrera']);

    if ($nombre === '') {
        $error = 'El nombre de la carrera es obligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO carrera (nombre_carrera, facultad, codigo_carrera) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $facultad, $codigo ?: null]);
            $mensaje = 'Carrera creada correctamente.';
        } catch (Exception $e) {
            $error = 'Error al crear carrera: ' . $e->getMessage();
        }
    }
}

// Editar carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id = (int)$_POST['id_carrera'];
    $nombre = trim($_POST['nombre_carrera']);
    $facultad = trim($_POST['facultad']);
    $codigo = trim($_POST['codigo_carrera']);

    if ($nombre === '') {
        $error = 'El nombre de la carrera es obligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE carrera SET nombre_carrera = ?, facultad = ?, codigo_carrera = ? WHERE id_carrera = ?");
            $stmt->execute([$nombre, $facultad, $codigo ?: null, $id]);
            $mensaje = 'Carrera actualizada correctamente.';
        } catch (Exception $e) {
            $error = 'Error al actualizar carrera: ' . $e->getMessage();
        }
    }
}

// Eliminar carrera
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("DELETE FROM carrera WHERE id_carrera = ?");
        $stmt->execute([$id]);
        header('Location: carreras.php?msg=eliminado');
        exit;
    } catch (Exception $e) {
        header('Location: carreras.php?err=noeliminado');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $mensaje = 'Carrera eliminada correctamente.';
}
if (isset($_GET['err']) && $_GET['err'] === 'noeliminado') {
    $error = 'No se pudo eliminar la carrera. Puede tener áreas o estudiantes asociados.';
}

// Carrera a editar (si viene en GET)
$carrera_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM carrera WHERE id_carrera = ?");
    $stmt->execute([$id]);
    $carrera_editar = $stmt->fetch();
}

// Listado de carreras: total_areas ahora vía fn_total_areas_carrera(), total_estudiantes sigue con subconsulta directa
$sql = "
SELECT c.id_carrera, c.nombre_carrera, c.facultad, c.codigo_carrera,
    fn_total_areas_carrera(c.id_carrera) AS total_areas,
    (SELECT COUNT(*) FROM estudiante e WHERE e.id_carrera = c.id_carrera) AS total_estudiantes
FROM carrera c
ORDER BY c.nombre_carrera ASC";
$carreras = $pdo->query($sql)->fetchAll();

// ===== Carreras sin codigo asignado (Consulta 2 - Integrante 2) =====
$carreras_sin_codigo = $pdo->query("
    SELECT nombre_carrera, facultad
    FROM carrera WHERE codigo_carrera IS NULL OR codigo_carrera = ''
")->fetchAll();

// ===== Estudiantes agrupados por carrera (Consulta 4 - Integrante 2) =====
$estudiantes_por_carrera = $pdo->query("
    SELECT c.nombre_carrera, COUNT(e.id_estudiante) AS total_estudiantes
    FROM carrera c LEFT JOIN estudiante e ON c.id_carrera = e.id_carrera
    GROUP BY c.id_carrera
")->fetchAll();

// ===== Carreras sin facultad especificada (Consulta 5 - Integrante 2) =====
$carreras_sin_facultad = $pdo->query("
    SELECT nombre_carrera, codigo_carrera
    FROM carrera WHERE facultad IS NULL OR facultad = ''
")->fetchAll();

// ===== Carreras con su facultad (Consulta 7 - Integrante 2) =====
$carreras_con_facultad = $pdo->query("
    SELECT nombre_carrera, facultad FROM carrera ORDER BY facultad
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Carreras - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/carreras.css">
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

    <a href="carreras.php" class="active">
        <i class="fa-solid fa-school"></i><span>Carreras</span>
    </a>

    <a href="areas.php">
        <i class="fa-solid fa-book-open"></i><span>Áreas</span>
    </a>

    <a href="tutores_areas.php">
        <i class="fa-solid fa-user-graduate"></i><span>Tutores-Áreas</span>
    </a>

    <a href="reportes.php">
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
        <h2>Gestión de Carreras</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?><div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- FORMULARIO CREAR/EDITAR -->
    <div class="form-crear">
        <h3><?= $carrera_editar ? 'Editar Carrera' : 'Nueva Carrera' ?></h3>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $carrera_editar ? 'editar' : 'crear' ?>">
            <?php if ($carrera_editar): ?>
                <input type="hidden" name="id_carrera" value="<?= $carrera_editar['id_carrera'] ?>">
            <?php endif; ?>

            <div class="fila">
                <input type="text" name="nombre_carrera" placeholder="Nombre de la carrera" required
                       value="<?= $carrera_editar ? htmlspecialchars($carrera_editar['nombre_carrera']) : '' ?>">
                <input type="text" name="facultad" placeholder="Facultad"
                       value="<?= $carrera_editar ? htmlspecialchars($carrera_editar['facultad']) : '' ?>">
                <input type="text" name="codigo_carrera" placeholder="Código (ej: INF)"
                       value="<?= $carrera_editar ? htmlspecialchars($carrera_editar['codigo_carrera']) : '' ?>">
            </div>

            <div class="acciones-form">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid <?= $carrera_editar ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                    <?= $carrera_editar ? 'Actualizar' : 'Crear' ?> Carrera
                </button>
                <?php if ($carrera_editar): ?>
                    <a href="carreras.php" class="cancelar"><i class="fa-solid fa-xmark"></i> Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="tabla-wrapper">
    <table id="tablaCarreras">
        <thead>
            <tr>
                <th>ID</th><th>Nombre</th><th>Facultad</th><th>Código</th><th>Áreas</th><th>Estudiantes</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($carreras) === 0): ?>
                <tr class="fila-vacia"><td colspan="7">No hay carreras registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($carreras as $c): ?>
                <tr>
                    <td><?= $c['id_carrera'] ?></td>
                    <td><?= htmlspecialchars($c['nombre_carrera']) ?></td>
                    <td><?= htmlspecialchars($c['facultad']) ?></td>
                    <td><?= htmlspecialchars($c['codigo_carrera']) ?></td>
                    <td><span class="contador"><?= $c['total_areas'] ?></span></td>
                    <td><span class="contador"><?= $c['total_estudiantes'] ?></span></td>
                    <td>
                        <div class="acciones">
                            <a class="btn btn-editar" href="carreras.php?editar=<?= $c['id_carrera'] ?>">
                                <i class="fa-solid fa-pen"></i> Editar
                            </a>
                            <a class="btn btn-eliminar" href="carreras.php?eliminar=<?= $c['id_carrera'] ?>"
                               onclick="return confirm('¿Eliminar esta carrera? Esta acción no se puede deshacer.');">
                                <i class="fa-solid fa-trash"></i> Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- SECCIONES EXTRA -->
    <div class="secciones-extra">
        <div class="card">
            <h3>Carreras sin código asignado</h3>
            <?php if (empty($carreras_sin_codigo)): ?>
                <div class="alerta-vacio"><i class="fa-solid fa-circle-check"></i> Todas las carreras tienen código asignado.</div>
            <?php else: ?>
            <table id="tablaSinCodigo">
                <thead><tr><th>Nombre</th><th>Facultad</th></tr></thead>
                <tbody>
                    <?php foreach ($carreras_sin_codigo as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre_carrera']) ?></td>
                        <td><?= htmlspecialchars($c['facultad']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Carreras sin facultad especificada</h3>
            <?php if (empty($carreras_sin_facultad)): ?>
                <div class="alerta-vacio"><i class="fa-solid fa-circle-check"></i> Todas las carreras tienen facultad especificada.</div>
            <?php else: ?>
            <table id="tablaSinFacultad">
                <thead><tr><th>Nombre</th><th>Código</th></tr></thead>
                <tbody>
                    <?php foreach ($carreras_sin_facultad as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre_carrera']) ?></td>
                        <td><?= htmlspecialchars($c['codigo_carrera']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Estudiantes por carrera</h3>
            <table id="tablaEstudiantesPorCarrera">
                <thead><tr><th>Carrera</th><th>Total estudiantes</th></tr></thead>
                <tbody>
                    <?php if (empty($estudiantes_por_carrera)): ?>
                        <tr class="fila-vacia"><td colspan="2">No hay datos disponibles.</td></tr>
                    <?php else: ?>
                        <?php foreach ($estudiantes_por_carrera as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['nombre_carrera']) ?></td>
                            <td><span class="contador"><?= $e['total_estudiantes'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Carreras con su facultad</h3>
            <table id="tablaConFacultad">
                <thead><tr><th>Carrera</th><th>Facultad</th></tr></thead>
                <tbody>
                    <?php if (empty($carreras_con_facultad)): ?>
                        <tr class="fila-vacia"><td colspan="2">No hay datos disponibles.</td></tr>
                    <?php else: ?>
                        <?php foreach ($carreras_con_facultad as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre_carrera']) ?></td>
                            <td><?= htmlspecialchars($c['facultad']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
    paginarTabla('tablaCarreras', 5);
    paginarTabla('tablaSinCodigo', 5);
    paginarTabla('tablaSinFacultad', 5);
    paginarTabla('tablaEstudiantesPorCarrera', 5);
    paginarTabla('tablaConFacultad', 5);
});
</script>
</body>
</html>