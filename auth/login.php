<?php
$ruta_base = "../";
require_once "../includes/conexion.php";
require_once "../includes/funciones.php";

// Si el usuario ya está logueado, redirigirlo a su dashboard
if (estaAutenticado()) {
    redirigirSegunRol($_SESSION['rol']);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ci = limpiarCadena($_POST['ci']);
    $password = $_POST['password'];

    if (empty($ci) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Consultar usuario por C.I.
        $sql = "SELECT u.*, r.nombre AS nombre_rol 
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE u.ci = ? AND u.estado = 1 
                LIMIT 1";
        
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "s", $ci);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($resultado)) {
            // Verificar contraseña encriptada (login normal)
            if (password_verify($password, $user['password'])) {
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['ci'] = $user['ci'];
                $_SESSION['nombre_completo'] = $user['nombres'] . " " . $user['apellidos'];
                $_SESSION['rol'] = $user['nombre_rol'];

                redirigirSegunRol($user['nombre_rol']);

            // Si el usuario es estudiante y tiene PIN de padres configurado, probar esa contraseña
            } elseif ($user['nombre_rol'] === 'estudiante' && !empty($user['password_padre']) && password_verify($password, $user['password_padre'])) {
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['ci'] = $user['ci'];
                $_SESSION['nombre_completo'] = "Familia de " . $user['nombres'] . " " . $user['apellidos'];
                $_SESSION['rol'] = 'padre';

                redirigirSegunRol('padre');

            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El C.I. ingresado no se encuentra registrado o está inactivo.";
        }
    }
}

require_once "../includes/header.php";
?>

<style>
/* --- ESTILOS PANTALLA DE LOGIN --- */
.login-wrapper {
    position: relative;
    width: 100%;
    min-height: calc(100vh - 120px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background-color: #f4f6f9;
}

/* Fondo con imagen y overlay de degrada burdeos */
.login-bg-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* Reemplaza esta imagen por la foto real de tu colegio */
    background: linear-gradient(135deg, rgba(120, 15, 25, 0.92) 0%, rgba(80, 5, 15, 0.75) 50%, rgba(15, 23, 42, 0.6) 100%), 
                url('../assets/img/fondologin.jpg') center/cover no-repeat;
    z-index: 1;
}

.login-container {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1050px;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    background: transparent;
    gap: 40px;
    align-items: center;
}

/* Columna Izquierda: Mensaje institucional */
.login-info-panel {
    color: #ffffff;
    padding-right: 20px;
}

.login-badge {
    display: inline-block;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #f87171;
    margin-bottom: 12px;
}

.login-info-panel h1 {
    font-size: 2.4rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 15px;
    color: #ffffff;
}

.login-info-panel p.description {
    font-size: 1rem;
    color: #e2e8f0;
    line-height: 1.6;
    margin-bottom: 30px;
}

.features-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 35px;
}

.feature-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.feature-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #ffffff;
    flex-shrink: 0;
}

.feature-text h4 {
    font-size: 0.98rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 3px 0;
}

.feature-text p {
    font-size: 0.85rem;
    color: #cbd5e1;
    margin: 0;
}

.slogan-tag {
    font-family: 'Georgia', serif;
    font-style: italic;
    font-size: 1.2rem;
    color: #fca5a5;
    border-left: 3px solid #f87171;
    padding-left: 12px;
}

/* Columna Derecha: Tarjeta de Login */
.login-card-panel {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px 35px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
}

.login-card-header {
    text-align: center;
    margin-bottom: 25px;
}

.login-logo-img {
    height: 55px;
    width: auto;
    margin-bottom: 12px;
}

.login-card-header h2 {
    font-size: 1.7rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 5px 0;
}

.login-card-header p {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0;
}

/* Inputs con ícono */
.input-icon-group {
    position: relative;
    margin-bottom: 20px;
}

.input-icon-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper i.field-icon {
    position: absolute;
    left: 14px;
    color: #94a3b8;
    font-size: 1rem;
}

.input-wrapper input {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.92rem;
    color: #0f172a;
    background-color: #f8fafc;
    transition: all 0.25s ease;
}

.input-wrapper input:focus {
    outline: none;
    border-color: #800020;
    background-color: #ffffff;
    box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
}

.toggle-password {
    position: absolute;
    right: 14px;
    cursor: pointer;
    color: #94a3b8;
    font-size: 1rem;
}

.btn-login-submit {
    width: 100%;
    padding: 13px;
    background-color: #800020;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s ease, transform 0.1s ease;
    margin-top: 10px;
}

.btn-login-submit:hover {
    background-color: #600018;
}

.btn-login-submit:active {
    transform: scale(0.98);
}

.login-footer-links {
    margin-top: 25px;
    text-align: center;
    font-size: 0.88rem;
    color: #64748b;
}

.login-footer-links a {
    color: #800020;
    font-weight: 700;
    text-decoration: none;
}

.login-footer-links a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 900px) {
    .login-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    .login-info-panel {
        text-align: center;
        padding-right: 0;
    }
    .feature-item {
        text-align: left;
    }
}
</style>

<div class="login-wrapper">
    <div class="login-bg-overlay"></div>

    <div class="login-container">
        
        <!-- PANEL IZQUIERDO: Información e Identidad Institucional -->
        <div class="login-info-panel">
            <span class="login-badge">— Bienvenido/a</span>
            <h1>U.E. Jesús de Nazareth</h1>
            <p class="description">Accede a tu cuenta para continuar con tus actividades académicas y administrativas.</p>

            <div class="features-list">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <div class="feature-text">
                        <h4>Gestión académica</h4>
                        <p>Consulta tus calificaciones y avances académicos.</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div class="feature-text">
                        <h4>Trámites y solicitudes</h4>
                        <p>Realiza tus gestiones y consultas en línea.</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <div class="feature-text">
                        <h4>Comunicación institucional</h4>
                        <p>Mantente informado de todas las novedades y comunicados.</p>
                    </div>
                </div>
            </div>

            <div class="slogan-tag">
                Jesús de Nazareth - Camino, Verdad y Vida
            </div>
        </div>

        <!-- PANEL DERECHO: Formulario de Inicio de Sesión -->
        <div class="login-card-panel">
            <div class="login-card-header">
                <!-- Puedes colocar aquí el escudo del colegio -->
                <img src="../assets/img/image.png" alt="Escudo Colegio" class="login-logo-img" onerror="this.style.display='none'">
                <h2>Iniciar Sesión</h2>
                <p>Ingresa tus datos para acceder al sistema.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert" style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem;">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="input-icon-group">
                    <label for="ci">Cédula de Identidad (C.I.)</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user field-icon"></i>
                        <input type="text" name="ci" id="ci" required placeholder="Ingresa tu C.I." value="<?php echo isset($_POST['ci']) ? htmlspecialchars($_POST['ci']) : ''; ?>">
                    </div>
                </div>

                <div class="input-icon-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="password" id="password" required placeholder="••••••••">
                        <i class="fa-regular fa-eye toggle-password" id="togglePass" onclick="togglePasswordVisibility()"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login-submit">
                    <i class="fa-solid fa-right-to-bracket"></i> Ingresar
                </button>
            </form>

            <div class="login-footer-links">
                <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
                <p style="margin-top: 10px; font-size: 0.8rem;">
                    <a href="../public/contacto.php" style="color: #64748b; font-weight: normal;"><i class="fa-regular fa-circle-question"></i> ¿Necesitas ayuda? Contáctanos</a>
                </p>
            </div>
        </div>

    </div>
</div>

<script>
// Función para ocultar / mostrar contraseña
function togglePasswordVisibility() {
    const passInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePass');
    
    if (passInput.type === 'password') {
        passInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>