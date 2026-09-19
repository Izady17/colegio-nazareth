<?php
session_start();
$ruta_base = "../";

// Verificar acceso exclusivo para Docentes (id_rol = 2)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_docente = $_SESSION['id_usuario'];
$fecha_hoy = date('Y-m-d');

// Mapear el día de la semana actual al español
$dias_esp = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sabado',
    'Sunday'    => 'Domingo'
];
$dia_hoy = $dias_esp[date('l')];

$mensaje_exito = "";
$mensaje_error = "";

// --------------------------------------------------------------------------
// 1. PROCESAR GUARDADO DE LA LISTA DE ASISTENCIA (POST)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_asistencia') {
    $id_lista = intval($_POST['id_lista'] ?? 0);
    $asistencias = $_POST['asistencia'] ?? []; // Array [id_estudiante => 'Presente'|'Falta']

    if ($id_lista > 0 && !empty($asistencias)) {
        // Verificar que la lista no esté en estado 'Publicada'
        $sql_chk = "SELECT estado FROM listas_asistencia WHERE id_lista = ?";
        $stmt_chk = mysqli_prepare($conexion, $sql_chk);
        mysqli_stmt_bind_param($stmt_chk, "i", $id_lista);
        mysqli_stmt_execute($stmt_chk);
        $res_chk = mysqli_stmt_get_result($stmt_chk);
        $row_chk = mysqli_fetch_assoc($res_chk);
        mysqli_stmt_close($stmt_chk);

        if ($row_chk && $row_chk['estado'] === 'Publicada') {
            $mensaje_error = "La lista ya ha sido publicada por administración y no se puede modificar.";
        } else {
            mysqli_begin_transaction($conexion);
            try {
                // Registrar o actualizar detalle de cada estudiante
                $sql_upsert = "INSERT INTO asistencia_detalle (id_lista, id_estudiante, estado) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE estado = VALUES(estado)";
                $stmt_up = mysqli_prepare($conexion, $sql_upsert);

                foreach ($asistencias as $id_estudiante => $estado_val) {
                    $id_est = intval($id_estudiante);
                    $est_clean = in_array($estado_val, ['Presente', 'Falta']) ? $estado_val : 'Falta';
                    mysqli_stmt_bind_param($stmt_up, "iis", $id_lista, $id_est, $est_clean);
                    mysqli_stmt_execute($stmt_up);
                }
                mysqli_stmt_close($stmt_up);

                // Actualizar estado de la lista a 'Subida'
                $sql_list = "UPDATE listas_asistencia SET estado = 'Subida', subida_en = CURRENT_TIMESTAMP WHERE id_lista = ?";
                $stmt_l = mysqli_prepare($conexion, $sql_list);
                mysqli_stmt_bind_param($stmt_l, "i", $id_lista);
                mysqli_stmt_execute($stmt_l);
                mysqli_stmt_close($stmt_l);

                mysqli_commit($conexion);
                $mensaje_exito = "Lista de asistencia guardada y enviada a regencia correctamente.";
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $mensaje_error = "Error al guardar la asistencia: " . $e->getMessage();
            }
        }
    }
}

// --------------------------------------------------------------------------
// 2. CONSULTAR HORARIOS DEL DÍA CON ASISTENCIA OBLIGATORIA
// --------------------------------------------------------------------------
$sql_horarios = "SELECT h.id_horario, h.hora_inicio, h.hora_fin, 
                        m.nombre_materia, m.turno,
                        p.id_paralelo, p.grado, p.letra
                 FROM horarios h
                 INNER JOIN materias m ON h.id_materia = m.id_materia
                 INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                 WHERE h.id_docente = ? AND h.dia = ? AND h.requiere_asistencia = 1
                 ORDER BY h.hora_inicio ASC";

$stmt_h = mysqli_prepare($conexion, $sql_horarios);
mysqli_stmt_bind_param($stmt_h, "is", $id_docente, $dia_hoy);
mysqli_stmt_execute($stmt_h);
$res_horarios = mysqli_stmt_get_result($stmt_h);

// Seleccionar el horario activo (por parámetro GET o el primero por defecto)
$id_horario_sel = intval($_GET['id_horario'] ?? 0);
$horarios_list = [];

while ($h = mysqli_fetch_assoc($res_horarios)) {
    $horarios_list[] = $h;
    if ($id_horario_sel === 0) {
        $id_horario_sel = $h['id_horario'];
    }
}
mysqli_stmt_close($stmt_h);

// Variables para la vista del formulario de toma de lista
$horario_activo = null;
$estudiantes = [];
$id_lista_actual = 0;
$estado_lista = 'Pendiente';

if ($id_horario_sel > 0) {
    // Buscar datos del horario activo
    foreach ($horarios_list as $h) {
        if ($h['id_horario'] === $id_horario_sel) {
            $horario_activo = $h;
            break;
        }
    }

    if ($horario_activo) {
        // Verificar/Crear cabecera en `listas_asistencia` para hoy
        $sql_lista = "SELECT id_lista, estado FROM listas_asistencia WHERE id_horario = ? AND fecha = ?";
        $stmt_l = mysqli_prepare($conexion, $sql_lista);
        mysqli_stmt_bind_param($stmt_l, "is", $id_horario_sel, $fecha_hoy);
        mysqli_stmt_execute($stmt_l);
        $res_l = mysqli_stmt_get_result($stmt_l);

        if ($row_l = mysqli_fetch_assoc($res_l)) {
            $id_lista_actual = $row_l['id_lista'];
            $estado_lista = $row_l['estado'];
        } else {
            // Crear la lista en estado Pendiente
            $sql_in_l = "INSERT INTO listas_asistencia (id_horario, fecha, estado) VALUES (?, ?, 'Pendiente')";
            $stmt_in = mysqli_prepare($conexion, $sql_in_l);
            mysqli_stmt_bind_param($stmt_in, "is", $id_horario_sel, $fecha_hoy);
            mysqli_stmt_execute($stmt_in);
            $id_lista_actual = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_in);
        }
        mysqli_stmt_close($stmt_l);

        // Obtener la nómina de estudiantes del paralelo y su estado marcado si existe
        $sql_est = "SELECT u.id_usuario, u.apellidos, u.nombres, u.ci,
                           COALESCE(ad.estado, 'Presente') AS estado_asistencia
                    FROM estudiante_paralelo ep
                    INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
                    LEFT JOIN asistencia_detalle ad ON ad.id_lista = ? AND ad.id_estudiante = u.id_usuario
                    WHERE ep.id_paralelo = ? AND u.estado = 1
                    ORDER BY u.apellidos ASC, u.nombres ASC";

        $stmt_e = mysqli_prepare($conexion, $sql_est);
        mysqli_stmt_bind_param($stmt_e, "ii", $id_lista_actual, $horario_activo['id_paralelo']);
        mysqli_stmt_execute($stmt_e);
        $res_e = mysqli_stmt_get_result($stmt_e);

        while ($e = mysqli_fetch_assoc($res_e)) {
            $estudiantes[] = $e;
        }
        mysqli_stmt_close($stmt_e);
    }
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>Control de Asistencia Diaria</h2>
    <p>Fecha: <strong><?php echo date('d/m/Y'); ?></strong> (Día <?php echo $dia_hoy; ?>)</p>
</div>
<div class="card hero-section" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Registro de Asistencia</h2>
        <p style="margin: 0;">Toma de asistencia diaria para tus materias asignadas.</p>
    </div>
    
    <!-- BOTÓN DE CONSULTA GENERAL -->
    <a href="consulta_asistencia.php" style="background: #6c757d; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
        🔍 Consultar Asistencias Publicadas
    </a>
</div>
<?php if ($mensaje_exito): ?>
    <div style="background-color: #d1e7dd; color: #0f5132; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($mensaje_exito); ?>
    </div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
    <div style="background-color: #f8d7da; color: #842029; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($mensaje_error); ?>
    </div>
<?php endif; ?>

<!-- Selector de Periodo de Clase del Día -->
<?php if (!empty($horarios_list)): ?>
    <div class="card" style="margin-bottom: 25px;">
        <h4 style="color: var(--primary); margin-bottom: 12px;">Clases del día que requieren lista:</h4>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php foreach ($horarios_list as $h): ?>
                <a href="asistencia.php?id_horario=<?php echo $h['id_horario']; ?>" 
                   style="padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;
                          background-color: <?php echo ($h['id_horario'] === $id_horario_sel) ? 'var(--primary)' : '#f0f0f0'; ?>;
                          color: <?php echo ($h['id_horario'] === $id_horario_sel) ? 'white' : 'var(--text-main)'; ?>;">
                    <?php echo $h['grado'] . "°" . $h['letra'] . " - " . htmlspecialchars($h['nombre_materia']) . " (" . substr($h['hora_inicio'], 0, 5) . ")"; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Planilla de Registro de Asistencia -->
<?php if ($horario_activo): ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 12px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="color: var(--primary); margin: 0;">
                    <?php echo $horario_activo['grado']; ?>° de Secundaria - Paralelo "<?php echo $horario_activo['letra']; ?>"
                </h3>
                <small style="color: var(--text-muted); font-weight: 600;">
                    Materia: <?php echo htmlspecialchars($horario_activo['nombre_materia']); ?> | Horario: <?php echo substr($horario_activo['hora_inicio'], 0, 5) . " - " . substr($horario_activo['hora_fin'], 0, 5); ?>
                </small>
            </div>
            <div>
                <span style="padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 0.85rem;
                             background-color: <?php echo ($estado_lista === 'Publicada') ? '#d1e7dd' : (($estado_lista === 'Subida') ? '#fff3cd' : '#f8d7da'); ?>;
                             color: <?php echo ($estado_lista === 'Publicada') ? '#0f5132' : (($estado_lista === 'Subida') ? '#856404' : '#842029'); ?>;">
                    Estado: <?php echo $estado_lista; ?>
                </span>
            </div>
        </div>

        <?php if ($estado_lista === 'Publicada'): ?>
            <div style="background-color: #e2e3e5; color: #41464b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                ℹ️ Esta lista ya ha sido verificada y publicada por el administrador/regente. No se permiten más cambios.
            </div>
        <?php endif; ?>

        <?php if (!empty($estudiantes)): ?>
            <form action="asistencia.php?id_horario=<?php echo $id_horario_sel; ?>" method="POST">
                <input type="hidden" name="accion" value="guardar_asistencia">
                <input type="hidden" name="id_lista" value="<?php echo $id_lista_actual; ?>">

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                                <th style="padding: 10px 8px;">N°</th>
                                <th style="padding: 10px 8px;">C.I.</th>
                                <th style="padding: 10px 8px;">Apellidos y Nombres</th>
                                <th style="padding: 10px 8px; text-align: center;">Estado de Asistencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $nro = 1;
                            foreach ($estudiantes as $est): 
                            ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 10px 8px; font-weight: 600;"><?php echo $nro++; ?></td>
                                    <td style="padding: 10px 8px;"><?php echo htmlspecialchars($est['ci']); ?></td>
                                    <td style="padding: 10px 8px; font-weight: 500;">
                                        <?php echo htmlspecialchars($est['apellidos'] . " " . $est['nombres']); ?>
                                    </td>
                                    <td style="padding: 10px 8px; text-align: center;">
                                        <div style="display: inline-flex; gap: 15px;">
                                            <label style="cursor: pointer; color: #2e7d32; font-weight: 600;">
                                                <input type="radio" 
                                                       name="asistencia[<?php echo $est['id_usuario']; ?>]" 
                                                       value="Presente" 
                                                       <?php echo ($est['estado_asistencia'] === 'Presente') ? 'checked' : ''; ?>
                                                       <?php echo ($estado_lista === 'Publicada') ? 'disabled' : ''; ?>> 
                                                Presente
                                            </label>
                                            
                                            <label style="cursor: pointer; color: #c62828; font-weight: 600;">
                                                <input type="radio" 
                                                       name="asistencia[<?php echo $est['id_usuario']; ?>]" 
                                                       value="Falta" 
                                                       <?php echo ($est['estado_asistencia'] === 'Falta') ? 'checked' : ''; ?>
                                                       <?php echo ($estado_lista === 'Publicada') ? 'disabled' : ''; ?>> 
                                                Falta
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($estado_lista !== 'Publicada'): ?>
                    <button type="submit" class="btn-submit" style="width: auto; padding: 12px 28px;">
                        <?php echo ($estado_lista === 'Subida') ? 'Actualizar Lista' : 'Guardar y Enviar Lista'; ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">No hay estudiantes inscritos en este paralelo.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 30px;">
        <p style="color: var(--text-muted);">No tienes periodos programados con toma de lista para el día de hoy (<?php echo $dia_hoy; ?>).</p>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>