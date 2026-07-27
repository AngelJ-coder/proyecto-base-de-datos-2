<?php
// tutor/material_apoyo.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'tutor') {
    header('Location: ../auth/login.php');
    exit;
}

$id_tutor = $_SESSION['id_usuario'];
$mensaje = '';
$error = '';

$tipos_validos = ['pdf','docx','pptx','xlsx'];
$carpeta_subidas = '../uploads/';

// Áreas que dicta el tutor
$stmt = $pdo->prepare("
    SELECT a.id_area, a.nombre_area
    FROM tutor_area ta
    JOIN area_academica a ON ta.id_area = a.id_area
    WHERE ta.id_tutor = ?
    ORDER BY a.nombre_area
");
$stmt->execute([$id_tutor]);
$mis_areas = $stmt->fetchAll();
$ids_mis_areas = array_column($mis_areas, 'id_area');

// Total de materiales visibles por area
$stmt_tot = $pdo->prepare("SELECT fn_total_materiales_visibles_area(?) AS total");
foreach ($mis_areas as &$a) {
    $stmt_tot->execute([$a['id_area']]);
    $a['total_materiales_visibles'] = $stmt_tot->fetchColumn();
}
unset($a);

// --- Subir nuevo material ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'subir') {
    $id_area = (int) ($_POST['id_area'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!in_array($id_area, $ids_mis_areas, true)) {
        $error = 'Solo puedes subir material a áreas que dictas.';
    } elseif ($titulo === '') {
        $error = 'El título es obligatorio.';
    } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Debes seleccionar un archivo válido.';
    } else {
        $nombre_original = $_FILES['archivo']['name'];
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

        if (!in_array($extension, $tipos_validos, true)) {
            $error = 'Tipo de archivo no permitido. Solo se aceptan: PDF, DOCX, PPTX, XLSX.';
        } else {
            if (!is_dir($carpeta_subidas)) {
                mkdir($carpeta_subidas, 0755, true);
            }
            $nombre_archivo = uniqid('mat_') . '.' . $extension;
            $ruta_destino = $carpeta_subidas . $nombre_archivo;

            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_destino)) {
                $tamano_kb = (int) round(filesize($ruta_destino) / 1024);
                try {
                    $stmt = $pdo->prepare("CALL sp_subir_material(?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_area, $id_tutor, $titulo, $descripcion ?: null, $ruta_destino, $extension, $tamano_kb]);
                    $stmt->closeCursor();
                    $mensaje = 'Material subido correctamente.';
                } catch (PDOException $e) {
                    $error = 'No se pudo guardar el material: ' . $e->getMessage();
                }
            } else {
                $error = 'Ocurrió un error al subir el archivo.';
            }
        }
    }
}

// --- Editar título/descripción ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id_material = (int) ($_POST['id_material'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($titulo !== '') {
        $stmt = $pdo->prepare("UPDATE material_apoyo SET titulo = ?, descripcion = ? WHERE id_material = ? AND id_tutor = ?");
        $stmt->execute([$titulo, $descripcion, $id_material, $id_tutor]);
        $mensaje = 'Material actualizado.';
    }
}

// --- Ocultar / mostrar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_visible') {
    $id_material = (int) ($_POST['id_material'] ?? 0);
    $stmt = $pdo->prepare("SELECT visible FROM material_apoyo WHERE id_material = ? AND id_tutor = ?");
    $stmt->execute([$id_material, $id_tutor]);
    $actual = $stmt->fetchColumn();
    if ($actual !== false) {
        $nuevo = $actual === 'si' ? 'no' : 'si';
        $stmt = $pdo->prepare("UPDATE material_apoyo SET visible = ? WHERE id_material = ? AND id_tutor = ?");
        $stmt->execute([$nuevo, $id_material, $id_tutor]);
        $mensaje = 'Visibilidad actualizada.';
    }
}

// --- Borrar material ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'borrar') {
    $id_material = (int) ($_POST['id_material'] ?? 0);

    $stmt = $pdo->prepare("SELECT ruta_archivo FROM material_apoyo WHERE id_material = ? AND id_tutor = ?");
    $stmt->execute([$id_material, $id_tutor]);
    $material = $stmt->fetch();

    if ($material) {
        $stmt = $pdo->prepare("DELETE FROM material_apoyo WHERE id_material = ? AND id_tutor = ?");
        $stmt->execute([$id_material, $id_tutor]);

        if (!empty($material['ruta_archivo']) && file_exists($material['ruta_archivo'])) {
            unlink($material['ruta_archivo']);
        }

        $mensaje = 'Material eliminado correctamente.';
    } else {
        $error = 'No se encontró el material a eliminar.';
    }
}

// Listar materiales del tutor
$stmt = $pdo->prepare("
    SELECT m.id_material, m.titulo, m.descripcion, m.tipo_archivo, m.tamano_kb,
        m.fecha_subida, m.visible, m.ruta_archivo, a.nombre_area
    FROM material_apoyo m
    JOIN area_academica a ON m.id_area = a.id_area
    WHERE m.id_tutor = ?
    ORDER BY m.fecha_subida DESC
");
$stmt->execute([$id_tutor]);
$mis_materiales = $stmt->fetchAll();

// Cantidad de materiales por tipo de archivo
$stmt = $pdo->prepare("
    SELECT tipo_archivo, COUNT(*) AS total
    FROM material_apoyo
    WHERE id_tutor = ?
    GROUP BY tipo_archivo
");
$stmt->execute([$id_tutor]);
$materiales_por_tipo = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material de Apoyo - Tutor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor_material.css">
    <style>
        .btn-action.delete{
    color:var(--red);
}
.btn-action.delete:hover{
    background:var(--red-soft);
}
    </style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" >
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

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="numero"><?= count($mis_materiales) ?></div>
            <div class="label">Materiales totales</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count(array_filter($mis_materiales, fn($m) => $m['visible'] === 'si')) ?></div>
            <div class="label">Materiales visibles</div>
        </div>
        <div class="stat-card">
            <div class="numero"><?= count($mis_areas) ?></div>
            <div class="label">Áreas con material</div>
        </div>
    </div>

    <!-- SECCIONES -->
    <div class="secciones">

        <!-- SUBIR MATERIAL -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>
                <i class="fa-solid fa-cloud-arrow-up"></i> Subir Nuevo Material
            </h3>
            <?php if (count($mis_areas) === 0): ?>
                <p class="vacio">
                    <i class="fa-solid fa-info-circle"></i> Primero debes tener al menos un área asignada para poder subir material.
                    <a href="mis_areas.php" class="btn-link">
                        <i class="fa-solid fa-arrow-right"></i> Ir a Mis Áreas
                    </a>
                </p>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="form-subir">
                    <input type="hidden" name="accion" value="subir">
                    <div class="form-group">
                        <label for="id_area">
                            <i class="fa-solid fa-bookmark"></i> Área
                        </label>
                        <select name="id_area" id="id_area" required>
                            <?php foreach ($mis_areas as $a): ?>
                                <option value="<?= $a['id_area'] ?>">
                                    <?= htmlspecialchars($a['nombre_area']) ?> (<?= $a['total_materiales_visibles'] ?> materiales)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="archivo">
                            <i class="fa-solid fa-file"></i> Archivo (PDF, DOCX, PPTX, XLSX)
                        </label>
                        <input type="file" name="archivo" id="archivo" accept=".pdf,.docx,.pptx,.xlsx" required>
                        <span class="file-hint">Máximo 50 MB</span>
                    </div>
                    <div class="form-group full">
                        <label for="titulo">
                            <i class="fa-solid fa-heading"></i> Título
                        </label>
                        <input type="text" name="titulo" id="titulo" maxlength="150" required placeholder="Ej: Apuntes de Álgebra Lineal">
                    </div>
                    <div class="form-group full">
                        <label for="descripcion">
                            <i class="fa-solid fa-note-sticky"></i> Descripción (Opcional)
                        </label>
                        <textarea name="descripcion" id="descripcion" maxlength="255" placeholder="Descripción breve del material..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-upload"></i> Subir Material
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- RESUMEN POR TIPO -->
        <div class="bloque">
            <h3>
                <i class="fa-solid fa-chart-bar"></i> Materiales por Tipo
            </h3>
            <div class="type-grid">
                <?php if (count($materiales_por_tipo) === 0): ?>
                    <p class="vacio">
                        <i class="fa-solid fa-inbox"></i> Aún no has subido materiales
                    </p>
                <?php else: ?>
                    <?php foreach ($materiales_por_tipo as $t): ?>
                    <div class="type-card <?= $t['tipo_archivo'] ?>">
                        <div class="type-icon">
                            <i class="fa-solid fa-file-<?= $t['tipo_archivo'] === 'pdf' ? 'pdf' : 'word' ?>"></i>
                        </div>
                        <div class="type-info">
                            <div class="type-name"><?= strtoupper($t['tipo_archivo']) ?></div>
                            <div class="type-count"><?= $t['total'] ?> archivo<?= $t['total'] != 1 ? 's' : '' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- MIS MATERIALES -->
        <div class="bloque" style="grid-column: 1 / -1;">
            <h3>
                <i class="fa-solid fa-library"></i> Mis Materiales
            </h3>
            <div class="table-wrapper">
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Área</th>
                            <th>Tipo</th>
                            <th>Tamaño</th>
                            <th>Subido</th>
                            <th>Visible</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($mis_materiales) === 0): ?>
                            <tr>
                                <td colspan="7" class="vacio">
                                    <i class="fa-solid fa-folder"></i> Aún no has subido materiales
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mis_materiales as $m): ?>
                            <tr>
                                <td>
                                    <div class="material-title">
                                        <strong><?= htmlspecialchars($m['titulo']) ?></strong>
                                        <?php if ($m['descripcion']): ?>
                                            <div class="material-desc"><?= htmlspecialchars(substr($m['descripcion'], 0, 60)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($m['nombre_area']) ?></td>
                                <td>
                                    <span class="badge <?= $m['tipo_archivo'] ?>">
                                        <i class="fa-solid fa-file-<?= $m['tipo_archivo'] === 'pdf' ? 'pdf' : 'word' ?>"></i>
                                        <?= strtoupper($m['tipo_archivo']) ?>
                                    </span>
                                </td>
                                <td class="size"><?= $m['tamano_kb'] ? $m['tamano_kb'].' KB' : '—' ?></td>
                                <td class="date"><?= $m['fecha_subida'] ?></td>
                                <td>
                                    <span class="badge visible-<?= $m['visible'] ?>">
                                        <i class="fa-solid fa-<?= $m['visible'] === 'si' ? 'eye' : 'eye-slash' ?>"></i>
                                        <?= $m['visible'] === 'si' ? 'Visible' : 'Oculto' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?= htmlspecialchars($m['ruta_archivo']) ?>" target="_blank" class="btn-action ver" title="Ver material">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn-action edit" onclick="document.getElementById('editar-<?= $m['id_material'] ?>').classList.toggle('abierta')">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="accion" value="toggle_visible">
                                            <input type="hidden" name="id_material" value="<?= $m['id_material'] ?>">
                                            <button type="submit" class="btn-action toggle" title="<?= $m['visible'] === 'si' ? 'Ocultar' : 'Mostrar' ?>">
                                                <i class="fa-solid fa-<?= $m['visible'] === 'si' ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este material? Esta acción no se puede deshacer.');">
                                            <input type="hidden" name="accion" value="borrar">
                                            <input type="hidden" name="id_material" value="<?= $m['id_material'] ?>">
                                            <button type="submit" class="btn-action delete" title="Eliminar material">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="edit-row" id="editar-<?= $m['id_material'] ?>">
                                <td colspan="7">
                                    <form method="post" class="form-editar">
                                        <input type="hidden" name="accion" value="editar">
                                        <input type="hidden" name="id_material" value="<?= $m['id_material'] ?>">
                                        <div class="edit-fields">
                                            <div class="form-group">
                                                <label>Título</label>
                                                <input type="text" name="titulo" value="<?= htmlspecialchars($m['titulo']) ?>" maxlength="150" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Descripción</label>
                                                <textarea name="descripcion" maxlength="255"><?= htmlspecialchars($m['descripcion'] ?? '') ?></textarea>
                                            </div>
                                            <button type="submit" class="btn-save">
                                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

</body>
</html>