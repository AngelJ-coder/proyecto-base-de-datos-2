<?php
// estudiante/perfil.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

// Actualizar datos personales (teléfono y/o contraseña)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = trim($_POST['telefono'] ?? '');
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';

    if ($password_nueva !== '' && $password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            if ($password_nueva !== '') {
                $hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuario SET telefono = ?, password = ? WHERE id_usuario = ?");
                $stmt->execute([$telefono, $hash, $id_estudiante]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuario SET telefono = ? WHERE id_usuario = ?");
                $stmt->execute([$telefono, $id_estudiante]);
            }
            $mensaje = 'Tu perfil fue actualizado correctamente.';
        } catch (PDOException $e) {
            $error = 'Ocurrió un error al actualizar tu perfil.';
        }
    }
}

// Nombre completo vía función almacenada
$stmt = $pdo->prepare("SELECT fn_nombre_completo(?) AS nombre_completo");
$stmt->execute([$id_estudiante]);
$nombre_completo = $stmt->fetchColumn();

// Datos del usuario y del estudiante
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, u.ci, u.email, u.telefono, u.fecha_registro, u.estado,
        e.registro_universitario, e.semestre, c.nombre_carrera, c.facultad
    FROM usuario u
    JOIN estudiante e ON u.id_usuario = e.id_estudiante
    JOIN carrera c ON e.id_carrera = c.id_carrera
    WHERE u.id_usuario = ?
");
$stmt->execute([$id_estudiante]);
$datos = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Perfil - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/perfil_estudiante.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
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

    <a href="perfil.php" class="active">
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
        <h2>Mi Perfil</h2>
        <a class="btn-link" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Volver al dashboard</a>
    </div>

    <?php if ($mensaje): ?><div class="msg-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="perfil-grid">

        <!-- DATOS ACADÉMICOS -->
        <div class="bloque">
            <div class="perfil-header">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nombre_completo ?: $_SESSION['nombre']) ?>&background=4f6df5&color=fff&size=64">
                <div>
                    <h3><?= htmlspecialchars($nombre_completo) ?></h3>
                    <span class="chip-estado <?= $datos['estado'] === 'activo' ? 'activo' : 'inactivo' ?>"><?= ucfirst($datos['estado']) ?></span>
                </div>
            </div>
            <table class="info">
                <tr><td><i class="fa-solid fa-id-card"></i> CI</td><td><?= htmlspecialchars($datos['ci']) ?></td></tr>
                <tr><td><i class="fa-solid fa-envelope"></i> Correo</td><td><?= htmlspecialchars($datos['email']) ?></td></tr>
                <tr><td><i class="fa-solid fa-graduation-cap"></i> Carrera</td><td><?= htmlspecialchars($datos['nombre_carrera']) ?> (<?= htmlspecialchars($datos['facultad']) ?>)</td></tr>
                <tr><td><i class="fa-solid fa-hashtag"></i> Registro universitario</td><td><?= htmlspecialchars($datos['registro_universitario']) ?></td></tr>
                <tr><td><i class="fa-solid fa-layer-group"></i> Semestre</td><td><?= htmlspecialchars($datos['semestre']) ?></td></tr>
                <tr><td><i class="fa-regular fa-calendar"></i> Registrado desde</td><td><?= htmlspecialchars(date('d/m/Y', strtotime($datos['fecha_registro']))) ?></td></tr>
            </table>
            <p class="nota"><i class="fa-solid fa-circle-info"></i> La carrera y el semestre son gestionados por coordinación académica.</p>
        </div>

        <!-- EDITAR DATOS -->
        <div class="bloque">
            <h3>Editar mis datos</h3>
            <form method="POST">
                <label for="telefono">Teléfono</label>
                <input type="text" name="telefono" id="telefono" value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>">

                <label for="password_nueva">Nueva contraseña (opcional)</label>
                <input type="password" name="password_nueva" id="password_nueva" placeholder="Dejar en blanco para no cambiar">

                <label for="password_confirmar">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmar" id="password_confirmar">

                <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
            </form>
        </div>

    </div>

</div>

</body>
</html>