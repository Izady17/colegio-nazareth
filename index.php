<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    require_once "includes/conexion.php";

$noticias_home = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT titulo, fecha_publicacion, imagen FROM noticias WHERE estado = 1 ORDER BY fecha_publicacion DESC LIMIT 3"
), MYSQLI_ASSOC);

$eventos_home = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT titulo, lugar_hora, fecha_evento FROM eventos WHERE estado = 1 AND fecha_evento >= CURDATE() ORDER BY fecha_evento ASC LIMIT 3"
), MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>U.E. Jesús de Nazareth - Oruro</title>
    <!-- Iconos FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #701c1c;
            --primary-dark: #4a1212;
            --secondary: #d97706;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); }

        /* Topbar & Navbar */
        .top-bar { background: var(--primary-dark); color: #fff; padding: 4px 5%; font-size: 0.78rem; display: flex; justify-content: space-between; }
        .main-nav { background: var(--primary); padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .logo-container { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; }
        .nav-links { display: flex; gap: 15px; list-style: none; margin: 0; padding: 0; }
        .nav-links a { color: #fff; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; opacity: 0.9; }
        .nav-links a.active, .nav-links a:hover { opacity: 1; font-weight: bold; }
        .nav-buttons { display: flex; gap: 8px; }
        .btn-nav { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: bold; }
        .btn-panel { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
        .btn-logout { background: #f59e0b; color: #000; }

        /* Hero Banner */
        .hero { 
            position: relative; 
            background-color: var(--primary-dark);
            background-image: linear-gradient(90deg, var(--primary-dark) 35%, rgba(74, 18, 18, 0.45) 80%), url('assets/img/frontis.jpg'); 
            background-repeat: no-repeat;
            background-size: auto 200%;
            background-position: right 70%;
            color: #fff; 
            padding: 60px 5%; 
            min-height: 420px; 
            display: flex; 
            align-items: center;
            overflow: hidden; 
        }
        .hero-watermark {
            position: absolute;
            left: 350px;
            top: 50%;
            transform: translateY(-50%);
            height: 90%;
            opacity: 0.18;
            pointer-events: none;
            z-index: 1;
        }
        .hero-btns { 
            display: flex; 
            gap: 12px; 
            align-items: center;
            margin-top: 15px;
        }

        .btn-hero-primary { 
            background-color: #be123c !important; 
            color: #ffffff !important; 
            padding: 10px 20px !important; 
            border-radius: 20px !important; 
            text-decoration: none !important; 
            font-weight: 600; 
            font-size: 0.85rem; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
        }

        .btn-hero-secondary { 
            background-color: rgba(0, 0, 0, 0.6) !important; 
            color: #ffffff !important; 
            padding: 10px 20px !important; 
            border-radius: 20px !important; 
            text-decoration: none !important; 
            font-size: 0.85rem; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Main Container */
        .container { max-width: 1200px; margin: -30px auto 40px; padding: 0 15px; position: relative; z-index: 10; }

        /* Stats & Accesos Rápido */
        .top-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 25px; }
        .stats-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .stat-card { background: #fff; padding: 15px 10px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-icon { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; color: #fff; }
        .stat-number { font-size: 1.2rem; font-weight: bold; color: var(--primary); }
        .stat-label { font-size: 0.7rem; color: var(--text-muted); }

        .accesos-container { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .accesos-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 10px; }
        .acceso-card { padding: 12px 8px; border-radius: 6px; color: #fff; text-decoration: none; text-align: center; font-size: 0.75rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 6px; }

        /* Content Grid */
        .main-content-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .card-box { background: #fff; border-radius: 8px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-box h3 { font-size: 1rem; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }

        /* Listas / Eventos */
        .news-item { display: flex; gap: 10px; margin-bottom: 12px; align-items: center; }
        .news-img { width: 50px; height: 50px; border-radius: 6px; object-fit: cover; background: #ccc; }
        .event-item { display: flex; gap: 12px; margin-bottom: 10px; align-items: center; }
        .event-badge { background: var(--primary); color: #fff; padding: 6px 10px; border-radius: 6px; text-align: center; font-size: 0.7rem; font-weight: bold; line-height: 1.1; }

        /* Footer */
        footer { background: var(--primary-dark); color: #fff; padding: 30px 5% 15px; margin-top: 40px; font-size: 0.8rem; }
        .footer-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    </style>
</head>
<body>

    <!-- Bar superior -->
    <div class="top-bar">
        <span><i class="fa-solid fa-location-dot"></i> Oruro, Bolivia | U.E. Jesús de Nazareth</span>
        <span><i>Camino, Verdad y Vida</i></span>
    </div>

    <!-- Navegación principal -->
    <nav class="main-nav">
    <a href="index.php" class="logo-container">
        <!-- Reemplazo del icono por el escudo oficial -->
        <img src="assets/img/image.png" 
             alt="Escudo U.E. Jesús de Nazareth" 
             style="height: 42px; width: auto; object-fit: contain;" 
             onerror="this.style.display='none'">
        <div>
            <strong style="font-size: 1.1rem; display: block;">U.E. Jesús de Nazareth</strong>
            <small style="font-size: 0.65rem; letter-spacing: 1px;">ORURO - BOLIVIA</small>
        </div>
    </a>
        <ul class="nav-links">
            <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i> Inicio</a></li>
            <li><a href="public/nosotros.php"><i class="fa-solid fa-users"></i> Nosotros</a></li>
            <li><a href="public/red-feyalegria.php"><i class="fa-solid fa-heart"></i> Red Fe y Alegría</a></li>
            <li><a href="public/docentes.php"><i class="fa-solid fa-chalkboard-user"></i> Plantel Docente</a></li>
            <li><a href="public/logros.php"><i class="fa-solid fa-trophy"></i> Logros</a></li>
            <li><a href="public/actividades.php"><i class="fa-solid fa-calendar-days"></i> Actividades</a></li>
            <li><a href="public/gabinete-psicologico.php"><i class="fa-solid fa-brain"></i> Gabinete</a></li>
            <li><a href="public/contacto.php"><i class="fa-solid fa-envelope"></i> Contacto</a></li>
        </ul>
        <div class="nav-buttons">
            <?php if (isset($_SESSION['rol'])): ?>
                <?php if ($_SESSION['rol'] === 'administrador'): ?>
                    <a href="admin/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-user"></i> Panel Admin</a>
                <?php elseif ($_SESSION['rol'] === 'docente'): ?>
                    <a href="tutor/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-user"></i> Panel Docente</a>
                <?php elseif ($_SESSION['rol'] === 'estudiante'): ?>
                    <a href="estudiante/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-user"></i> Panel Estudiante</a>
                <?php endif; ?>
                <a href="auth/logout.php" class="btn-nav btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-nav btn-panel"><i class="fa-solid fa-user"></i> Iniciar Sesión</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Banner Principal -->
    <section class="hero">
        <img src="assets/img/image.png" class="hero-watermark" alt="Logo de fondo">

        <div class="hero-content">
            <small style="letter-spacing: 1px;">U.E. JESÚS DE NAZARETH</small>
            <h1>Bienvenido a la<br>U.E. Jesús de Nazareth</h1>
            <p>Formando estudiantes con excelencia académica, disciplina y valores en la ciudad de Oruro.</p>
            <div class="hero-btns">
                <a href="#" class="btn-hero-primary">Inscripciones 2026 &rarr;</a>
                <a href="public/nosotros.php" class="btn-hero-secondary"><i class="fa-solid fa-circle-play"></i> Conoce nuestra institución</a>
            </div>
        </div>
    </section>

    <!-- Contenido Principal -->
    <div class="container">
        
        <!-- Métricas + Accesos rápidos -->
        <div class="top-grid">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e11d48;"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="stat-number">850+</div>
                    <div class="stat-label">Estudiantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #d97706;"><i class="fa-solid fa-calendar"></i></div>
                    <div class="stat-number">34</div>
                    <div class="stat-label">Años de trayectoria</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #2563eb;"><i class="fa-solid fa-chalkboard-teacher"></i></div>
                    <div class="stat-number">45+</div>
                    <div class="stat-label">Docentes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #16a34a;"><i class="fa-solid fa-award"></i></div>
                    <div class="stat-number">120+</div>
                    <div class="stat-label">Logros</div>
                </div>
            </div>

            <div class="accesos-container">
                <strong style="font-size: 0.85rem; color: var(--text-muted);">Accesos rápidos</strong>
                <div class="accesos-grid">
                    <a href="public/nosotros.php" class="acceso-card" style="background: #881337;"><i class="fa-solid fa-building"></i> Nuestra Institución</a>
                    <a href="public/actividades.php" class="acceso-card" style="background: #1d4ed8;"><i class="fa-solid fa-calendar-check"></i> Actividades</a>
                    <a href="public/docentes.php" class="acceso-card" style="background: #15803d;"><i class="fa-solid fa-users-gear"></i> Plantel Docente</a>
                    <a href="public/gabinete-psicologico.php" class="acceso-card" style="background: #c2410c;"><i class="fa-solid fa-head-side-virus"></i> Gabinete Psicológico</a>
                    <a href="auth/login.php" class="acceso-card" style="background: #6b21a8;"><i class="fa-solid fa-laptop-code"></i> Campus Virtual</a>
                </div>
            </div>
        </div>

        <!-- Secciones informativas -->
        <div class="main-content-grid">
            <!-- Nuestra Institución -->
            <div class="card-box">
                <h3><i class="fa-solid fa-landmark"></i> Nuestra Institución</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;">Fundada en 1991 en la zona Sudeste de Oruro, brindamos educación integral en Inicial, Primaria y Secundaria.</p>
                <ul style="font-size: 0.8rem; list-style: none; line-height: 1.8;">
                    <li>📌 Zona Sudeste - Oruro</li>
                    <li>🎓 Niveles: Primaria y Secundaria</li>
                </ul>
            </div>

            <!-- Últimas Noticias -->
            <div class="card-box">
    <h3><i class="fa-solid fa-bullhorn"></i> Últimas Noticias</h3>
    <?php if (!empty($noticias_home)): ?>
        <?php foreach ($noticias_home as $n): ?>
            <div class="news-item">
                <div class="news-img" <?php echo !empty($n['imagen']) ? 'style="background-image:url(\'' . htmlspecialchars($n['imagen']) . '\');background-size:cover;"' : ''; ?>></div>
                <div>
                    <strong style="font-size: 0.8rem; display: block;"><?php echo htmlspecialchars($n['titulo']); ?></strong>
                    <small style="color: var(--text-muted);"><?php echo date("d \d\e F \d\e Y", strtotime($n['fecha_publicacion'])); ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="font-size: 0.85rem; color: var(--text-muted);">No hay noticias publicadas por el momento.</p>
    <?php endif; ?>
</div>

            <!-- Próximos Eventos -->
            <div class="card-box">
    <h3><i class="fa-solid fa-calendar-days"></i> Próximos Eventos</h3>
    <?php if (!empty($eventos_home)): ?>
        <?php foreach ($eventos_home as $ev): ?>
            <div class="event-item">
                <div class="event-badge"><?php echo date('d', strtotime($ev['fecha_evento'])); ?><br><small><?php echo strtoupper(date('M', strtotime($ev['fecha_evento']))); ?></small></div>
                <div>
                    <strong style="font-size: 0.8rem; display: block;"><?php echo htmlspecialchars($ev['titulo']); ?></strong>
                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($ev['lugar_hora'] ?? ''); ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="font-size: 0.85rem; color: var(--text-muted);">No hay eventos próximos registrados.</p>
    <?php endif; ?>
</div>
        </div>

    </div>

    <!-- Pie de página -->
    <footer>
        <div class="footer-grid">
            <div>
                <strong>U.E. Jesús de Nazareth</strong>
                <p style="margin-top: 8px; opacity: 0.8;">Oruro - Bolivia</p>
            </div>
            <div>
                <p><i class="fa-solid fa-location-dot"></i> Av. 6 de Agosto s/n - Zona Sudeste</p>
                <p><i class="fa-solid fa-phone"></i> +591 2 525 6789</p>
            </div>
            <div>
                <p><i class="fa-solid fa-heart"></i> Fe y Alegría Bolivia</p>
            </div>
            <div>
                <p>&copy; 2026 U.E. Jesús de Nazareth. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

</body>
</html>