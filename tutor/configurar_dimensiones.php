<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_docente = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_mensaje = "";

// 1. Obtener todas las asignaciones del docente
$sql_asignaciones = "SELECT DISTINCT h.id_paralelo, h.id_materia, p.grado, p.letra, m.nombre_materia
                      FROM horarios h
                      INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                      INNER JOIN materias m ON h.id_materia = m.id_materia
                      WHERE h.id_docente = ?
                      ORDER BY p.grado ASC, p.letra ASC, m.nombre_materia ASC";

$stmt_asig = mysqli_prepare($conexion, $sql_asignaciones);
mysqli_stmt_bind_param($stmt_asig, "i", $id_docente);
mysqli_stmt_execute($stmt_asig);
$res_asig = mysqli_stmt_get_result($stmt_asig);

$asignaciones = [];
while ($row = mysqli_fetch_assoc($res_asig)) {
    $asignaciones[] = $row;
}
mysqli_stmt_close($stmt_asig);

// Capturar parámetros
$asignacion_sel = $_GET['asignacion'] ?? ($_POST['asignacion'] ?? '');
$trimestre = intval($_GET['trimestre'] ?? ($_POST['trimestre'] ?? 1));

$id_paralelo = 0;
$id_materia = 0;
$es_todos = ($asignacion_sel === 'todos');

if (!$es_todos && !empty($asignacion_sel)) {
    $partes = explode('-', $asignacion_sel);
    if (count($partes) === 2) {
        $id_paralelo = intval($partes[0]);
        $id_materia = intval($partes[1]);
    }
}

// 2. Procesar Formulario de Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_config') {
    $max_ser = floatval($_POST['max_ser'] ?? 0);
    $max_saber = floatval($_POST['max_saber'] ?? 0);
    $max_hacer = floatval($_POST['max_hacer'] ?? 0);
    $max_decidir = floatval($_POST['max_decidir'] ?? 0);
    $max_asistencia = floatval($_POST['max_asistencia'] ?? 0);
    $max_autoeval = floatval($_POST['max_autoeval'] ?? 5);

    $suma_total = $max_ser + $max_saber + $max_hacer + $max_decidir + $max_asistencia + $max_autoeval;

    if ($suma_total > 100) {
        $mensaje = "Error: La suma total con autoevaluación no puede superar los 100 puntos (Suma actual: {$suma_total}).";
        $tipo_mensaje = "error";
    } else {
        mysqli_begin_transaction($conexion);
        try {
            // Guardar o actualizar la Autoevaluación del docente para este trimestre
            $sql_auto = "INSERT INTO autoevaluacion_docente (id_docente, trimestre, valor_maximo)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE valor_maximo = VALUES(valor_maximo)";
            $stmt_auto = mysqli_prepare($conexion, $sql_auto);
            mysqli_stmt_bind_param($stmt_auto, "iid", $id_docente, $trimestre, $max_autoeval);
            mysqli_stmt_execute($stmt_auto);
            mysqli_stmt_close($stmt_auto);

            // Guardar configuración de dimensiones
            $sql_upsert = "INSERT INTO configuracion_dimensiones 
                            (id_paralelo, id_materia, id_docente, trimestre, max_ser, max_saber, max_hacer, max_decidir, max_asistencia)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE 
                            max_ser = VALUES(max_ser),
                            max_saber = VALUES(max_saber),
                            max_hacer = VALUES(max_hacer),
                            max_decidir = VALUES(max_decidir),
                            max_asistencia = VALUES(max_asistencia)";
            
            $stmt_u = mysqli_prepare($conexion, $sql_upsert);

            if ($es_todos) {
                foreach ($asignaciones as $asig) {
                    $p_id = $asig['id_paralelo'];
                    $m_id = $asig['id_materia'];
                    mysqli_stmt_bind_param($stmt_u, "iiiiddddd", $p_id, $m_id, $id_docente, $trimestre, $max_ser, $max_saber, $max_hacer, $max_decidir, $max_asistencia);
                    mysqli_stmt_execute($stmt_u);
                }
                $mensaje = "¡Configuración y Autoevaluación guardadas en TODOS tus cursos para el Trimestre {$trimestre}!";
            } else {
                mysqli_stmt_bind_param($stmt_u, "iiiiddddd", $id_paralelo, $id_materia, $id_docente, $trimestre, $max_ser, $max_saber, $max_hacer, $max_decidir, $max_asistencia);
                mysqli_stmt_execute($stmt_u);
                $mensaje = "¡Configuración guardada exitosamente!";
            }

            mysqli_stmt_close($stmt_u);
            mysqli_commit($conexion);
            $tipo_mensaje = "exito";

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error al guardar la configuración: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 3. Cargar configuración previa de autoevaluación
$val_autoeval = 5.00;
$sql_ad = "SELECT valor_maximo FROM autoevaluacion_docente WHERE id_docente = ? AND trimestre = ? LIMIT 1";
$stmt_ad = mysqli_prepare($conexion, $sql_ad);
mysqli_stmt_bind_param($stmt_ad, "ii", $id_docente, $trimestre);
mysqli_stmt_execute($stmt_ad);
$res_ad = mysqli_stmt_get_result($stmt_ad);
if ($row_ad = mysqli_fetch_assoc($res_ad)) {
    $val_autoeval = floatval($row_ad['valor_maximo']);
}
mysqli_stmt_close($stmt_ad);

// 4. Cargar configuración previa de dimensiones
$config_actual = null;
if (!$es_todos && $id_paralelo > 0 && $id_materia > 0) {
    $sql_cd = "SELECT * FROM configuracion_dimensiones 
               WHERE id_paralelo = ? AND id_materia = ? AND id_docente = ? AND trimestre = ? LIMIT 1";
    $stmt_cd = mysqli_prepare($conexion, $sql_cd);
    mysqli_stmt_bind_param($stmt_cd, "iiii", $id_paralelo, $id_materia, $id_docente, $trimestre);
    mysqli_stmt_execute($stmt_cd);
    $res_cd = mysqli_stmt_get_result($stmt_cd);
    $config_actual = mysqli_fetch_assoc($res_cd);
    mysqli_stmt_close($stmt_cd);
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>⚙️ Configuración de Dimensiones y Ponderaciones</h2>
    <p>Define los puntajes máximos por dimensión para un curso o aplícalos a todas tus materias simultáneamente.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="card" style="margin-bottom: 20px; padding: 15px; background: <?php echo ($tipo_mensaje === 'exito') ? '#e8f5e9' : '#ffebee'; ?>; border-left: 5px solid <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
        <strong style="color: <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
            <?php echo ($tipo_mensaje === 'exito') ? '✅' : '❌'; ?> <?php echo htmlspecialchars($mensaje); ?>
        </strong>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Seleccionar Curso o Todos</h3>
    <form action="configurar_dimensiones.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
            <label style="font-weight: 600;">Materia y Curso:</label>
            <select name="asignacion" required onchange="this.form.submit()">
                <option value="">-- Seleccionar Asignatura --</option>
                <option value="todos" <?php echo ($es_todos) ? 'selected' : ''; ?>>🌟 TODOS MIS CURSOS</option>
                <optgroup label="Cursos Individuales">
                    <?php foreach ($asignaciones as $a): ?>
                        <?php 
                        $val_compuesto = $a['id_paralelo'] . "-" . $a['id_materia'];
                        $selected = ($asignacion_sel === $val_compuesto) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $val_compuesto; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($a['nombre_materia']); ?> — <?php echo $a['grado']; ?>° "<?php echo $a['letra']; ?>"
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div class="form-group" style="margin: 0; width: 180px;">
            <label style="font-weight: 600;">Trimestre:</label>
            <select name="trimestre" onchange="this.form.submit()">
                <option value="1" <?php echo ($trimestre === 1) ? 'selected' : ''; ?>>1° Trimestre</option>
                <option value="2" <?php echo ($trimestre === 2) ? 'selected' : ''; ?>>2° Trimestre</option>
                <option value="3" <?php echo ($trimestre === 3) ? 'selected' : ''; ?>>3° Trimestre</option>
            </select>
        </div>
    </form>
</div>

<?php if ($es_todos || ($id_paralelo > 0 && $id_materia > 0)): ?>
    <form action="configurar_dimensiones.php" method="POST" onsubmit="return validarSuma();">
        <input type="hidden" name="action" value="guardar_config">
        <input type="hidden" name="asignacion" value="<?php echo htmlspecialchars($asignacion_sel); ?>">
        <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">

        <div class="card">
            <?php if ($es_todos): ?>
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px;">
                    <strong style="color: #1e40af;">ℹ️ Modo Global Activado:</strong> 
                    <span style="color: #1e3a8a;">La configuración que guardes se replicará automáticamente en todos los cursos a tu cargo en el Trimestre <?php echo $trimestre; ?>.</span>
                </div>
            <?php endif; ?>

            <h3 style="color: var(--primary); margin-bottom: 15px;">Establecer Puntajes Máximos</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label style="font-weight: 600;">SER (Pts):</label>
                    <input type="number" step="0.1" min="0" max="100" id="max_ser" name="max_ser" value="<?php echo $config_actual['max_ser'] ?? 10; ?>" required oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label style="font-weight: 600;">SABER (Pts):</label>
                    <input type="number" step="0.1" min="0" max="100" id="max_saber" name="max_saber" value="<?php echo $config_actual['max_saber'] ?? 35; ?>" required oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label style="font-weight: 600;">HACER (Pts):</label>
                    <input type="number" step="0.1" min="0" max="100" id="max_hacer" name="max_hacer" value="<?php echo $config_actual['max_hacer'] ?? 35; ?>" required oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label style="font-weight: 600;">DECIDIR (Pts):</label>
                    <input type="number" step="0.1" min="0" max="100" id="max_decidir" name="max_decidir" value="<?php echo $config_actual['max_decidir'] ?? 10; ?>" required oninput="calcularTotal()">
                </div>

                <div class="form-group">
                    <label style="font-weight: 600;">ASISTENCIA (Pts):</label>
                    <input type="number" step="0.1" min="0" max="100" id="max_asistencia" name="max_asistencia" value="<?php echo $config_actual['max_asistencia'] ?? 5; ?>" required oninput="calcularTotal()">
                </div>

                <!-- CAMPO AUTOEVALUACIÓN AGREGADO AL FORMULARIO -->
                <div class="form-group">
                    <label style="font-weight: 600; color: #b91c1c;">AUTOEVAL. (Pts):</label>
                    <input type="number" step="0.1" min="0" max="10" id="max_autoeval" name="max_autoeval" value="<?php echo $val_autoeval; ?>" required oninput="calcularTotal()" style="border-color: #fca5a5;">
                </div>
            </div>

            <div style="margin-top: 15px; padding: 12px; background: #f8fafc; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600; color: #475569;">Puntaje Máximo Acumulado:</span>
                <span id="lbl_total" style="font-weight: 700; font-size: 1.2rem; color: #059669;">100.00 Pts</span>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn-submit" style="width: auto; padding: 10px 25px;">
                    💾 Guardar Ponderación <?php echo ($es_todos) ? 'en Todos los Cursos' : ''; ?>
                </button>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
function calcularTotal() {
    const ser = parseFloat(document.getElementById('max_ser').value) || 0;
    const saber = parseFloat(document.getElementById('max_saber').value) || 0;
    const hacer = parseFloat(document.getElementById('max_hacer').value) || 0;
    const decidir = parseFloat(document.getElementById('max_decidir').value) || 0;
    const asistencia = parseFloat(document.getElementById('max_asistencia').value) || 0;
    const autoeval = parseFloat(document.getElementById('max_autoeval').value) || 0;

    const total = ser + saber + hacer + decidir + asistencia + autoeval;
    const lbl = document.getElementById('lbl_total');
    
    lbl.textContent = total.toFixed(2) + ' Pts';
    if (total > 100) {
        lbl.style.color = '#dc2626';
    } else {
        lbl.style.color = '#059669';
    }
}

function validarSuma() {
    const ser = parseFloat(document.getElementById('max_ser').value) || 0;
    const saber = parseFloat(document.getElementById('max_saber').value) || 0;
    const hacer = parseFloat(document.getElementById('max_hacer').value) || 0;
    const decidir = parseFloat(document.getElementById('max_decidir').value) || 0;
    const asistencia = parseFloat(document.getElementById('max_asistencia').value) || 0;
    const autoeval = parseFloat(document.getElementById('max_autoeval').value) || 0;

    if ((ser + saber + hacer + decidir + asistencia + autoeval) > 100) {
        alert('La suma de las dimensiones y autoevaluación no puede superar los 100 puntos.');
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', calcularTotal);
</script>

<?php require_once "../includes/footer_panel.php"; ?>