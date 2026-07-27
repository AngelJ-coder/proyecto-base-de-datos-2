<?php
// index.php
session_start();
require_once 'config/conexion.php';

if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol'])) {
    header('Location: auth/login.php');
    exit;
}

switch ($_SESSION['rol']) {
    case 'administrador':
        header('Location: admin/usuarios.php');
        break;
    case 'coordinador':
        header('Location: coordinador/solicitudes.php');
        break;
    case 'tutor':
        header('Location: tutor/sesiones.php');
        break;
    case 'estudiante':
        header('Location: estudiante/mis_sesiones.php');
        break;
    default:
        header('Location: auth/login.php');
        break;
}
exit;