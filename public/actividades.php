<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";
require_once "../includes/header.php";
?>

<div class="card">
    <h2 style="color: var(--primary-color); margin-bottom: 15px;">Actividades e Iniciativas Institucionales</h2>
    <p style="margin-bottom: 25px;">Resumen de los eventos, logros estudiantiles y actividades extracurriculares desarrolladas en Oruro.</p>

    <div style="display: flex; flex-direction: column; gap: 20px;">
        
        <article style="border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; background-color: #fff;">
            <span style="font-size: 0.85rem; color: #666; font-weight: bold;">📅 06 de Agosto</span>
            <h3 style="color: var(--primary-color); margin: 8px 0;">Desfile Cívico Institucional</h3>
            <p>Participación destacada de la banda de música y delegaciones de los niveles Primaria y Secundaria en el desfile escolar patrio en honor a las Fiestas Patrias en Oruro.</p>
        </article>

        <article style="border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; background-color: #fff;">
            <span style="font-size: 0.85rem; color: #666; font-weight: bold;">🏆 15 de Mayo</span>
            <h3 style="color: var(--primary-color); margin: 8px 0;">Feria de Ciencia y Tecnología</h3>
            <p>Estudiantes presentaron proyectos tecnológicos e innovaciones aplicadas a la resolución de problemáticas locales en las áreas de robótica, informática y medio ambiente.</p>
        </article>

        <article style="border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; background-color: #fff;">
            <span style="font-size: 0.85rem; color: #666; font-weight: bold;">🎨 21 de Septiembre</span>
            <h3 style="color: var(--primary-color); margin: 8px 0;">Jornada Cultural y Deportiva</h3>
            <p>Celebración del Día del Estudiante y la Primavera con actividades de confraternización, disciplinas deportivas e interpretación artística.</p>
        </article>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>