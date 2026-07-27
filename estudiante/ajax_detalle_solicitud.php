<?php
// estudiante/ajax_detalle_solicitud.php
// Archivo NUEVO — no modifica ningun archivo existente.
// Devuelve en JSON el detalle completo de una solicitud/tutoria asignada.

session_start();
require_once '../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$id_solicitud = $_GET['id_solicitud'] ?? null;

if (!$id_solicitud) {
    echo json_encode(['error' => 'Parametro incompleto']);
    exit;
}

try {
    // Verificar que la solicitud pertenece al estudiante
    $stmt = $pdo->prepare("
        SELECT s.id_solicitud, s.turno, s.motivo, s.estado, s.fecha_solicitud, a.nombre_area
        FROM solicitud_tutoria s
        JOIN area_academica a ON s.id_area = a.id_area
        WHERE s.id_solicitud = ? AND s.id_estudiante = ?
    ");
    $stmt->execute([$id_solicitud, $id_estudiante]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        echo json_encode(['error' => 'Solicitud no encontrada']);
        exit;
    }

    // Plan de tutoria
    $stmt = $pdo->prepare("
        SELECT id_plan, objetivo, fecha_inicio, fecha_fin_estimada, numero_sesiones_estimadas, estado
        FROM plan_tutoria WHERE id_solicitud = ?
    ");
    $stmt->execute([$id_solicitud]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    $sesiones = [];
    if ($plan) {
        // Sesiones del plan, con tutor y detalle de modalidad
        $stmt = $pdo->prepare("
            SELECT st.id_sesion, st.fecha, st.hora_inicio, st.hora_fin, st.modalidad, st.estado,
                   CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                   dm.aula, dm.edificio, dm.ubicacion, dm.plataforma, dm.enlace,
                   ev.calificacion, ev.observaciones, ev.recomendaciones
            FROM sesion_tutoria st
            JOIN tutor t ON st.id_tutor = t.id_tutor
            JOIN usuario u ON t.id_tutor = u.id_usuario
            LEFT JOIN detalle_modalidad_sesion dm ON dm.id_sesion = st.id_sesion
            LEFT JOIN evaluacion_sesion ev ON ev.id_sesion = st.id_sesion AND ev.id_estudiante = ?
            WHERE st.id_plan = ?
            ORDER BY st.fecha, st.hora_inicio
        ");
        $stmt->execute([$id_estudiante, $plan['id_plan']]);
        $sesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'solicitud' => $solicitud,
        'plan' => $plan ?: null,
        'sesiones' => $sesiones
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar el detalle']);
}