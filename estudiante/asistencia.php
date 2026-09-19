<?php
session_start();
$ruta_base = "../";

// Control de acceso exclusivo para Estudiantes / Alumnos
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['estudiante', 'alumno'])) {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_estudiante = $_SESSION['id_usuario'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. Obtener el paralelo asignado al estudiante
$sql_ep = "SELECT p.id_paralelo, p.grado, p.letra 
           FROM estudiante_paralelo ep
           INNER JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
           WHERE ep.id_estudiante = ? 
           LIMIT 1";

$stmt_ep = mysqli_prepare($conexion, $sql_ep);
$id_paralelo = 0;
$datos_curso = null;

if ($stmt_ep) {
    mysqli_stmt_bind_param($stmt_ep, "i", $id_estudiante);
    mysqli_stmt_execute($stmt_ep);
    $res_ep = mysqli_stmt_get_result($stmt_ep);
    if ($datos_curso = mysqli_fetch_assoc($res_ep)) {
        $id_paralelo = $datos_curso['id_paralelo'];
    }
    mysqli_stmt_close($stmt_ep);
}

// 2. Consultar historial de asistencias PUBLICADAS del estudiante
$asistencias = [];
$resumen = ['Presente' => 0, 'Falta' => 0, 'Permiso' => 0];

if ($id_paralelo > 0) {
    $sql_asistencia = "SELECT la.fecha, h.hora_inicio, h.hora_fin, 
                              m.nombre_materia, 
                              ad.estado AS estado_asistencia
                       FROM asistencia_detalle ad
                       INNER JOIN listas_asistencia la ON ad.id_lista = la.id_lista
                       INNER JOIN horarios h ON la.id_horario = h.id_horario
                       INNER JOIN materias m ON h.id_materia = m.id_materia
                       WHERE ad.id_estudiante = ? 
                         AND la.estado = 'Publicada'
                         AND DATE_FORMAT(la.fecha, '%Y-%m') = ?
                       ORDER BY la.fecha DESC, h.hora_inicio ASC";

    $stmt_a = mysqli_prepare($conexion, $sql_asistencia);
    mysqli_stmt_bind_param($stmt_a, "is", $id_estudiante, $mes_filtro);
    mysqli_stmt_execute($stmt_a);
    $res_a = mysqli_stmt_get_result($stmt_a);

    while ($row = mysqli_fetch_assoc($res_a)) {
        $asistencias[] = $row;
        if (isset($resumen[$row['estado_asistencia']])) {
            $resumen[$row['estado_asistencia']]++;
        }
    }
    mysqli_stmt_close($stmt_a);
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📊 Mi Récord de Asistencia</h2>
    <?php if ($datos_curso): ?>
        <p>Curso: <strong><?php echo $datos_curso['grado']; ?>° de Secundaria - Paralelo "<?php echo $datos_curso['letra']; ?>"</strong></p>
    <?php else: ?>
        <p>Consulta tus asistencias, faltas y permisos confirmados por regencia.</p>
    <?php endif; ?>
</div>

<!-- Filtro de Mes y Tarjetas Resumen -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="card" style="padding: 15px;">
        <form action="asistencia.php" method="GET" style="margin: 0;">
            <label for="mes" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 5px;">Filtrar por Mes:</label>
            <input type="month" name="mes" id="mes" value="<?php echo htmlspecialchars($mes_filtro); ?>" onchange="this.form.submit()" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </form>
    </div>

    <div class="card" style="padding: 15px; border-left: 5px solid #2e7d32; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">PRESENTES</span>
            <h3 style="margin: 0; color: #2e7d32; font-size: 1.5rem;"><?php echo $resumen['Presente']; ?></h3>
        </div>
        <span style="font-size: 1.8rem;">✅</span>
    </div>

    <div class="card" style="padding: 15px; border-left: 5px solid #c62828; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">FALTAS</span>
            <h3 style="margin: 0; color: #c62828; font-size: 1.5rem;"><?php echo $resumen['Falta']; ?></h3>
        </div>
        <span style="font-size: 1.8rem;">❌</span>
    </div>

    <div class="card" style="padding: 15px; border-left: 5px solid #0288d1; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">PERMISOS</span>
            <h3 style="margin: 0; color: #0288d1; font-size: 1.5rem;"><?php echo $resumen['Permiso']; ?></h3>
        </div>
        <span style="font-size: 1.8rem;">📝</span>
    </div>
</div>

<!-- Tabla del Récord -->
<div class="card">
    <h3 style="color: var(--primary); margin-bottom: 15px;">Detalle de Asistencias Publicadas</h3>

    <?php if ($id_paralelo === 0): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">No tienes un paralelo asignado actualmente.</p>
    <?php elseif (!empty($asistencias)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 10px 8px;">Fecha</th>
                        <th style="padding: 10px 8px;">Materia</th>
                        <th style="padding: 10px 8px;">Horario</th>
                        <th style="padding: 10px 8px; text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistencias as $a): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px 8px; font-weight: 600;">
                                <?php echo date('d/m/Y', strtotime($a['fecha'])); ?>
                            </td>
                            <td style="padding: 10px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars($a['nombre_materia']); ?>
                            </td>
                            <td style="padding: 10px 8px; color: var(--text-muted); font-size: 0.88rem;">
                                <?php echo substr($a['hora_inicio'], 0, 5) . " - " . substr($a['hora_fin'], 0, 5); ?>
                            </td>
                            <td style="padding: 10px 8px; text-align: center;">
                                <?php
                                $badge_bg = '#e8f5e9'; $badge_c = '#2e7d32'; $label = 'Presente';
                                if ($a['estado_asistencia'] === 'Falta') {
                                    $badge_bg = '#ffebee'; $badge_c = '#c62828'; $label = 'Falta';
                                } elseif ($a['estado_asistencia'] === 'Permiso') {
                                    $badge_bg = '#e1f5fe'; $badge_c = '#0288d1'; $label = 'Permiso / Licencia';
                                }
                                ?>
                                <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_c; ?>; padding: 4px 12px; border-radius: 12px; font-size: 0.82rem; font-weight: 700; display: inline-block;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 25px 0;">
            No se encontraron registros de asistencia publicados para este mes.
        </p>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>