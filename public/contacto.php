<?php
$ruta_base = "../";
require_once "../includes/header.php";
require_once "../includes/conexion.php";

$mensaje_exito = "";
$mensaje_error = "";

// Procesar envío del formulario si se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if (!empty($nombre) && !empty($email) && !empty($mensaje)) {
        // Guardar mensaje si la tabla mensajes_contacto existe, o enviar por correo
        if (isset($conexion)) {
            $stmt = $conexion->prepare("INSERT INTO mensajes_contacto (nombre, email, telefono, asunto, mensaje, creado_en) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sssss", $nombre, $email, $telefono, $asunto, $mensaje);
                $stmt->execute();
                $stmt->close();
            }
        }
        $mensaje_exito = "¡Gracias por contactarnos! Tu mensaje ha sido enviado con éxito. Nos pondremos en contacto a la brevedad.";
    } else {
        $mensaje_error = "Por favor completa todos los campos requeridos (*).";
    }
}
?>

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #334155;
    }

    .contacto-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 60px;
    }

    /* 1. Hero Section */
    .hero-contacto {
        position: relative;
        background: linear-gradient(90deg, rgba(69, 10, 10, 0.95) 35%, rgba(69, 10, 10, 0.65) 100%), url('assets/img/image.jpg') center/cover no-repeat;
        color: #ffffff;
        padding: 60px 40px;
        border-radius: 0 0 16px 16px;
        margin-bottom: 35px;
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

    .hero-contacto h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 0 0 12px;
    }

    .hero-contacto p {
        font-size: 0.95rem;
        max-width: 550px;
        line-height: 1.5;
        opacity: 0.9;
        margin-bottom: 25px;
    }

    .hero-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn-hero-call {
        background-color: #be123c;
        color: #ffffff !important;
        padding: 10px 22px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s ease;
    }

    .btn-hero-call:hover {
        background-color: #9f1239;
    }

    .btn-hero-ws {
        background-color: #10b981;
        color: #ffffff !important;
        padding: 10px 22px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s ease;
    }

    .btn-hero-ws:hover {
        background-color: #059669;
    }

    /* Layout Principal (Formulario + Info) */
    .main-grid {
    display: grid;
    grid-template-columns: 1.8fr 1.2fr;
    gap: 30px;
    align-items: start; /* <-- Esto evita que las columnas se estiren hacia abajo */
    margin-bottom: 25px; /* Reducimos el margen inferior */
}

    @media (max-width: 992px) {
        .main-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Card Formulario */
    .card-box {
    background: #ffffff;
    border-radius: 14px;
    padding: 25px 30px; /* Reducimos ligeramente el padding interno */
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

    .card-title {
        color: #be123c;
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 6px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-subtitle {
        font-size: 0.83rem;
        color: #64748b;
        margin-bottom: 25px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
    }

    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.88rem;
        outline: none;
        background-color: #f8fafc;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }

    .form-control:focus {
        border-color: #be123c;
        background-color: #ffffff;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 110px;
    }

    .btn-submit {
        background-color: #be123c;
        color: #ffffff;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.2s;
        margin-top: 10px;
    }

    .btn-submit:hover {
        background-color: #9f1239;
    }

    /* Panel Lateral de Información */
    .info-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px;
        background: #f8fafc;
        border-radius: 10px;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s ease;
    }

    .info-icon {
        width: 42px;
        height: 42px;
        background-color: #be123c;
        color: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .info-content {
        flex: 1;
    }

    .info-content h4 {
        margin: 0 0 3px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #1e293b;
    }

    .info-content p {
        margin: 0;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.3;
    }

    .info-arrow {
        color: #cbd5e1;
        font-size: 0.9rem;
    }

    /* Card WhatsApp Institucional */
    .ws-card {
        background-color: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin-top: 20px;
    }

    .ws-card-icon {
        width: 48px;
        height: 48px;
        background-color: #10b981;
        color: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin: 0 auto 10px;
    }

    .ws-card h4 {
        margin: 0 0 4px;
        font-size: 0.9rem;
        color: #065f46;
        font-weight: 700;
    }

    .ws-card .ws-num {
        font-size: 1.1rem;
        font-weight: 800;
        color: #047857;
        margin-bottom: 6px;
    }

    .ws-card p {
        font-size: 0.75rem;
        color: #047857;
        margin-bottom: 12px;
    }

    .btn-ws-chat {
        background-color: #10b981;
        color: #ffffff !important;
        text-decoration: none;
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Redes Sociales */
    .social-section {
        margin-top: 20px;
    }

    .social-section h4 {
        font-size: 0.85rem;
        font-weight: 700;
        color: #be123c;
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .social-icons {
        display: flex;
        gap: 10px;
    }

    .social-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        text-decoration: none;
        font-size: 1rem;
        transition: opacity 0.2s;
    }

    .social-btn:hover {
        opacity: 0.85;
    }

    .btn-fb { background-color: #1877f2; }
    .btn-ig { background-color: #e4405f; }
    .btn-tk { background-color: #000000; }
    .btn-yt { background-color: #ff0000; }

    /* Sección Mapa */
    .map-section {
    margin-bottom: 25px; /* Reducimos el margen entre el mapa y las FAQs */
}

    .map-container {
        width: 100%;
        height: 350px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }

    .map-container iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }

    /* Preguntas Frecuentes FAQ */
    .faq-section {
        margin-bottom: 40px;
    }

    .faq-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .faq-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    @media (max-width: 768px) {
        .faq-grid {
            grid-template-columns: 1fr;
        }
    }

    .faq-item {
        background: #ffffff;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        cursor: pointer;
    }

    .faq-question {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .faq-answer {
        display: none;
        font-size: 0.82rem;
        color: #64748b;
        margin-top: 10px;
        line-height: 1.4;
        border-top: 1px solid #f1f5f9;
        padding-top: 10px;
    }

    .faq-item.active .faq-answer {
        display: block;
    }

    /* CTA Inferior Fe y Alegría */
    .cta-banner {
        background: linear-gradient(90deg, #450a0a 0%, #2a0a0a 100%);
        color: #ffffff;
        border-radius: 14px;
        padding: 35px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .cta-banner-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .cta-banner-icon {
        font-size: 2.2rem;
        color: #fca5a5;
    }

    .cta-banner h2 {
        font-size: 1.3rem;
        margin: 0 0 5px;
        font-weight: 800;
    }

    .cta-banner p {
        font-size: 0.85rem;
        margin: 0;
        opacity: 0.85;
    }

    .btn-cta-inscripciones {
        background: #be123c;
        color: #ffffff !important;
        padding: 12px 24px;
        border-radius: 25px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 700;
        white-space: nowrap;
        transition: background 0.2s;
    }

    .btn-cta-inscripciones:hover {
        background: #9f1239;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-bottom: 20px;
    }

    .alert-success {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background-color: #ffe4e6;
        color: #9f1239;
        border: 1px solid #fecdd3;
    }
</style>

<div class="contacto-container">

    <!-- 1. Hero Section -->
    <div class="hero-contacto">
        <span class="hero-subtitle">CONTÁCTANOS</span>
        <h1>Estamos para ayudarte</h1>
        <p>Si tienes dudas sobre inscripciones, información académica o cualquier consulta, no dudes en escribirnos. Estamos aquí para apoyarte.</p>
        
        <div class="hero-buttons">
            <a href="tel:+59125200000" class="btn-hero-call"><i class="fa-solid fa-phone"></i> Llamar ahora</a>
            <a href="https://wa.me/59177212345" target="_blank" class="btn-hero-ws"><i class="fa-brands fa-whatsapp"></i> Escribir por WhatsApp</a>
        </div>
    </div>

    <!-- 2. Layout Principal en 2 Columnas -->
    <div class="main-grid">

        <!-- COLUMNA IZQUIERDA: Formulario + Mapa (Encuéntranos) -->
        <div class="col-izquierda" style="display: flex; flex-direction: column; gap: 25px;">
            
            <!-- Card Formulario -->
            <div class="card-box">
                <h2 class="card-title"><i class="fa-solid fa-paper-plane"></i> Envíanos un mensaje</h2>
                <p class="card-subtitle">Completa el formulario y nos pondremos en contacto contigo a la brevedad posible.</p>

                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success"><?php echo $mensaje_exito; ?></div>
                <?php endif; ?>

                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-error"><?php echo $mensaje_error; ?></div>
                <?php endif; ?>

                <form action="contacto.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre completo *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Tu nombre" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Correo electrónico *</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="tu@ejemplo.com" required>
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" class="form-control" placeholder="Tu número de contacto">
                        </div>

                        <div class="form-group">
                            <label for="asunto">Asunto</label>
                            <select id="asunto" name="asunto" class="form-control">
                                <option value="">Selecciona un asunto</option>
                                <option value="Inscripciones">Información de Inscripciones</option>
                                <option value="Academico">Consulta Académica</option>
                                <option value="Gabinete">Gabinete Psicológico</option>
                                <option value="Otro">Otro asunto</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="mensaje">Mensaje *</label>
                            <textarea id="mensaje" name="mensaje" class="form-control" placeholder="Escribe tu consulta o mensaje..." required></textarea>
                        </div>

                        <div class="form-group full-width">
                            <button type="submit" class="btn-submit">
                                <i class="fa-solid fa-paper-plane"></i> Enviar consulta
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Card Mapa / Encuéntranos -->
            <div class="card-box map-section">
                <h2 class="card-title"><i class="fa-solid fa-location-dot"></i> Encuéntranos</h2>
                <p class="card-subtitle">Estamos ubicados en una zona accesible y segura de la ciudad de Oruro.</p>

                <div class="map-container">
                    <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d6382.323895634349!2d-67.09989472906439!3d-17.9809470886528!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x93e2b0cbec8de549%3A0xfd545bf60c38281d!2sJes%C3%BAs%20de%20Nazareth!5e0!3m2!1ses-419!2sbo!4v1789833648836!5m2!1ses-419!2sbo" width="600" height="450"
                width="100%" 
                height="100%" 
                style="border:0; min-height: 350px;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Información de Contacto -->
        <div class="col-derecha">
            <div class="card-box">
                <h2 class="card-title"><i class="fa-solid fa-address-book"></i> Información de Contacto</h2>
                <p class="card-subtitle">Medios oficiales de atención e información de la Unidad Educativa.</p>

                <div class="info-list">
                    <div class="info-item">
                        <div class="info-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="info-content">
                            <h4>Dirección</h4>
                            <p>Calle Tomás Frías y Peralta Soruco<br>Zona Sud Este, Oruro - Bolivia</p>
                        </div>
                        <i class="fa-solid fa-chevron-right info-arrow"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fa-solid fa-phone"></i></div>
                        <div class="info-content">
                            <h4>Teléfonos</h4>
                            <p>(+591) 2 52-XXXXX<br>Lunes a Viernes - 08:00 a 12:30 | 14:00 a 18:00</p>
                        </div>
                        <i class="fa-solid fa-chevron-right info-arrow"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
                        <div class="info-content">
                            <h4>Correo Electrónico</h4>
                            <p>info@jesusdenazareth.edu.bo</p>
                        </div>
                        <i class="fa-solid fa-chevron-right info-arrow"></i>
                    </div>

                    <div class="info-item">
                        <div class="info-icon"><i class="fa-regular fa-clock"></i></div>
                        <div class="info-content">
                            <h4>Horario de Atención</h4>
                            <p>Lunes a Viernes<br>08:00 a 12:30 | 14:00 a 18:00</p>
                        </div>
                        <i class="fa-solid fa-chevron-right info-arrow"></i>
                    </div>
                </div>

                <!-- WhatsApp Card -->
                <div class="ws-card">
                    <div class="ws-card-icon"><i class="fa-brands fa-whatsapp"></i></div>
                    <h4>WhatsApp Institucional</h4>
                    <div class="ws-num">+591 772 12345</div>
                    <p>Escríbenos directamente y te ayudaremos.</p>
                    <a href="https://wa.me/59177212345" target="_blank" class="btn-ws-chat">
                        <i class="fa-brands fa-whatsapp"></i> Chatear ahora
                    </a>
                </div>

                <!-- Redes Sociales -->
                <!-- Redes Sociales -->
<div class="social-section">
    <h4><i class="fa-solid fa-share-nodes"></i> Síguenos en redes</h4>
    <div class="social-icons">
        <!-- Reemplaza las URL entre comillas por las de la institución -->
        <a href="https://www.facebook.com/p/Unidad-Educativa-Jes%C3%BAs-de-Nazareth-Secundaria-Oruro-100065640484583/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="social-btn btn-fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="social-btn btn-ig" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
        <a href="https://www.tiktok.com/" target="_blank" rel="noopener noreferrer" class="social-btn btn-tk" title="TikTok"><i class="fa-brands fa-tiktok"></i></a>
        <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer" class="social-btn btn-yt" title="YouTube"><i class="fa-brands fa-youtube"></i></a>
    </div>
    <p style="font-size: 0.75rem; color: #64748b; margin-top: 8px;">¡Forma parte de nuestra comunidad!</p>
</div>
            </div>
        </div>

    </div> <!-- <-- AQUÍ ESTABA EL DIV DE CIERRE FALTANTE DE MAIN-GRID -->

    <!-- 4. Preguntas Frecuentes FAQ -->
    <div class="card-box faq-section" style="margin-bottom: 25px;">
        <div class="faq-header">
            <div>
                <h2 class="card-title"><i class="fa-solid fa-circle-question"></i> Preguntas Frecuentes</h2>
                <p class="card-subtitle" style="margin-bottom: 0;">Resolvemos las dudas más comunes de nuestra comunidad educativa.</p>
            </div>
        </div>

        <div class="faq-grid">
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-question">
                    ¿Dónde se realizan las inscripciones?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Las inscripciones se realizan presencialmente en Secretaría de la Unidad Educativa o mediante nuestro formulario en línea habilitado en la temporada correspondiente.
                </div>
            </div>

            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-question">
                    ¿Qué documentos se necesitan?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Se requiere certificado de nacimiento del estudiante, fotocopia de C.I. del estudiante y del tutor, libreta del año anterior y formulario RUDE completado.
                </div>
            </div>

            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-question">
                    ¿Hay nivel inicial?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Sí, contamos con Nivel Inicial (Kínder / Pre-kínder), Nivel Primario y Nivel Secundario en ambos turnos.
                </div>
            </div>

            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-question">
                    ¿Cuáles son los horarios de clases?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Turno Mañana: 08:00 a 12:30.<br>Turno Tarde: 14:00 a 18:15 según el nivel correspondiente.
                </div>
            </div>
        </div>
    </div>

    <!-- 5. CTA Inferior Fe y Alegría -->
    <div class="cta-banner">
        <div class="cta-banner-content">
            <div class="cta-banner-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <h2>Forma parte de nuestra comunidad educativa</h2>
                <p>Educación con valores, excelencia y compromiso.</p>
            </div>
        </div>
        <a href="contacto.php" class="btn-cta-inscripciones">Inscripciones 2026</a>
    </div>

</div>

<script>
    function toggleFaq(element) {
        element.classList.toggle('active');
        const icon = element.querySelector('.faq-question i');
        if (element.classList.contains('active')) {
            icon.className = 'fa-solid fa-chevron-up';
        } else {
            icon.className = 'fa-solid fa-chevron-down';
        }
    }
</script>

<?php require_once "../includes/footer.php"; ?>