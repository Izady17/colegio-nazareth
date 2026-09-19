<?php
$ruta_base = "../";
require_once "../includes/header.php";
?>

<style>
    /* Estilos globales y reseteo suave */
    body {
        background-color: #f8fafc;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #334155;
    }

    .nosotros-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 60px;
    }

    /* Hero Section */
    .hero-nosotros {
        position: relative;
        background: linear-gradient(90deg, rgba(69, 10, 10, 0.95) 40%, rgba(69, 10, 10, 0.6) 100%), url('../assets/img/frontis.jpg') center/cover no-repeat;
        color: #ffffff;
        padding: 60px 40px;
        border-radius: 0 0 16px 16px;
        margin-bottom: 25px;
    }

    .hero-subtitle {
        font-size: 0.75rem;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #fca5a5;
        font-weight: 700;
        display: block;
        margin-bottom: 8px;
    }

    .hero-nosotros h1 {
        font-size: 2.8rem;
        font-weight: 800;
        margin: 0 0 12px;
    }

    .hero-nosotros p {
        font-size: 1rem;
        max-width: 500px;
        line-height: 1.5;
        opacity: 0.9;
        margin-bottom: 25px;
    }

    .btn-hero {
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
        transition: background 0.3s ease;
    }

    .btn-hero:hover {
        background-color: #9f1239;
    }

    /* Tarjetas Estadísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 50px;
    }

    .stat-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #ffffff;
    }

    .stat-num {
        font-size: 1.4rem;
        font-weight: 800;
        color: #1e293b;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 4px;
    }

    /* Secciones de Encabezado */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 25px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
        font-weight: 700;
        color: #881337;
        margin: 0;
    }

    .section-subtitle {
        font-size: 0.8rem;
        color: #94a3b8;
    }

    /* Línea del tiempo */
    .timeline-container {
        background: #ffffff;
        border-radius: 12px;
        padding: 30px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        margin-bottom: 50px;
    }

    .timeline-desc {
        font-size: 0.88rem;
        color: #475569;
        line-height: 1.6;
        margin-bottom: 35px;
    }

    .timeline-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
        position: relative;
        text-align: center;
    }

    .timeline-grid::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 10%;
        right: 10%;
        height: 2px;
        background: #f1f5f9;
        z-index: 1;
    }

    .timeline-item {
        position: relative;
        z-index: 2;
    }

    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #be123c;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 0.9rem;
    }

    .timeline-year {
        font-weight: 800;
        color: #881337;
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .timeline-text {
        font-size: 0.72rem;
        color: #64748b;
        line-height: 1.3;
    }

    /* Misión y Visión */
    .mv-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 50px;
    }

    .mv-card {
        border-radius: 12px;
        padding: 25px;
        display: flex;
        gap: 20px;
    }

    .mv-card.mision {
        background: #fff1f2;
    }

    .mv-card.vision {
        background: #eff6ff;
    }

    .mv-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .mision .mv-icon { background: #ffe4e6; color: #be123c; }
    .vision .mv-icon { background: #dbeafe; color: #2563eb; }

    .mv-card h3 {
        margin: 0 0 10px;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .mision h3 { color: #be123c; }
    .vision h3 { color: #2563eb; }

    .mv-card p {
        margin: 0;
        font-size: 0.82rem;
        color: #475569;
        line-height: 1.5;
    }

    /* Valores */
    .valores-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 15px;
        margin-bottom: 50px;
    }

    .valor-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px 10px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .valor-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #ffe4e6;
        color: #be123c;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 1rem;
    }

    .valor-card strong {
        display: block;
        font-size: 0.8rem;
        color: #1e293b;
    }

    /* Galería Instalaciones */
    .instalaciones-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 15px;
        margin-bottom: 50px;
    }

    .instalacion-card {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        height: 130px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .instalacion-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .instalacion-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.8) 100%);
        color: #ffffff;
        padding: 8px 10px;
        font-size: 0.72rem;
        font-weight: 600;
    }

    /* Bloque Inferior Doble (Fe y Alegría / Directivo) */
    .bottom-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 50px;
    }

    .feyalegria-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .feyalegria-header {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #be123c;
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 12px;
    }

    .feyalegria-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 15px;
    }

    .feyalegria-content p {
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.4;
        margin: 0;
    }

    .directivo-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .directivos-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 15px;
    }

    .directivo-card {
        text-align: center;
    }

    .directivo-avatar {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 8px;
        background: #e2e8f0;
    }

    .directivo-card strong {
        display: block;
        font-size: 0.78rem;
        color: #1e293b;
    }

    .directivo-card small {
        font-size: 0.68rem;
        color: #94a3b8;
    }

    /* Banner Call to Action */
    .cta-banner {
        background: linear-gradient(90deg, #450a0a 0%, #2a0a0a 100%);
        color: #ffffff;
        border-radius: 12px;
        padding: 30px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .cta-banner h2 {
        font-size: 1.3rem;
        margin: 0 0 5px;
        font-weight: 700;
    }

    .cta-banner p {
        font-size: 0.85rem;
        margin: 0;
        opacity: 0.8;
    }

    .btn-cta {
        background: #be123c;
        color: #ffffff !important;
        padding: 10px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }
</style>

<div class="nosotros-container">

    <!-- Header / Banner Hero -->
    <div class="hero-nosotros">
        <span class="hero-subtitle">U.E. JESÚS DE NAZARETH</span>
        <h1>Nosotros</h1>
        <p>Más de 30 años formando estudiantes con excelencia académica, disciplina y valores en la ciudad de Oruro.</p>
        <a href="#historia" class="btn-hero"><i class="fa-solid fa-compass"></i> Conoce nuestra historia</a>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #be123c;"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <div class="stat-num">850+</div>
                <div class="stat-label">Estudiantes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d97706;"><i class="fa-solid fa-calendar"></i></div>
            <div>
                <div class="stat-num">34</div>
                <div class="stat-label">Años de trayectoria</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #2563eb;"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="stat-num">45+</div>
                <div class="stat-label">Docentes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #059669;"><i class="fa-solid fa-trophy"></i></div>
            <div>
                <div class="stat-num">120+</div>
                <div class="stat-label">Logros alcanzados</div>
            </div>
        </div>
    </div>

    <!-- Nuestra Historia (Línea de Tiempo) -->
    <div class="section-header" id="historia">
        <h2 class="section-title"><i class="fa-solid fa-building-columns"></i> Nuestra Historia</h2>
        <span class="section-subtitle">Una trayectoria de compromiso con la educación orureña</span>
    </div>

    <div class="timeline-container">
        <p class="timeline-desc">
            La <strong>Unidad Educativa Jesús de Nazareth</strong> fue fundada en <strong>1991</strong> bajo la iniciativa de una congregación religiosa, con la noble misión de brindar educación integral y en valores a la niñez y juventud de la zona Sud Este de la ciudad de Oruro. Desde entonces, hemos crecido junto a nuestra comunidad, formando generaciones de estudiantes comprometidos con el desarrollo académico, cultural y social de Bolivia.
        </p>

        <div class="timeline-grid">
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fa-solid fa-house"></i></div>
                <div class="timeline-year">1991</div>
                <div class="timeline-text">Fundación de la Unidad Educativa</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fa-solid fa-users"></i></div>
                <div class="timeline-year">2005</div>
                <div class="timeline-text">Ampliación de infraestructura</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fa-solid fa-book-open"></i></div>
                <div class="timeline-year">2015</div>
                <div class="timeline-text">Incorporación de laboratorios tecnológicos</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fa-solid fa-desktop"></i></div>
                <div class="timeline-year">2020</div>
                <div class="timeline-text">Fortalecimiento de plataformas virtuales</div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="timeline-year">2025</div>
                <div class="timeline-text">850+ estudiantes y 45+ docentes</div>
            </div>
        </div>
    </div>

    <!-- Misión y Visión -->
    <div class="mv-grid">
        <div class="mv-card mision">
            <div class="mv-icon"><i class="fa-solid fa-bullseye"></i></div>
            <div>
                <h3>Misión</h3>
                <p>Proporcionar una educación de excelencia académica en los niveles Primaria y Secundaria, basada en valores sociocomunitarios, disciplina y pensamiento crítico, formando ciudadanas y ciudadanos con alta capacidad técnica y científica al servicio de la sociedad.</p>
            </div>
        </div>
        <div class="mv-card vision">
            <div class="mv-icon"><i class="fa-solid fa-eye"></i></div>
            <div>
                <h3>Visión</h3>
                <p>Ser reconocida como una institución educativa modelo en el departamento de Oruro, destacada por la innovación tecnológica en sus procesos académicos, el liderazgo en proyectos socioformativos y la sólida formación integral de sus estudiantes.</p>
            </div>
        </div>
    </div>

    <!-- Nuestros Valores -->
    <div class="section-header">
        <h2 class="section-title"><i class="fa-solid fa-heart"></i> Nuestros Valores</h2>
        <span class="section-subtitle">Principios que guían nuestra labor educativa</span>
    </div>

    <div class="valores-grid">
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-heart"></i></div>
            <strong>Respeto</strong>
        </div>
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-users"></i></div>
            <strong>Solidaridad</strong>
        </div>
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <strong>Excelencia</strong>
        </div>
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-circle-check"></i></div>
            <strong>Responsabilidad</strong>
        </div>
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-user-group"></i></div>
            <strong>Honestidad</strong>
        </div>
        <div class="valor-card">
            <div class="valor-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
            <strong>Compromiso Social</strong>
        </div>
    </div>

    <!-- Instalaciones -->
    <div class="section-header">
        <h2 class="section-title"><i class="fa-solid fa-images"></i> Conoce nuestras instalaciones</h2>
        <span class="section-subtitle">Espacios que inspiran grandes aprendizajes</span>
    </div>

    <div class="instalaciones-grid">
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Patio principal">
            <div class="instalacion-overlay">Patio principal</div>
        </div>
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Aulas equipadas">
            <div class="instalacion-overlay">Aulas equipadas</div>
        </div>
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Laboratorio de computación">
            <div class="instalacion-overlay">Laboratorio de computación</div>
        </div>
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Biblioteca">
            <div class="instalacion-overlay">Biblioteca</div>
        </div>
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Área deportiva">
            <div class="instalacion-overlay">Área deportiva</div>
        </div>
        <div class="instalacion-card">
            <img src="../assets/img/frontis.jpg" alt="Actos cívicos">
            <div class="instalacion-overlay">Actos cívicos</div>
        </div>
    </div>

    <!-- Red Fe y Alegría & Equipo Directivo -->
    <div class="bottom-grid">
        <div class="feyalegria-box">
            <div class="feyalegria-header">
                <i class="fa-solid fa-people-roof"></i> Somos parte de la Red Fe y Alegría
            </div>
            <div class="feyalegria-content">
                <p>Integrados a una red latinoamericana de educación popular integral, trabajamos por una sociedad más justa, solidaria e inclusiva.</p>
                <!-- Logotipo simulado Fe y Alegría -->
                <div style="text-align: center; color: #be123c; font-weight: 800; font-size: 0.9rem; min-width: 100px;">
                    <i class="fa-solid fa-people-group fa-2x"></i><br>
                    <small style="font-size: 0.65rem; color: #64748b; display: block;">Fe y Alegría<br>BOLIVIA</small>
                </div>
            </div>
            <a href="red-feyalegria.php" class="btn-hero" style="font-size: 0.78rem; padding: 8px 16px; align-self: flex-start;">Conoce más sobre Fe y Alegría &rarr;</a>
        </div>

        <div class="directivo-box">
            <div class="section-title" style="font-size: 1.1rem;">
                <i class="fa-solid fa-user-tie"></i> Equipo Directivo
            </div>
            <span class="section-subtitle">Comprometidos con nuestra comunidad</span>

            <div class="directivos-list">
                <div class="directivo-card">
                    <img src="../assets/img/frontis.jpg" alt="Lic. Juan Pérez" class="directivo-avatar">
                    <strong>Lic. Juan Pérez</strong>
                    <small>Director</small>
                </div>
                <div class="directivo-card">
                    <img src="../assets/img/frontis.jpg" alt="Lic. María López" class="directivo-avatar">
                    <strong>Lic. María López</strong>
                    <small>Subdirectora</small>
                </div>
                <div class="directivo-card">
                    <img src="../assets/img/frontis.jpg" alt="Prof. Carlos Miranda" class="directivo-avatar">
                    <strong>Prof. Carlos Miranda</strong>
                    <small>Coordinador Académico</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Final -->
    <div class="cta-banner">
        <div>
            <h2>Forma parte de nuestra comunidad educativa</h2>
            <p>Juntos seguimos construyendo un mejor futuro para nuestras niñas, niños y jóvenes.</p>
        </div>
        <a href="contacto.php" class="btn-cta"><i class="fa-solid fa-envelope"></i> Contáctanos</a>
    </div>

</div>

<?php require_once "../includes/footer.php"; ?>