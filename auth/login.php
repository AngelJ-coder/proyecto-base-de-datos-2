<?php
// auth/login.php
session_start();
require_once '../config/conexion.php';

$error = '';
$max_intentos = 5;
$tiempo_bloqueo = 300; // 5 minutos en segundos

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validar formato de email
        if ($email === '' || $password === '') {
            $error = 'Debes ingresar email y contraseña.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El formato del correo no es válido.';
        } else {

            // Control de intentos fallidos (protección contra fuerza bruta)
            $clave_intentos = 'intentos_' . md5($email);
            $clave_bloqueo  = 'bloqueo_' . md5($email);

            if (isset($_SESSION[$clave_bloqueo]) && time() < $_SESSION[$clave_bloqueo]) {
                $restante = $_SESSION[$clave_bloqueo] - time();
                $error = "Demasiados intentos fallidos. Intenta de nuevo en " . ceil($restante / 60) . " minuto(s).";
            } else {

                $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ? AND estado = 'activo'");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    // Si la contraseña guardada no es un hash valido (ej: texto plano),
                    // se convierte automaticamente a hash real y se actualiza en la BD
                    $info_hash = password_get_info($usuario['password']);
                    if (!$info_hash['algo']) {
                        $nuevo_hash = password_hash($usuario['password'], PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE usuario SET password = ? WHERE id_usuario = ?");
                        $upd->execute([$nuevo_hash, $usuario['id_usuario']]);
                        $usuario['password'] = $nuevo_hash;
                    }
                }

                if ($usuario && password_verify($password, $usuario['password'])) {

                    // Login correcto: limpiar intentos previos
                    unset($_SESSION[$clave_intentos]);
                    unset($_SESSION[$clave_bloqueo]);

                    // Regenerar ID de sesión para prevenir fixation
                    session_regenerate_id(true);

                    // Determinar el rol del usuario
                    $rol = null;

                    $stmt = $pdo->prepare("SELECT id_administrador FROM administrador WHERE id_administrador = ?");
                    $stmt->execute([$usuario['id_usuario']]);
                    if ($stmt->fetch()) $rol = 'administrador';

                    if (!$rol) {
                        $stmt = $pdo->prepare("SELECT id_coordinador FROM coordinador WHERE id_coordinador = ?");
                        $stmt->execute([$usuario['id_usuario']]);
                        if ($stmt->fetch()) $rol = 'coordinador';
                    }

                    if (!$rol) {
                        $stmt = $pdo->prepare("SELECT id_tutor FROM tutor WHERE id_tutor = ?");
                        $stmt->execute([$usuario['id_usuario']]);
                        if ($stmt->fetch()) $rol = 'tutor';
                    }

                    if (!$rol) {
                        $stmt = $pdo->prepare("SELECT id_estudiante FROM estudiante WHERE id_estudiante = ?");
                        $stmt->execute([$usuario['id_usuario']]);
                        if ($stmt->fetch()) $rol = 'estudiante';
                    }

                    if ($rol) {
                        $_SESSION['id_usuario'] = $usuario['id_usuario'];
                        $_SESSION['nombre']     = $usuario['nombre'];
                        $_SESSION['apellido']   = $usuario['apellido'];
                        $_SESSION['email']      = $usuario['email'];
                        $_SESSION['rol']        = $rol;

                        switch ($rol) {
                            case 'administrador':
                                header('Location: ../admin/dashboard.php');
                                break;
                            case 'coordinador':
                                header('Location: ../coordinador/dashboard.php');
                                break;
                            case 'tutor':
                                header('Location: ../tutor/dashboard.php');
                                break;
                            case 'estudiante':
                                header('Location: ../estudiante/dashboard.php');
                                break;
                        }
                        exit;
                    } else {
                        $error = 'El usuario no tiene un rol asignado.';
                    }

                } else {
                    // Login fallido: incrementar contador de intentos
                    $_SESSION[$clave_intentos] = ($_SESSION[$clave_intentos] ?? 0) + 1;

                    if ($_SESSION[$clave_intentos] >= $max_intentos) {
                        $_SESSION[$clave_bloqueo] = time() + $tiempo_bloqueo;
                        $error = "Demasiados intentos fallidos. Cuenta bloqueada temporalmente por " . ($tiempo_bloqueo / 60) . " minutos.";
                    } else {
                        $intentos_restantes = $max_intentos - $_SESSION[$clave_intentos];
                        $error = "Email o contraseña incorrectos. Te quedan $intentos_restantes intento(s).";
                    }
                }
            }
        }
    }

    // Regenerar token CSRF tras cada intento de POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Tutorías Académicas</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        <p class="subtitulo">Sistema de Tutorías Académicas</p>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" id="formLogin" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="campo">
                <label for="email">Correo:</label>
                <input type="email" name="email" id="email" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       autocomplete="username">
                <span class="mensaje-error" id="error-email"></span>
            </div>

            <div class="campo">
                <label for="password">Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required
                           autocomplete="current-password">
                    <button type="button" id="togglePassword" class="toggle-password" aria-label="Mostrar contraseña">👁</button>
                </div>
                <span class="mensaje-error" id="error-password"></span>
            </div>

            <button type="submit" id="btnSubmit">Ingresar</button>
        </form>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>