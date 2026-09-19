<?php
$ruta_base = "../";
require_once "../includes/header.php";
require_once "../includes/conexion.php"; // Asegúrate de que esta sea la ruta a tu conexión PDO o mysqli

// 1. Obtener todas las materias/áreas para el filtro dinámico
$query_materias = "SELECT * FROM materias ORDER BY nombre_materia ASC";
$res_materias = $conexion->query($query_materias);

$materias = [];
if ($res_materias) {
    while ($row = $res_materias->fetch_assoc()) {
        $materias[] = $row;
    }
}

// 2. Obtener los docentes combinando docentes_perfil, usuarios (nombres, apellidos) y materias
$query_docentes = "SELECT 
                    dp.id_docente,
                    dp.titulo_academico,
                    dp.foto,
                    u.nombres,
                    u.apellidos,
                    m.id_materia,
                    m.nombre_materia
                   FROM docentes_perfil dp
                   INNER JOIN usuarios u ON dp.id_usuario = u.id_usuario
                   LEFT JOIN materias m ON dp.id_area = m.id_materia
                   ORDER BY u.apellidos ASC";
$res_docentes = $conexion->query($query_docentes);

$docentes = [];
if ($res_docentes) {
    while ($row = $res_docentes->fetch_assoc()) {
        $docentes[] = $row;
    }
}

// Contadores para las tarjetas de estadísticas
$total_docentes = count($docentes);
$total_areas = count($materias);
?>

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #334155;
    }

    .docentes-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 60px;
    }

    /* Hero Section Plantel Docente */
    .hero-docentes {
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

    .hero-docentes h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 0 0 12px;
    }

    .hero-docentes p {
        font-size: 0.95rem;
        max-width: 550px;
        line-height: 1.5;
        opacity: 0.9;
        margin-bottom: 20px;
    }

    /* Tarjetas de Estadísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 35px;
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

    /* Filtro por Áreas / Materias */
    .filter-section {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 35px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .filter-label {
        font-weight: 700;
        font-size: 0.9rem;
        color: #881337;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-select {
        flex: 1;
        min-width: 250px;
        padding: 10px 15px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.9rem;
        outline: none;
        color: #334155;
        background-color: #f8fafc;
        transition: border-color 0.2s ease;
    }

    .filter-select:focus {
        border-color: #be123c;
    }

    /* Grilla de Docentes */
    .section-title-box {
        margin-bottom: 25px;
    }

    .section-title-box h2 {
        font-size: 1.4rem;
        font-weight: 800;
        color: #881337;
        margin: 0 0 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title-box p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0;
    }

    .docentes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .docente-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px 15px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-top: 4px solid #be123c;
    }

    .docente-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }

    .docente-img {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 12px;
        border: 3px solid #ffe4e6;
        background-color: #f1f5f9;
    }

    .docente-nombre {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px;
        line-height: 1.3;
    }

    .docente-titulo {
        font-size: 0.75rem;
        color: #be123c;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .docente-materia-badge {
        display: inline-block;
        background-color: #f1f5f9;
        color: #475569;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 600;
    }

    .no-results {
        display: none;
        grid-column: 1 / -1;
        text-align: center;
        padding: 40px;
        background: #ffffff;
        border-radius: 12px;
        color: #64748b;
    }

    /* Banner CTA Inferior */
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

<div class="docentes-container">

    <!-- Hero Plantel Docente -->
    <div class="hero-docentes">
        <span class="hero-subtitle">PLANTEL DOCENTE</span>
        <h1>Profesionales comprometidos con la formación integral</h1>
        <p>Nuestro equipo docente está conformado por profesionales con vocación, experiencia y un profundo compromiso con la educación y los valores que nos identifican.</p>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #be123c;"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="stat-num"><?php echo $total_docentes; ?></div>
                <div class="stat-label">Docentes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d97706;"><i class="fa-solid fa-book-bookmark"></i></div>
            <div>
                <div class="stat-num"><?php echo $total_areas; ?></div>
                <div class="stat-label">Áreas académicas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #2563eb;"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="stat-num">34</div>
                <div class="stat-label">Años de experiencia</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #059669;"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <div class="stat-num">850+</div>
                <div class="stat-label">Estudiantes atendidos</div>
            </div>
        </div>
    </div>

    <!-- Filtro de Asignaturas / Áreas -->
    <div class="filter-section">
        <div class="filter-label">
            <i class="fa-solid fa-filter"></i> Filtrar por área:
        </div>
        <select id="materiaFilter" class="filter-select" onchange="filtrarDocentes()">
            <option value="todas">-- Todas las áreas --</option>
            <?php foreach ($materias as $mat): ?>
                <option value="<?php echo $mat['id_materia']; ?>">
                    <?php echo htmlspecialchars($mat['nombre_materia']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Título Sección General Docentes -->
    <div class="section-title-box">
        <h2><i class="fa-solid fa-users"></i> Docentes</h2>
        <p>Acompañan el desarrollo académico, construyendo las bases para un mejor futuro.</p>
    </div>

   <!-- Listado Dinámico de Docentes -->
<div class="docentes-grid" id="docentesGrid">
    <?php if (!empty($docentes)): ?>
        <?php foreach ($docentes as $doc): ?>
            <?php 
                $nombre_completo = trim(($doc['nombres'] ?? '') . ' ' . ($doc['apellidos'] ?? ''));
                $titulo = !empty($doc['titulo_academico']) ? $doc['titulo_academico'] : 'Docente';
                $materia_nombre = !empty($doc['nombre_materia']) ? $doc['nombre_materia'] : 'Área General';
                $materia_id = $doc['id_materia'] ?? '0';

                // Determinar ruta de foto
                $nombre_foto = !empty($doc['foto']) ? $doc['foto'] : '';
                $ruta_foto = "assets/img/" . $nombre_foto;

                // Generar un SVG de avatar con fondo si falla la foto
                $iniciales = mb_strtoupper(mb_substr($doc['nombres'] ?? 'D', 0, 1) . mb_substr($doc['apellidos'] ?? 'D', 0, 1));
                $fallback_svg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='90' height='90' viewBox='0 0 90 90'><rect width='90' height='90' fill='%23ffe4e6'/><text x='50%' y='55%' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='32' font-weight='bold' fill='%23be123c'>{$iniciales}</text></svg>";
            ?>
            
            <div class="docente-card" data-materia="<?php echo $materia_id; ?>">
                <img src="<?php echo !empty($nombre_foto) ? htmlspecialchars($ruta_foto) : $fallback_svg; ?>" 
                     alt="<?php echo htmlspecialchars($nombre_completo); ?>" 
                     class="docente-img"
                     onerror="this.onerror=null; this.src='<?php echo $fallback_svg; ?>';">
                
                <h3 class="docente-nombre"><?php echo htmlspecialchars($nombre_completo); ?></h3>
                <div class="docente-titulo"><?php echo htmlspecialchars($titulo); ?></div>
                <span class="docente-materia-badge"><?php echo htmlspecialchars($materia_nombre); ?></span>
            </div>

        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-results" style="display: block;">
            <p>No se encontraron docentes registrados en la base de datos.</p>
        </div>
    <?php endif; ?>

    <div id="noResults" class="no-results">
        <i class="fa-solid fa-magnifying-glass fa-2x" style="margin-bottom: 10px; color: #cbd5e1;"></i>
        <p>No hay docentes asignados al área seleccionada.</p>
    </div>
</div>

    <!-- Call to Action Inferior -->
    <div class="cta-banner">
        <div>
            <h2>Nuestro equipo docente</h2>
            <p>Profesionales que inspiran, acompañan y construyen juntos un mejor futuro.</p>
        </div>
        <a href="actividades.php" class="btn-cta"><i class="fa-solid fa-calendar-days"></i> Ver actividades y logros &rarr;</a>
    </div>

</div>

<script>
    function filtrarDocentes() {
        const selectedMateria = document.getElementById('materiaFilter').value;
        const cards = document.querySelectorAll('.docente-card');
        const noResults = document.getElementById('noResults');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardMateria = card.getAttribute('data-materia');

            if (selectedMateria === 'todas' || cardMateria === selectedMateria) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCount === 0 && cards.length > 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }
</script>

<?php require_once "../includes/footer.php"; ?>