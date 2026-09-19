<?php
session_start();
$ruta_base = "../";

// Control de acceso: Solo Administradores
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

// ==========================================
// PROCESAMIENTO DE ACCIONES POST (Crear Rápido, Crear Individual, Eliminar)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // A) CARGA RÁPIDA DE 3 PERIODOS (TURNO MAÑANA)
    if ($accion === 'crear_rapido') {
        $id_paralelo = intval($_POST['id_paralelo'] ?? 0);
        $dia         = trim($_POST['dia'] ?? '');
        $periodos    = $_POST['periodos'] ?? [];

        if ($id_paralelo <= 0 || empty($dia)) {
            header("Location: horarios.php?error=" . urlencode("Debes seleccionar el paralelo y el día."));
            exit();
        }

        mysqli_begin_transaction($conexion);
        $insertados = 0;
        $errores = [];

        foreach ($periodos as $nro_periodo => $datos) {
            $id_materia          = !empty($datos['id_materia']) ? intval($datos['id_materia']) : null;
            $id_docente          = !empty($datos['id_docente']) ? intval($datos['id_docente']) : null;
            $hora_inicio         = $datos['hora_inicio'] ?? '';
            $hora_fin            = $datos['hora_fin'] ?? '';
            $requiere_asistencia = isset($datos['requiere_asistencia']) ? 1 : 0;

            if (!$id_materia) {
                continue;
            }

            if (!$id_docente) {
                $errores[] = "Periodo $nro_periodo: Asignaste materia pero no seleccionaste docente.";
                continue;
            }

            // 1. Validar cruce de paralelo en el periodo
            $sql_cruce_par = "SELECT id_horario FROM horarios WHERE id_paralelo = ? AND dia = ? AND (? < hora_fin AND ? > hora_inicio)";
            $stmt_par = mysqli_prepare($conexion, $sql_cruce_par);
            mysqli_stmt_bind_param($stmt_par, "isss", $id_paralelo, $dia, $hora_inicio, $hora_fin);
            mysqli_stmt_execute($stmt_par);
            mysqli_stmt_store_result($stmt_par);
            if (mysqli_stmt_num_rows($stmt_par) > 0) {
                $errores[] = "Periodo $nro_periodo ($hora_inicio - $hora_fin): El paralelo ya tiene una clase registrada en ese horario.";
                mysqli_stmt_close($stmt_par);
                continue;
            }
            mysqli_stmt_close($stmt_par);

            // 2. Validar cruce de docente en el periodo
            $sql_cruce_doc = "SELECT id_horario FROM horarios WHERE id_docente = ? AND dia = ? AND (? < hora_fin AND ? > hora_inicio)";
            $stmt_doc = mysqli_prepare($conexion, $sql_cruce_doc);
            mysqli_stmt_bind_param($stmt_doc, "isss", $id_docente, $dia, $hora_inicio, $hora_fin);
            mysqli_stmt_execute($stmt_doc);
            mysqli_stmt_store_result($stmt_doc);
            if (mysqli_stmt_num_rows($stmt_doc) > 0) {
                $errores[] = "Periodo $nro_periodo ($hora_inicio - $hora_fin): El docente ya imparte clase en otro curso a esa misma hora.";
                mysqli_stmt_close($stmt_doc);
                continue;
            }
            mysqli_stmt_close($stmt_doc);

            // Insertar periodo
            $sql_ins = "INSERT INTO horarios (id_paralelo, id_materia, id_docente, dia, hora_inicio, hora_fin, requiere_asistencia) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_ins = mysqli_prepare($conexion, $sql_ins);
            mysqli_stmt_bind_param($stmt_ins, "iiisssi", $id_paralelo, $id_materia, $id_docente, $dia, $hora_inicio, $hora_fin, $requiere_asistencia);
            if (mysqli_stmt_execute($stmt_ins)) {
                $insertados++;
            }
            mysqli_stmt_close($stmt_ins);
        }

        if (!empty($errores)) {
            mysqli_rollback($conexion);
            header("Location: horarios.php?error=" . urlencode("Conflictos al guardar el día:<br>" . implode("<br>", $errores)));
            exit();
        } else if ($insertados === 0) {
            mysqli_rollback($conexion);
            header("Location: horarios.php?error=" . urlencode("No seleccionaste ninguna materia para registrar."));
            exit();
        } else {
            mysqli_commit($conexion);
            header("Location: horarios.php?exito=" . urlencode("¡Éxito! Se registraron $insertados periodos para el día $dia correctamente."));
            exit();
        }
    }

    // B) CREAR HORARIO INDIVIDUAL
    if ($accion === 'crear') {
        $id_paralelo         = intval($_POST['id_paralelo'] ?? 0);
        $id_materia          = intval($_POST['id_materia'] ?? 0);
        $id_docente          = intval($_POST['id_docente'] ?? 0);
        $dia                 = trim($_POST['dia'] ?? '');
        $hora_inicio         = $_POST['hora_inicio'] ?? '';
        $hora_fin            = $_POST['hora_fin'] ?? '';
        $requiere_asistencia = isset($_POST['requiere_asistencia']) ? 1 : 0;

        if ($id_paralelo <= 0 || $id_materia <= 0 || $id_docente <= 0 || empty($dia) || empty($hora_inicio) || empty($hora_fin)) {
            header("Location: horarios.php?error=" . urlencode("Todos los campos marcados con * son obligatorios."));
            exit();
        }

        if ($hora_inicio >= $hora_fin) {
            header("Location: horarios.php?error=" . urlencode("La hora de inicio debe ser menor a la hora de fin."));
            exit();
        }

        // Cruce Docente
        $sql_cruce_docente = "SELECT id_horario FROM horarios WHERE id_docente = ? AND dia = ? AND (? < hora_fin AND ? > hora_inicio)";
        $stmt_doc = mysqli_prepare($conexion, $sql_cruce_docente);
        mysqli_stmt_bind_param($stmt_doc, "isss", $id_docente, $dia, $hora_inicio, $hora_fin);
        mysqli_stmt_execute($stmt_doc);
        mysqli_stmt_store_result($stmt_doc);
        if (mysqli_stmt_num_rows($stmt_doc) > 0) {
            mysqli_stmt_close($stmt_doc);
            header("Location: horarios.php?error=" . urlencode("Cruce detectado: El docente seleccionado ya tiene asignada una clase en ese mismo día y horario."));
            exit();
        }
        mysqli_stmt_close($stmt_doc);

        // Cruce Paralelo
        $sql_cruce_paralelo = "SELECT id_horario FROM horarios WHERE id_paralelo = ? AND dia = ? AND (? < hora_fin AND ? > hora_inicio)";
        $stmt_par = mysqli_prepare($conexion, $sql_cruce_paralelo);
        mysqli_stmt_bind_param($stmt_par, "isss", $id_paralelo, $dia, $hora_inicio, $hora_fin);
        mysqli_stmt_execute($stmt_par);
        mysqli_stmt_store_result($stmt_par);
        if (mysqli_stmt_num_rows($stmt_par) > 0) {
            mysqli_stmt_close($stmt_par);
            header("Location: horarios.php?error=" . urlencode("Cruce detectado: Este paralelo ya tiene una materia asignada en ese mismo día y horario."));
            exit();
        }
        mysqli_stmt_close($stmt_par);

        $sql_insert = "INSERT INTO horarios (id_paralelo, id_materia, id_docente, dia, hora_inicio, hora_fin, requiere_asistencia) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = mysqli_prepare($conexion, $sql_insert);
        mysqli_stmt_bind_param($stmt_ins, "iiisssi", $id_paralelo, $id_materia, $id_docente, $dia, $hora_inicio, $hora_fin, $requiere_asistencia);

        if (mysqli_stmt_execute($stmt_ins)) {
            mysqli_stmt_close($stmt_ins);
            header("Location: horarios.php?exito=" . urlencode("Horario individual registrado exitosamente."));
            exit();
        } else {
            mysqli_stmt_close($stmt_ins);
            header("Location: horarios.php?error=" . urlencode("Error en la base de datos al registrar el horario."));
            exit();
        }
    }

    // C) ELIMINAR HORARIO
    if ($accion === 'eliminar') {
        $id_horario = intval($_POST['id_horario'] ?? 0);
        if ($id_horario > 0) {
            $sql_del = "DELETE FROM horarios WHERE id_horario = ?";
            $stmt_del = mysqli_prepare($conexion, $sql_del);
            mysqli_stmt_bind_param($stmt_del, "i", $id_horario);
            if (mysqli_stmt_execute($stmt_del)) {
                mysqli_stmt_close($stmt_del);
                header("Location: horarios.php?exito=" . urlencode("Horario eliminado correctamente."));
                exit();
            } else {
                mysqli_stmt_close($stmt_del);
                header("Location: horarios.php?error=" . urlencode("Error al intentar eliminar el horario."));
                exit();
            }
        }
    }
}

// ==========================================
// CONSULTAS DE VISTA
// ==========================================
$mensaje_exito = $_GET['exito'] ?? '';
$mensaje_error = $_GET['error'] ?? '';

// Obtener paralelos
$sql_paralelos = "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado ASC, letra ASC";
$res_paralelos = mysqli_query($conexion, $sql_paralelos);
$paralelos_arr = [];
while ($row = mysqli_fetch_assoc($res_paralelos)) {
    $paralelos_arr[] = $row;
}

// Obtener materias
$sql_materias = "SELECT id_materia, nombre_materia, turno FROM materias ORDER BY turno ASC, nombre_materia ASC";
$res_materias = mysqli_query($conexion, $sql_materias);
$materias_arr = [];
while ($row = mysqli_fetch_assoc($res_materias)) {
    $materias_arr[] = $row;
}

// Obtener docentes
$sql_docentes = "SELECT id_usuario, nombres, apellidos FROM usuarios WHERE id_rol = 2 AND estado = 1 ORDER BY apellidos ASC, nombres ASC";
$res_docentes = mysqli_query($conexion, $sql_docentes);
$docentes_arr = [];
while ($row = mysqli_fetch_assoc($res_docentes)) {
    $docentes_arr[] = $row;
}

// Obtener horarios registrados
$sql_horarios = "SELECT h.id_horario, h.dia, h.hora_inicio, h.hora_fin, h.requiere_asistencia,
                        p.id_paralelo, p.grado, p.letra,
                        m.nombre_materia, m.turno,
                        u.nombres AS docente_nombres, u.apellidos AS docente_apellidos
                 FROM horarios h
                 INNER JOIN paralelos p ON h.id_paralelo = p.id_paralelo
                 INNER JOIN materias m ON h.id_materia = m.id_materia
                 INNER JOIN usuarios u ON h.id_docente = u.id_usuario
                 ORDER BY p.grado ASC, p.letra ASC, FIELD(h.dia, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Sábado'), h.hora_inicio ASC";
$res_horarios = mysqli_query($conexion, $sql_horarios);

$horarios_agrupados = [];
while ($row = mysqli_fetch_assoc($res_horarios)) {
    $key_paralelo = $row['grado'] . "° de Secundaria - Paralelo \"" . $row['letra'] . "\"";
    $horarios_agrupados[$key_paralelo][] = $row;
}

require_once "../includes/header_panel.php";
?>

<!-- Estilos Específicos para Horarios -->
<style>
    .schedule-header-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 24px 28px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    .schedule-header-card h2 {
        margin: 0 0 6px 0;
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }
    .schedule-header-card p {
        margin: 0;
        color: #94a3b8;
        font-size: 0.95rem;
    }

    /* Pestañas de Navegación */
    .nav-tabs-custom {
        display: flex;
        gap: 10px;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 24px;
    }
    .tab-btn {
        background: none;
        border: none;
        padding: 12px 20px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tab-btn:hover {
        color: #0f172a;
    }
    .tab-btn.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }

    /* Contenedores de Sección */
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }

    /* Tarjetas del Formulario */
    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 24px;
    }
    .form-card-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Tabla de Carga Rápida */
    .quick-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .quick-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    .quick-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .quick-table tr:last-child td {
        border-bottom: none;
    }

    /* Inputs y Selects Estilizados */
    .form-control-custom {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.9rem;
        color: #1e293b;
        background-color: #fff;
        transition: border-color 0.15s ease;
    }
    .form-control-custom:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* Badges Visuales */
    .badge-shift {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .badge-morning { background: #e0f2fe; color: #0369a1; }
    .badge-afternoon { background: #ffedf7; color: #9d174d; }

    .badge-day {
        background: #f1f5f9;
        color: #334155;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
    }

    /* Botones Pro */
    .btn-action-primary {
        background-color: #2563eb;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-action-primary:hover { background-color: #1d4ed8; }

    .btn-action-success {
        background-color: #16a34a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-action-success:hover { background-color: #15803d; }

    .btn-delete {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fca5a5;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-delete:hover {
        background: #dc2626;
        color: #ffffff;
    }

    /* Contenedor de Alertas */
    .alert-custom {
        padding: 14px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-success-custom { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger-custom { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<!-- Encabezado Principal -->
<div class="schedule-header-card">
    <h2>📅 Gestión de Horarios Escolares</h2>
    <p>Planificación de periodos académicos, asignación docente y control de asistencia por paralelo.</p>
</div>

<!-- Alertas de Sistema -->
<?php if ($mensaje_exito): ?>
    <div class="alert-custom alert-success-custom">
        <span>✅</span>
        <div><?php echo urldecode($mensaje_exito); ?></div>
    </div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
    <div class="alert-custom alert-danger-custom">
        <span>⚠️</span>
        <div><?php echo urldecode($mensaje_error); ?></div>
    </div>
<?php endif; ?>

<!-- Navegación por Pestañas -->
<div class="nav-tabs-custom">
    <button class="tab-btn active" onclick="openTab('tab-rapido', this)">
        ⚡ Carga Rápida (Turno Mañana)
    </button>
    <button class="tab-btn" onclick="openTab('tab-individual', this)">
        ➕ Registrar Periodo Individual
    </button>
    <button class="tab-btn" onclick="openTab('tab-listado', this)">
        📋 Horarios Asignados (<?php echo count($horarios_agrupados); ?> Cursos)
    </button>
</div>

<!-- ========================================== -->
<!-- PESTAÑA 1: CARGA RÁPIDA POR DÍA -->
<!-- ========================================== -->
<div id="tab-rapido" class="tab-content active">
    <div class="form-card" style="border-top: 4px solid #16a34a;">
        <div class="form-card-title">
            <span>⚡ Asignación Masiva por Día</span>
        </div>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: -8px; margin-bottom: 20px;">
            Define en un solo paso los 3 periodos principales de la jornada escolar para un paralelo específico.
        </p>

        <form action="horarios.php" method="POST">
            <input type="hidden" name="accion" value="crear_rapido">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; background: #f8fafc; padding: 18px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Paralelo *</label>
                    <select name="id_paralelo" class="form-control-custom" required>
                        <option value="">-- Seleccionar curso --</option>
                        <?php foreach ($paralelos_arr as $p): ?>
                            <option value="<?php echo $p['id_paralelo']; ?>">
                                <?php echo $p['grado']; ?>° de Secundaria - Paralelo "<?php echo $p['letra']; ?>"
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Día de la Semana *</label>
                    <select name="dia" class="form-control-custom" required>
                        <option value="">-- Seleccionar día --</option>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miercoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Sabado">Sábado</option>
                    </select>
                </div>
            </div>

            <!-- Tabla Interactiva -->
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table class="quick-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Periodo</th>
                            <th style="width: 140px;">Horario</th>
                            <th>Materia</th>
                            <th>Docente</th>
                            <th style="width: 110px; text-align: center;">¿Toma Lista?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Periodo 1 -->
                        <tr>
                            <td><strong style="color: #1e293b;">Periodo 1</strong></td>
                            <td>
                                <span class="badge-day">08:00 - 09:20</span>
                                <input type="hidden" name="periodos[1][hora_inicio]" value="08:00:00">
                                <input type="hidden" name="periodos[1][hora_fin]" value="09:20:00">
                            </td>
                            <td>
                                <select name="periodos[1][id_materia]" class="form-control-custom">
                                    <option value="">-- Libre / Ninguna --</option>
                                    <?php foreach ($materias_arr as $m): ?>
                                        <option value="<?php echo $m['id_materia']; ?>">[<?php echo $m['turno']; ?>] <?php echo htmlspecialchars($m['nombre_materia']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="periodos[1][id_docente]" class="form-control-custom">
                                    <option value="">-- Seleccionar Docente --</option>
                                    <?php foreach ($docentes_arr as $d): ?>
                                        <option value="<?php echo $d['id_usuario']; ?>"><?php echo htmlspecialchars($d['apellidos'] . " " . $d['nombres']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="periodos[1][requiere_asistencia]" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                            </td>
                        </tr>

                        <!-- Periodo 2 -->
                        <tr>
                            <td><strong style="color: #1e293b;">Periodo 2</strong></td>
                            <td>
                                <span class="badge-day">09:30 - 10:50</span>
                                <input type="hidden" name="periodos[2][hora_inicio]" value="09:30:00">
                                <input type="hidden" name="periodos[2][hora_fin]" value="10:50:00">
                            </td>
                            <td>
                                <select name="periodos[2][id_materia]" class="form-control-custom">
                                    <option value="">-- Libre / Ninguna --</option>
                                    <?php foreach ($materias_arr as $m): ?>
                                        <option value="<?php echo $m['id_materia']; ?>">[<?php echo $m['turno']; ?>] <?php echo htmlspecialchars($m['nombre_materia']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="periodos[2][id_docente]" class="form-control-custom">
                                    <option value="">-- Seleccionar Docente --</option>
                                    <?php foreach ($docentes_arr as $d): ?>
                                        <option value="<?php echo $d['id_usuario']; ?>"><?php echo htmlspecialchars($d['apellidos'] . " " . $d['nombres']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="periodos[2][requiere_asistencia]" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                            </td>
                        </tr>

                        <!-- Periodo 3 -->
                        <tr>
                            <td><strong style="color: #1e293b;">Periodo 3</strong></td>
                            <td>
                                <span class="badge-day">11:00 - 12:20</span>
                                <input type="hidden" name="periodos[3][hora_inicio]" value="11:00:00">
                                <input type="hidden" name="periodos[3][hora_fin]" value="12:20:00">
                            </td>
                            <td>
                                <select name="periodos[3][id_materia]" class="form-control-custom">
                                    <option value="">-- Libre / Ninguna --</option>
                                    <?php foreach ($materias_arr as $m): ?>
                                        <option value="<?php echo $m['id_materia']; ?>">[<?php echo $m['turno']; ?>] <?php echo htmlspecialchars($m['nombre_materia']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="periodos[3][id_docente]" class="form-control-custom">
                                    <option value="">-- Seleccionar Docente --</option>
                                    <?php foreach ($docentes_arr as $d): ?>
                                        <option value="<?php echo $d['id_usuario']; ?>"><?php echo htmlspecialchars($d['apellidos'] . " " . $d['nombres']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="periodos[3][requiere_asistencia]" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn-action-success">
                💾 Guardar Día Completo
            </button>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- PESTAÑA 2: REGISTRO INDIVIDUAL -->
<!-- ========================================== -->
<div id="tab-individual" class="tab-content">
    <div class="form-card" style="border-top: 4px solid #2563eb;">
        <div class="form-card-title">
            <span>➕ Registrar Horario Individual / Turno Tarde</span>
        </div>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: -8px; margin-bottom: 20px;">
            Ideal para asignaciones fuera del rango estándar o materias optativas en horarios especiales.
        </p>

        <form action="horarios.php" method="POST">
            <input type="hidden" name="accion" value="crear">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Paralelo *</label>
                    <select name="id_paralelo" class="form-control-custom" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($paralelos_arr as $p): ?>
                            <option value="<?php echo $p['id_paralelo']; ?>">
                                <?php echo $p['grado']; ?>° de Secundaria - Paralelo "<?php echo $p['letra']; ?>"
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Materia *</label>
                    <select name="id_materia" class="form-control-custom" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($materias_arr as $m): ?>
                            <option value="<?php echo $m['id_materia']; ?>">
                                [<?php echo $m['turno']; ?>] <?php echo htmlspecialchars($m['nombre_materia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Docente *</label>
                    <select name="id_docente" class="form-control-custom" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($docentes_arr as $d): ?>
                            <option value="<?php echo $d['id_usuario']; ?>">
                                <?php echo htmlspecialchars($d['apellidos'] . " " . $d['nombres']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Día *</label>
                    <select name="dia" class="form-control-custom" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miercoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Sabado">Sábado</option>
                    </select>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Hora Inicio *</label>
                    <input type="time" name="hora_inicio" class="form-control-custom" required>
                </div>

                <div>
                    <label style="font-weight: 600; font-size: 0.88rem; color: #334155; margin-bottom: 6px; display: block;">Hora Fin *</label>
                    <input type="time" name="hora_fin" class="form-control-custom" required>
                </div>
            </div>

            <div style="margin-bottom: 24px; background: #f8fafc; padding: 12px 16px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 600; color: #334155;">
                    <input type="checkbox" name="requiere_asistencia" value="1" style="width: 18px; height: 18px;">
                    ¿Requiere toma de lista obligatoria en este periodo?
                </label>
            </div>

            <div>
                <button type="submit" class="btn-action-primary">
                    💾 Guardar Horario Individual
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- PESTAÑA 3: HORARIOS REGISTRADOS -->
<!-- ========================================== -->
<div id="tab-listado" class="tab-content">
    <?php if (!empty($horarios_agrupados)): ?>
        <?php foreach ($horarios_agrupados as $nombre_paralelo => $lista_clases): ?>
            <div class="form-card">
                <div class="form-card-title" style="justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: #eff6ff; color: #2563eb; padding: 6px 12px; border-radius: 6px; font-size: 0.95rem;">🏫</span>
                        <span style="color: #0f172a; font-size: 1.1rem;"><?php echo $nombre_paralelo; ?></span>
                    </div>
                    <span style="font-size: 0.85rem; font-weight: 500; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 20px;">
                        <?php echo count($lista_clases); ?> Clases Registradas
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="quick-table">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Materia</th>
                                <th>Turno</th>
                                <th>Docente</th>
                                <th style="text-align: center;">Asistencia</th>
                                <th style="text-align: center; width: 100px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_clases as $clase): ?>
                                <tr>
                                    <td>
                                        <span class="badge-day"><?php echo $clase['dia']; ?></span>
                                    </td>
                                    <td style="font-weight: 600; color: #334155;">
                                        <?php echo substr($clase['hora_inicio'], 0, 5) . " - " . substr($clase['hora_fin'], 0, 5); ?>
                                    </td>
                                    <td>
                                        <strong style="color: #0f172a;"><?php echo htmlspecialchars($clase['nombre_materia']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge-shift <?php echo $clase['turno'] === 'Mañana' ? 'badge-morning' : 'badge-afternoon'; ?>">
                                            <?php echo $clase['turno']; ?>
                                        </span>
                                    </td>
                                    <td style="color: #475569;">
                                        <?php echo htmlspecialchars($clase['docente_apellidos'] . " " . $clase['docente_nombres']); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($clase['requiere_asistencia']): ?>
                                            <span style="color: #16a34a; font-weight: 600; font-size: 0.85rem;">✓ Requerida</span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <form action="horarios.php" method="POST" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este horario?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_horario" value="<?php echo $clase['id_horario']; ?>">
                                            <button type="submit" class="btn-delete">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="form-card" style="text-align: center; padding: 48px 24px;">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">📭</div>
            <h4 style="margin: 0 0 6px 0; color: #1e293b; font-size: 1.1rem;">Sin Horarios Registrados</h4>
            <p style="color: #64748b; margin: 0; font-size: 0.9rem;">No hay asignaciones de clases activas en la base de datos.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Scripts de Interacción UI -->
<script>
    function openTab(tabId, element) {
        // Ocultar todos los contenidos de pestañas
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.classList.remove('active'));

        // Desactivar botones de pestañas
        const buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        // Activar la pestaña elegida
        document.getElementById(tabId).classList.add('active');
        element.classList.add('active');
    }
</script>

<?php require_once "../includes/footer_panel.php"; ?>