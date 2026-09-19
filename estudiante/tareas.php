<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";
require_once "../includes/header_panel.php";

$id_estudiante = $_SESSION['id_usuario'];

// Tareas/actividades reales asignadas al paralelo del estudiante (sistema de dimensiones)
$sql = "SELECT ad.id_actividad, ad.titulo, ad.descripcion, ad.dimension, ad.fecha_limite, ad.puntaje_maximo,
               m.nombre_materia, cd.trimestre,
               na.entregado, na.revisado
        FROM configuracion_dimensiones cd
        INNER JOIN estudiante_paralelo ep ON ep.id_paralelo = cd.id_paralelo
        INNER JOIN actividades_dimension ad ON ad.id_config = cd.id_config
        INNER JOIN materias m ON cd.id_materia = m.id_materia
        LEFT JOIN notas_actividades na ON na.id_actividad = ad.id_actividad AND na.id_estudiante = ep.id_estudiante
        WHERE ep.id_estudiante = ?
        ORDER BY (ad.fecha_limite IS NULL), ad.fecha_limite ASC, m.nombre_materia ASC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_estudiante);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// Agrupar por materia para que se vea ordenado
$tareas_por_materia = [];
while ($t = mysqli_fetch_assoc($resultado)) {
    $tareas_por_materia[$t['nombre_materia']][] = $t;
}
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📚 Mis Tareas y Actividades</h2>
    <p>Revisa las actividades asignadas por tus docentes en cada materia.</p>
</div>

<?php if (!empty($tareas_por_materia)): ?>
    <?php foreach ($tareas_por_materia as $materia => $lista): ?>
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="color: var(--primary); margin-bottom: 15px;">📖 <?php echo htmlspecialchars($materia); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($lista as $t): ?>
                    <?php
                        if ($t['revisado']) {
                            $color = '#22c55e'; $bg = '#dcfce7'; $texto_color = '#15803d'; $estado_txt = '✅ Revisada';
                        } elseif ($t['entregado']) {
                            $color = '#eab308'; $bg = '#fef9c3'; $texto_color = '#854d0e'; $estado_txt = '📤 Entregada';
                        } else {
                            $color = '#dc2626'; $bg = '#fee2e2'; $texto_color = '#991b1b'; $estado_txt = '⏳ Pendiente';
                        }
                    ?>
                    <div style="border: 1px solid var(--border); border-left: 4px solid <?php echo $color; ?>; padding: 14px; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
                            <h4 style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($t['titulo']); ?></h4>
                            <span style="background: <?php echo $bg; ?>; color: <?php echo $texto_color; ?>; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">
                                <?php echo $estado_txt; ?>
                            </span>
                        </div>
                        <?php if (!empty($t['descripcion'])): ?>
                            <p style="color: var(--text-main); font-size: 0.9rem; margin: 6px 0;"><?php echo nl2br(htmlspecialchars($t['descripcion'])); ?></p>
                        <?php endif; ?>
                        <small style="color: var(--text-muted); font-weight: 600;">
                            🏷️ Dimensión: <?php echo htmlspecialchars($t['dimension']); ?>
                            <?php if (!empty($t['fecha_limite'])): ?>
                                &nbsp;|&nbsp; 📅 Entrega: <?php echo date('d/m/Y', strtotime($t['fecha_limite'])); ?>
                            <?php endif; ?>
                            &nbsp;|&nbsp; Trimestre <?php echo (int)$t['trimestre']; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 30px;">
        <p style="color: var(--text-muted);">Todavía no tienes actividades asignadas.</p>
    </div>
<?php endif; ?>

<?php require_once "../includes/footer_panel.php"; ?>
