<?php
// coordinador/exportar_reporte.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

$fecha_ini = $_GET['fecha_ini'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$id_carrera = $_GET['id_carrera'] ?? '';

$sql = "SELECT * FROM vw_reporte_tutorias WHERE fecha BETWEEN ? AND ?";
$params = [$fecha_ini, $fecha_fin];
if ($id_carrera !== '') {
    $sql .= " AND nombre_carrera = (SELECT nombre_carrera FROM carrera WHERE id_carrera = ?)";
    $params[] = $id_carrera;
}
$sql .= " ORDER BY fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$detalle = $stmt->fetchAll();

$nombre_archivo = 'reporte_tutorias_' . $fecha_ini . '_a_' . $fecha_fin . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

$salida = fopen('php://output', 'w');

// BOM para que Excel reconozca UTF-8 correctamente
fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($salida, [
    'ID Sesion', 'Fecha', 'Estudiante', 'Tutor', 'Area', 'Carrera',
    'Turno', 'Modalidad', 'Estado', 'Calificacion'
]);

foreach ($detalle as $d) {
    fputcsv($salida, [
        $d['id_sesion'],
        $d['fecha'],
        $d['estudiante'],
        $d['tutor'],
        $d['nombre_area'],
        $d['nombre_carrera'],
        ucfirst($d['turno']),
        ucfirst($d['modalidad']),
        ucfirst($d['estado']),
        $d['calificacion'] !== null ? number_format($d['calificacion'], 2) : '',
    ]);
}

fclose($salida);
exit;