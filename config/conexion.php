<?php
// config/conexion.php

$host = 'localhost';
$db   = 'tutorias_academicas';
$user = 'root';
$pass = ''; // en XAMPP por defecto no tiene contraseña
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Unificar la codificación y collation de la conexión
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_spanish_ci");

} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}