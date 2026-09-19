<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/header.php";

// Consulta para obtener los logros ordenados por fecha más reciente
$sql_logros = "SELECT * FROM logros ORDER BY fecha DESC";
$res_logros = mysqli_query($conexion, $sql_logros);
?>

<div class="card hero-section" style="text-align: center; margin-bottom: 30px;">
    <h1>Logros y Talento Estudiantil</h1>
    <p>Reconocimiento al esfuerzo, dedicación y excelencia de nuestros estudiantes en distintas disciplinas.</p>
</div>

<!-- Rejilla de Tarjetas de Logros -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php if ($res_logros && mysqli_num_rows($res_logros) > 0): ?>
        <?php while ($logro = mysqli_fetch_assoc($res_logros)): ?>
            <?php
                // Asignar color e ícono según la categoría
                $badge_bg = '#edf2f7';
                $badge_color = 'var(--text-main)';
                $icono = '🏆';

                if ($logro['categoria'] === 'Deportivo') {
                    $badge_bg = '#feebc8';
                    $badge_color = '#742a2a';
                    $icono = '⚽';
                } elseif ($logro['categoria'] === 'Académico') {
                    $badge_bg = '#ebf8ff';
                    $badge_color = '#2c5282';
                    $icono = '🥇';
                } elseif ($logro['categoria'] === 'Artístico') {
                    $badge_bg = '#faf5ff';
                    $badge_color = '#553c9a';
                    $icono = '🎨';
                }
            ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary);">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="background-color: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                            <?php echo $icono . ' ' . $logro['categoria']; ?>
                        </span>
                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                            📅 <?php echo date("d/m/Y", strtotime($logro['fecha'])); ?>
                        </span>
                    </div>
                    
                    <h3 style="color: var(--primary); font-size: 1.15rem; margin-bottom: 10px; line-height: 1.3;">
                        <?php echo $logro['titulo']; ?>
                    </h3>
                    
                    <p style="color: var(--text-main); font-size: 0.95rem; line-height: 1.5;">
                        <?php echo $logro['descripcion']; ?>
                    </p>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; color: var(--text-muted);">
            <p>No hay logros registrados en este momento.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>