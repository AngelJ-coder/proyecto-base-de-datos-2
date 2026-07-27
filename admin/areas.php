<?php
// admin/areas.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Crear area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre_area']);
    $descripcion = trim($_POST['descripcion']);
    $id_carrera = (int)$_POST['id_carrera'];

    if ($nombre === '' || $id_carrera === 0) {
        $error = 'El nombre del área y la carrera son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("CALL sp_crear_area(?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $id_carrera]);
            $stmt->closeCursor();
            $mensaje = 'Área creada correctamente.';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Ya existe esta area en la carrera') !== false) {
                $error = 'Ya existe un área con ese nombre en la carrera seleccionada.';
            } else {
                $error = 'Error al crear área: ' . $e->getMessage();
            }
        }
    }
}

// Editar area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id = (int)$_POST['id_area'];
    $nombre = trim($_POST['nombre_area']);
    $descripcion = trim($_POST['descripcion']);
    $id_carrera = (int)$_POST['id_carrera'];

    if ($nombre === '' || $id_carrera === 0) {
        $error = 'El nombre del área y la carrera son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE area_academica SET nombre_area = ?, descripcion = ?, id_carrera = ? WHERE id_area = ?");
            $stmt->execute([$nombre, $descripcion, $id_carrera, $id]);
            $mensaje = 'Área actualizada correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al actualizar área: ' . $e->getMessage();
        }
    }
}

// Eliminar area
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("DELETE FROM area_academica WHERE id_area = ?");
        $stmt->execute([$id]);
        header('Location: areas.php?msg=eliminado');
        exit;
    } catch (Exception $e) {
        header('Location: areas.php?err=noeliminado');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $mensaje = 'Área eliminada correctamente.';
}
if (isset($_GET['err']) && $_GET['err'] === 'noeliminado') {
    $error = 'No se pudo eliminar el área. Puede tener tutores, solicitudes o material asociado.';
}

// Area a editar
$area_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM area_academica WHERE id_area = ?");
    $stmt->execute([$id]);
    $area_editar = $stmt->fetch();
}

// Carreras para el select
$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carrera ORDER BY nombre_carrera")->fetchAll();

// Filtro por carrera usando sp_areas_por_carrera
$filtro_carrera = isset($_GET['filtro_carrera']) ? (int)$_GET['filtro_carrera'] : 0;

if ($filtro_carrera > 0) {
    $stmt = $pdo->prepare("CALL sp_areas_por_carrera(?)");
    $stmt->execute([$filtro_carrera]);
    $areas_sp = $stmt->fetchAll();
    $stmt->closeCursor();

    $areas = [];
    foreach ($areas_sp as $a) {
        $stmt2 = $pdo->prepare("
            SELECT c.nombre_carrera,
                (SELECT COUNT(*) FROM tutor_area ta WHERE ta.id_area = ?) AS total_tutores,
                (SELECT COUNT(*) FROM solicitud_tutoria s WHERE s.id_area = ?) AS total_solicitudes
            FROM carrera c WHERE c.id_carrera = ?
        ");
        $stmt2->execute([$a['id_area'], $a['id_area'], $filtro_carrera]);
        $extra = $stmt2->fetch();

        $areas[] = array_merge($a, $extra, ['id_carrera' => $filtro_carrera]);
    }
} else {
    $sql = "
    SELECT a.id_area, a.nombre_area, a.descripcion, a.id_carrera, c.nombre_carrera,
        (SELECT COUNT(*) FROM tutor_area ta WHERE ta.id_area = a.id_area) AS total_tutores,
        (SELECT COUNT(*) FROM solicitud_tutoria s WHERE s.id_area = a.id_area) AS total_solicitudes
    FROM area_academica a
    JOIN carrera c ON a.id_carrera = c.id_carrera
    ORDER BY c.nombre_carrera, a.nombre_area";
    $areas = $pdo->query($sql)->fetchAll();
}

// ===== Buscar area por nombre (Consulta 6 - Integrante 2) =====
$busqueda_nombre = isset($_GET['buscar_nombre']) ? trim($_GET['buscar_nombre']) : '';
$areas_encontradas = [];
if ($busqueda_nombre !== '') {
    $stmt = $pdo->prepare("
        SELECT id_area, nombre_area, descripcion
        FROM area_academica WHERE nombre_area LIKE ?
    ");
    $stmt->execute(['%' . $busqueda_nombre . '%']);
    $areas_encontradas = $stmt->fetchAll();
}

// ===== Areas con descripcion vacia o nula (Consulta 3 - Integrante 2) =====
$areas_sin_descripcion = $pdo->query("
    SELECT a.nombre_area, c.nombre_carrera
    FROM area_academica a JOIN carrera c ON a.id_carrera = c.id_carrera
    WHERE a.descripcion IS NULL OR a.descripcion = ''
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Áreas Académicas - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/areas.css">
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

    <a href="areas.php" class="active">
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
        <h2>Gestión de Áreas Académicas</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?><div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- FORMULARIO CREAR/EDITAR -->
    <div class="form-crear">
        <h3><?= $area_editar ? 'Editar Área' : 'Nueva Área Académica' ?></h3>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $area_editar ? 'editar' : 'crear' ?>">
            <?php if ($area_editar): ?>
                <input type="hidden" name="id_area" value="<?= $area_editar['id_area'] ?>">
            <?php endif; ?>

            <div class="fila">
                <input type="text" name="nombre_area" placeholder="Nombre del área" required
                       value="<?= $area_editar ? htmlspecialchars($area_editar['nombre_area']) : '' ?>">
                <select name="id_carrera" required>
                    <option value="">-- Seleccionar carrera --</option>
                    <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id_carrera'] ?>"
                            <?= ($area_editar && $area_editar['id_carrera'] == $c['id_carrera']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre_carrera']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fila">
                <input type="text" name="descripcion" placeholder="Descripción"
                       value="<?= $area_editar ? htmlspecialchars($area_editar['descripcion']) : '' ?>">
            </div>

            <div class="acciones-form">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid <?= $area_editar ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                    <?= $area_editar ? 'Actualizar' : 'Crear' ?> Área
                </button>
                <?php if ($area_editar): ?>
                    <a href="areas.php" class="cancelar"><i class="fa-solid fa-xmark"></i> Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- FILTRO POR CARRERA -->
    <div class="filtro-carrera">
        <form method="GET">
            <label><i class="fa-solid fa-filter"></i> Filtrar por carrera</label>
            <select name="filtro_carrera" onchange="this.form.submit()">
                <option value="0">-- Todas las carreras --</option>
                <?php foreach ($carreras as $c): ?>
                    <option value="<?= $c['id_carrera'] ?>" <?= $filtro_carrera == $c['id_carrera'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre_carrera']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="tabla-wrapper">
    <table id="tablaAreas">
        <thead>
            <tr>
                <th>ID</th><th>Área</th><th>Descripción</th><th>Carrera</th><th>Tutores</th><th>Solicitudes</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($areas) === 0): ?>
                <tr class="fila-vacia"><td colspan="7">No hay áreas para mostrar.</td></tr>
            <?php else: ?>
                <?php foreach ($areas as $a): ?>
                <tr>
                    <td><?= $a['id_area'] ?></td>
                    <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                    <td><?= htmlspecialchars($a['descripcion']) ?></td>
                    <td><?= htmlspecialchars($a['nombre_carrera']) ?></td>
                    <td><span class="contador"><?= $a['total_tutores'] ?></span></td>
                    <td><span class="contador"><?= $a['total_solicitudes'] ?></span></td>
                    <td>
                        <div class="acciones">
                            <a class="btn btn-editar" href="areas.php?editar=<?= $a['id_area'] ?>">
                                <i class="fa-solid fa-pen"></i> Editar
                            </a>
                            <a class="btn btn-eliminar" href="areas.php?eliminar=<?= $a['id_area'] ?>"
                               onclick="return confirm('¿Eliminar esta área? Esta acción no se puede deshacer.');">
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
        <div class="card full-width">
            <h3>Buscar área por nombre</h3>
            <form method="GET" class="buscador">
                <input type="text" name="buscar_nombre" placeholder="Ej: Base de Datos, Programación..."
                       value="<?= htmlspecialchars($busqueda_nombre) ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
            </form>
            <?php if ($busqueda_nombre !== ''): ?>
                <?php if (empty($areas_encontradas)): ?>
                    <div class="alerta-vacio" style="background:var(--orange-soft); color:#92400e;">
                        <i class="fa-solid fa-circle-info"></i> No se encontró ningún área con "<?= htmlspecialchars($busqueda_nombre) ?>".
                    </div>
                <?php else: ?>
                <table id="tablaBusqueda">
                    <thead><tr><th>Área</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <?php foreach ($areas_encontradas as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                            <td><?= htmlspecialchars($a['descripcion']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card full-width">
            <h3>Áreas con descripción pendiente de completar</h3>
            <?php if (empty($areas_sin_descripcion)): ?>
                <div class="alerta-vacio"><i class="fa-solid fa-circle-check"></i> Todas las áreas tienen descripción registrada.</div>
            <?php else: ?>
            <table id="tablaSinDescripcion">
                <thead><tr><th>Área</th><th>Carrera</th></tr></thead>
                <tbody>
                    <?php foreach ($areas_sin_descripcion as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                        <td><?= htmlspecialchars($a['nombre_carrera']) ?></td>
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
    paginarTabla('tablaAreas', 5);
    paginarTabla('tablaBusqueda', 5);
    paginarTabla('tablaSinDescripcion', 5);
});
</script>
</body>
</html>