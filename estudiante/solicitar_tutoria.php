<?php
// estudiante/solicitar_tutoria.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: ../auth/login.php');
    exit;
}

$id_estudiante = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

// Obtener id_carrera del estudiante
$stmt = $pdo->prepare("SELECT id_carrera FROM estudiante WHERE id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$id_carrera = $stmt->fetchColumn();

// Áreas académicas de su carrera, vía la vista vw_areas_por_carrera
$stmt = $pdo->prepare("
    SELECT v.id_area, v.nombre_area, v.descripcion
    FROM vw_areas_por_carrera v
    JOIN carrera c ON v.nombre_carrera = c.nombre_carrera
    WHERE c.id_carrera = ?
    ORDER BY v.nombre_area
");
$stmt->execute([$id_carrera]);
$areas_disponibles = $stmt->fetchAll();

// Procesar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_area = $_POST['id_area'] ?? null;
    $turno = $_POST['turno'] ?? null;
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id_area || !$turno || $motivo === '') {
        $error = 'Todos los campos son obligatorios.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO solicitud_tutoria (id_estudiante, id_area, turno, motivo, estado)
                VALUES (?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$id_estudiante, $id_area, $turno, $motivo]);
            $mensaje = 'Tu solicitud de tutoría fue registrada correctamente. Un coordinador la revisará pronto.';
        } catch (PDOException $e) {
            $error = 'Ocurrió un error al registrar tu solicitud. Intenta nuevamente.';
        }
    }
}

// Nombre de carrera para mostrar en el área (fn_nombre_carrera) junto a cada opción
function nombreCarreraDeArea($pdo, $id_area) {
    $stmt = $pdo->prepare("SELECT fn_nombre_carrera(?) AS carrera");
    $stmt->execute([$id_area]);
    return $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitar Tutoría - Estudiante</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/estudiante_solicitar.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php">
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitar_tutoria.php" class="active">
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
        <h2>Solicitar Tutoría</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
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

    <div class="bloque form-bloque">
        <h3><i class="fa-solid fa-user-graduate"></i> Nueva solicitud de tutoría</h3>

        <?php if (count($areas_disponibles) === 0): ?>
            <p class="vacio">
                <i class="fa-solid fa-info-circle"></i> Aún no hay áreas académicas registradas para tu carrera.
            </p>
        <?php else: ?>
            <form method="POST" class="form-solicitud">
                <div class="form-group full">
                    <label for="id_area">
                        <i class="fa-solid fa-bookmark"></i> Área académica
                    </label>
                    <select name="id_area" id="id_area" required>
                        <option value="">-- Selecciona un área --</option>
                        <?php foreach ($areas_disponibles as $a): ?>
                            <option value="<?= $a['id_area'] ?>"><?= htmlspecialchars($a['nombre_area']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Se muestran solo las áreas de tu carrera: <?= htmlspecialchars(nombreCarreraDeArea($pdo, $areas_disponibles[0]['id_area'])) ?></span>
                </div>

                <div class="form-group full">
                    <label for="turno">
                        <i class="fa-solid fa-clock"></i> Turno preferido
                    </label>
                    <select name="turno" id="turno" required>
                        <option value="">-- Selecciona un turno --</option>
                        <option value="mañana">Mañana</option>
                        <option value="tarde">Tarde</option>
                        <option value="noche">Noche</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="motivo">
                        <i class="fa-solid fa-note-sticky"></i> Motivo de la solicitud
                    </label>
                    <textarea name="motivo" id="motivo" placeholder="Describe brevemente en qué necesitas apoyo..." required></textarea>
                </div>
                <!-- BLOQUE NUEVO: sugerencia de disponibilidad en tiempo real -->
<div class="form-group full" id="disponibilidad-box" style="display:none;">
    <div class="disp-info">
        <i class="fa-solid fa-circle-info"></i>
        <span id="disponibilidad-texto">Consultando disponibilidad...</span>
    </div>
</div>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Enviar solicitud
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>
<!-- SCRIPT NUEVO: agregar justo antes de </body> -->
<script>
(function(){
    const selArea = document.getElementById('id_area');
    const selTurno = document.getElementById('turno');
    const box = document.getElementById('disponibilidad-box');
    const texto = document.getElementById('disponibilidad-texto');

    async function consultarDisponibilidad(){
        const idArea = selArea.value;
        const turno = selTurno.value;

        if(!idArea || !turno){
            box.style.display = 'none';
            return;
        }

        box.style.display = 'flex';
        texto.textContent = 'Consultando disponibilidad...';
        box.classList.remove('disp-ok','disp-warn');

        try{
            const resp = await fetch(`ajax_disponibilidad.php?id_area=${encodeURIComponent(idArea)}&turno=${encodeURIComponent(turno)}`);
            const data = await resp.json();

            if(data.error){
                texto.textContent = 'No se pudo consultar la disponibilidad.';
                return;
            }

            if(data.total > 0){
                box.classList.add('disp-ok');
                texto.textContent = `${data.total} tutor(es) disponible(s) en este turno para esta área.`;
            } else {
                box.classList.add('disp-warn');
                texto.textContent = 'No hay tutores disponibles en este turno para esta área. Puedes elegir otro turno o enviar igual tu solicitud.';
            }
        } catch(e){
            texto.textContent = 'No se pudo consultar la disponibilidad.';
        }
    }

    selArea.addEventListener('change', consultarDisponibilidad);
    selTurno.addEventListener('change', consultarDisponibilidad);
})();
</script>
</body>
</html>