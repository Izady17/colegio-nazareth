<?php
session_start();
$ruta_base = "../";

// Control de acceso: Docentes / Tutores
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['docente', 'tutor', 'profesor'])) {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_docente = $_SESSION['id_usuario'];

// OBTENER ÚNICAMENTE LOS HORARIOS ASIGNADOS A ESTE DOCENTE
$sql = "SELECT h.dia, h.hora_inicio, h.hora_fin, h.requiere_asistencia,
               m.nombre_materia, m.turno,
               p.grado, p.letra
        FROM horarios h
        INNER JOIN materias m ON h.id_materia = m.id_materia
        INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
        WHERE h.id_docente = ?
        ORDER BY FIELD(h.dia, 'Lunes', 'Martes', 'Miercoles', 'Miércoles', 'Jueves', 'Viernes', 'Sabado', 'Sábado'), h.hora_inicio ASC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_docente);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Agrupar clases por día
$horario_docente = [
    'Lunes'     => [],
    'Martes'    => [],
    'Miércoles' => [],
    'Jueves'    => [],
    'Viernes'   => [],
    'Sábado'    => []
];

while ($row = mysqli_fetch_assoc($res)) {
    $dia = $row['dia'];
    if ($dia === 'Miercoles') $dia = 'Miércoles';
    if ($dia === 'Sabado') $dia = 'Sábado';
    
    if (isset($horario_docente[$dia])) {
        $horario_docente[$dia][] = $row;
    }
}
mysqli_stmt_close($stmt);

// Determinar el día y la hora actual para los indicadores en vivo
$dias_map = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => null];
$hoy_dia = $dias_map[(int)date('N')];
$hora_actual = date('H:i:s');

$clase_actual = null;
if ($hoy_dia && isset($horario_docente[$hoy_dia])) {
    foreach ($horario_docente[$hoy_dia] as $clase) {
        if ($hora_actual >= $clase['hora_inicio'] && $hora_actual <= $clase['hora_fin']) {
            $clase_actual = $clase;
            break;
        }
    }
}

function estadoClaseHorario($hora_inicio, $hora_fin, $hora_actual) {
    if ($hora_actual > $hora_fin) return 'pasada';
    if ($hora_actual >= $hora_inicio && $hora_actual <= $hora_fin) return 'en_curso';
    return 'proxima';
}

require_once "../includes/header_panel.php";
?>

<!-- Estilos Estructurados para el Panel del Docente -->
<style>
    .teacher-schedule-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 24px 28px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    .teacher-schedule-header h2 {
        margin: 0 0 6px 0;
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }
    .teacher-schedule-header p {
        margin: 0;
        color: #94a3b8;
        font-size: 0.95rem;
    }

    /* Banner del Estado Actual */
    .status-banner {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 14px;
        font-size: 0.95rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }
    .status-banner-live {
        background: #fefce8;
        border: 1px solid #fef08a;
        border-left: 5px solid #eab308;
        color: #854d0e;
    }
    .status-banner-off {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 5px solid #64748b;
        color: #475569;
    }

    /* Grid Semanal de Tarjetas */
    .schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .day-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .day-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .day-card.is-today {
        border-top: 4px solid #eab308;
    }
    .day-card.not-today {
        border-top: 4px solid #2563eb;
    }

    .day-header {
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
    }
    .day-header-today { background: #fffdf0; }
    .day-header-normal { background: #f8fafc; }

    .day-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
    }
    .day-title-today { color: #a16207; }
    .day-title-normal { color: #1e293b; }

    .class-count-badge {
        font-size: 0.78rem;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: 700;
    }
    .count-today { background: #fef3c7; color: #92400e; }
    .count-normal { background: #e2e8f0; color: #475569; }

    /* Tarjetas de Clases Individuales */
    .class-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 12px;
        position: relative;
    }
    .class-item:last-child {
        margin-bottom: 0;
    }

    .class-time-tag {
        font-size: 0.75rem;
        background: #f1f5f9;
        color: #334155;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-family: monospace;
    }

    /* Badges de Estado y Turno */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.72rem;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
    }
    .badge-morning { background: #e0f2fe; color: #0369a1; }
    .badge-afternoon { background: #ffedd5; color: #c2410c; }
    .badge-attendance { background: #dcfce7; color: #15803d; }
    .badge-passed { background: #f1f5f9; color: #64748b; }
    .badge-live { background: #fef08a; color: #854d0e; }
    .badge-next { background: #e2e8f0; color: #334155; }
</style>

<!-- Encabezado Principal -->
<div class="teacher-schedule-header">
    <h2>📅 Mi Horario de Clases</h2>
    <p>Consulte su asignación semanal de materias, periodos y cursos asignados.</p>
</div>

<!-- Banner del Estado Actual -->
<?php if (!$hoy_dia): ?>
    <div class="status-banner status-banner-off">
        <span style="font-size: 1.3rem;">🌙</span>
        <div>
            <strong>Fuera de horario escolar</strong> — Hoy es domingo, no hay clases programadas.
        </div>
    </div>
<?php elseif ($clase_actual): ?>
    <div class="status-banner status-banner-live">
        <span style="font-size: 1.3rem;">🟡</span>
        <div>
            <strong style="text-transform: uppercase; letter-spacing: 0.5px;">Dictando ahora:</strong>
            <span style="font-weight: 700; color: #0f172a; margin-left: 4px;">
                <?php echo htmlspecialchars($clase_actual['nombre_materia']); ?>
            </span>
            — Curso <?php echo $clase_actual['grado']; ?>° "<?php echo $clase_actual['letra']; ?>"
            <span style="font-weight: 600; margin-left: 6px;">
                (<?php echo substr($clase_actual['hora_inicio'], 0, 5) . " - " . substr($clase_actual['hora_fin'], 0, 5); ?>)
            </span>
        </div>
    </div>
<?php else: ?>
    <div class="status-banner status-banner-off">
        <span style="font-size: 1.3rem;">⚪</span>
        <div>
            <strong>Sin clase en este momento</strong> — Consulté la parrilla semanal a continuación.
        </div>
    </div>
<?php endif; ?>

<!-- Grilla de Horario Semanal -->
<div class="schedule-grid">
    <?php foreach ($horario_docente as $dia => $clases): ?>
        <?php $es_hoy = ($dia === $hoy_dia); ?>
        <div class="day-card <?php echo $es_hoy ? 'is-today' : 'not-today'; ?>">
            
            <!-- Encabezado del Día -->
            <div class="day-header <?php echo $es_hoy ? 'day-header-today' : 'day-header-normal'; ?>">
                <h3 class="day-title <?php echo $es_hoy ? 'day-title-today' : 'day-title-normal'; ?>">
                    <?php echo $dia; ?><?php echo $es_hoy ? ' (Hoy)' : ''; ?>
                </h3>
                <span class="class-count-badge <?php echo $es_hoy ? 'count-today' : 'count-normal'; ?>">
                    <?php echo count($clases); ?> <?php echo count($clases) === 1 ? 'clase' : 'clases'; ?>
                </span>
            </div>

            <!-- Lista de Clases del Día -->
            <div style="padding: 16px;">
                <?php if (!empty($clases)): ?>
                    <?php foreach ($clases as $clase): ?>
                        <?php
                            $color_borde = '#cbd5e1';
                            $badge_estado = '';
                            
                            if ($es_hoy) {
                                $estado = estadoClaseHorario($clase['hora_inicio'], $clase['hora_fin'], $hora_actual);
                                if ($estado === 'pasada') {
                                    $color_borde = '#22c55e';
                                    $badge_estado = '<span class="badge-custom badge-passed">🟢 Finalizada</span>';
                                } elseif ($estado === 'en_curso') {
                                    $color_borde = '#eab308';
                                    $badge_estado = '<span class="badge-custom badge-live">🟡 En curso</span>';
                                } else {
                                    $color_borde = '#94a3b8';
                                    $badge_estado = '<span class="badge-custom badge-next">⚪ Próxima</span>';
                                }
                            }
                        ?>
                        <div class="class-item" style="border-left: 4px solid <?php echo $color_borde; ?>;">
                            
                            <!-- Materia y Hora -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <strong style="font-size: 0.95rem; color: #0f172a;">
                                    <?php echo htmlspecialchars($clase['nombre_materia']); ?>
                                </strong>
                                <span class="class-time-tag">
                                    <?php echo substr($clase['hora_inicio'], 0, 5) . " - " . substr($clase['hora_fin'], 0, 5); ?>
                                </span>
                            </div>

                            <!-- Paralelo / Curso -->
                            <div style="font-size: 0.85rem; color: #475569; margin-bottom: 10px;">
                                📍 <strong>Curso:</strong> <?php echo $clase['grado']; ?>° de Sec. "<?php echo $clase['letra']; ?>"
                            </div>

                            <!-- Etiquetas Informativas -->
                            <div style="display: flex; gap: 6px; font-size: 0.75rem; flex-wrap: wrap;">
                                <span class="badge-custom <?php echo $clase['turno'] === 'Mañana' ? 'badge-morning' : 'badge-afternoon'; ?>">
                                    Turno <?php echo $clase['turno']; ?>
                                </span>

                                <?php if ($clase['requiere_asistencia']): ?>
                                    <span class="badge-custom badge-attendance">
                                        ✓ Toma de lista obligatoria
                                    </span>
                                <?php endif; ?>

                                <?php echo $badge_estado; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px 10px;">
                        <span style="font-size: 1.5rem; display: block; margin-bottom: 4px;">☕</span>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">
                            Sin clases programadas para este día.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>