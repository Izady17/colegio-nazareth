<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($ruta_base)) {
    $ruta_base = "./";
}

$rol_actual = $_SESSION['rol'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Nombre e iniciales para el avatar
$nombre_mostrar = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Usuario';
$inicial = mb_strtoupper(mb_substr(trim($nombre_mostrar), 0, 1));

// Menú lateral usando clases de FontAwesome en lugar de Emojis
$menu = [];
if ($rol_actual === 'administrador') {
    $menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'admin/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horarios Académicos', 'href' => $ruta_base . 'admin/horarios.php', 'match' => ['horarios.php']],
        ['icon' => 'fa-solid fa-clipboard-user', 'label' => 'Control de Asistencia', 'href' => $ruta_base . 'admin/asistencia.php', 'match' => ['asistencia.php']],
        ['icon' => 'fa-solid fa-users-gear', 'label' => 'Gestión de Usuarios', 'href' => $ruta_base . 'admin/usuarios.php', 'match' => ['usuarios.php']],
        ['icon' => 'fa-solid fa-bullhorn', 'label' => 'Avisos Institucionales', 'href' => $ruta_base . 'admin/avisos.php', 'match' => ['avisos.php']],
        ['icon' => 'fa-solid fa-key', 'label' => 'PINs de Padres', 'href' => $ruta_base . 'admin/pines_padres.php', 'match' => ['pines_padres.php']],
    ];
} elseif ($rol_actual === 'docente') {
    $menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'tutor/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horario de Clases', 'href' => $ruta_base . 'tutor/horarios.php', 'match' => ['horarios.php']],
        ['icon' => 'fa-solid fa-pen-to-square', 'label' => 'Gestión de Actividades', 'href' => $ruta_base . 'tutor/actividades.php', 'match' => ['actividades.php']],
        ['icon' => 'fa-solid fa-sliders', 'label' => 'Ponderaciones', 'href' => $ruta_base . 'tutor/configurar_dimensiones.php', 'match' => ['configurar_dimensiones.php']],
        ['icon' => 'fa-solid fa-clipboard-check', 'label' => 'Asistencia', 'href' => $ruta_base . 'tutor/asistencia.php', 'match' => ['asistencia.php', 'consulta_asistencia.php']],
        ['icon' => 'fa-solid fa-paper-plane', 'label' => 'Enviar Recordatorio', 'href' => $ruta_base . 'tutor/enviar-recordatorio.php', 'match' => ['enviar-recordatorio.php']],
    ];
} elseif ($rol_actual === 'estudiante') {
    $menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'estudiante/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horario de Clases', 'href' => $ruta_base . 'estudiante/horario.php', 'match' => ['horario.php']],
        ['icon' => 'fa-solid fa-book-bookmark', 'label' => 'Tareas y Actividades', 'href' => $ruta_base . 'estudiante/tareas.php', 'match' => ['tareas.php']],
        ['icon' => 'fa-solid fa-user-check', 'label' => 'Mi Asistencia', 'href' => $ruta_base . 'estudiante/asistencia.php', 'match' => ['asistencia.php']],
        ['icon' => 'fa-solid fa-bell', 'label' => 'Recordatorios', 'href' => $ruta_base . 'estudiante/recordatorios.php', 'match' => ['recordatorios.php']],
        ['icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Plantel Docente', 'href' => $ruta_base . 'public/docentes.php', 'match' => []],
    ];
} elseif ($rol_actual === 'padre') {
    $menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'padres/dashboard.php', 'match' => ['dashboard.php']],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>U.E. Jesús de Nazareth - Panel</title>
    
    <!-- Hojas de estilo y FontAwesome para iconos profesionales -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $ruta_base; ?>assets/css/styles.css">

    <style>
        /* Ajustes de estilo inline para complementar styles.css */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand img {
            height: 42px;
            width: auto;
            object-fit: contain;
        }
        .sidebar-brand .brand-text strong {
            display: block;
            font-size: 0.95rem;
            color: #ffffff;
            line-height: 1.2;
        }
        .sidebar-brand .brand-text small {
            font-size: 0.62rem;
            color: #fca5a5;
            letter-spacing: 0.8px;
        }
        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        .sidebar-nav li a .icon {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            opacity: 0.85;
        }
        .sidebar-nav li a:hover, .sidebar-nav li a.active {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-weight: 600;
            border-left: 4px solid #f87171;
        }
        .btn-toggle-mobile {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #334155;
            cursor: pointer;
            padding: 5px 10px;
        }
        @media (max-width: 768px) {
            .btn-toggle-mobile { display: block; }
        }
    </style>
</head>
<body style="display:block;">

<div class="app-shell">
    <!-- MENÚ LATERAL (SIDEBAR) -->
    <aside class="sidebar" id="sidebarNav">
        <div class="sidebar-brand">
            <img src="<?php echo $ruta_base; ?>assets/img/escudo.png" alt="Escudo U.E. Jesús de Nazareth" onerror="this.src='<?php echo $ruta_base; ?>assets/img/image.png'">
            <div class="brand-text">
                <strong>U.E. Jesús de Nazareth</strong>
                <small>DISCIPLINA · FE · CONOCIMIENTO</small>
            </div>
        </div>

        <ul class="sidebar-nav">
            <?php foreach ($menu as $item): ?>
                <?php $activo = in_array($pagina_actual, $item['match']); ?>
                <li>
                    <a href="<?php echo $item['href']; ?>" class="<?php echo $activo ? 'active' : ''; ?>">
                        <span class="icon"><i class="<?php echo $item['icon']; ?>"></i></span>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <!-- Botón Salir -->
            <li style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 5px;">
                <a href="<?php echo $ruta_base; ?>auth/logout.php" style="color: #fca5a5;">
                    <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-quote">
            "Camino, Verdad y Vida"<br>— U.E. Jesús de Nazareth
        </div>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <div class="main-area">
        <!-- BARRA SUPERIOR (TOPBAR) -->
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 10px;">
                <button class="btn-toggle-mobile" onclick="toggleSidebar()" title="Menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-search">
                    <input type="text" placeholder="Buscar en el portal..." disabled title="Próximamente">
                </div>
            </div>

            <div class="topbar-right">
                <div class="topbar-bell" style="position: relative; cursor: pointer;" title="Notificaciones">
                    <i class="fa-regular fa-bell" style="font-size: 1.1rem; color: #475569;"></i>
                </div>
                
                <a href="<?php echo $ruta_base; ?>index.php" style="text-decoration:none; color:inherit;" title="Ir al Sitio Público">
                    <div class="topbar-user">
                        <div class="avatar" style="background-color: #800020; color: #ffffff; font-weight: 700;">
                            <?php echo htmlspecialchars($inicial); ?>
                        </div>
                        <div class="user-info">
                            <strong><?php echo htmlspecialchars($nombre_mostrar); ?></strong>
                            <small><?php echo htmlspecialchars(ucfirst($rol_actual)); ?></small>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- CONTENIDO DINÁMICO DEL PANEL -->
        <div class="panel-content">

<script>
function toggleSidebar() {
    const sidebar = id = document.getElementById('sidebarNav');
    sidebar.classList.toggle('active');
}
</script>
