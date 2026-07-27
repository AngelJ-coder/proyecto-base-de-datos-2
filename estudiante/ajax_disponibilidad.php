<?php
// estudiante/ajax_disponibilidad.php
// Endpoint NUEVO — no modifica ningun archivo existente.
// Devuelve, en JSON, cuantos tutores hay disponibles para un area y turno dados.

session_start();
require_once '../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_area = $_GET['id_area'] ?? null;
$turno   = $_GET['turno'] ?? null;

if (!$id_area || !$turno) {
    echo json_encode(['error' => 'Parametros incompletos']);
    exit;
}

try {
    // Se apoya en la tabla tutor_area + disponibilidad_tutor + usuario,
    // el mismo criterio que usa sp_tutores_disponibles, pero sin modalidad fija
    // para no obligar al estudiante a elegir modalidad en este formulario.
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id_usuario, u.nombre, u.apellido, ta.nivel_experiencia, dt.modalidad
        FROM tutor t
        JOIN usuario u ON t.id_tutor = u.id_usuario
        JOIN tutor_area ta ON t.id_tutor = ta.id_tutor
        JOIN disponibilidad_tutor dt ON t.id_tutor = dt.id_tutor
        WHERE ta.id_area = ?
          AND dt.turno = ?
          AND dt.estado = 'activo'
          AND u.estado = 'activo'
    ");
    $stmt->execute([$id_area, $turno]);
    $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'total' => count($tutores),
        'tutores' => $tutores
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar disponibilidad']);
}