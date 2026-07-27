<?php
// admin/usuarios.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre   = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $ci       = trim($_POST['ci']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $telefono = trim($_POST['telefono']);
    $rol      = $_POST['rol'];

    if ($nombre === '' || $apellido === '' || $ci === '' || $email === '' || $password === '' || $rol === '') {
        $error = 'Todos los campos obligatorios deben completarse.';
    } else {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("CALL sp_crear_usuario(?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $ci, $email, $hash]);
            $stmt->closeCursor();

            $stmtId = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = ? ORDER BY id_usuario DESC LIMIT 1");
            $stmtId->execute([$email]);
            $id_nuevo = $stmtId->fetchColumn();

            if (!$id_nuevo) {
                throw new Exception('No se pudo obtener el ID del usuario recién creado.');
            }

            if ($telefono !== '') {
                $pdo->prepare("UPDATE usuario SET telefono = ? WHERE id_usuario = ?")->execute([$telefono, $id_nuevo]);
            }

            switch ($rol) {
                case 'administrador':
                    $stmt = $pdo->prepare("INSERT INTO administrador (id_administrador, nivel_acceso) VALUES (?, 'soporte')");
                    $stmt->execute([$id_nuevo]);
                    break;
                case 'coordinador':
                    $stmt = $pdo->prepare("INSERT INTO coordinador (id_coordinador, cargo) VALUES (?, ?)");
                    $stmt->execute([$id_nuevo, $_POST['cargo'] ?? '']);
                    break;
                case 'tutor':
                    $stmt = $pdo->prepare("INSERT INTO tutor (id_tutor, especialidad_principal, grado_academico, disponible, modalidad_preferida) VALUES (?, ?, ?, 'si', 'ambas')");
                    $stmt->execute([$id_nuevo, $_POST['especialidad'] ?? '', $_POST['grado'] ?? '']);
                    break;
                case 'estudiante':
                    $stmt = $pdo->prepare("INSERT INTO estudiante (id_estudiante, registro_universitario, id_carrera, semestre) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id_nuevo, $_POST['registro'] ?? '', $_POST['id_carrera'] ?? 1, $_POST['semestre'] ?? 1]);
                    break;
            }

            $pdo->commit();
            $mensaje = 'Usuario creado correctamente.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al crear usuario: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $estado_actual = $pdo->prepare("SELECT estado FROM usuario WHERE id_usuario = ?");
    $estado_actual->execute([$id]);
    $estado = $estado_actual->fetchColumn();

    $nuevo_estado = ($estado === 'activo') ? 'inactivo' : 'activo';

    $stmt = $pdo->prepare("CALL sp_cambiar_estado_usuario(?, ?)");
    $stmt->execute([$id, $nuevo_estado]);
    $stmt->closeCursor();

    header('Location: usuarios.php');
    exit;
}

$sql = "
SELECT v.id_usuario, v.nombre, v.apellido, v.email, u.telefono, u.estado, v.rol
FROM vw_usuarios_por_rol v
JOIN usuario u ON v.id_usuario = u.id_usuario
ORDER BY v.id_usuario DESC";
$usuarios = $pdo->query($sql)->fetchAll();

$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carrera")->fetchAll();

$busqueda_email = '';
$usuario_encontrado = null;
if (isset($_GET['buscar_email']) && trim($_GET['buscar_email']) !== '') {
    $busqueda_email = trim($_GET['buscar_email']);
    $stmt = $pdo->prepare("
        SELECT id_usuario, nombre, apellido, estado
        FROM usuario WHERE email = ?
    ");
    $stmt->execute([$busqueda_email]);
    $usuario_encontrado = $stmt->fetch();
}

$coordinadores = $pdo->query("
    SELECT u.nombre, u.apellido, c.cargo
    FROM coordinador c JOIN usuario u ON c.id_coordinador = u.id_usuario
")->fetchAll();

$usuarios_con_telefono = $pdo->query("
    SELECT nombre, apellido, telefono, email
    FROM usuario WHERE telefono IS NOT NULL AND telefono <> ''
")->fetchAll();

$usuarios_inactivos = $pdo->query("
    SELECT nombre, apellido, email, fecha_registro
    FROM usuario WHERE estado = 'inactivo'
")->fetchAll();

$usuarios_recientes = $pdo->query("
    SELECT nombre, apellido, fecha_registro
    FROM usuario
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/usuarios.css">
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

    <a href="usuarios.php" class="active">
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
        <h2>Gestión de Usuarios</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
            
        </div>
    </div>

    <?php if ($mensaje): ?><div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- FORMULARIO CREAR USUARIO -->
    <div class="form-crear">
        <h3>Nuevo Usuario</h3>
        <form method="POST" id="formCrear">
            <input type="hidden" name="accion" value="crear">

            <div class="fila">
                <input type="text" name="nombre" placeholder="Nombre" required>
                <input type="text" name="apellido" placeholder="Apellido" required>
                <input type="text" name="ci" placeholder="CI" required>
            </div>
            <div class="fila">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <input type="text" name="telefono" placeholder="Teléfono">
            </div>

            <label>Rol del usuario</label>
            <div class="rol-tabs">
                <div class="rol-tab" data-rol="administrador">
                    <i class="fa-solid fa-user-shield"></i> Administrador
                </div>
                <div class="rol-tab" data-rol="coordinador">
                    <i class="fa-solid fa-briefcase"></i> Coordinador
                </div>
                <div class="rol-tab" data-rol="tutor">
                    <i class="fa-solid fa-user-graduate"></i> Tutor
                </div>
                <div class="rol-tab" data-rol="estudiante">
                    <i class="fa-solid fa-user"></i> Estudiante
                </div>
            </div>
            <input type="hidden" name="rol" id="rolSelect" required>

            <div id="campo-administrador" class="campos-rol-panel">
                <span style="color:var(--muted); font-size:.85rem;">
                    <i class="fa-solid fa-circle-info"></i> Este rol no requiere campos adicionales. Se creará con nivel de acceso "soporte".
                </span>
            </div>

            <div id="campo-coordinador" class="campos-rol-panel">
                <input type="text" name="cargo" placeholder="Cargo">
            </div>

            <div id="campo-tutor" class="campos-rol-panel">
                <input type="text" name="especialidad" placeholder="Especialidad principal">
                <input type="text" name="grado" placeholder="Grado académico">
            </div>

            <div id="campo-estudiante" class="campos-rol-panel">
                <input type="text" name="registro" placeholder="Registro universitario">
                <select name="id_carrera">
                    <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre_carrera']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="semestre" placeholder="Semestre" min="1" max="12">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i> Crear Usuario
            </button>
        </form>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="tabla-wrapper">
    <table id="tablaUsuarios">
        <thead>
            <tr>
                <th>ID</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($usuarios) === 0): ?>
                <tr class="fila-vacia"><td colspan="8">No hay usuarios registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id_usuario'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['apellido']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['telefono']) ?></td>
                    <td><?= htmlspecialchars($u['rol']) ?></td>
                    <td><span class="badge <?= $u['estado'] ?>"><?= $u['estado'] ?></span></td>
                    <td>
                        <a class="btn btn-toggle" href="usuarios.php?toggle=<?= $u['id_usuario'] ?>"
                           onclick="return confirm('¿Cambiar estado de este usuario?');">
                            <?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- BUSCADOR -->
    <div class="card full-width">
        <h3>Buscar usuario por correo</h3>
        <form method="GET" class="buscador">
            <input type="email" name="buscar_email" placeholder="ejemplo@umsa.bo"
                   value="<?= htmlspecialchars($busqueda_email) ?>" required>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        </form>
        <?php if ($busqueda_email !== ''): ?>
            <?php if ($usuario_encontrado): ?>
                <div class="resultado-ok">
                    Encontrado: <strong><?= htmlspecialchars($usuario_encontrado['nombre'] . ' ' . $usuario_encontrado['apellido']) ?></strong>
                    (ID: <?= $usuario_encontrado['id_usuario'] ?>) — Estado:
                    <span class="badge <?= $usuario_encontrado['estado'] ?>"><?= $usuario_encontrado['estado'] ?></span>
                </div>
            <?php else: ?>
                <div class="resultado-vacio">No se encontró ningún usuario con ese correo.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- SECCIONES EXTRA -->
    <div class="secciones-extra">
        <div class="card">
            <h3>Coordinadores y su cargo</h3>
            <table id="tablaCoordinadores">
                <thead><tr><th>Nombre</th><th>Apellido</th><th>Cargo</th></tr></thead>
                <tbody>
                    <?php if (empty($coordinadores)): ?>
                    <tr class="fila-vacia"><td colspan="3">No hay coordinadores registrados.</td></tr>
                    <?php else: ?>
                    <?php foreach ($coordinadores as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                        <td><?= htmlspecialchars($c['apellido']) ?></td>
                        <td><?= htmlspecialchars($c['cargo']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Usuarios con teléfono registrado</h3>
            <table id="tablaTelefono">
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Email</th></tr></thead>
                <tbody>
                    <?php if (empty($usuarios_con_telefono)): ?>
                    <tr class="fila-vacia"><td colspan="3">Ningún usuario tiene teléfono registrado.</td></tr>
                    <?php else: ?>
                    <?php foreach ($usuarios_con_telefono as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                        <td><?= htmlspecialchars($u['telefono']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Usuarios inactivos</h3>
            <table id="tablaInactivos">
                <thead><tr><th>Nombre</th><th>Email</th><th>Registrado</th></tr></thead>
                <tbody>
                    <?php if (empty($usuarios_inactivos)): ?>
                    <tr class="fila-vacia"><td colspan="3">No hay usuarios inactivos.</td></tr>
                    <?php else: ?>
                    <?php foreach ($usuarios_inactivos as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['fecha_registro']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Registrados en el último mes</h3>
            <table id="tablaRecientes">
                <thead><tr><th>Nombre</th><th>Fecha de registro</th></tr></thead>
                <tbody>
                    <?php if (empty($usuarios_recientes)): ?>
                    <tr class="fila-vacia"><td colspan="2">No hay registros en el último mes.</td></tr>
                    <?php else: ?>
                    <?php foreach ($usuarios_recientes as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                        <td><?= htmlspecialchars($u['fecha_registro']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="../assets/js/usuarios.js"></script>
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
    paginarTabla('tablaUsuarios', 5);
    paginarTabla('tablaCoordinadores', 5);
    paginarTabla('tablaTelefono', 5);
    paginarTabla('tablaInactivos', 5);
    paginarTabla('tablaRecientes', 5);
});
</script>
</body>
</html>