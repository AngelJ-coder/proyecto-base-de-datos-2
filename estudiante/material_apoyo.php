<?php
// estudiante/material_apoyo.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];

// Áreas de la carrera del estudiante, con el conteo de materiales visibles vía fn_total_materiales_area()
$stmt = $pdo->prepare("
    SELECT id_area, nombre_area, fn_total_materiales_area(id_area) AS total_materiales
    FROM area_academica
    WHERE id_carrera = (SELECT id_carrera FROM estudiante WHERE id_estudiante = ?)
    ORDER BY nombre_area
");
$stmt->execute([$id_estudiante]);
$areas = $stmt->fetchAll();

// Cantidad de materiales por tutor en las áreas de la carrera del estudiante
$stmt = $pdo->prepare("
    SELECT CONCAT(u.nombre,' ',u.apellido) AS tutor, COUNT(m.id_material) AS total_materiales
    FROM material_apoyo m
    JOIN tutor t ON m.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    JOIN area_academica a ON m.id_area = a.id_area
    WHERE m.visible = 'si'
      AND a.id_carrera = (SELECT id_carrera FROM estudiante WHERE id_estudiante = ?)
    GROUP BY t.id_tutor
    ORDER BY total_materiales DESC
");
$stmt->execute([$id_estudiante]);
$materiales_por_tutor = $stmt->fetchAll();

$filtro_area = $_GET['id_area'] ?? '';

// Listado de materiales visibles, filtrado opcionalmente por área
$sql = "
    SELECT m.titulo, m.descripcion, m.tipo_archivo, m.ruta_archivo, m.tamano_kb, m.fecha_subida,
        a.nombre_area, CONCAT(u.nombre,' ',u.apellido) AS tutor
    FROM material_apoyo m
    JOIN area_academica a ON m.id_area = a.id_area
    JOIN tutor t ON m.id_tutor = t.id_tutor
    JOIN usuario u ON t.id_tutor = u.id_usuario
    WHERE m.visible = 'si'
      AND a.id_carrera = (SELECT id_carrera FROM estudiante WHERE id_estudiante = ?)
";
$params = [$id_estudiante];
if ($filtro_area !== '') {
    $sql .= " AND a.id_area = ?";
    $params[] = $filtro_area;
}
$sql .= " ORDER BY m.fecha_subida DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materiales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Material de Apoyo - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/material_apoyo.css">
<style>
.mat-card .acciones-material{
    display:flex;
    gap:10px;
    align-self:flex-start;
}

.mat-card a.ver,
.mat-card a.descargar{
    font-weight:700;
    text-decoration:none;
    font-size:.82rem;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 12px;
    border-radius:8px;
    transition:.18s ease;
}

.mat-card a.ver{
    color:var(--accent);
    background:var(--accent-soft);
}

.mat-card a.ver:hover{
    background:var(--accent);
    color:#ffffff;
}

.mat-card a.descargar{
    color:var(--green);
    background:var(--green-soft);
}

.mat-card a.descargar:hover{
    background:var(--green);
    color:#ffffff;
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

    <a href="material_apoyo.php" class="active">
        <i class="fa-solid fa-folder-open"></i><span>Material de Apoyo</span>
    </a>

    <a href="perfil.php">
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
        <h2>Material de Apoyo</h2>
        <a class="btn-link" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Volver al dashboard</a>
    </div>

    <!-- BLOQUE: MATERIALES POR TUTOR -->
    <div class="bloque" style="margin-bottom:20px;">
        <h3>Materiales por tutor</h3>
        <table>
            <thead><tr><th>Tutor</th><th>Materiales</th></tr></thead>
            <tbody>
                <?php if (count($materiales_por_tutor) === 0): ?>
                    <tr><td colspan="2" class="vacio">No hay materiales disponibles todavía.</td></tr>
                <?php else: ?>
                    <?php foreach ($materiales_por_tutor as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['tutor']) ?></td>
                        <td><?= $t['total_materiales'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- FILTROS POR ÁREA -->
    <div class="filtros-areas">
        <a href="material_apoyo.php" class="chip-area <?= $filtro_area === '' ? 'activo' : '' ?>">Todas las áreas</a>
        <?php foreach ($areas as $a): ?>
            <a href="material_apoyo.php?id_area=<?= $a['id_area'] ?>" class="chip-area <?= $filtro_area == $a['id_area'] ? 'activo' : '' ?>">
                <?= htmlspecialchars($a['nombre_area']) ?> (<?= $a['total_materiales'] ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- GRID DE MATERIALES -->
    <div class="grid-materiales">
        <?php if (count($materiales) === 0): ?>
            <div class="bloque">
                <p class="vacio">No hay materiales disponibles con este filtro.</p>
            </div>
        <?php else: ?>
            <?php foreach ($materiales as $m): ?>
            <div class="mat-card">
                <span class="tipo <?= $m['tipo_archivo'] ?>"><?= strtoupper($m['tipo_archivo']) ?></span>
                <h4><?= htmlspecialchars($m['titulo']) ?></h4>
                <div class="desc"><?= htmlspecialchars($m['descripcion'] ?: 'Sin descripción.') ?></div>
                <div class="meta">
                    <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($m['nombre_area']) ?><br>
                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($m['tutor']) ?><br>
                    <i class="fa-regular fa-calendar"></i> <?= htmlspecialchars(date('d/m/Y', strtotime($m['fecha_subida']))) ?><br>
                    <?php if ($m['tamano_kb']): ?>
                        <i class="fa-solid fa-weight-hanging"></i> <?= $m['tamano_kb'] ?> KB
                    <?php endif; ?>
                </div>
                <div class="acciones-material">
                    <a class="ver" href="<?= htmlspecialchars($m['ruta_archivo']) ?>" target="_blank">
                        <i class="fa-solid fa-eye"></i> Ver
                    </a>
                    <a class="descargar" href="<?= htmlspecialchars($m['ruta_archivo']) ?>" download>
                        <i class="fa-solid fa-download"></i> Descargar
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>