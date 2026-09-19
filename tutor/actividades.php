<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

$id_docente = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_mensaje = "";

// 1. Obtener materias y paralelos asignados al docente
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

// Detectar la "jornada actual" del docente según su horario en vivo
$dias_map = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => null];
$hoy_dia = $dias_map[(int)date('N')];
$hora_actual = date('H:i:s');
$jornada_actual = null;

if ($hoy_dia) {
    $sql_actual = "SELECT h.id_paralelo, h.id_materia, p.grado, p.letra, m.nombre_materia
                   FROM horarios h
                   INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                   INNER JOIN materias m ON h.id_materia = m.id_materia
                   WHERE h.id_docente = ? AND h.dia = ? AND ? BETWEEN h.hora_inicio AND h.hora_fin
                   LIMIT 1";
    $stmt_act = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($stmt_act, "iss", $id_docente, $hoy_dia, $hora_actual);
    mysqli_stmt_execute($stmt_act);
    $jornada_actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_act));
    mysqli_stmt_close($stmt_act);
}

// Si el docente no eligió nada manualmente todavía, usar su clase actual por defecto
if ($id_paralelo === 0 && $id_materia === 0 && $jornada_actual) {
    $id_paralelo = (int)$jornada_actual['id_paralelo'];
    $id_materia = (int)$jornada_actual['id_materia'];
}
$id_actividad_eval = intval($_GET['id_actividad'] ?? 0);
$tab_activa = $_GET['tab'] ?? ($_POST['tab'] ?? 'actividades');

// 2. Cargar configuración activa de dimensiones
$config_actual = null;
if ($id_paralelo > 0 && $id_materia > 0) {
    $sql_cd = "SELECT * FROM configuracion_dimensiones 
               WHERE id_paralelo = ? AND id_materia = ? AND id_docente = ? AND trimestre = ? 
               LIMIT 1";
    $stmt_cd = mysqli_prepare($conexion, $sql_cd);
    mysqli_stmt_bind_param($stmt_cd, "iiii", $id_paralelo, $id_materia, $id_docente, $trimestre);
    mysqli_stmt_execute($stmt_cd);
    $res_cd = mysqli_stmt_get_result($stmt_cd);
    $config_actual = mysqli_fetch_assoc($res_cd);
    mysqli_stmt_close($stmt_cd);
}

// Obtener tope máximo de autoevaluación (evita Undefined Array Key)
$max_autoevaluacion_val = floatval($config_actual['max_autoevaluaciones'] ?? ($config_actual['max_autoevaluacion'] ?? 5.00));

// 3. PROCESAR ACCIONES (Crear Actividad / Guardar Notas / Guardar Autoevaluaciones)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. Crear Nueva Actividad
    if ($action === 'crear_actividad' && $config_actual) {
        $dimension = $_POST['dimension'] ?? 'Saber';
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $puntaje_maximo = floatval($_POST['puntaje_maximo'] ?? 10.00);
        $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : NULL;

        if (!empty($titulo)) {
            $sql_ins_act = "INSERT INTO actividades_dimension (id_config, dimension, titulo, descripcion, puntaje_maximo, fecha_limite)
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_act = mysqli_prepare($conexion, $sql_ins_act);
            mysqli_stmt_bind_param($stmt_act, "isssds", $config_actual['id_config'], $dimension, $titulo, $descripcion, $puntaje_maximo, $fecha_limite);
            
            if (mysqli_stmt_execute($stmt_act)) {
                $mensaje = "¡Actividad creada correctamente!";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "Error al crear la actividad.";
                $tipo_mensaje = "error";
            }
            mysqli_stmt_close($stmt_act);
        }
    }

    // B. Guardar Calificaciones Masivas de Actividades
    if ($action === 'guardar_notas' && $id_actividad_eval > 0) {
        $notas = $_POST['notas'] ?? [];
        $entregados = $_POST['entregados'] ?? [];

        mysqli_begin_transaction($conexion);
        try {
            $sql_upsert_nota = "INSERT INTO notas_actividades (id_actividad, id_estudiante, entregado, nota, revisado)
                                VALUES (?, ?, ?, ?, 1)
                                ON DUPLICATE KEY UPDATE 
                                  entregado = VALUES(entregado),
                                  nota = VALUES(nota),
                                  revisado = 1";

            $stmt_un = mysqli_prepare($conexion, $sql_upsert_nota);

            foreach ($notas as $id_estudiante => $valor_nota) {
                $est_id = intval($id_estudiante);
                $nota_val = floatval($valor_nota);
                $is_entregado = isset($entregados[$est_id]) ? 1 : 0;

                mysqli_stmt_bind_param($stmt_un, "iiid", $id_actividad_eval, $est_id, $is_entregado, $nota_val);
                mysqli_stmt_execute($stmt_un);
            }
            mysqli_stmt_close($stmt_un);
            mysqli_commit($conexion);

            $mensaje = "¡Calificaciones guardadas exitosamente!";
            $tipo_mensaje = "exito";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error al guardar notas: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    // C. Guardar Autoevaluaciones Masivas en la tabla `autoevaluaciones` (columna `nota`)
    if ($action === 'guardar_autoevaluacion' && $config_actual) {
        $autoevaluaciones = $_POST['autoevaluacion'] ?? [];

        mysqli_begin_transaction($conexion);
        try {
            $sql_upsert_auto = "INSERT INTO autoevaluaciones (id_config, id_estudiante, nota)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE nota = VALUES(nota)";
            $stmt_auto = mysqli_prepare($conexion, $sql_upsert_auto);

            foreach ($autoevaluaciones as $id_estudiante => $val_auto) {
                $est_id = intval($id_estudiante);
                $nota_auto = min(floatval($val_auto), $max_autoevaluacion_val);

                mysqli_stmt_bind_param($stmt_auto, "iid", $config_actual['id_config'], $est_id, $nota_auto);
                mysqli_stmt_execute($stmt_auto);
            }
            mysqli_stmt_close($stmt_auto);
            mysqli_commit($conexion);

            $mensaje = "¡Autoevaluaciones guardadas correctamente!";
            $tipo_mensaje = "exito";
            $tab_activa = "autoevaluacion";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error al guardar autoevaluaciones: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    // D. Citar a un estudiante en riesgo a entrevista (conecta con la alerta que ve el estudiante/padre)
    if ($action === 'citar_entrevista' && $config_actual) {
        $id_estudiante_citar = intval($_POST['id_estudiante'] ?? 0);
        $motivo = trim($_POST['motivo_entrevista'] ?? '');

        if ($id_estudiante_citar > 0) {
            $sql_citar = "INSERT INTO boletin_trimestral (id_config, id_estudiante, en_riesgo, entrevista_citada, motivo_entrevista, fecha_entrevista_citada)
                          VALUES (?, ?, 1, 1, ?, NOW())
                          ON DUPLICATE KEY UPDATE
                            en_riesgo = 1,
                            entrevista_citada = 1,
                            motivo_entrevista = VALUES(motivo_entrevista),
                            fecha_entrevista_citada = NOW()";
            $stmt_citar = mysqli_prepare($conexion, $sql_citar);
            mysqli_stmt_bind_param($stmt_citar, "iis", $config_actual['id_config'], $id_estudiante_citar, $motivo);
            if (mysqli_stmt_execute($stmt_citar)) {
                $mensaje = "Estudiante citado a entrevista correctamente.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "Error al registrar la citación.";
                $tipo_mensaje = "error";
            }
            mysqli_stmt_close($stmt_citar);
            $tab_activa = "alertas";
        }
    }
}

// 4. Modo Calificación Individual de una Actividad
$actividad_activa = null;
$estudiantes_lista = [];
if ($id_actividad_eval > 0) {
    $sql_act_info = "SELECT a.*, c.id_paralelo, c.id_materia, c.trimestre 
                     FROM actividades_dimension a
                     INNER JOIN configuracion_dimensiones c ON a.id_config = c.id_config
                     WHERE a.id_actividad = ? AND c.id_docente = ? LIMIT 1";
    $stmt_ai = mysqli_prepare($conexion, $sql_act_info);
    mysqli_stmt_bind_param($stmt_ai, "ii", $id_actividad_eval, $id_docente);
    mysqli_stmt_execute($stmt_ai);
    $actividad_activa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ai));
    mysqli_stmt_close($stmt_ai);

    if ($actividad_activa) {
        $id_paralelo = $actividad_activa['id_paralelo'];
        $id_materia = $actividad_activa['id_materia'];
        $trimestre = $actividad_activa['trimestre'];

        $sql_est = "SELECT u.id_usuario, u.nombres, u.apellidos, u.ci, n.entregado, n.nota, n.revisado
                    FROM estudiante_paralelo ep
                    INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
                    LEFT JOIN notas_actividades n ON (n.id_actividad = ? AND n.id_estudiante = u.id_usuario)
                    WHERE ep.id_paralelo = ?
                    ORDER BY u.apellidos ASC, u.nombres ASC";

        $stmt_e = mysqli_prepare($conexion, $sql_est);
        mysqli_stmt_bind_param($stmt_e, "ii", $id_actividad_eval, $id_paralelo);
        mysqli_stmt_execute($stmt_e);
        $res_e = mysqli_stmt_get_result($stmt_e);
        while ($row = mysqli_fetch_assoc($res_e)) {
            $estudiantes_lista[] = $row;
        }
        mysqli_stmt_close($stmt_e);
    }
}

// 5. Cargar Actividades del Curso
$actividades_agrupadas = ['Ser' => [], 'Saber' => [], 'Hacer' => [], 'Decidir' => []];
if ($config_actual && $id_actividad_eval === 0) {
    $sql_list_act = "SELECT * FROM actividades_dimension WHERE id_config = ? ORDER BY creado_en DESC";
    $stmt_la = mysqli_prepare($conexion, $sql_list_act);
    mysqli_stmt_bind_param($stmt_la, "i", $config_actual['id_config']);
    mysqli_stmt_execute($stmt_la);
    $res_la = mysqli_stmt_get_result($stmt_la);
    while ($row = mysqli_fetch_assoc($res_la)) {
        $actividades_agrupadas[$row['dimension']][] = $row;
    }
    mysqli_stmt_close($stmt_la);
}

// 6. Cargar Datos para Centralizador, Autoevaluación y Alertas
$centralizador_datos = [];
$centralizador_datos = calcularCentralizadorTrimestral($conexion, $id_paralelo, $config_actual);

// Estado de citación a entrevista ya registrado (para no perderlo al recargar)
$citaciones_registradas = [];
if ($config_actual) {
    $sql_cit = "SELECT id_estudiante, entrevista_citada, motivo_entrevista FROM boletin_trimestral WHERE id_config = ?";
    $stmt_cit = mysqli_prepare($conexion, $sql_cit);
    mysqli_stmt_bind_param($stmt_cit, "i", $config_actual['id_config']);
    mysqli_stmt_execute($stmt_cit);
    $res_cit = mysqli_stmt_get_result($stmt_cit);
    while ($row = mysqli_fetch_assoc($res_cit)) {
        $citaciones_registradas[$row['id_estudiante']] = $row;
    }
    mysqli_stmt_close($stmt_cit);
}

require_once "../includes/header_panel.php";
?>

<style>
.tab-container { display: flex; gap: 10px; border-bottom: 2px solid var(--border); margin-bottom: 20px; }
.tab-btn { padding: 10px 20px; border: none; background: none; font-weight: 600; cursor: pointer; color: #64748b; border-bottom: 3px solid transparent; }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #f8fafc; border-radius: 6px 6px 0 0; }
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📋 Gestión Pedagógica e Integrada</h2>
    <p>Administra tareas, autoevaluaciones independientes, centralizador trimestral y alertas de riesgo.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="card" style="margin-bottom: 20px; padding: 15px; background: <?php echo ($tipo_mensaje === 'exito') ? '#e8f5e9' : '#ffebee'; ?>; border-left: 5px solid <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
        <strong style="color: <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
            <?php echo ($tipo_mensaje === 'exito') ? '✅' : '❌'; ?> <?php echo htmlspecialchars($mensaje); ?>
        </strong>
    </div>
<?php endif; ?>

<!-- SELECTOR DE CURSO / TRIMESTRE -->
<?php if ($id_actividad_eval === 0): ?>
    <div class="card" style="margin-bottom: 25px;">
        <h3 style="color: var(--primary); margin-bottom: 15px;">1. Selección de Asignatura</h3>
        <?php if ($jornada_actual): ?>
            <div style="background: #fef9c3; border-left: 4px solid #eab308; padding: 8px 14px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; color: #854d0e;">
                🟡 <strong>Tu jornada ahora:</strong> <?php echo htmlspecialchars($jornada_actual['nombre_materia']); ?>
                — <?php echo $jornada_actual['grado']; ?>° "<?php echo $jornada_actual['letra']; ?>"
                <?php if ($id_paralelo == $jornada_actual['id_paralelo'] && $id_materia == $jornada_actual['id_materia']): ?>
                    <span style="font-weight: 700;">(seleccionada abajo)</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form action="actividades.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="tab" id="tab_input" value="<?php echo htmlspecialchars($tab_activa); ?>">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                <label style="font-weight: 600;">Materia y Curso:</label>
                <select name="asignacion" required onchange="actualizarAsignacion(this.value)">
                    <option value="">-- Seleccionar Asignatura --</option>
                    <?php foreach ($asignaciones as $a): ?>
                        <?php 
                        $val_compuesto = $a['id_paralelo'] . "-" . $a['id_materia'];
                        $selected = ($id_paralelo == $a['id_paralelo'] && $id_materia == $a['id_materia']) ? 'selected' : '';
                        $es_actual = ($jornada_actual && $jornada_actual['id_paralelo'] == $a['id_paralelo'] && $jornada_actual['id_materia'] == $a['id_materia']);
                        ?>
                        <option value="<?php echo $val_compuesto; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($a['nombre_materia']); ?> — <?php echo $a['grado']; ?>° "<?php echo $a['letra']; ?>"<?php echo $es_actual ? ' 🟡 (Ahora)' : ''; ?>
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

            <button type="submit" class="btn-submit" style="width: auto; padding: 9px 20px;">Cargar Datos</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($id_paralelo > 0 && $id_materia > 0 && $id_actividad_eval === 0): ?>
    <?php if (!$config_actual): ?>
        <div class="card" style="background: #fff3e0; border-left: 5px solid #e65100; text-align: center; padding: 20px;">
            <p style="margin: 0; color: #e65100; font-weight: 600;">
                ⚠️ Las dimensiones no están configuradas para este curso/trimestre. 
                <a href="configurar_dimensiones.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>" style="text-decoration: underline; color: #e65100;">Configurar dimensiones aquí</a>.
            </p>
        </div>
    <?php else: ?>

        <!-- BARRA DE PESTAÑAS (TABS) -->
        <div class="tab-container">
            <button class="tab-btn <?php echo ($tab_activa === 'actividades') ? 'active' : ''; ?>" onclick="switchTab('actividades')">📝 Tareas y Evaluación</button>
            <button class="tab-btn <?php echo ($tab_activa === 'autoevaluacion') ? 'active' : ''; ?>" onclick="switchTab('autoevaluacion')">🙋‍♂️ Autoevaluación Estudiantes</button>
            <button class="tab-btn <?php echo ($tab_activa === 'centralizador') ? 'active' : ''; ?>" onclick="switchTab('centralizador')">📊 Centralizador Trimestral</button>
            <button class="tab-btn <?php echo ($tab_activa === 'alertas') ? 'active' : ''; ?>" onclick="switchTab('alertas')">⚠️ Alertas de Riesgo</button>
        </div>

        <!-- PESTAÑA 1: ACTIVIDADES -->
        <div id="tab-actividades" class="tab-content <?php echo ($tab_activa === 'actividades') ? 'active' : ''; ?>">
            <!-- Formulario de creación de actividades -->
            <div class="card" style="margin-bottom: 25px;">
                <h3 style="color: var(--primary); margin-bottom: 15px;">➕ Crear Nueva Actividad / Tarea</h3>
                <form action="actividades.php" method="POST">
                    <input type="hidden" name="action" value="crear_actividad">
                    <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
                    <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
                    <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">
                    <input type="hidden" name="tab" value="actividades">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-weight: 600;">Dimensión:</label>
                            <select name="dimension" required>
                                <option value="Ser">SER (Máx: <?php echo number_format($config_actual['max_ser'] ?? 10, 2); ?> pts)</option>
                                <option value="Saber" selected>SABER (Máx: <?php echo number_format($config_actual['max_saber'] ?? 35, 2); ?> pts)</option>
                                <option value="Hacer">HACER (Máx: <?php echo number_format($config_actual['max_hacer'] ?? 35, 2); ?> pts)</option>
                                <option value="Decidir">DECIDIR (Máx: <?php echo number_format($config_actual['max_decidir'] ?? 10, 2); ?> pts)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-weight: 600;">Título:</label>
                            <input type="text" name="titulo" required placeholder="Ej: Examen Unidad 1...">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-weight: 600;">Puntaje Máximo:</label>
                            <input type="number" step="0.5" min="0.5" max="100" name="puntaje_maximo" value="10.00" required>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label style="font-weight: 600;">Fecha Límite:</label>
                            <input type="date" name="fecha_limite">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600;">Descripción:</label>
                        <textarea name="descripcion" rows="2" placeholder="Detalles de la tarea..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit" style="width: auto; padding: 10px 22px;">📌 Publicar Actividad</button>
                </form>
            </div>

            <!-- Listado de Actividades -->
            <div class="card">
                <h3 style="color: var(--primary); margin-bottom: 20px;">📚 Actividades Registradas</h3>
                <?php foreach (['Ser' => 'SER', 'Saber' => 'SABER', 'Hacer' => 'HACER', 'Decidir' => 'DECIDIR'] as $dim_key => $dim_nombre): 
                    $lista = $actividades_agrupadas[$dim_key];
                ?>
                    <div style="margin-bottom: 25px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                        <div style="background: #f1f5f9; padding: 10px 15px; font-weight: 700; color: var(--primary); display: flex; justify-content: space-between; align-items: center;">
                            <span>Dimensión: <?php echo $dim_nombre; ?></span>
                            <span style="font-size: 0.82rem; background: #e2e8f0; padding: 3px 10px; border-radius: 10px;">Total: <?php echo count($lista); ?></span>
                        </div>
                        <?php if (empty($lista)): ?>
                            <p style="padding: 15px; margin: 0; color: #64748b; font-size: 0.9rem;">No hay actividades registradas en esta dimensión.</p>
                        <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border); background: #fafafa; font-size: 0.85rem; text-align: left;">
                                        <th style="padding: 10px 15px;">Título</th>
                                        <th style="padding: 10px 15px;">Máx.</th>
                                        <th style="padding: 10px 15px;">Fecha Límite</th>
                                        <th style="padding: 10px 15px; text-align: right;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista as $act): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px;"><strong><?php echo htmlspecialchars($act['titulo']); ?></strong></td>
                                            <td style="padding: 12px 15px; font-weight: 600; color: #2e7d32;"><?php echo number_format($act['puntaje_maximo'], 2); ?> pts</td>
                                            <td style="padding: 12px 15px; font-size: 0.88rem;"><?php echo $act['fecha_limite'] ? date('d/m/Y', strtotime($act['fecha_limite'])) : 'Sin fecha'; ?></td>
                                            <td style="padding: 12px 15px; text-align: right;">
                                                <a href="actividades.php?id_actividad=<?php echo $act['id_actividad']; ?>" class="btn-submit" style="display: inline-block; width: auto; padding: 6px 14px; font-size: 0.82rem; background: #0288d1; text-decoration: none;">✏️ Calificar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PESTAÑA 2: AUTOEVALUACIÓN (COMPONENTE INDEPENDIENTE) -->
        <div id="tab-autoevaluacion" class="tab-content <?php echo ($tab_activa === 'autoevaluacion') ? 'active' : ''; ?>">
            <div class="card">
                <h3 style="color: var(--primary); margin-bottom: 10px;">🙋‍♂️ Transcripción de Autoevaluación del Estudiante</h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">
                    📌 <strong>Nota:</strong> La autoevaluación es un valor independiente de hasta <strong><?php echo number_format($max_autoevaluacion_val, 2); ?> Pts</strong>. No afecta a las dimensiones Ser, Saber, Hacer o Decidir.
                </p>
                
                <form action="actividades.php" method="POST">
                    <input type="hidden" name="action" value="guardar_autoevaluacion">
                    <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
                    <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
                    <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">
                    <input type="hidden" name="tab" value="autoevaluacion">

                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid var(--border); text-align: left;">
                                <th style="padding: 10px 15px;">Estudiante</th>
                                <th style="padding: 10px 15px; width: 220px;">Puntaje Autoevaluación (Máx: <?php echo number_format($max_autoevaluacion_val, 2); ?> Pts)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($centralizador_datos as $row): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px 15px;"><strong><?php echo htmlspecialchars($row['nombre_completo']); ?></strong></td>
                                    <td style="padding: 10px 15px;">
                                        <input type="number" step="0.5" min="0" max="<?php echo $max_autoevaluacion_val; ?>" name="autoevaluacion[<?php echo $row['id_usuario']; ?>]" value="<?php echo number_format($row['autoevaluacion'], 2, '.', ''); ?>" style="padding: 6px 10px; width: 100%;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" class="btn-submit" style="width: auto; padding: 10px 25px;">💾 Guardar Autoevaluaciones</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PESTAÑA 3: CENTRALIZADOR TRIMESTRAL -->
        <div id="tab-centralizador" class="tab-content <?php echo ($tab_activa === 'centralizador') ? 'active' : ''; ?>">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <h3 style="color: var(--primary); margin: 0;">📊 Centralizador Trimestral (Sobre 100 Pts)</h3>
                    <a href="exportar_excel.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>"
                       style="background:#16a34a;color:white;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:0.85rem;font-weight:700;">
                        📥 Exportar a Excel
                    </a>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background: #f1f5f9; border-bottom: 2px solid var(--border); text-align: center;">
                                <th style="padding: 10px; text-align: left;">Estudiante</th>
                                <th style="padding: 10px;">SER (<?php echo $config_actual['max_ser'] ?? 10; ?>)</th>
                                <th style="padding: 10px;">SABER (<?php echo $config_actual['max_saber'] ?? 35; ?>)</th>
                                <th style="padding: 10px;">HACER (<?php echo $config_actual['max_hacer'] ?? 35; ?>)</th>
                                <th style="padding: 10px;">DECIDIR (<?php echo $config_actual['max_decidir'] ?? 10; ?>)</th>
                                <th style="padding: 10px;">ASIS. (<?php echo $config_actual['max_asistencia'] ?? 5; ?>)</th>
                                <th style="padding: 10px;">AUTO. (<?php echo $max_autoevaluacion_val; ?>)</th>
                                <th style="padding: 10px; font-weight: 700; background: #e2e8f0;">TOTAL</th>
                                <th style="padding: 10px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($centralizador_datos as $row): ?>
                                <tr style="border-bottom: 1px solid #eee; text-align: center;">
                                    <td style="padding: 10px; text-align: left;"><strong><?php echo htmlspecialchars($row['nombre_completo']); ?></strong></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['ser'], 1); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['saber'], 1); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['hacer'], 1); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['decidir'], 1); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['asistencia'], 1); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($row['autoevaluacion'], 1); ?></td>
                                    <td style="padding: 10px; font-weight: 700; background: #f8fafc; color: <?php echo ($row['total'] >= 51) ? '#15803d' : '#b91c1c'; ?>;">
                                        <?php echo number_format($row['total'], 1); ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; background: <?php echo ($row['total'] >= 51) ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo ($row['total'] >= 51) ? '#15803d' : '#b91c1c'; ?>;">
                                            <?php echo $row['estado']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 4: ALERTAS DE RIESGO -->
        <div id="tab-alertas" class="tab-content <?php echo ($tab_activa === 'alertas') ? 'active' : ''; ?>">
            <div class="card">
                <h3 style="color: var(--primary); margin-bottom: 15px;">⚠️ Alertas y Estudiantes en Riesgo (&lt; 51 Pts)</h3>
                <?php 
                $estudiantes_riesgo = array_filter($centralizador_datos, fn($e) => $e['total'] < 51);
                ?>
                <?php if (empty($estudiantes_riesgo)): ?>
                    <p style="color: #16a34a; font-weight: 600; padding: 15px; background: #f0fdf4; border-radius: 6px;">🎉 ¡Excelente! No hay estudiantes en riesgo pedagógico en este curso.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #fef2f2; border-bottom: 2px solid #fca5a5; text-align: left;">
                                <th style="padding: 10px 15px; color: #991b1b;">Estudiante</th>
                                <th style="padding: 10px 15px; color: #991b1b;">Cédula</th>
                                <th style="padding: 10px 15px; color: #991b1b; text-align: center;">Nota Acumulada</th>
                                <th style="padding: 10px 15px; color: #991b1b;">Citación a Entrevista</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes_riesgo as $r): ?>
                                <?php $ya_citado = isset($citaciones_registradas[$r['id_usuario']]) && $citaciones_registradas[$r['id_usuario']]['entrevista_citada']; ?>
                                <tr style="border-bottom: 1px solid #fee2e2;">
                                    <td style="padding: 10px 15px;"><strong><?php echo htmlspecialchars($r['nombre_completo']); ?></strong></td>
                                    <td style="padding: 10px 15px; color: #666;"><?php echo htmlspecialchars($r['ci']); ?></td>
                                    <td style="padding: 10px 15px; text-align: center; font-weight: 700; color: #dc2626;"><?php echo number_format($r['total'], 1); ?> pts</td>
                                    <td style="padding: 10px 15px;">
                                        <?php if ($ya_citado): ?>
                                            <span style="background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:12px;font-size:0.8rem;font-weight:700;">📢 Ya citado</span>
                                        <?php else: ?>
                                            <form action="actividades.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>&tab=alertas" method="POST" style="display:flex; gap:6px; align-items:center;">
                                                <input type="hidden" name="action" value="citar_entrevista">
                                                <input type="hidden" name="id_estudiante" value="<?php echo $r['id_usuario']; ?>">
                                                <input type="text" name="motivo_entrevista" placeholder="Motivo (opcional)" style="padding: 5px 8px; font-size: 0.82rem; flex: 1; min-width: 140px;">
                                                <button type="submit" style="background:#dc2626;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:0.8rem; white-space:nowrap;">📢 Citar Entrevista</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
<?php endif; ?>

<!-- MODAL / PANTALLA DE CALIFICACIÓN INDIVIDUAL -->
<?php if ($actividad_activa): ?>
    <div class="card" style="margin-bottom: 20px;">
        <a href="actividades.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>" style="text-decoration: none; color: #0288d1; font-weight: 600;">
            ← Volver a la lista de actividades
        </a>
        <h3 style="color: var(--primary); margin-top: 10px;">Calificando: <?php echo htmlspecialchars($actividad_activa['titulo']); ?></h3>
        <p style="margin: 0; color: #666; font-size: 0.9rem;">
            Dimensión: <strong><?php echo strtoupper($actividad_activa['dimension']); ?></strong> | Puntaje Máximo: <strong><?php echo number_format($actividad_activa['puntaje_maximo'], 2); ?> pts</strong>
        </p>
    </div>

    <form action="actividades.php?id_actividad=<?php echo $id_actividad_eval; ?>" method="POST">
        <input type="hidden" name="action" value="guardar_notas">
        <div class="card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 12px 15px;">#</th>
                        <th style="padding: 12px 15px;">Estudiante</th>
                        <th style="padding: 12px 15px; text-align: center;">¿Entregó?</th>
                        <th style="padding: 12px 15px; width: 180px;">Nota (Máx: <?php echo number_format($actividad_activa['puntaje_maximo'], 2); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($estudiantes_lista as $est): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 15px; color: #777;"><?php echo $idx++; ?></td>
                            <td style="padding: 10px 15px;"><strong><?php echo htmlspecialchars($est['apellidos'] . " " . $est['nombres']); ?></strong></td>
                            <td style="padding: 10px 15px; text-align: center;">
                                <input type="checkbox" name="entregados[<?php echo $est['id_usuario']; ?>]" value="1" <?php echo ($est['entregado'] == 1) ? 'checked' : ''; ?>>
                            </td>
                            <td style="padding: 10px 15px;">
                                <input type="number" step="0.5" min="0" max="<?php echo $actividad_activa['puntaje_maximo']; ?>" name="notas[<?php echo $est['id_usuario']; ?>]" value="<?php echo ($est['nota'] !== null) ? number_format($est['nota'], 2, '.', '') : '0.00'; ?>" required style="padding: 6px 10px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn-submit" style="width: auto; padding: 10px 25px;">💾 Guardar Calificaciones</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.getAttribute('onclick').includes(tabName));
    if (activeBtn) activeBtn.classList.add('active');
    
    const activeContent = document.getElementById('tab-' + tabName);
    if (activeContent) activeContent.classList.add('active');

    const tabInput = document.getElementById('tab_input');
    if (tabInput) tabInput.value = tabName;
}

function actualizarAsignacion(valor) {
    if (!valor) {
        document.getElementById('id_paralelo').value = 0;
        document.getElementById('id_materia').value = 0;
        return;
    }
    const partes = valor.split('-');
    document.getElementById('id_paralelo').value = partes[0];
    document.getElementById('id_materia').value = partes[1];
}
</script>

<?php require_once "../includes/footer_panel.php"; ?>