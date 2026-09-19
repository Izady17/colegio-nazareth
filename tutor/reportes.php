<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/header_panel.php";
?>

<div class="card hero-section" style="margin-bottom: 25px;">
    <h2>📊 Reportes y Alertas de Riesgo</h2>
    <p>Consulta las proyecciones académicas y la lista de estudiantes en riesgo de reprobación.</p>
</div>

<div class="card" style="text-align: center; padding: 40px;">
    <p style="color: #64748b; font-size: 1.1rem;">Los reportes consolidados están integrados dentro del **Centralizador Trimestral** en Gestión de Actividades.</p>
    <a href="actividades.php" class="btn-submit" style="display: inline-block; width: auto; margin-top: 15px; text-decoration: none; padding: 10px 20px;">
        Ir a Gestión de Actividades
    </a>
</div>

<?php require_once "../includes/footer_panel.php"; ?>