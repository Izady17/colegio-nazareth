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

// 1. Asignaciones del docente
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

$id_paralelo = intval($_GET['id_paralelo'] ?? ($_POST['id_paralelo'] ?? 0));
$id_materia = intval($_GET['id_materia'] ?? ($_POST['id_materia'] ?? 0));
$trimestre = intval($_GET['trimestre'] ?? ($_POST['trimestre'] ?? 1));

// 2. Obtener tope de autoevaluación docente
$max_autoeval = 5.00;
$sql_ad = "SELECT valor_maximo FROM autoevaluacion_docente WHERE id_docente = ? AND trimestre = ? LIMIT 1";
$stmt_ad = mysqli_prepare($conexion, $sql_ad);
mysqli_stmt_bind_param($stmt_ad, "ii", $id_docente, $trimestre);
mysqli_stmt_execute($stmt_ad);
$res_ad = mysqli_stmt_get_result($stmt_ad);
if ($row_ad = mysqli_fetch_assoc($res_ad)) {
    $max_autoeval = floatval($row_ad['valor_maximo']);
}
mysqli_stmt_close($stmt_ad);

// 3. Obtener id_config de configuracion_dimensiones
$config_actual = null;
if ($id_paralelo > 0 && $id_materia > 0) {
    $sql_cd = "SELECT id_config FROM configuracion_dimensiones 
               WHERE id_paralelo = ? AND id_materia = ? AND id_docente = ? AND trimestre = ? 
               LIMIT 1";
    $stmt_cd = mysqli_prepare($conexion, $sql_cd);
    mysqli_stmt_bind_param($stmt_cd, "iiii", $id_paralelo, $id_materia, $id_docente, $trimestre);
    mysqli_stmt_execute($stmt_cd);
    $res_cd = mysqli_stmt_get_result($stmt_cd);
    $config_actual = mysqli_fetch_assoc($res_cd);
    mysqli_stmt_close($stmt_cd);
}

// 4. Guardar autoevaluaciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_autoevaluacion' && $config_actual) {
    $notas_auto = $_POST['notas_auto'] ?? [];
    $id_config = $config_actual['id_config'];

    mysqli_begin_transaction($conexion);
    try {
        $sql_upsert = "INSERT INTO autoevaluaciones (id_config, id_estudiante, nota)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE nota = VALUES(nota)";
        $stmt_u = mysqli_prepare($conexion, $sql_upsert);

        foreach ($notas_auto as $id_estudiante => $val_nota) {
            $est_id = intval($id_estudiante);
            $nota_num = min(floatval($val_nota), $max_autoeval);
            mysqli_stmt_bind_param($stmt_u, "iid", $id_config, $est_id, $nota_num);
            mysqli_stmt_execute($stmt_u);
        }
        mysqli_stmt_close($stmt_u);
        mysqli_commit($conexion);

        $mensaje = "¡Autoevaluaciones guardadas correctamente!";
        $tipo_mensaje = "exito";
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $mensaje = "Error al guardar autoevaluaciones: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// 5. Cargar lista de estudiantes y sus autoevaluaciones
$estudiantes_lista = [];
if ($config_actual) {
    $sql_est = "SELECT u.id_usuario, u.nombres, u.apellidos, u.ci, a.nota
                FROM estudiante_paralelo ep
                INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
                LEFT JOIN autoevaluaciones a ON (a.id_config = ? AND a.id_estudiante = u.id_usuario)
                WHERE ep.id_paralelo = ?
                ORDER BY u.apellidos ASC, u.nombres ASC";
    $stmt_e = mysqli_prepare($conexion, $sql_est);
    mysqli_stmt_bind_param($stmt_e, "ii", $config_actual['id_config'], $id_paralelo);
    mysqli_stmt_execute($stmt_e);
    $res_e = mysqli_stmt_get_result($stmt_e);

    while ($row = mysqli_fetch_assoc($res_e)) {
        $estudiantes_lista[] = $row;
    }
    mysqli_stmt_close($stmt_e);
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📝 Transcripción de Autoevaluación</h2>
    <p>Registra las notas otorgadas manualmente por cada estudiante en su autoevaluación física.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="card" style="margin-bottom: 20px; padding: 15px; background: <?php echo ($tipo_mensaje === 'exito') ? '#e8f5e9' : '#ffebee'; ?>; border-left: 5px solid <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
        <strong style="color: <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
            <?php echo ($tipo_mensaje === 'exito') ? '✅' : '❌'; ?> <?php echo htmlspecialchars($mensaje); ?>
        </strong>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Seleccionar Curso y Trimestre</h3>
    <form action="autoevaluacion_estudiantes.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
            <label style="font-weight: 600;">Materia y Curso:</label>
            <select name="asignacion" required onchange="actualizarAsignacion(this.value)">
                <option value="">-- Seleccionar Asignatura --</option>
                <?php foreach ($asignaciones as $a): ?>
                    <?php 
                    $val_compuesto = $a['id_paralelo'] . "-" . $a['id_materia'];
                    $selected = ($id_paralelo == $a['id_paralelo'] && $id_materia == $a['id_materia']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $val_compuesto; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($a['nombre_materia']); ?> — <?php echo $a['grado']; ?>° "<?php echo $a['letra']; ?>"
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="id_paralelo" id="id_paralelo" value="<?php echo $id_paralelo; ?>">
            <input type="hidden" name="id_materia" id="id_materia" value="<?php echo $id_materia; ?>">
        </div>

        <div class="form-group" style="margin: 0; width: 180px;">
            <label style="font-weight: 600;">Trimestre:</label>
            <select name="trimestre" onchange="this.form.submit()">
                <option value="1" <?php echo ($trimestre === 1) ? 'selected' : ''; ?>>1° Trimestre</option>
                <option value="2" <?php echo ($trimestre === 2) ? 'selected' : ''; ?>>2° Trimestre</option>
                <option value="3" <?php echo ($trimestre === 3) ? 'selected' : ''; ?>>3° Trimestre</option>
            </select>
        </div>

        <button type="submit" class="btn-submit" style="width: auto; padding: 9px 20px;">Cargar Alumnos</button>
    </form>
</div>

<?php if ($id_paralelo > 0 && $id_materia > 0): ?>
    <?php if (!$config_actual): ?>
        <div class="card" style="background: #fff3e0; border-left: 5px solid #e65100; text-align: center; padding: 20px;">
            <p style="margin: 0; color: #e65100; font-weight: 600;">
                ⚠️ No se han configurado las dimensiones para este curso en el Trimestre <?php echo $trimestre; ?>.
            </p>
        </div>
    <?php else: ?>
        <form action="autoevaluacion_estudiantes.php" method="POST">
            <input type="hidden" name="action" value="guardar_autoevaluacion">
            <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
            <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
            <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="color: var(--primary); margin: 0;">Lista de Autoevaluaciones</h3>
                    <span style="background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem;">
                        Tope Máximo Permitido: <?php echo number_format($max_autoeval, 2); ?> pts
                    </span>
                </div>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 10px 15px;">#</th>
                            <th style="padding: 10px 15px;">Estudiante</th>
                            <th style="padding: 10px 15px; width: 200px;">Nota Autoevaluación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($estudiantes_lista as $est): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px 15px; color: #777;"><?php echo $idx++; ?></td>
                                <td style="padding: 10px 15px;">
                                    <strong><?php echo htmlspecialchars($est['apellidos'] . " " . $est['nombres']); ?></strong>
                                </td>
                                <td style="padding: 10px 15px;">
                                    <input type="number" step="0.5" min="0" max="<?php echo $max_autoeval; ?>" name="notas_auto[<?php echo $est['id_usuario']; ?>]" value="<?php echo ($est['nota'] !== null) ? number_format($est['nota'], 2, '.', '') : '0.00'; ?>" required style="padding: 6px 10px;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn-submit" style="width: auto; padding: 10px 25px;">
                        💾 Guardar Autoevaluaciones
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

<script>
function actualizarAsignacion(valor) {
    if (!valor) return;
    const partes = valor.split('-');
    document.getElementById('id_paralelo').value = partes[0];
    document.getElementById('id_materia').value = partes[1];
}
</script>

<?php require_once "../includes/footer_panel.php"; ?>