<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($ruta_base)) {
    $ruta_base = "./";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>U.E. Jesús de Nazareth</title>
    
    <!-- Librería de Iconos FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Hoja de estilos principal -->
    <link rel="stylesheet" href="<?php echo $ruta_base; ?>assets/css/styles.css">

    <style>
        :root {
            --primary-dark: #450a0a;
            --primary: #701c1c;
            --primary-light: #b91c1c;
        }

        .top-bar {
            background-color: var(--primary-dark) !important;
            color: #ffffff !important;
            padding: 6px 5% !important;
            font-size: 0.78rem !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        .main-nav {
            background-color: var(--primary) !important;
            padding: 12px 5% !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15) !important;
        }

        .logo-container {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            color: #ffffff !important;
            text-decoration: none !important;
        }

        .nav-links {
            display: flex !important;
            gap: 15px !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
            align-items: center !important;
        }

        .nav-links a {
            color: #ffffff !important;
            text-decoration: none !important;
            font-size: 0.85rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            opacity: 0.9 !important;
            padding: 5px 8px !important;
            border-radius: 4px !important;
            transition: all 0.2s ease !important;
        }

        .nav-links a:hover, .nav-links a.active {
            opacity: 1 !important;
            background: rgba(255, 255, 255, 0.15) !important;
            font-weight: 600 !important;
        }

        .nav-buttons {
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
        }

        .btn-nav {
            padding: 6px 12px !important;
            border-radius: 4px !important;
            text-decoration: none !important;
            font-size: 0.8rem !important;
            font-weight: bold !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 5px !important;
        }

        .btn-panel {
            background: rgba(255,255,255,0.15) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255,255,255,0.3) !important;
        }

        .btn-panel:hover {
            background: rgba(255,255,255,0.25) !important;
        }

        .btn-logout {
            background: #f59e0b !important;
            color: #000000 !important;
        }

        .btn-logout:hover {
            background: #d97706 !important;
        }
    </style>
</head>
<body>

<!-- Bar superior -->
<div class="top-bar">
    <span><i class="fa-solid fa-location-dot"></i> Oruro, Bolivia | U.E. Jesús de Nazareth</span>
    <span>
        <?php 
        if (isset($_SESSION['nombre_completo'])) {
            echo "Bienvenido(a), <strong>" . htmlspecialchars($_SESSION['nombre_completo']) . "</strong>";
        } elseif (isset($_SESSION['nombre'])) {
            echo "Bienvenido(a), <strong>" . htmlspecialchars($_SESSION['nombre']) . "</strong>";
        } else {
            echo "<i>Camino, Verdad y Vida</i>";
        }
        ?>
    </span>
</div>

<!-- Navegación principal -->
<nav class="main-nav">
    <!-- LOGO Y NOMBRE DE LA INSTITUCIÓN -->
<a href="<?php echo $ruta_base; ?>index.php" class="logo-container" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
    
    <!-- Escudo oficial (reemplaza al icono del edificio) -->
    <img src="<?php echo $ruta_base; ?>assets/img/image.png" 
         alt="Escudo U.E. Jesús de Nazareth" 
         style="height: 45px; width: auto; object-fit: contain;"
         onerror="this.style.display='none'">

    <div class="logo-text">
        <span style="font-weight: 800; font-size: 1.2rem; color: #ffffff; display: block; line-height: 1.1;">
            U.E. Jesús de Nazareth
        </span>
        <small style="font-size: 0.72rem; color: #fca5a5; letter-spacing: 1px; font-weight: 600;">
            ORURO - BOLIVIA
        </small>
    </div>
</a>

    <ul class="nav-links">
        <li><a href="<?php echo $ruta_base; ?>index.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/nosotros.php"><i class="fa-solid fa-users"></i> Nosotros</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/red-feyalegria.php"><i class="fa-solid fa-heart"></i> Red Fe y Alegría</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/docentes.php"><i class="fa-solid fa-chalkboard-user"></i> Plantel Docente</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/logros.php"><i class="fa-solid fa-trophy"></i> Logros</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/actividades.php"><i class="fa-solid fa-calendar-days"></i> Actividades</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/gabinete-psicologico.php"><i class="fa-solid fa-brain"></i> Gabinete</a></li>
        <li><a href="<?php echo $ruta_base; ?>public/contacto.php"><i class="fa-solid fa-envelope"></i> Contacto</a></li>
    </ul>

    <div class="nav-buttons">
        <?php if (isset($_SESSION['rol'])): ?>
            <?php if ($_SESSION['rol'] === 'administrador'): ?>
                <a href="<?php echo $ruta_base; ?>admin/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-user-shield"></i> Panel Admin</a>
            <?php elseif ($_SESSION['rol'] === 'docente'): ?>
                <a href="<?php echo $ruta_base; ?>tutor/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-chalkboard-user"></i> Panel Docente</a>
            <?php elseif ($_SESSION['rol'] === 'estudiante'): ?>
                <a href="<?php echo $ruta_base; ?>estudiante/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-user-graduate"></i> Panel Estudiante</a>
            <?php elseif ($_SESSION['rol'] === 'padre'): ?>
                <a href="<?php echo $ruta_base; ?>padres/dashboard.php" class="btn-nav btn-panel"><i class="fa-solid fa-users"></i> Panel Familia</a>
            <?php endif; ?>
            <a href="<?php echo $ruta_base; ?>auth/logout.php" class="btn-nav btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
        <?php else: ?>
            <a href="<?php echo $ruta_base; ?>auth/login.php" class="btn-nav btn-panel"><i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión</a>
        <?php endif; ?>
    </div>
</nav>