<?php
// admin/perfil.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';
$id_usuario = $_SESSION['id_usuario'];

// Actualizar datos personales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_datos') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);

    if ($nombre === '' || $apellido === '' || $email === '') {
        $error = 'Nombre, apellido y email son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, email = ?, telefono = ? WHERE id_usuario = ?");
            $stmt->execute([$nombre, $apellido, $email, $telefono, $id_usuario]);

            $_SESSION['nombre'] = $nombre;
            $mensaje = 'Datos actualizados correctamente.';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Ese correo ya está en uso por otro usuario.';
            } else {
                $error = 'Error al actualizar datos: ' . $e->getMessage();
            }
        }
    }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
    $actual = $_POST['password_actual'];
    $nueva = $_POST['password_nueva'];
    $confirmar = $_POST['password_confirmar'];

    $stmt = $pdo->prepare("SELECT password FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $hash_actual = $stmt->fetchColumn();

    if (!password_verify($actual, $hash_actual)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } else {
        $hash_nuevo = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuario SET password = ? WHERE id_usuario = ?");
        $stmt->execute([$hash_nuevo, $id_usuario]);
        $mensaje = 'Contraseña actualizada correctamente.';
    }
}

// Datos del usuario actual
$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.apellido, u.ci, u.email, u.telefono, u.fecha_registro, u.estado, a.nivel_acceso
    FROM usuario u
    JOIN administrador a ON a.id_administrador = u.id_usuario
    WHERE u.id_usuario = ?
");
$stmt->execute([$id_usuario]);
$perfil = $stmt->fetch();

// Actividad reciente: ultimas acciones relevantes que puede ver el propio admin (usuarios que el mismo dio de alta recientemente, como contexto informativo)
$actividad_reciente = $pdo->query("
    SELECT nombre, apellido, fecha_registro
    FROM usuario
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY fecha_registro DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Perfil - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/perfil.css">
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

    <a href="reportes.php">
        <i class="fa-solid fa-chart-line"></i><span>Reportes</span>
    </a>

    <a href="mantenimiento.php">
        <i class="fa-solid fa-screwdriver-wrench"></i><span>Mantenimiento</span>
    </a>

    <a href="perfil.php" class="active">
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
        <h2>Mi Perfil</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- ENCABEZADO DE PERFIL -->
    <div class="perfil-header">
        <div class="perfil-avatar">
            <?= strtoupper(substr($perfil['nombre'], 0, 1) . substr($perfil['apellido'], 0, 1)) ?>
        </div>
        <div class="perfil-info">
            <h2><?= htmlspecialchars($perfil['nombre'] . ' ' . $perfil['apellido']) ?></h2>
            <div class="perfil-rol">
                <i class="fa-solid fa-shield-halved"></i>
                Administrador &middot; Nivel de acceso: <?= htmlspecialchars($perfil['nivel_acceso']) ?>
            </div>
        </div>
        <span class="badge-estado <?= htmlspecialchars($perfil['estado']) ?>">
            <?= htmlspecialchars(ucfirst($perfil['estado'])) ?>
        </span>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta alerta-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- GRID DE PERFIL -->
    <div class="secciones">

        <div class="bloque">
            <h3>Información de la cuenta</h3>
            <table class="datos-lectura">
                <tr><td>CI</td><td><?= htmlspecialchars($perfil['ci']) ?></td></tr>
                <tr><td>Fecha de registro</td><td><?= htmlspecialchars($perfil['fecha_registro']) ?></td></tr>
                <tr><td>Estado</td><td><span class="badge-estado <?= htmlspecialchars($perfil['estado']) ?>"><?= htmlspecialchars(ucfirst($perfil['estado'])) ?></span></td></tr>
                <tr><td>Nivel de acceso</td><td><?= htmlspecialchars($perfil['nivel_acceso']) ?></td></tr>
            </table>
        </div>

        <div class="bloque">
            <h3>Usuarios registrados esta semana</h3>
            <?php if (count($actividad_reciente) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay registros en los últimos 7 días.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Nombre</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($actividad_reciente as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></td>
                        <td><?= htmlspecialchars($r['fecha_registro']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-pen"></i> Editar datos personales</h3>
            <form method="POST" class="form-perfil">
                <input type="hidden" name="accion" value="actualizar_datos">
                <div class="fila">
                    <div class="campo">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($perfil['nombre']) ?>">
                    </div>
                    <div class="campo">
                        <label>Apellido</label>
                        <input type="text" name="apellido" required value="<?= htmlspecialchars($perfil['apellido']) ?>">
                    </div>
                </div>
                <div class="fila">
                    <div class="campo">
                        <label>Email</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($perfil['email']) ?>">
                    </div>
                    <div class="campo">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($perfil['telefono']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primario">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
                </button>
            </form>
        </div>

        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-lock"></i> Cambiar contraseña</h3>
            <form method="POST" class="form-perfil">
                <input type="hidden" name="accion" value="cambiar_password">
                <div class="fila">
                    <div class="campo">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" required>
                    </div>
                </div>
                <div class="fila">
                    <div class="campo">
                        <label>Nueva contraseña</label>
                        <input type="password" name="password_nueva" required minlength="6">
                    </div>
                    <div class="campo">
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmar" required minlength="6">
                    </div>
                </div>
                <button type="submit" class="btn btn-secundario">
                    <i class="fa-solid fa-key"></i> Actualizar contraseña
                </button>
            </form>
        </div>

    </div>

</div>
</body>
</html>