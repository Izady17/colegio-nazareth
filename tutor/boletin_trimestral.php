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

// Obtener id_config y topes
$config_actual = null;
if ($id_paralelo > 0 && $id_materia > 0) {
    $sql_cd = "SELECT * FROM configuracion_dimensiones 
               WHERE id_paralelo = ? AND id_materia = ? AND id_docente = ? AND trimestre = ? LIMIT 1";
    $stmt_cd = mysqli_prepare($conexion, $sql_cd);
    mysqli_stmt_bind_param($stmt_cd, "iiii", $id_paralelo, $id_materia, $id_docente, $trimestre);
    mysqli_stmt_execute($stmt_cd);
    $res_cd = mysqli_stmt_get_result($stmt_cd);
    $config_actual = mysqli_fetch_assoc($res_cd);
    mysqli_stmt_close($stmt_cd);
}

// Obtener tope autoevaluacion docente
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

// PROCESAR BOTONES DE CÁLCULO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config_actual) {
    $action = $_POST['action'] ?? '';
    $id_config = $config_actual['id_config'];

    // 1. CALCULAR PROMEDIO PROYECTADO
    if ($action === 'calcular_proyeccion') {
        mysqli_begin_transaction($conexion);
        try {
            // Traer estudiantes del paralelo
            $sql_est = "SELECT id_estudiante FROM estudiante_paralelo WHERE id_paralelo = ?";
            $stmt_e = mysqli_prepare($conexion, $sql_est);
            mysqli_stmt_bind_param($stmt_e, "i", $id_paralelo);
            mysqli_stmt_execute($stmt_e);
            $res_e = mysqli_stmt_get_result($stmt_e);

            while ($est = mysqli_fetch_assoc($res_e)) {
                $id_est = $est['id_estudiante'];

                // Sumar notas actuales de tareas por dimensión
                $sql_sum_dim = "SELECT a.dimension, SUM(n.nota) as acumulado
                                FROM actividades_dimension a
                                INNER JOIN notas_actividades n ON a.id_actividad = n.id_actividad
                                WHERE a.id_config = ? AND n.id_estudiante = ?
                                GROUP BY a.dimension";
                $stmt_sd = mysqli_prepare($conexion, $sql_sum_dim);
                mysqli_stmt_bind_param($stmt_sd, "ii", $id_config, $id_est);
                mysqli_stmt_execute($stmt_sd);
                $res_sd = mysqli_stmt_get_result($stmt_sd);

                $ser = 0; $saber = 0; $hacer = 0; $decidir = 0;
                while ($sd = mysqli_fetch_assoc($res_sd)) {
                    if ($sd['dimension'] === 'Ser') $ser = min(floatval($sd['acumulado']), $config_actual['max_ser']);
                    if ($sd['dimension'] === 'Saber') $saber = min(floatval($sd['acumulado']), $config_actual['max_saber']);
                    if ($sd['dimension'] === 'Hacer') $hacer = min(floatval($sd['acumulado']), $config_actual['max_hacer']);
                    if ($sd['dimension'] === 'Decidir') $decidir = min(floatval($sd['acumulado']), $config_actual['max_decidir']);
                }
                mysqli_stmt_close($stmt_sd);

                // Asistencia proyectada (Asume el valor máximo)
                $asistencia_proj = $config_actual['max_asistencia'];

                // Autoevaluación proyectada (Asume el máximo)
                $auto_proj = $max_autoeval;

                $promedio_proyectado = $ser + $saber + $hacer + $decidir + $asistencia_proj + $auto_proj;
                $en_riesgo = ($promedio_proyectado < 51) ? 1 : 0;

                // Upsert en boletin_trimestral
                $sql_up_b = "INSERT INTO boletin_trimestral 
                                (id_config, id_estudiante, nota_ser, nota_saber, nota_hacer, nota_decidir, nota_asistencia, nota_autoevaluacion, promedio_proyectado, en_riesgo)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE 
                                nota_ser = VALUES(nota_ser),
                                nota_saber = VALUES(nota_saber),
                                nota_hacer = VALUES(nota_hacer),
                                nota_decidir = VALUES(nota_decidir),
                                promedio_proyectado = VALUES(promedio_proyectado),
                                en_riesgo = VALUES(en_riesgo)";

                $stmt_ub = mysqli_prepare($conexion, $sql_up_b);
                mysqli_stmt_bind_param($stmt_ub, "iidddddddi", $id_config, $id_est, $ser, $saber, $hacer, $decidir, $asistencia_proj, $auto_proj, $promedio_proyectado, $en_riesgo);
                mysqli_stmt_execute($stmt_ub);
                mysqli_stmt_close($stmt_ub);
            }
            mysqli_stmt_close($stmt_e);
            mysqli_commit($conexion);

            $mensaje = "Proyección del trimestre calculada correctamente.";
            $tipo_mensaje = "exito";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error al proyectar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    // 2. CALCULAR PROMEDIO FINAL
    if ($action === 'calcular_final') {
        mysqli_begin_transaction($conexion);
        try {
            $sql_est = "SELECT id_estudiante FROM estudiante_paralelo WHERE id_paralelo = ?";
            $stmt_e = mysqli_prepare($conexion, $sql_est);
            mysqli_stmt_bind_param($stmt_e, "i", $id_paralelo);
            mysqli_stmt_execute($stmt_e);
            $res_e = mysqli_stmt_get_result($stmt_e);

            while ($est = mysqli_fetch_assoc($res_e)) {
                $id_est = $est['id_estudiante'];

                // Sumar notas por dimensión
                $sql_sum_dim = "SELECT a.dimension, SUM(n.nota) as acumulado
                                FROM actividades_dimension a
                                INNER JOIN notas_actividades n ON a.id_actividad = n.id_actividad
                                WHERE a.id_config = ? AND n.id_estudiante = ?
                                GROUP BY a.dimension";
                $stmt_sd = mysqli_prepare($conexion, $sql_sum_dim);
                mysqli_stmt_bind_param($stmt_sd, "ii", $id_config, $id_est);
                mysqli_stmt_execute($stmt_sd);
                $res_sd = mysqli_stmt_get_result($stmt_sd);

                $ser = 0; $saber = 0; $hacer = 0; $decidir = 0;
                while ($sd = mysqli_fetch_assoc($res_sd)) {
                    if ($sd['dimension'] === 'Ser') $ser = min(floatval($sd['acumulado']), $config_actual['max_ser']);
                    if ($sd['dimension'] === 'Saber') $saber = min(floatval($sd['acumulado']), $config_actual['max_saber']);
                    if ($sd['dimension'] === 'Hacer') $hacer = min(floatval($sd['acumulado']), $config_actual['max_hacer']);
                    if ($sd['dimension'] === 'Decidir') $decidir = min(floatval($sd['acumulado']), $config_actual['max_decidir']);
                }
                mysqli_stmt_close($stmt_sd);

                // Asistencia real calculada desde listas_asistencia / asistencia_detalle
                $sql_asig_real = "SELECT 
                                    COUNT(*) as total_clases,
                                    SUM(CASE WHEN ad.estado IN ('presente', 'atraso') THEN 1 ELSE 0 END) as asistidas
                                  FROM listas_asistencia la
                                  INNER JOIN asistencia_detalle ad ON la.id_lista = ad.id_lista
                                  WHERE la.id_paralelo = ? AND la.id_materia = ? AND ad.id_estudiante = ?";
                $stmt_ar = mysqli_prepare($conexion, $sql_asig_real);
                mysqli_stmt_bind_param($stmt_ar, "iii", $id_paralelo, $id_materia, $id_est);
                mysqli_stmt_execute($stmt_ar);
                $res_ar = mysqli_stmt_get_result($stmt_ar);
                $row_ar = mysqli_fetch_assoc($res_ar);
                mysqli_stmt_close($stmt_ar);

                $tot_clases = intval($row_ar['total_clases'] ?? 0);
                $asistidas = intval($row_ar['asistidas'] ?? 0);
                $pct_asistencia = ($tot_clases > 0) ? ($asistidas / $tot_clases) : 1.0;
                $nota_asistencia = $pct_asistencia * $config_actual['max_asistencia'];

                // Autoevaluación real ingresada
                $nota_auto = 0.00;
                $sql_au = "SELECT nota FROM autoevaluaciones WHERE id_config = ? AND id_estudiante = ? LIMIT 1";
                $stmt_au = mysqli_prepare($conexion, $sql_au);
                mysqli_stmt_bind_param($stmt_au, "ii", $id_config, $id_est);
                mysqli_stmt_execute($stmt_au);
                $res_au = mysqli_stmt_get_result($stmt_au);
                if ($r_au = mysqli_fetch_assoc($res_au)) {
                    $nota_auto = min(floatval($r_au['nota']), $max_autoeval);
                }
                mysqli_stmt_close($stmt_au);

                $nota_final = $ser + $saber + $hacer + $decidir + $nota_asistencia + $nota_auto;

                $sql_up_f = "INSERT INTO boletin_trimestral 
                                (id_config, id_estudiante, nota_ser, nota_saber, nota_hacer, nota_decidir, nota_asistencia, nota_autoevaluacion, nota_final, estado_trimestre)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Cerrado')
                             ON DUPLICATE KEY UPDATE 
                                nota_ser = VALUES(nota_ser),
                                nota_saber = VALUES(nota_saber),
                                nota_hacer = VALUES(nota_hacer),
                                nota_decidir = VALUES(nota_decidir),
                                nota_asistencia = VALUES(nota_asistencia),
                                nota_autoevaluacion = VALUES(nota_autoevaluacion),
                                nota_final = VALUES(nota_final),
                                estado_trimestre = 'Cerrado'";

                $stmt_uf = mysqli_prepare($conexion, $sql_up_f);
                mysqli_stmt_bind_param($stmt_uf, "iiddddddd", $id_config, $id_est, $ser, $saber, $hacer, $decidir, $nota_asistencia, $nota_auto, $nota_final);
                mysqli_stmt_execute($stmt_uf);
                mysqli_stmt_close($stmt_uf);
            }
            mysqli_stmt_close($stmt_e);
            mysqli_commit($conexion);

            $mensaje = "Promedio final del trimestre consolidado y registrado.";
            $tipo_mensaje = "exito";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $mensaje = "Error al consolidar promedio: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    // 3. REGISTRAR CITACIÓN / ENTREVISTA
    if ($action === 'citar_entrevista') {
        $id_estudiante_citar = intval($_POST['id_estudiante'] ?? 0);
        $motivo = trim($_POST['motivo_entrevista'] ?? '');

        if ($id_estudiante_citar > 0) {
            $sql_cit = "UPDATE boletin_trimestral 
                        SET entrevista_citada = 1, motivo_entrevista = ?, fecha_entrevista_citada = NOW()
                        WHERE id_config = ? AND id_estudiante = ?";
            $stmt_c = mysqli_prepare($conexion, $sql_cit);
            mysqli_stmt_bind_param($stmt_c, "sii", $motivo, $id_config, $id_estudiante_citar);
            mysqli_stmt_execute($stmt_c);
            mysqli_stmt_close($stmt_c);

            $mensaje = "Citación registrada formalmente para el estudiante.";
            $tipo_mensaje = "exito";
        }
    }
}

// Cargar consolidado de notas del boletín
$boletin_lista = [];
if ($config_actual) {
    $sql_b = "SELECT u.id_usuario, u.nombres, u.apellidos, u.ci,
                     b.nota_ser, b.nota_saber, b.nota_hacer, b.nota_decidir, 
                     b.nota_asistencia, b.nota_autoevaluacion, b.nota_final, 
                     b.promedio_proyectado, b.en_riesgo, b.entrevista_citada, b.motivo_entrevista
              FROM estudiante_paralelo ep
              INNER JOIN usuarios u ON ep.id_estudiante = u.id_usuario
              LEFT JOIN boletin_trimestral b ON (b.id_config = ? AND b.id_estudiante = u.id_usuario)
              WHERE ep.id_paralelo = ?
              ORDER BY u.apellidos ASC, u.nombres ASC";
    $stmt_bl = mysqli_prepare($conexion, $sql_b);
    mysqli_stmt_bind_param($stmt_bl, "ii", $config_actual['id_config'], $id_paralelo);
    mysqli_stmt_execute($stmt_bl);
    $res_bl = mysqli_stmt_get_result($stmt_bl);

    while ($row = mysqli_fetch_assoc($res_bl)) {
        $boletin_lista[] = $row;
    }
    mysqli_stmt_close($stmt_bl);
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📊 Centralizador de Calificaciones y Alertas de Riesgo</h2>
    <p>Calcula proyecciones preventivas, gestiona entrevistas a estudiantes en riesgo y consolida notas finales.</p>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="card" style="margin-bottom: 20px; padding: 15px; background: <?php echo ($tipo_mensaje === 'exito') ? '#e8f5e9' : '#ffebee'; ?>; border-left: 5px solid <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
        <strong style="color: <?php echo ($tipo_mensaje === 'exito') ? '#2e7d32' : '#c62828'; ?>;">
            <?php echo ($tipo_mensaje === 'exito') ? '✅' : '❌'; ?> <?php echo htmlspecialchars($mensaje); ?>
        </strong>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 25px;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Seleccionar Asignatura y Trimestre</h3>
    <form action="boletin_trimestral.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
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
            <select name="trimestre" onchange="this.form.submit()">
                <option value="1" <?php echo ($trimestre === 1) ? 'selected' : ''; ?>>1° Trimestre</option>
                <option value="2" <?php echo ($trimestre === 2) ? 'selected' : ''; ?>>2° Trimestre</option>
                <option value="3" <?php echo ($trimestre === 3) ? 'selected' : ''; ?>>3° Trimestre</option>
            </select>
        </div>

        <button type="submit" class="btn-submit" style="width: auto; padding: 9px 20px;">Cargar Boletín</button>
    </form>
</div>

<?php if ($id_paralelo > 0 && $id_materia > 0 && $config_actual): ?>
    <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
        <form action="boletin_trimestral.php" method="POST">
            <input type="hidden" name="action" value="calcular_proyeccion">
            <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
            <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
            <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">
            <button type="submit" class="btn-submit" style="background: #f59e0b; width: auto; padding: 10px 20px;">
                📈 Calcular Promedio Proyectado
            </button>
        </form>

        <form action="boletin_trimestral.php" method="POST">
            <input type="hidden" name="action" value="calcular_final">
            <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
            <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
            <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">
            <button type="submit" class="btn-submit" style="background: #10b981; width: auto; padding: 10px 20px;">
                🔒 Calcular Promedio FINAL Real
            </button>
        </form>

        <a href="exportar_excel.php?id_paralelo=<?php echo $id_paralelo; ?>&id_materia=<?php echo $id_materia; ?>&trimestre=<?php echo $trimestre; ?>" class="btn-submit" style="background: #1e293b; text-decoration: none; width: auto; padding: 10px 20px;">
            📥 Exportar a Excel
        </a>
    </div>

    <div class="card">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="background: #f1f5f9; border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 10px;">Estudiante</th>
                        <th style="padding: 10px; text-align: center;">SER</th>
                        <th style="padding: 10px; text-align: center;">SABER</th>
                        <th style="padding: 10px; text-align: center;">HACER</th>
                        <th style="padding: 10px; text-align: center;">DECIDIR</th>
                        <th style="padding: 10px; text-align: center;">ASIST.</th>
                        <th style="padding: 10px; text-align: center;">AUTO.</th>
                        <th style="padding: 10px; text-align: center; background: #fffbe6;">PROYECTADO</th>
                        <th style="padding: 10px; text-align: center; background: #e6fffa;">FINAL REAL</th>
                        <th style="padding: 10px; text-align: center;">ESTADO / CITACIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boletin_lista as $b): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <strong><?php echo htmlspecialchars($b['apellidos'] . " " . $b['nombres']); ?></strong>
                            </td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_ser'] ?? 0, 1); ?></td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_saber'] ?? 0, 1); ?></td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_hacer'] ?? 0, 1); ?></td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_decidir'] ?? 0, 1); ?></td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_asistencia'] ?? 0, 1); ?></td>
                            <td style="padding: 10px; text-align: center;"><?php echo number_format($b['nota_autoevaluacion'] ?? 0, 1); ?></td>
                            
                            <!-- Proyectado -->
                            <td style="padding: 10px; text-align: center; font-weight: 700; background: #fffbe6; color: <?php echo ($b['en_riesgo'] == 1) ? '#dc2626' : '#059669'; ?>;">
                                <?php echo number_format($b['promedio_proyectado'] ?? 0, 1); ?>
                            </td>

                            <!-- Final Real -->
                            <td style="padding: 10px; text-align: center; font-weight: 700; background: #e6fffa; color: <?php echo (($b['nota_final'] ?? 0) < 51) ? '#dc2626' : '#2563eb'; ?>;">
                                <?php echo number_format($b['nota_final'] ?? 0, 1); ?>
                            </td>

                            <!-- Estado / Citaciones -->
                            <td style="padding: 10px; text-align: center;">
                                <?php if ($b['en_riesgo'] == 1): ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 10px; font-weight: 700; font-size: 0.75rem;">
                                        ⚠️ EN RIESGO
                                    </span>
                                    <br>
                                    <?php if ($b['entrevista_citada'] == 1): ?>
                                        <small style="color: #2563eb; font-weight: 600;">✓ Citado a Entrevista</small>
                                    <?php else: ?>
                                        <button type="button" onclick="abrirModalCitacion(<?php echo $b['id_usuario']; ?>, '<?php echo htmlspecialchars($b['nombres'] . " " . $b['apellidos']); ?>')" style="margin-top: 4px; padding: 3px 8px; font-size: 0.75rem; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                            📢 Citar Entrevista
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-weight: 700; font-size: 0.75rem;">
                                        NORMAL
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal sencillo de citación -->
<div id="modalCitacion" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
    <div style="background: white; padding: 25px; border-radius: 8px; width: 100%; max-width: 450px;">
        <h3 style="margin-top: 0; color: var(--primary);">Solicitar Entrevista con Tutor/Padre</h3>
        <p id="nombreEstudianteModal" style="font-weight: 600; color: #475569;"></p>
        
        <form action="boletin_trimestral.php" method="POST">
            <input type="hidden" name="action" value="citar_entrevista">
            <input type="hidden" name="id_paralelo" value="<?php echo $id_paralelo; ?>">
            <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
            <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>">
            <input type="hidden" name="id_estudiante" id="idEstudianteModal">

            <div class="form-group">
                <label style="font-weight: 600;">Motivo u Observación (Opcional):</label>
                <textarea name="motivo_entrevista" rows="3" placeholder="Motivo por el cual se solicita citación..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="cerrarModalCitacion()" style="padding: 8px 15px; background: #94a3b8; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn-submit" style="width: auto; padding: 8px 18px; background: #dc2626;">Confirmar Citación</button>
            </div>
        </form>
    </div>
</div>

<script>
function actualizarAsignacion(valor) {
    if (!valor) return;
    const partes = valor.split('-');
    document.getElementById('id_paralelo').value = partes[0];
    document.getElementById('id_materia').value = partes[1];
}

function abrirModalCitacion(idEstudiante, nombre) {
    document.getElementById('idEstudianteModal').value = idEstudiante;
    document.getElementById('nombreEstudianteModal').textContent = 'Estudiante: ' + nombre;
    document.getElementById('modalCitacion').style.display = 'flex';
}

function cerrarModalCitacion() {
    document.getElementById('modalCitacion').style.display = 'none';
}
</script>

<?php require_once "../includes/footer_panel.php"; ?>