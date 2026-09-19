<?php
session_start();
$ruta_base = "../";

// Control de acceso: Estudiantes / Alumnos
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['estudiante', 'alumno'])) {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_estudiante = $_SESSION['id_usuario'];
$id_paralelo = 0;
$datos_curso = null;

// 1. Obtener el paralelo desde la tabla estudiante_paralelo
$sql_ep = "SELECT p.id_paralelo, p.grado, p.letra 
           FROM estudiante_paralelo ep
           INNER JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
           WHERE ep.id_estudiante = ? 
           LIMIT 1";

$stmt_ep = mysqli_prepare($conexion, $sql_ep);
if ($stmt_ep) {
    mysqli_stmt_bind_param($stmt_ep, "i", $id_estudiante);
    mysqli_stmt_execute($stmt_ep);
    $res_ep = mysqli_stmt_get_result($stmt_ep);
    if ($datos_curso = mysqli_fetch_assoc($res_ep)) {
        $id_paralelo = $datos_curso['id_paralelo'];
    }
    mysqli_stmt_close($stmt_ep);
}

// Estructura base para días de la semana
$horario_estudiante = [
    'Lunes'     => [],
    'Martes'    => [],
    'Miércoles' => [],
    'Jueves'    => [],
    'Viernes'   => [],
    'Sábado'    => []
];

// 2. Obtener materias y profesores asignados al paralelo
if ($id_paralelo > 0) {
    $sql_horario = "SELECT h.dia, h.hora_inicio, h.hora_fin,
                           m.nombre_materia, m.turno,
                           u.nombres AS docente_nombres, u.apellidos AS docente_apellidos
                    FROM horarios h
                    INNER JOIN materias m ON h.id_materia = m.id_materia
                    INNER JOIN usuarios u ON h.id_docente = u.id_usuario
                    WHERE h.id_paralelo = ?
                    ORDER BY FIELD(h.dia, 'Lunes', 'Martes', 'Miercoles', 'Miércoles', 'Jueves', 'Viernes', 'Sabado', 'Sábado'), h.hora_inicio ASC";

    $stmt_h = mysqli_prepare($conexion, $sql_horario);
    mysqli_stmt_bind_param($stmt_h, "i", $id_paralelo);
    mysqli_stmt_execute($stmt_h);
    $res_h = mysqli_stmt_get_result($stmt_h);

    while ($row = mysqli_fetch_assoc($res_h)) {
        $dia = $row['dia'];
        if ($dia === 'Miercoles') $dia = 'Miércoles';
        if ($dia === 'Sabado') $dia = 'Sábado';

        if (isset($horario_estudiante[$dia])) {
            $horario_estudiante[$dia][] = $row;
        }
    }
    mysqli_stmt_close($stmt_h);
}

// Determinar el día y la hora actual para los indicadores en vivo
$dias_map = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => null];
$hoy_dia = $dias_map[(int)date('N')];
$hora_actual = date('H:i:s');

// Buscar si hay una clase en curso ahora mismo (para el banner superior)
$clase_actual = null;
if ($hoy_dia && isset($horario_estudiante[$hoy_dia])) {
    foreach ($horario_estudiante[$hoy_dia] as $clase) {
        if ($hora_actual >= $clase['hora_inicio'] && $hora_actual <= $clase['hora_fin']) {
            $clase_actual = $clase;
            break;
        }
    }
}

/**
 * Devuelve el estado visual de una clase: 'pasada', 'en_curso' o 'proxima'
 */
function estadoClaseHorario($hora_inicio, $hora_fin, $hora_actual) {
    if ($hora_actual > $hora_fin) return 'pasada';
    if ($hora_actual >= $hora_inicio && $hora_actual <= $hora_fin) return 'en_curso';
    return 'proxima';
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📚 Mi Horario Escolar</h2>
    <?php if ($datos_curso): ?>
        <p>Curso: <strong><?php echo $datos_curso['grado']; ?>° de Secundaria - Paralelo "<?php echo $datos_curso['letra']; ?>"</strong></p>
    <?php else: ?>
        <p>Consulta tu programación semanal de clases y profesores asignados.</p>
    <?php endif; ?>
</div>

<?php if (!$hoy_dia): ?>
    <div class="card" style="background-color: #f1f5f9; border-left: 4px solid #64748b; margin-bottom: 20px;">
        <strong style="color: #475569;">🌙 Fuera de horario escolar</strong> — hoy es domingo, no hay clases.
    </div>
<?php elseif ($clase_actual): ?>
    <div class="card" style="background-color: #fef9c3; border-left: 4px solid #eab308; margin-bottom: 20px;">
        <strong style="color: #854d0e;">🟡 Ahora mismo:</strong>
        <?php echo htmlspecialchars($clase_actual['nombre_materia']); ?>
        (<?php echo substr($clase_actual['hora_inicio'], 0, 5) . " - " . substr($clase_actual['hora_fin'], 0, 5); ?>)
        con <?php echo htmlspecialchars($clase_actual['docente_apellidos'] . " " . $clase_actual['docente_nombres']); ?>
    </div>
<?php else: ?>
    <div class="card" style="background-color: #f1f5f9; border-left: 4px solid #64748b; margin-bottom: 20px;">
        <strong style="color: #475569;">⚪ Fuera de horario escolar</strong> en este momento.
    </div>
<?php endif; ?>

<?php if ($id_paralelo === 0): ?>
    <div class="card" style="text-align: center; padding: 30px;">
        <p style="color: var(--text-muted); margin: 0;">No tienes un paralelo asignado actualmente en el sistema. Consulta con la administración.</p>
    </div>
<?php else: ?>
    <!-- Vista por días -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <?php foreach ($horario_estudiante as $dia => $clases): ?>
            <?php $es_hoy = ($dia === $hoy_dia); ?>
            <div class="card" style="padding: 0; overflow: hidden; border-top: 4px solid <?php echo $es_hoy ? '#eab308' : '#0d6efd'; ?>;">
                <div style="background-color: <?php echo $es_hoy ? '#fffbeb' : '#f8f9fa'; ?>; padding: 12px 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: <?php echo $es_hoy ? '#b45309' : '#0d6efd'; ?>; font-size: 1.1rem;">
                        <?php echo $dia; ?><?php echo $es_hoy ? ' (Hoy)' : ''; ?>
                    </h3>
                    <span style="font-size: 0.8rem; background: #e9ecef; color: #495057; padding: 2px 8px; border-radius: 12px; font-weight: bold;">
                        <?php echo count($clases); ?> <?php echo count($clases) === 1 ? 'materia' : 'materias'; ?>
                    </span>
                </div>

                <div style="padding: 15px;">
                    <?php if (!empty($clases)): ?>
                        <?php foreach ($clases as $clase): ?>
                            <?php
                                $color_borde = '#0d6efd';
                                $badge_estado = '';
                                if ($es_hoy) {
                                    $estado = estadoClaseHorario($clase['hora_inicio'], $clase['hora_fin'], $hora_actual);
                                    if ($estado === 'pasada') {
                                        $color_borde = '#22c55e';
                                        $badge_estado = '<span style="font-size:0.7rem;background:#dcfce7;color:#15803d;padding:2px 6px;border-radius:4px;font-weight:600;">🟢 Ya dada</span>';
                                    } elseif ($estado === 'en_curso') {
                                        $color_borde = '#eab308';
                                        $badge_estado = '<span style="font-size:0.7rem;background:#fef9c3;color:#854d0e;padding:2px 6px;border-radius:4px;font-weight:600;">🟡 En curso</span>';
                                    } else {
                                        $color_borde = '#94a3b8';
                                        $badge_estado = '<span style="font-size:0.7rem;background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;font-weight:600;">⚪ Próxima</span>';
                                    }
                                }
                            ?>
                            <div style="background: #ffffff; border: 1px solid var(--border); border-left: 4px solid <?php echo $color_borde; ?>; border-radius: 6px; padding: 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                    <strong style="font-size: 0.95rem; color: #1f2937;">
                                        <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                                    </strong>
                                    <span style="font-size: 0.75rem; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                        <?php echo substr($clase['hora_inicio'], 0, 5) . " - " . substr($clase['hora_fin'], 0, 5); ?>
                                    </span>
                                </div>

                                <div style="font-size: 0.85rem; color: #4b5563; margin-bottom: 6px;">
                                    👨‍🏫 <strong>Profesor:</strong> <?php echo htmlspecialchars($clase['docente_apellidos'] . ' ' . $clase['docente_nombres']); ?>
                                </div>

                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <span style="font-size: 0.75rem; background: <?php echo $clase['turno'] === 'Mañana' ? '#fef3c7' : '#ffedd5'; ?>; color: <?php echo $clase['turno'] === 'Mañana' ? '#92400e' : '#c2410c'; ?>; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                        Turno <?php echo $clase['turno']; ?>
                                    </span>
                                    <?php echo $badge_estado; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.88rem; text-align: center; margin: 15px 0;">
                            Sin clases este día.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>