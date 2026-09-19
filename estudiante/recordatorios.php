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

// Consulta corregida: Recordatorios generales (id_paralelo IS NULL) o específicos de su paralelo
$sql = "SELECT r.* 
        FROM recordatorios r
        LEFT JOIN estudiante_paralelo ep ON r.id_paralelo = ep.id_paralelo
        WHERE r.id_paralelo IS NULL OR ep.id_estudiante = ?
        ORDER BY r.fecha_publicacion DESC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_estudiante);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>Recordatorios y Avisos</h2>
    <p>Comunicados importantes y notas relevantes para tu curso.</p>
</div>

<div class="card">
    <?php if (mysqli_num_rows($resultado) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <?php while ($rec = mysqli_fetch_assoc($resultado)): ?>
                <div style="border-bottom: 1px solid var(--border); padding-bottom: 15px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
                        📅 <?php echo date('d/m/Y H:i', strtotime($rec['fecha_publicacion'])); ?>
                    </span>
                    <h4 style="color: var(--primary); margin: 5px 0;"><?php echo htmlspecialchars($rec['titulo']); ?></h4>
                    <p style="color: var(--text-main); font-size: 0.95rem; line-height: 1.5; margin: 0;">
                        <?php echo nl2br(htmlspecialchars($rec['contenido'])); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">No hay recordatorios registrados hasta el momento.</p>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer_panel.php"; ?>