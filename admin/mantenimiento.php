<?php
// admin/mantenimiento.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header('Location: ../auth/login.php');
    exit;
}

$mensaje = '';
$error = '';

// Ejecutar ocultamiento de materiales antiguos (>365 dias)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ocultar_materiales') {
    try {
        $pdo->exec("CALL sp_ocultar_materiales_antiguos()");
        $mensaje = 'Materiales con más de un año de antigüedad ocultados correctamente.';
    } catch (Exception $e) {
        $error = 'Error al ejecutar el mantenimiento: ' . $e->getMessage();
    }
}

// Info de contexto: cuantos materiales visibles hay actualmente
$total_visibles = $pdo->query("SELECT COUNT(*) FROM material_apoyo WHERE visible='si'")->fetchColumn();
$total_ocultos = $pdo->query("SELECT COUNT(*) FROM material_apoyo WHERE visible='no'")->fetchColumn();

// Materiales candidatos a ocultarse (mas de 365 dias, aun visibles)
$candidatos = $pdo->query("
    SELECT id_material, titulo, fecha_subida, DATEDIFF(NOW(), fecha_subida) AS dias
    FROM material_apoyo
    WHERE visible = 'si' AND DATEDIFF(NOW(), fecha_subida) > 365
    ORDER BY fecha_subida ASC
")->fetchAll();

// ===== Materiales de apoyo actualmente ocultos (Consulta 4 - Integrante 5) =====
// Esta consulta se tomó del archivo consultar_n.sql como una consulta directa.
// Se usa aquí para mostrar materiales de apoyo que están ocultos.
$materiales_ocultos = $pdo->query("
    SELECT titulo, tipo_archivo, fecha_subida
    FROM material_apoyo WHERE visible = 'no'
    ORDER BY fecha_subida DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mantenimiento - Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/reportes.css">
<link rel="stylesheet" href="../assets/css/mantenimiento.css">
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

    <a href="mantenimiento.php" class="active">
        <i class="fa-solid fa-screwdriver-wrench"></i><span>Mantenimiento</span>
    </a>

    <a href="perfil.php">
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
        <h2>Mantenimiento</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta alerta-ok"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- TARJETAS RESUMEN -->
    <div class="grid-cards">
        <div class="card-stat">
            <div class="card-icono verde"><i class="fa-solid fa-eye"></i></div>
            <div>
                <div class="card-numero"><?= $total_visibles ?></div>
                <div class="card-label">Materiales visibles</div>
            </div>
        </div>
        <div class="card-stat">
            <div class="card-icono gris"><i class="fa-solid fa-eye-slash"></i></div>
            <div>
                <div class="card-numero"><?= $total_ocultos ?></div>
                <div class="card-label">Materiales ocultos</div>
            </div>
        </div>
        <div class="card-stat">
            <div class="card-icono naranja"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div>
                <div class="card-numero"><?= count($candidatos) ?></div>
                <div class="card-label">Pendientes de ocultar (&gt;1 año)</div>
            </div>
        </div>
    </div>

    <!-- GRID DE BLOQUES -->
    <div class="secciones">

        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-broom"></i> Ocultar materiales antiguos</h3>
            <p class="descripcion">
                Esta acción marca como <strong>no visibles</strong> los materiales de apoyo con más de 365 días
                desde su subida. Los archivos no se eliminan, solo dejan de mostrarse a tutores y estudiantes.
            </p>

            <?php if (count($candidatos) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-check"></i> No hay materiales pendientes de ocultar.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Título</th><th>Fecha de subida</th><th>Días</th></tr></thead>
                    <tbody>
                        <?php foreach ($candidatos as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['titulo']) ?></td>
                            <td><?= htmlspecialchars($m['fecha_subida']) ?></td>
                            <td><span class="contador"><?= htmlspecialchars($m['dias']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="POST" class="form-mantenimiento">
                    <input type="hidden" name="accion" value="ocultar_materiales">
                    <button type="submit" class="btn btn-naranja"
                        onclick="return confirm('¿Ocultar todos los materiales con más de un año de antigüedad?');">
                        <i class="fa-solid fa-broom"></i> Ejecutar mantenimiento
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="bloque bloque-ancho">
            <h3><i class="fa-solid fa-box-archive"></i> Materiales actualmente ocultos</h3>
            <?php if (count($materiales_ocultos) === 0): ?>
                <p class="vacio"><i class="fa-solid fa-circle-info"></i> No hay materiales ocultos en este momento.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Título</th><th>Tipo</th><th>Fecha de subida</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($materiales_ocultos as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['titulo']) ?></td>
                            <td><span class="badge-tipo"><?= htmlspecialchars(strtoupper($m['tipo_archivo'])) ?></span></td>
                            <td><?= htmlspecialchars($m['fecha_subida']) ?></td>
                            <td><span class="badge-oculto">Oculto</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

</div>
</body>
</html>