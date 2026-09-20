<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($ruta_base)) {$ruta_base = "./";
}

$rol_actual =$_SESSION['rol'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Nombre e iniciales para el avatar
$nombre_mostrar =$_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? $_SESSION['nombres'] ?? 'Usuario';
$inicial = mb_strtoupper(mb_substr(trim($nombre_mostrar), 0, 1));

// Menú lateral usando clases de FontAwesome
$menu = [];
if ($rol_actual === 'administrador') {$menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'admin/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horarios Académicos', 'href' => $ruta_base . 'admin/horarios.php', 'match' => ['horarios.php']],
        ['icon' => 'fa-solid fa-clipboard-user', 'label' => 'Control de Asistencia', 'href' => $ruta_base . 'admin/asistencia.php', 'match' => ['asistencia.php']],
        ['icon' => 'fa-solid fa-users-gear', 'label' => 'Gestión de Usuarios', 'href' => $ruta_base . 'admin/usuarios.php', 'match' => ['usuarios.php']],
        ['icon' => 'fa-solid fa-bullhorn', 'label' => 'Avisos Institucionales', 'href' => $ruta_base . 'admin/avisos.php', 'match' => ['avisos.php']],
        ['icon' => 'fa-solid fa-key', 'label' => 'PINs de Padres', 'href' => $ruta_base . 'admin/pines_padres.php', 'match' => ['pines_padres.php']],
        ['icon' => 'fa-solid fa-globe', 'label' => 'Contenido Web', 'href' => $ruta_base . 'admin/contenido_web.php', 'match' => ['contenido_web.php']],
        ['icon' => 'fa-solid fa-trophy', 'label' => 'Actividades y Logros', 'href' => $ruta_base . 'admin/actividades_logros.php', 'match' => ['actividades_logros.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Gestión Académica', 'href' => $ruta_base . 'admin/trimestres.php', 'match' => ['trimestres.php']],
    ];
} elseif ($rol_actual === 'docente' ||$rol_actual === 'tutor' || $rol_actual === 'profesor') {$menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'tutor/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horario de Clases', 'href' => $ruta_base . 'tutor/horarios.php', 'match' => ['horarios.php']],
        ['icon' => 'fa-solid fa-pen-to-square', 'label' => 'Gestión de Actividades', 'href' => $ruta_base . 'tutor/actividades.php', 'match' => ['actividades.php']],
        ['icon' => 'fa-solid fa-sliders', 'label' => 'Ponderaciones', 'href' => $ruta_base . 'tutor/configurar_dimensiones.php', 'match' => ['configurar_dimensiones.php']],
        ['icon' => 'fa-solid fa-clipboard-check', 'label' => 'Asistencia', 'href' => $ruta_base . 'tutor/asistencia.php', 'match' => ['asistencia.php', 'consulta_asistencia.php']],
        ['icon' => 'fa-solid fa-paper-plane', 'label' => 'Enviar Recordatorio', 'href' => $ruta_base . 'tutor/enviar-recordatorio.php', 'match' => ['enviar-recordatorio.php']],
    ];
} elseif ($rol_actual === 'estudiante') {$menu = [
        ['icon' => 'fa-solid fa-house', 'label' => 'Inicio', 'href' => $ruta_base . 'estudiante/dashboard.php', 'match' => ['dashboard.php']],
        ['icon' => 'fa-solid fa-calendar-days', 'label' => 'Horario de Clases', 'href' => $ruta_base . 'estudiante/horario.php', 'match' => ['horario.php']],
        ['icon' => 'fa-solid fa-book-bookmark', 'label' => 'Tareas y Actividades', 'href' => $ruta_base . 'estudiante/tareas.php', 'match' => ['tareas.php']],
        ['icon' => 'fa-solid fa-user-check', 'label' => 'Mi Asistencia', 'href' => $ruta_base . 'estudiante/asistencia.php', 'match' => ['asistencia.php']],
        ['icon' => 'fa-solid fa-bell', 'label' => 'Recordatorios', 'href' => $ruta_base . 'estudiante/recordatorios.php', 'match' => ['recordatorios.php']],
        ['icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Plantel Docente', 'href' => $ruta_base . 'public/docentes.php', 'match' => []],
    ];
} elseif ($rol_actual === 'padre') {$menu = [
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
    
    <!-- Hojas de estilo y FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $ruta_base; ?>assets/css/styles.css">

    <style>
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

        /* Widget IA Flotante */
        #ai-widget-container {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 9999;
            font-family: system-ui, -apple-system, sans-serif;
        }
        #ai-chat-box {
            display: none;
            width: 350px;
            height: 460px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid #e2e8f0;
            flex-direction: column;
            overflow: hidden;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

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
            <?php foreach ($menu as$item): ?>
                <?php $activo = in_array($pagina_actual,$item['match']); ?>
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

<!-- WIDGET INTEGRADO DE ASISTENTE IA -->
<div id="ai-widget-container">
    <div id="ai-chat-box">
        <div style="background: #800020; color: #ffffff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.2rem;">🤖</span>
                <strong style="font-size: 0.95rem;">Asistente Nazareth IA</strong>
            </div>
            <button id="close-ai" style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer;">&times;</button>
        </div>

        <div id="ai-messages" style="flex: 1; padding: 12px; overflow-y: auto; background: #f8fafc; font-size: 0.88rem; display: flex; flex-direction: column; gap: 10px;">
            <div style="background: #e2e8f0; color: #1e293b; padding: 10px 12px; border-radius: 8px; max-width: 85%;">
                👋 ¡Hola, <?php echo htmlspecialchars($nombre_mostrar); ?>! Soy la IA del colegio. Pregúntame sobre tus clases, asistencias o tareas.
            </div>
        </div>

        <div style="padding: 10px; background: #ffffff; border-top: 1px solid #e2e8f0; display: flex; gap: 6px;">
            <input type="text" id="ai-input" placeholder="Escribe tu consulta..." style="flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; outline: none;">
            <button id="ai-send" style="background: #800020; color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                ➤
            </button>
        </div>
    </div>

    <button id="toggle-ai" style="background: #800020; color: white; border: none; border-radius: 50%; width: 55px; height: 55px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.3); float: right; display: flex; align-items: center; justify-content: center;">
        🤖
    </button>
</div>

<!-- SCRIPTS DE INTERACCIÓN -->
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarNav');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const box = document.getElementById('ai-chat-box');
    const toggle = document.getElementById('toggle-ai');
    const close = document.getElementById('close-ai');
    const send = document.getElementById('ai-send');
    const input = document.getElementById('ai-input');
    const messages = document.getElementById('ai-messages');

    if (toggle && box && close) {
        toggle.onclick = function() {
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'flex' : 'none';
        };
        close.onclick = function() {
            box.style.display = 'none';
        };
    }

    function enviarPreguntaIA() {
        const txt = input.value.trim();
        if (!txt) return;

        messages.innerHTML += `<div style="background: #800020; color: white; padding: 8px 12px; border-radius: 8px; max-width: 85%; align-self: flex-end;">${txt}</div>`;
        input.value = '';
        messages.scrollTop = messages.scrollHeight;

        const loading = document.createElement('div');
        loading.style.cssText = 'background: #e2e8f0; color: #64748b; padding: 6px 10px; border-radius: 8px; align-self: flex-start; font-style: italic;';
        loading.textContent = 'Procesando consulta...';
        messages.appendChild(loading);
        messages.scrollTop = messages.scrollHeight;

        let body = new FormData();
        body.append('pregunta', txt);

        fetch('<?php echo $ruta_base; ?>api/asistente_ia.php', { method: 'POST', body: body })
        .then(r => r.json())
        .then(data => {
            messages.removeChild(loading);
            messages.innerHTML += `<div style="background: #e2e8f0; color: #1e293b; padding: 8px 12px; border-radius: 8px; max-width: 85%; align-self: flex-start; white-space: pre-wrap;">${data.respuesta}</div>`;
            messages.scrollTop = messages.scrollHeight;
        })
        .catch(err => {
            messages.removeChild(loading);
            messages.innerHTML += `<div style="background: #fee2e2; color: #991b1b; padding: 8px 12px; border-radius: 8px; align-self: flex-start;">Error al conectar con la IA.</div>`;
            messages.scrollTop = messages.scrollHeight;
        });
    }

    if (send && input) {
        send.onclick = enviarPreguntaIA;
        input.onkeypress = function(e) { 
            if (e.key === 'Enter') enviarPreguntaIA(); 
        };
    }
});
</script>