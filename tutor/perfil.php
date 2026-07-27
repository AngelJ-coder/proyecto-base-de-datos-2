<?php
// tutor/perfil.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

$modalidades_validas = ['presencial','virtual','ambas'];

// --- Actualizar datos personales ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_personales') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if ($nombre === '' || $apellido === '') {
        $error = 'Nombre y apellido son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, telefono = ? WHERE id_usuario = ?");
            $stmt->execute([$nombre, $apellido, $telefono, $id_tutor]);

            $_SESSION['nombre'] = $nombre;
            $mensaje = 'Datos personales actualizados correctamente.';
        } catch (PDOException $e) {
            $error = 'No se pudieron actualizar los datos personales.';
        }
    }
}

// --- Actualizar datos profesionales ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_profesionales') {
    $especialidad_principal = trim($_POST['especialidad_principal'] ?? '');
    $grado_academico = trim($_POST['grado_academico'] ?? '');
    $modalidad_preferida = $_POST['modalidad_preferida'] ?? 'ambas';

    if (!in_array($modalidad_preferida, $modalidades_validas, true)) {
        $modalidad_preferida = 'ambas';
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE tutor SET especialidad_principal = ?, grado_academico = ?, modalidad_preferida = ?
            WHERE id_tutor = ?
        ");
        $stmt->execute([$especialidad_principal, $grado_academico, $modalidad_preferida, $id_tutor]);

        $mensaje = 'Datos profesionales actualizados correctamente.';
    } catch (PDOException $e) {
        $error = 'No se pudieron actualizar los datos profesionales.';
    }
}

// --- Cambiar contraseña ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
    $actual = $_POST['password_actual'] ?? '';
    $nueva = $_POST['password_nueva'] ?? '';
    $confirmar = $_POST['password_confirmar'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$id_tutor]);
    $hash_actual = $stmt->fetchColumn();

    if (!password_verify($actual, $hash_actual)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } else {
        $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuario SET password = ? WHERE id_usuario = ?");
        $stmt->execute([$nuevo_hash, $id_tutor]);
        $mensaje = 'Contraseña actualizada correctamente.';
    }
}

// Cargar datos actuales
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, u.ci, u.email, u.telefono, u.fecha_registro,
        t.especialidad_principal, t.grado_academico, t.modalidad_preferida, t.disponible
    FROM usuario u
    JOIN tutor t ON t.id_tutor = u.id_usuario
    WHERE u.id_usuario = ?
");
$stmt->execute([$id_tutor]);
$datos = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor_perfil.css">
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="mis_sesiones.php">
        <i class="fa-solid fa-calendar-check"></i><span>Mis Sesiones</span>
    </a>

    <a href="disponibilidad.php">
        <i class="fa-solid fa-clock"></i><span>Disponibilidad</span>
    </a>

    <a href="mis_areas.php">
        <i class="fa-solid fa-book-open"></i><span>Mis Áreas</span>
    </a>

    <a href="registrar_evaluacion.php">
        <i class="fa-solid fa-star"></i><span>Evaluaciones</span>
    </a>

    <a href="marcar_asistencia.php" >
        <i class="fa-solid fa-clipboard-check"></i><span>Asistencia</span>
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


    <!-- BANNER DE PERFIL -->
    <div class="perfil-banner">
        <div class="perfil-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=ffffff&color=4f6df5&bold=true&size=128">
        </div>
        <div class="perfil-info">
            <h3><?= htmlspecialchars($datos['nombre'] . ' ' . $datos['apellido']) ?></h3>
            <p><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($datos['email']) ?></p>
            <div class="perfil-tags">
                <?php if (!empty($datos['especialidad_principal'])): ?>
                    <span class="tag"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($datos['especialidad_principal']) ?></span>
                <?php endif; ?>
                <span class="tag <?= $datos['disponible'] === 'si' ? 'tag-ok' : 'tag-off' ?>">
                    <i class="fa-solid fa-circle"></i> <?= $datos['disponible'] === 'si' ? 'Disponible' : 'No disponible' ?>
                </span>
            </div>
        </div>
    </div>
    <!-- ALERTS -->
    <?php if ($mensaje): ?>
        <div class="alerta-msg ok">
            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta-msg error">
            <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <!-- SECCIONES -->
    <div class="secciones">

        <!-- DATOS PERSONALES -->
        <div class="bloque">
            <h3>
                <i class="fa-solid fa-user-pen"></i> Datos Personales
            </h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="accion" value="actualizar_personales">

                <div class="form-group">
                    <label for="nombre">
                        <i class="fa-solid fa-user"></i> Nombre
                    </label>
                    <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($datos['nombre']) ?>" maxlength="80" required>
                </div>

                <div class="form-group">
                    <label for="apellido">
                        <i class="fa-solid fa-user"></i> Apellido
                    </label>
                    <input type="text" name="apellido" id="apellido" value="<?= htmlspecialchars($datos['apellido']) ?>" maxlength="80" required>
                </div>

                <div class="form-group readonly">
                    <label for="ci">
                        <i class="fa-solid fa-id-card"></i> Carnet de Identidad
                    </label>
                    <input type="text" id="ci" value="<?= htmlspecialchars($datos['ci']) ?>" disabled>
                    <span class="hint">No editable</span>
                </div>

                <div class="form-group readonly">
                    <label for="email">
                        <i class="fa-solid fa-envelope"></i> Correo Electrónico
                    </label>
                    <input type="text" id="email" value="<?= htmlspecialchars($datos['email']) ?>" disabled>
                    <span class="hint">No editable</span>
                </div>

                <div class="form-group full">
                    <label for="telefono">
                        <i class="fa-solid fa-phone"></i> Teléfono
                    </label>
                    <input type="text" name="telefono" id="telefono" value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>" maxlength="20" placeholder="Ej: +591 123 456 789">
                </div>

                <div class="form-group full">
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- DATOS PROFESIONALES -->
        <div class="bloque">
            <h3>
                <i class="fa-solid fa-briefcase"></i> Datos Profesionales
            </h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="accion" value="actualizar_profesionales">

                <div class="form-group full">
                    <label for="especialidad_principal">
                        <i class="fa-solid fa-graduation-cap"></i> Especialidad Principal
                    </label>
                    <input type="text" name="especialidad_principal" id="especialidad_principal" value="<?= htmlspecialchars($datos['especialidad_principal'] ?? '') ?>" maxlength="120" placeholder="Ej: Matemáticas Aplicadas">
                </div>

                <div class="form-group full">
                    <label for="grado_academico">
                        <i class="fa-solid fa-certificate"></i> Grado Académico
                    </label>
                    <input type="text" name="grado_academico" id="grado_academico" value="<?= htmlspecialchars($datos['grado_academico'] ?? '') ?>" maxlength="80" placeholder="Ej: Licenciado en Ciencias">
                </div>

                <div class="form-group">
                    <label for="modalidad_preferida">
                        <i class="fa-solid fa-chalkboard-user"></i> Modalidad Preferida
                    </label>
                    <select name="modalidad_preferida" id="modalidad_preferida">
                        <?php foreach ($modalidades_validas as $m): ?>
                            <option value="<?= $m ?>" <?= $datos['modalidad_preferida'] === $m ? 'selected' : '' ?>>
                                <?= $m === 'presencial' ? '📍 Presencial' : ($m === 'virtual' ? '💻 Virtual' : '📍 💻 Ambas') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group readonly">
                    <label for="fecha_registro">
                        <i class="fa-solid fa-calendar"></i> Fecha de Registro
                    </label>
                    <input type="text" id="fecha_registro" value="<?= htmlspecialchars($datos['fecha_registro']) ?>" disabled>
                    <span class="hint">No editable</span>
                </div>

                <div class="form-group full">
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- CAMBIAR CONTRASEÑA -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>
                <i class="fa-solid fa-lock"></i> Cambiar Contraseña
            </h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="accion" value="cambiar_password">

                <div class="form-group full">
                    <label for="password_actual">
                        <i class="fa-solid fa-key"></i> Contraseña Actual
                    </label>
                    <input type="password" name="password_actual" id="password_actual" required>
                </div>

                <div class="form-group">
                    <label for="password_nueva">
                        <i class="fa-solid fa-lock"></i> Nueva Contraseña
                    </label>
                    <input type="password" name="password_nueva" id="password_nueva" minlength="8" required>
                    <span class="hint">Mínimo 8 caracteres</span>
                </div>

                <div class="form-group">
                    <label for="password_confirmar">
                        <i class="fa-solid fa-lock"></i> Confirmar Contraseña
                    </label>
                    <input type="password" name="password_confirmar" id="password_confirmar" minlength="8" required>
                </div>

                <div class="form-group full">
                    <button type="submit" class="btn-submit danger">
                        <i class="fa-solid fa-shield"></i> Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

</body>
</html>