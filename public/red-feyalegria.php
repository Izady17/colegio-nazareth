<?php
$ruta_base = "../";
require_once "../includes/header.php";
?>

<style>
    /* Estilos específicos de la sección Red Fe y Alegría */
    .feyalegria-hero {
        position: relative;
        background: linear-gradient(90deg, #450a0a 35%, rgba(69, 10, 10, 0.55) 80%), url('../assets/img/niñosfe.jpg') center/cover no-repeat;
        color: #ffffff;
        padding: 60px 5%;
        min-height: 400px;
        display: flex;
        align-items: center;
    }

    .feyalegria-hero-content {
        max-width: 650px;
    }

    .hero-tag {
        font-size: 0.75rem;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        font-weight: 700;
        color: #fca5a5;
        display: block;
        margin-bottom: 8px;
    }

    .feyalegria-hero h1 {
        font-size: 2.2rem;
        line-height: 1.2;
        margin-bottom: 12px;
        font-weight: 800;
    }

    .feyalegria-hero p {
        font-size: 0.92rem;
        opacity: 0.95;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .btn-red-primary {
        background-color: #be123c;
        color: #ffffff !important;
        padding: 10px 22px;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
    }

    .stats-banner {
        background: #ffffff;
        padding: 25px 20px;
        border-radius: 10px;
        margin: -30px auto 40px;
        max-width: 1100px;
        position: relative;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
        text-align: center;
    }

    .stat-box {
        border-right: 1px solid #f0f0f0;
        padding: 0 10px;
    }

    .stat-box:last-child {
        border-right: none;
    }

    .stat-icon {
        color: #be123c;
        font-size: 1.4rem;
        margin-bottom: 6px;
    }

    .stat-num {
        font-size: 1.3rem;
        font-weight: 800;
        color: #701c1c;
    }

    .stat-desc {
        font-size: 0.72rem;
        color: #6c757d;
        line-height: 1.2;
    }

    .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: center;
        margin-bottom: 50px;
    }

    .about-img-box {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .about-img-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .about-overlay-tag {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(69, 10, 10, 0.95) 100%);
        color: #fff;
        padding: 20px;
        text-align: center;
        font-style: italic;
        font-size: 0.9rem;
    }

    .principios-box {
        background: #ffffff;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 40px;
    }

    .principios-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
        margin-top: 20px;
    }

    .principio-card {
        background: #fdf2f2;
        padding: 15px 10px;
        border-radius: 8px;
        text-align: center;
    }

    .principio-card i {
        color: #be123c;
        font-size: 1.3rem;
        margin-bottom: 8px;
        display: block;
    }

    .principio-card strong {
        display: block;
        font-size: 0.8rem;
        color: #1e293b;
    }

    .presencia-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-top: 15px;
    }

    .presencia-card {
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .presencia-card strong {
        display: block;
        font-size: 0.78rem;
        color: #1e293b;
    }

    .presencia-card small {
        font-size: 0.68rem;
        color: #6c757d;
    }

    .cta-bottom {
        background: linear-gradient(90deg, #450a0a 0%, #2a0a0a 100%);
        color: #ffffff;
        border-radius: 12px;
        padding: 35px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 40px;
    }
</style>

<!-- Banner Principal -->
<section class="feyalegria-hero">
    <div class="feyalegria-hero-content">
        <span class="hero-tag">RED FE Y ALEGRÍA</span>
        <h1>Juntos por una<br>educación transformadora</h1>
        <p>Somos parte de la Red Internacional de Fe y Alegría, que trabaja por una educación popular integral, inclusiva y de calidad, especialmente para los más vulnerables.</p>
        <a href="https://www.feyalegria.org/bolivia/fe-y-alegria-oruro/" class="btn-red-primary"><i class="fa-solid fa-heart"></i> Conoce más sobre Fe y Alegría &rarr;</a>
    </div>
</section>

<!-- Contenido principal -->
<div class="container" style="padding: 0 5%;">
    <!-- Estadísticas -->
    <div class="stats-banner">
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div class="stat-num">+ 1.000.000</div>
            <div class="stat-desc">Estudiantes en América Latina</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-school"></i></div>
            <div class="stat-num">1.000+</div>
            <div class="stat-desc">Centros educativos en 22 países</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-globe"></i></div>
            <div class="stat-num">60 años</div>
            <div class="stat-desc">de experiencia en educación popular</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-num">21 países</div>
            <div class="stat-desc">de presencia internacional</div>
        </div>
        <div class="stat-box">
            <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div class="stat-num">+ 100.000</div>
            <div class="stat-desc">Docentes y colaboradores</div>
        </div>
    </div>

    <!-- Sección ¿Qué es Fe y Alegría? -->
    <div class="about-grid">
        <div>
            <small style="color: #be123c; font-weight: 700; text-transform: uppercase; font-size: 0.75rem;">¿QUÉ ES FE Y ALEGRÍA?</small>
            <h2 style="font-size: 1.6rem; color: #1e293b; margin: 8px 0 15px;">Una red educativa con rostro humano</h2>
            <p style="font-size: 0.88rem; color: #6c757d; line-height: 1.6; margin-bottom: 12px;">
                Fe y Alegría es un movimiento internacional de educación popular integral, que nace en 1955 en Venezuela y hoy está presente en más de 20 países de América Latina, África y Europa.
            </p>
            <p style="font-size: 0.88rem; color: #6c757d; line-height: 1.6; margin-bottom: 20px;">
                Su misión es promover una educación de calidad, inclusiva y transformadora, inspirada en los valores del Evangelio y en la búsqueda de la justicia social.
            </p>
            <a href="https://www.feyalegria.org/bolivia/fe-y-alegria-oruro/" class="btn-red-primary">Más sobre Fe y Alegría &rarr;</a>
        </div>
        <div class="about-img-box">
            <img src="../assets/img/feyalegria.jpg" alt="Estudiantes Fe y Alegría">
            <div class="about-overlay-tag">
                "Una educación que forma, valores que perduran"
            </div>
        </div>
    </div>

    <!-- Principios -->
    <div class="principios-box">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-heart" style="color: #be123c; font-size: 1.2rem;"></i>
            <div>
                <h3 style="font-size: 1.1rem; color: #1e293b; margin:0;">Nuestros Principios</h3>
                <small style="color: #6c757d;">La Red Fe y Alegría se fundamenta en valores que guían nuestra acción educativa y social:</small>
            </div>
        </div>
        <div class="principios-grid">
            <div class="principio-card">
                <i class="fa-solid fa-user"></i>
                <strong>Centralidad en la persona</strong>
            </div>
            <div class="principio-card">
                <i class="fa-solid fa-book-open"></i>
                <strong>Educación integral</strong>
            </div>
            <div class="principio-card">
                <i class="fa-solid fa-people-group"></i>
                <strong>Participación comunitaria</strong>
            </div>
            <div class="principio-card">
                <i class="fa-solid fa-scale-balanced"></i>
                <strong>Justicia social</strong>
            </div>
            <div class="principio-card">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <strong>Solidaridad</strong>
            </div>
        </div>
    </div>

    <!-- Presencia -->
    <div style="margin-bottom: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div>
                <h3 style="font-size: 1.1rem; color: #1e293b; margin:0;"><i class="fa-solid fa-location-dot" style="color: #be123c;"></i> Nuestra presencia</h3>
                <p style="font-size: 0.8rem; color: #6c757d; margin:0;">Fe y Alegría está presente en todo el territorio nacional y en muchos países del mundo.</p>
            </div>
            <a href="#" class="btn-red-primary" style="font-size: 0.75rem; padding: 6px 14px;">Ver mapa completo &rarr;</a>
        </div>

        <div class="presencia-grid">
            <div class="presencia-card">
                <strong>San Ignacio de Loyola</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
            <div class="presencia-card">
                <strong>Virgen del Mar</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
            <div class="presencia-card">
                <strong>La Kantuta</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
            <div class="presencia-card">
                <strong>Josefa Murillo</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
            <div class="presencia-card">
                <strong>Claudina Thevenet</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
            <div class="presencia-card">
                <strong>Corazón de Jesús</strong>
                <small>Red Fe y Alegría - Oruro</small>
            </div>
        </div>
    </div>

    <!-- CTA Final -->
    <div class="cta-bottom">
        <div>
            <h3 style="font-size: 1.3rem; margin-bottom: 6px;"><i class="fa-solid fa-users"></i> Somos parte de la Red Fe y Alegría</h3>
            <p style="font-size: 0.85rem; opacity: 0.9; margin:0;">Trabajamos por una educación popular integral, inclusiva y de calidad, construyendo juntos un mundo más justo y solidario.</p>
        </div>
        <a href="#" class="btn-red-primary" style="white-space: nowrap;"><i class="fa-solid fa-user-plus"></i> Únete a nuestra comunidad &rarr;</a>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>