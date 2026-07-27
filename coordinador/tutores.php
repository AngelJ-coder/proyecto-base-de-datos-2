<?php
// coordinador/tutores.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: ../auth/login.php');
    exit;
}

// Filtros
$filtro_area = $_GET['id_area'] ?? '';
$filtro_disponible = $_GET['disponible'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$joins = [];
$where = ["u.estado = 'activo'"];
$params = [];

if ($filtro_area !== '') {
    $joins[] = "JOIN tutor_area ta_f ON t.id_tutor = ta_f.id_tutor";
    $where[] = "ta_f.id_area = ?";
    $params[] = $filtro_area;
}
if ($filtro_disponible !== '') {
    $where[] = "t.disponible = ?";
    $params[] = $filtro_disponible;
}
if ($buscar !== '') {
    $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR t.especialidad_principal LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql = "SELECT DISTINCT u.id_usuario AS id_tutor, u.nombre, u.apellido, u.email, u.telefono,
    t.especialidad_principal, t.grado_academico, t.disponible, t.modalidad_preferida
FROM tutor t
JOIN usuario u ON t.id_tutor = u.id_usuario
" . implode(' ', $joins) . "
WHERE " . implode(' AND ', $where) . "
ORDER BY u.nombre, u.apellido";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tutores = $stmt->fetchAll();

$areas = $pdo->query("SELECT id_area, nombre_area FROM area_academica ORDER BY nombre_area")->fetchAll();

$areas_por_tutor = [];
$disponibilidad_por_tutor = [];
$carga_por_tutor = [];

// Totales para las cards superiores
$total_tutores = count($tutores);
$total_disponibles = 0;
$total_no_disponibles = 0;

if ($total_tutores > 0) {
    $ids = array_column($tutores, 'id_tutor');
    $ids_flip = array_flip($ids);
    $in = implode(',', array_fill(0, count($ids), '?'));

    foreach ($tutores as $t) {
        if ($t['disponible'] === 'si') {
            $total_disponibles++;
        } else {
            $total_no_disponibles++;
        }
    }

    // Areas y nivel de experiencia por tutor
    $stmt = $pdo->prepare("
        SELECT ta.id_tutor, a.nombre_area, ta.nivel_experiencia
        FROM tutor_area ta
        JOIN area_academica a ON ta.id_area = a.id_area
        WHERE ta.id_tutor IN ($in)
        ORDER BY a.nombre_area
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $areas_por_tutor[$row['id_tutor']][] = $row;
    }

    // Disponibilidad via vw_disponibilidad_general (ahora con id_tutor), filtrada en PHP
    $stmt = $pdo->query("SELECT * FROM vw_disponibilidad_general ORDER BY FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado'), turno");
    foreach ($stmt->fetchAll() as $row) {
        if (isset($ids_flip[$row['id_tutor']])) {
            $disponibilidad_por_tutor[$row['id_tutor']][] = $row;
        }
    }

    // Carga horaria: desglose por estado (sigue siendo SQL directo, el SP no lo desglosa)
    $stmt = $pdo->prepare("
        SELECT id_tutor,
            SUM(CASE WHEN estado IN ('programada','en curso') THEN 1 ELSE 0 END) AS sesiones_activas,
            SUM(CASE WHEN estado = 'finalizada' THEN 1 ELSE 0 END) AS sesiones_finalizadas
        FROM sesion_tutoria
        WHERE id_tutor IN ($in)
        GROUP BY id_tutor
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $carga_por_tutor[$row['id_tutor']] = $row;
    }

    // Total de sesiones por tutor vía sp_tutores_carga_horaria (ahora con id_tutor)
    $stmt = $pdo->query("CALL sp_tutores_carga_horaria()");
    foreach ($stmt->fetchAll() as $row) {
        $id = $row['id_tutor'];
        if (isset($carga_por_tutor[$id])) {
            $carga_por_tutor[$id]['total_sesiones'] = $row['total_sesiones'];
        } else {
            $carga_por_tutor[$id] = ['total_sesiones' => $row['total_sesiones'], 'sesiones_activas' => 0, 'sesiones_finalizadas' => 0];
        }
    }
    $stmt->closeCursor();
}

$dias_abrev = ['lunes'=>'Lun','martes'=>'Mar','miercoles'=>'Mié','jueves'=>'Jue','viernes'=>'Vie','sabado'=>'Sáb'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tutores - Coordinador</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<link rel="stylesheet" href="../assets/css/tutores.css">

</head>
<body>



<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">Tutorías</div>

    <a href="dashboard.php" >
        <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
    </a>

    <a href="solicitudes.php">
        <i class="fa-solid fa-inbox"></i><span>Solicitudes</span>
    </a>

    <a href="asignar_sesion.php">
        <i class="fa-solid fa-calendar-check"></i><span>Asignar Sesión</span>
    </a>

    <a href="sesiones.php">
        <i class="fa-solid fa-calendar-days"></i><span>Sesiones</span>
    </a>

    <a href="tutores.php" class="active">
        <i class="fa-solid fa-chalkboard-user"></i><span>Tutores</span>
    </a>

    <a href="historial_estudiante.php">
        <i class="fa-solid fa-clock-rotate-left"></i><span>Historial Estudiante</span>
    </a>

    <a href="notificaciones.php">
        <i class="fa-solid fa-bell"></i><span>Notificaciones</span>
    </a>

    <a href="reportes.php">
        <i class="fa-solid fa-chart-line"></i><span>Reportes</span>
    </a>

    <a href="../auth/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i><span>Salir</span>
    </a>
</div>



<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Gestión de Tutores</h2>

        <div class="user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre']) ?>&background=4f6df5&color=fff">
            <?= htmlspecialchars($_SESSION['nombre']) ?>
        </div>
    </div>

    <!-- CARDS RESUMEN -->
    <div class="cards">

        <div class="card">
            <div class="icon blue"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="numero"><?= $total_tutores ?></div>
            <div class="label">Tutores encontrados</div>
        </div>

        <div class="card">
            <div class="icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="numero"><?= $total_disponibles ?></div>
            <div class="label">Disponibles</div>
        </div>

        <div class="card">
            <div class="icon red"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="numero"><?= $total_no_disponibles ?></div>
            <div class="label">No disponibles</div>
        </div>

        <div class="card">
            <div class="icon orange"><i class="fa-solid fa-book-open"></i></div>
            <div class="numero"><?= count($areas) ?></div>
            <div class="label">Áreas académicas</div>
        </div>

    </div>

    <!-- FILTROS -->
    <form method="GET" class="filtros">
        <input type="text" name="buscar" placeholder="Buscar por nombre o especialidad" value="<?= htmlspecialchars($buscar) ?>">

        <select name="id_area">
            <option value="">Todas las áreas</option>
            <?php foreach ($areas as $a): ?>
                <option value="<?= $a['id_area'] ?>" <?= $filtro_area == $a['id_area'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['nombre_area']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="disponible">
            <option value="">Disponibilidad: todas</option>
            <option value="si" <?= $filtro_disponible==='si'?'selected':'' ?>>Disponible</option>
            <option value="no" <?= $filtro_disponible==='no'?'selected':'' ?>>No disponible</option>
        </select>

        <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>

    <!-- GRID DE TUTORES -->
    <div class="grid-tutores">
        <?php if (count($tutores) === 0): ?>
            <div class="vacio">
                <i class="fa-solid fa-user-slash"></i>
                No se encontraron tutores con estos filtros.
            </div>
        <?php else: ?>
            <?php foreach ($tutores as $t):
                $carga = $carga_por_tutor[$t['id_tutor']] ?? ['total_sesiones'=>0,'sesiones_activas'=>0,'sesiones_finalizadas'=>0];
            ?>
            <div class="card-tutor">
                <div class="encabezado">
                    <div>
                        <h4><?= htmlspecialchars($t['nombre'].' '.$t['apellido']) ?></h4>
                        <div class="sub"><?= htmlspecialchars($t['grado_academico'] ?: 'Sin grado registrado') ?></div>
                    </div>
                    <div class="estado-pill <?= $t['disponible'] ?>">
                        <span class="estado-dot <?= $t['disponible'] ?>"></span>
                        <?= $t['disponible']==='si' ? 'Disponible' : 'No disponible' ?>
                    </div>
                </div>

                <div class="contacto">
                    <span><i class="fa-solid fa-envelope"></i><?= htmlspecialchars($t['email']) ?></span>
                    <?php if ($t['telefono']): ?>
                        <span><i class="fa-solid fa-phone"></i><?= htmlspecialchars($t['telefono']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="info-linea">
                    Especialidad: <b><?= htmlspecialchars($t['especialidad_principal'] ?: '—') ?></b>
                    &nbsp;·&nbsp; Modalidad: <b><?= ucfirst($t['modalidad_preferida']) ?></b>
                </div>

                <div class="seccion-mini">
                    <div class="titulo-mini">Áreas que dicta</div>
                    <?php if (empty($areas_por_tutor[$t['id_tutor']])): ?>
                        <span class="sub">Sin áreas asignadas.</span>
                    <?php else: ?>
                        <?php foreach ($areas_por_tutor[$t['id_tutor']] as $a): ?>
                            <span class="etiqueta <?= $a['nivel_experiencia'] ?>"><?= htmlspecialchars($a['nombre_area']) ?> (<?= ucfirst($a['nivel_experiencia']) ?>)</span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="seccion-mini">
                    <div class="titulo-mini">Disponibilidad</div>
                    <?php if (empty($disponibilidad_por_tutor[$t['id_tutor']])): ?>
                        <span class="sub">Sin disponibilidad registrada.</span>
                    <?php else: ?>
                        <?php foreach ($disponibilidad_por_tutor[$t['id_tutor']] as $d): ?>
                            <div class="fila-disp">
                                <i class="fa-solid fa-clock"></i>
                                <?= $dias_abrev[$d['dia_semana']] ?? ucfirst($d['dia_semana']) ?> · <?= ucfirst($d['turno']) ?> · <?= ucfirst($d['modalidad']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="num"><?= $carga['total_sesiones'] ?></div>
                        <div class="lbl">Total</div>
                    </div>
                    <div class="stat">
                        <div class="num"><?= $carga['sesiones_activas'] ?></div>
                        <div class="lbl">Activas</div>
                    </div>
                    <div class="stat">
                        <div class="num"><?= $carga['sesiones_finalizadas'] ?></div>
                        <div class="lbl">Finalizadas</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>