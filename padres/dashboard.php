<?php
session_start();
$ruta_base = "../";

// Control de acceso exclusivo para la vista de padres de familia
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'padre') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$id_estudiante = $_SESSION['id_usuario'];

// 1. Datos del curso del estudiante
$sql_ep = "SELECT p.grado, p.letra
           FROM estudiante_paralelo ep
           INNER JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
           WHERE ep.id_estudiante = ?
           LIMIT 1";
$stmt_ep = mysqli_prepare($conexion, $sql_ep);
mysqli_stmt_bind_param($stmt_ep, "i", $id_estudiante);
mysqli_stmt_execute($stmt_ep);
$datos_curso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ep));
mysqli_stmt_close($stmt_ep);

// 2. Citaciones a entrevista pendientes (mismo criterio que el dashboard del estudiante)
$sql_entrevistas = "SELECT b.motivo_entrevista, b.fecha_entrevista_citada,
                            m.nombre_materia, u.nombres AS docente_nombres, u.apellidos AS docente_apellidos, cd.trimestre
                     FROM boletin_trimestral b
                     INNER JOIN configuracion_dimensiones cd ON b.id_config = cd.id_config
                     INNER JOIN materias m ON cd.id_materia = m.id_materia
                     INNER JOIN usuarios u ON cd.id_docente = u.id_usuario
                     WHERE b.id_estudiante = ? AND b.entrevista_citada = 1
                     ORDER BY b.fecha_entrevista_citada DESC";
$stmt_ent = mysqli_prepare($conexion, $sql_entrevistas);
mysqli_stmt_bind_param($stmt_ent, "i", $id_estudiante);
mysqli_stmt_execute($stmt_ent);
$entrevistas_pendientes = mysqli_fetch_all(mysqli_stmt_get_result($stmt_ent), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_ent);

// 3. Resumen de asistencia (solo listas ya publicadas)
$resumen_asistencia = ['Presente' => 0, 'Falta' => 0, 'Atraso' => 0, 'Permiso' => 0];
$sql_asist = "SELECT ad.estado
              FROM asistencia_detalle ad
              INNER JOIN listas_asistencia la ON ad.id_lista = la.id_lista
              WHERE ad.id_estudiante = ? AND la.estado = 'Publicada'";
$stmt_asist = mysqli_prepare($conexion, $sql_asist);
mysqli_stmt_bind_param($stmt_asist, "i", $id_estudiante);
mysqli_stmt_execute($stmt_asist);
$res_asist = mysqli_stmt_get_result($stmt_asist);
$total_registros = 0;
while ($row = mysqli_fetch_assoc($res_asist)) {
    if (isset($resumen_asistencia[$row['estado']])) {
        $resumen_asistencia[$row['estado']]++;
    }
    $total_registros++;
}
mysqli_stmt_close($stmt_asist);
$pct_asistencia = $total_registros > 0 ? round(($resumen_asistencia['Presente'] / $total_registros) * 100) : null;

// 4. Actividades: solo el estado (revisado / no entregado), nunca la nota
$sql_act = "SELECT ad.titulo, ad.dimension, m.nombre_materia,
                   na.entregado, na.revisado
            FROM notas_actividades na
            INNER JOIN actividades_dimension ad ON na.id_actividad = ad.id_actividad
            INNER JOIN configuracion_dimensiones cd ON ad.id_config = cd.id_config
            INNER JOIN materias m ON cd.id_materia = m.id_materia
            WHERE na.id_estudiante = ?
            ORDER BY na.actualizado_en DESC
            LIMIT 20";
$stmt_act = mysqli_prepare($conexion, $sql_act);
mysqli_stmt_bind_param($stmt_act, "i", $id_estudiante);
mysqli_stmt_execute($stmt_act);
$actividades = mysqli_fetch_all(mysqli_stmt_get_result($stmt_act), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_act);

require_once "../includes/header_panel.php";
?>

<?php if (!empty($entrevistas_pendientes)): ?>
    <div class="card" style="background-color: #fdecea; border: 2px solid #dc2626; margin-bottom: 20px;">
        <h3 style="color: #b91c1c; margin-bottom: 10px;">⚠️ Citación a Entrevista</h3>
        <?php foreach ($entrevistas_pendientes as $ent): ?>
            <div style="padding: 10px 0; border-top: 1px solid #f5c6cb;">
                <p style="margin: 0; color: #7f1d1d;">
                    <strong><?php echo htmlspecialchars($ent['nombre_materia']); ?></strong>
                    (Trimestre <?php echo (int)$ent['trimestre']; ?>) —
                    Docente: <?php echo htmlspecialchars($ent['docente_apellidos'] . " " . $ent['docente_nombres']); ?>
                </p>
                <?php if (!empty($ent['motivo_entrevista'])): ?>
                    <p style="margin: 5px 0 0; color: #7f1d1d;"><em><?php echo htmlspecialchars($ent['motivo_entrevista']); ?></em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p style="margin-top: 12px; font-weight: 600; color: #7f1d1d;">
            Estimado padre/madre de familia, por favor asista a la entrevista presencial con el docente para coordinar los detalles.
        </p>
    </div>
<?php endif; ?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>👨‍👩‍👧 Portal de Familia</h2>
    <p>
        Resumen de <strong><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></strong>
        <?php if ($datos_curso): ?>
            — <?php echo $datos_curso['grado']; ?>° de Secundaria, Paralelo "<?php echo $datos_curso['letra']; ?>"
        <?php endif; ?>
    </p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="card" style="padding: 15px; border-left: 5px solid #2e7d32;">
        <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">% DE ASISTENCIA</span>
        <h3 style="margin: 5px 0 0; color: #2e7d32; font-size: 1.8rem;">
            <?php echo $pct_asistencia !== null ? $pct_asistencia . "%" : "Sin datos"; ?>
        </h3>
    </div>
    <div class="card" style="padding: 15px; border-left: 5px solid #c62828;">
        <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">FALTAS REGISTRADAS</span>
        <h3 style="margin: 5px 0 0; color: #c62828; font-size: 1.8rem;"><?php echo $resumen_asistencia['Falta']; ?></h3>
    </div>
    <div class="card" style="padding: 15px; border-left: 5px solid #f57c00;">
        <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">ATRASOS</span>
        <h3 style="margin: 5px 0 0; color: #f57c00; font-size: 1.8rem;"><?php echo $resumen_asistencia['Atraso']; ?></h3>
    </div>
    <div class="card" style="padding: 15px; border-left: 5px solid #0288d1;">
        <span style="font-size: 0.8rem; color: #4b5563; font-weight: 600;">PERMISOS</span>
        <h3 style="margin: 5px 0 0; color: #0288d1; font-size: 1.8rem;"><?php echo $resumen_asistencia['Permiso']; ?></h3>
    </div>
</div>

<div class="card">
    <h3 style="color: var(--primary); margin-bottom: 15px;">📋 Tareas y Actividades Recientes</h3>
    <?php if (!empty($actividades)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                        <th style="padding: 8px;">Materia</th>
                        <th style="padding: 8px;">Dimensión</th>
                        <th style="padding: 8px;">Actividad</th>
                        <th style="padding: 8px; text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actividades as $a): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 8px;"><?php echo htmlspecialchars($a['nombre_materia']); ?></td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($a['dimension']); ?></td>
                            <td style="padding: 8px;"><?php echo htmlspecialchars($a['titulo']); ?></td>
                            <td style="padding: 8px; text-align: center;">
                                <?php if ($a['revisado']): ?>
                                    <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:12px;font-weight:700;font-size:0.82rem;">Revisada</span>
                                <?php elseif ($a['entregado']): ?>
                                    <span style="background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:12px;font-weight:700;font-size:0.82rem;">Entregada</span>
                                <?php else: ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-weight:700;font-size:0.82rem;">No entregada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">Aún no hay actividades registradas.</p>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>
