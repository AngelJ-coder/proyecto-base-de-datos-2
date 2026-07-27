<?php
// admin/tutores_areas.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Asignar area a tutor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar') {
    $id_tutor = (int)$_POST['id_tutor'];
    $id_area = (int)$_POST['id_area'];
    $nivel = $_POST['nivel_experiencia'];

    if ($id_tutor === 0 || $id_area === 0) {
        $error = 'Debes seleccionar tutor y área.';
    } else {
        // Validar que el tutor esté activo antes de asignar
        $stmtActivo = $pdo->prepare("SELECT fn_usuario_activo(?) AS activo");
        $stmtActivo->execute([$id_tutor]);
        $activo = $stmtActivo->fetchColumn();

        if (!$activo) {
            $error = 'No se puede asignar un área a un tutor inactivo.';
        } else {
            try {
                $check = $pdo->prepare("SELECT 1 FROM tutor_area WHERE id_tutor = ? AND id_area = ?");
                $check->execute([$id_tutor, $id_area]);
                if ($check->fetch()) {
                    $error = 'Este tutor ya tiene asignada esa área.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tutor_area (id_tutor, id_area, nivel_experiencia) VALUES (?, ?, ?)");
                    $stmt->execute([$id_tutor, $id_area, $nivel]);
                    $mensaje = 'Área asignada correctamente al tutor.';
                }
            } catch (Exception $e) {
                $error = 'Error al asignar: ' . $e->getMessage();
            }
        }
    }
}

// Editar nivel de experiencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_nivel') {
    $id_tutor = (int)$_POST['id_tutor'];
    $id_area = (int)$_POST['id_area'];
    $nivel = $_POST['nivel_experiencia'];

    try {
        $stmt = $pdo->prepare("UPDATE tutor_area SET nivel_experiencia = ? WHERE id_tutor = ? AND id_area = ?");
        $stmt->execute([$nivel, $id_tutor, $id_area]);
        $mensaje = 'Nivel de experiencia actualizado.';
    } catch (Exception $e) {
        $error = 'Error al actualizar: ' . $e->getMessage();
    }
}

// Quitar asignacion
if (isset($_GET['quitar_tutor']) && isset($_GET['quitar_area'])) {
    $id_tutor = (int)$_GET['quitar_tutor'];
    $id_area = (int)$_GET['quitar_area'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tutor_area WHERE id_tutor = ? AND id_area = ?");
        $stmt->execute([$id_tutor, $id_area]);
        header('Location: tutores_areas.php?msg=eliminado');
        exit;
    } catch (Exception $e) {
        header('Location: tutores_areas.php?err=noeliminado');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $mensaje = 'Asignación eliminada correctamente.';
}
if (isset($_GET['err']) && $_GET['err'] === 'noeliminado') {
    $error = 'No se pudo eliminar la asignación.';
}

// Tutores para el select
$tutores = $pdo->query("
    SELECT t.id_tutor, u.nombre, u.apellido
    FROM tutor t
    JOIN usuario u ON t.id_tutor = u.id_usuario
    WHERE u.estado = 'activo'
    ORDER BY u.nombre
")->fetchAll();

// Areas para el select
$areas = $pdo->query("
    SELECT a.id_area, a.nombre_area, c.nombre_carrera
    FROM area_academica a
    JOIN carrera c ON a.id_carrera = c.id_carrera
    ORDER BY c.nombre_carrera, a.nombre_area
")->fetchAll();

// Listado de asignaciones tutor-area
$sql = "
SELECT ta.id_tutor, ta.id_area, ta.nivel_experiencia,
    CONCAT(u.nombre, ' ', u.apellido) AS tutor,
    a.nombre_area, c.nombre_carrera
FROM tutor_area ta
JOIN tutor t ON ta.id_tutor = t.id_tutor
JOIN usuario u ON t.id_tutor = u.id_usuario
JOIN area_academica a ON ta.id_area = a.id_area
JOIN carrera c ON a.id_carrera = c.id_carrera
ORDER BY u.nombre, a.nombre_area";
$asignaciones = $pdo->query($sql)->fetchAll();

// ===== Tutores con grado academico especifico (Consulta 4 - Integrante 3) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para filtrar tutores por grado académico.
$filtro_grado = isset($_GET['filtro_grado']) ? trim($_GET['filtro_grado']) : '';
$tutores_por_grado = [];
if ($filtro_grado !== '') {
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.apellido, t.grado_academico
        FROM tutor t JOIN usuario u ON t.id_tutor = u.id_usuario
        WHERE t.grado_academico LIKE ?
    ");
    $stmt->execute(['%' . $filtro_grado . '%']);
    $tutores_por_grado = $stmt->fetchAll();
}

// ===== Tutores marcados como disponibles actualmente (Consulta 5 - Integrante 3) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para mostrar los tutores que están marcados como disponibles.
$tutores_disponibles = $pdo->query("
    SELECT u.nombre, u.apellido, t.modalidad_preferida
    FROM tutor t JOIN usuario u ON t.id_tutor = u.id_usuario
    WHERE t.disponible = 'si'
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tutores y Áreas - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/tutores_areas.css">
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

    <a href="tutores_areas.php" class="active">
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
        <h2>Asignación de Áreas a Tutores</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?><div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- FORMULARIO NUEVA ASIGNACIÓN -->
    <div class="form-crear">
        <h3>Nueva Asignación</h3>
        <?php if (count($tutores) === 0): ?>
            <p><i class="fa-solid fa-circle-info"></i> No hay tutores activos registrados.</p>
        <?php elseif (count($areas) === 0): ?>
            <p><i class="fa-solid fa-circle-info"></i> No hay áreas académicas registradas.</p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="accion" value="asignar">
            <div class="fila">
                <select name="id_tutor" required>
                    <option value="">-- Seleccionar tutor --</option>
                    <?php foreach ($tutores as $t): ?>
                        <option value="<?= $t['id_tutor'] ?>"><?= htmlspecialchars($t['nombre'].' '.$t['apellido']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="id_area" required>
                    <option value="">-- Seleccionar área --</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id_area'] ?>"><?= htmlspecialchars($a['nombre_area'].' ('.$a['nombre_carrera'].')') ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="nivel_experiencia" required>
                    <option value="basico">Básico</option>
                    <option value="intermedio">Intermedio</option>
                    <option value="avanzado">Avanzado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-link"></i> Asignar Área
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="tabla-wrapper">
    <table id="tablaAsignaciones">
        <thead>
            <tr>
                <th>Tutor</th><th>Área</th><th>Carrera</th><th>Nivel</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($asignaciones) === 0): ?>
                <tr class="fila-vacia"><td colspan="5">Aún no hay asignaciones registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($asignaciones as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['tutor']) ?></td>
                    <td><?= htmlspecialchars($a['nombre_area']) ?></td>
                    <td><?= htmlspecialchars($a['nombre_carrera']) ?></td>
                    <td>
                        <form method="POST" class="nivel-form">
                            <input type="hidden" name="accion" value="editar_nivel">
                            <input type="hidden" name="id_tutor" value="<?= $a['id_tutor'] ?>">
                            <input type="hidden" name="id_area" value="<?= $a['id_area'] ?>">
                            <select name="nivel_experiencia" class="nivel-inline <?= $a['nivel_experiencia'] ?>" onchange="this.form.submit()">
                                <option value="basico" <?= $a['nivel_experiencia']==='basico'?'selected':'' ?>>Básico</option>
                                <option value="intermedio" <?= $a['nivel_experiencia']==='intermedio'?'selected':'' ?>>Intermedio</option>
                                <option value="avanzado" <?= $a['nivel_experiencia']==='avanzado'?'selected':'' ?>>Avanzado</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <a class="btn btn-eliminar"
                           href="tutores_areas.php?quitar_tutor=<?= $a['id_tutor'] ?>&quitar_area=<?= $a['id_area'] ?>"
                           onclick="return confirm('¿Quitar esta área al tutor?');">
                            <i class="fa-solid fa-trash"></i> Quitar
                        </a>
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
            <h3>Buscar tutores por grado académico</h3>
            <form method="GET" class="buscador">
                <input type="text" name="filtro_grado" placeholder="Ej: Maestría, Doctorado, Licenciatura..."
                       value="<?= htmlspecialchars($filtro_grado) ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
            </form>
            <?php if ($filtro_grado !== ''): ?>
                <?php if (empty($tutores_por_grado)): ?>
                    <div class="alerta-vacio"><i class="fa-solid fa-circle-info"></i> Ningún tutor coincide con "<?= htmlspecialchars($filtro_grado) ?>".</div>
                <?php else: ?>
                <table id="tablaPorGrado">
                    <thead><tr><th>Nombre</th><th>Grado académico</th></tr></thead>
                    <tbody>
                        <?php foreach ($tutores_por_grado as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']) ?></td>
                            <td><?= htmlspecialchars($t['grado_academico']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card full-width">
            <h3>Tutores marcados como disponibles</h3>
            <?php if (empty($tutores_disponibles)): ?>
                <div class="alerta-vacio"><i class="fa-solid fa-circle-info"></i> No hay tutores marcados como disponibles actualmente.</div>
            <?php else: ?>
            <table id="tablaDisponibles">
                <thead><tr><th>Nombre</th><th>Modalidad preferida</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($tutores_disponibles as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']) ?></td>
                        <td><?= htmlspecialchars($t['modalidad_preferida']) ?></td>
                        <td><span class="badge disponible">Disponible</span></td>
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
    paginarTabla('tablaAsignaciones', 5);
    paginarTabla('tablaPorGrado', 5);
    paginarTabla('tablaDisponibles', 5);
});
</script>
</body>
</html>