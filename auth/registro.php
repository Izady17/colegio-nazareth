<?php
session_start();
$ruta_base = "../";

// Si ya hay sesión activa, redirigir al inicio o dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: " . $ruta_base . "index.php");
    exit();
}

require_once "../includes/conexion.php"; // Ajusta a la ruta real de tu archivo de conexión

$errores = [];
$exito = "";

// Obtener Áreas para el desplegable de Docentes
$sql_areas = "SELECT id_area, nombre_area FROM areas ORDER BY nombre_area ASC";
$res_areas = mysqli_query($conexion, $sql_areas);

// Obtener Paralelos para el desplegable de Estudiantes
$sql_paralelos = "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado ASC, letra ASC";
$res_paralelos = mysqli_query($conexion, $sql_paralelos);

// Procesar Formulario mediante POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ci                 = trim($_POST['ci'] ?? '');
    $nombres            = trim($_POST['nombres'] ?? '');
    $apellidos          = trim($_POST['apellidos'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $password           = $_POST['password'] ?? '';
    $confirm_password   = $_POST['confirm_password'] ?? '';
    $id_rol             = intval($_POST['id_rol'] ?? 0); // 2 = Docente, 3 = Estudiante

    // Campos específicos
    $id_paralelo        = intval($_POST['id_paralelo'] ?? 0);
    $id_area_lista      = array_map('intval', $_POST['id_area'] ?? []);
    $titulo_academico   = trim($_POST['titulo_academico'] ?? '');

    // VALIDACIONES
    if (empty($ci) || empty($nombres) || empty($apellidos) || empty($password)) {
        $errores[] = "Todos los campos obligatorios deben ser completados.";
    }

    if ($password !== $confirm_password) {
        $errores[] = "Las contraseñas ingresadas no coinciden.";
    }

    // Seguridad: Solo permitir roles 2 (Docente) y 3 (Estudiante)
    if (!in_array($id_rol, [2, 3])) {
        $errores[] = "Selección de rol no válida.";
    }

    // Validar campos específicos por rol
    if ($id_rol === 3 && $id_paralelo <= 0) {
        $errores[] = "Debes seleccionar un curso y paralelo válido.";
    }
    if ($id_rol === 2 && (empty($id_area_lista) || empty($titulo_academico))) {
        $errores[] = "Debes seleccionar al menos un área/materia y especificar tu título académico.";
    }

    // Validar duplicados de CI y Email
    if (empty($errores)) {
        $stmt_check = mysqli_prepare($conexion, "SELECT id_usuario FROM usuarios WHERE ci = ? OR email = ?");
        mysqli_stmt_bind_param($stmt_check, "ss", $ci, $email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $errores[] = "El número de CI o correo electrónico ya se encuentra registrado.";
        }
        mysqli_stmt_close($stmt_check);
    }

    // PROCESAMIENTO E INSERCIÓN CON TRANSACCIÓN
    if (empty($errores)) {
        mysqli_begin_transaction($conexion);

        try {
            // 1. Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // 2. Insertar en tabla 'usuarios'
            $stmt_usuario = mysqli_prepare(
                $conexion, 
                "INSERT INTO usuarios (ci, nombres, apellidos, email, password, id_rol, estado) VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            mysqli_stmt_bind_param($stmt_usuario, "sssssi", $ci, $nombres, $apellidos, $email, $password_hash, $id_rol);
            
            if (!mysqli_stmt_execute($stmt_usuario)) {
                throw new Exception("Error al guardar el usuario principal.");
            }

            // Obtener el id_usuario recién generado
            $id_usuario_nuevo = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_usuario);

            // 3. Insertar perfil según corresponda
            if ($id_rol === 3) {
                // Perfil Estudiante
                $stmt_est = mysqli_prepare($conexion, "INSERT INTO estudiante_paralelo (id_estudiante, id_paralelo) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt_est, "ii", $id_usuario_nuevo, $id_paralelo);
                if (!mysqli_stmt_execute($stmt_est)) {
                    throw new Exception("Error al asociar el paralelo del estudiante.");
                }
                mysqli_stmt_close($stmt_est);

                // Generar automáticamente el PIN de acceso para los padres de familia
                $pin_padre = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $pin_padre_hash = password_hash($pin_padre, PASSWORD_DEFAULT);
                $stmt_pin = mysqli_prepare($conexion, "UPDATE usuarios SET password_padre = ?, pin_padre_plano = ? WHERE id_usuario = ?");
                mysqli_stmt_bind_param($stmt_pin, "ssi", $pin_padre_hash, $pin_padre, $id_usuario_nuevo);
                if (!mysqli_stmt_execute($stmt_pin)) {
                    throw new Exception("Error al generar el PIN de padres de familia.");
                }
                mysqli_stmt_close($stmt_pin);

            } elseif ($id_rol === 2) {
                // Perfil Docente (el área principal es la primera seleccionada, por compatibilidad)
                $id_area_principal = $id_area_lista[0];
                $stmt_doc = mysqli_prepare($conexion, "INSERT INTO docentes_perfil (id_usuario, id_area, titulo_academico) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_doc, "iis", $id_usuario_nuevo, $id_area_principal, $titulo_academico);
                if (!mysqli_stmt_execute($stmt_doc)) {
                    throw new Exception("Error al crear el perfil del docente.");
                }
                mysqli_stmt_close($stmt_doc);

                // Registrar TODAS las materias/áreas que dicta (incluida la principal)
                $stmt_mat = mysqli_prepare($conexion, "INSERT INTO docente_materias (id_usuario, id_area) VALUES (?, ?)");
                foreach ($id_area_lista as $id_area_item) {
                    mysqli_stmt_bind_param($stmt_mat, "ii", $id_usuario_nuevo, $id_area_item);
                    if (!mysqli_stmt_execute($stmt_mat)) {
                        throw new Exception("Error al asociar una de las materias del docente.");
                    }
                }
                mysqli_stmt_close($stmt_mat);
            }

            // Confirmar cambios
            mysqli_commit($conexion);

            // Redirigir a login con mensaje exitoso
            header("Location: login.php?mensaje=registro_exitoso");
            exit();

        } catch (Exception $e) {
            // Revertir cambios ante cualquier fallo
            mysqli_rollback($conexion);
            $errores[] = "No se pudo completar el registro: " . $e->getMessage();
        }
    }
}

require_once "../includes/header.php"; 
?>

<style>
/* --- ESTILOS REGISTRO --- */
.register-wrapper {
    position: relative;
    width: 100%;
    min-height: calc(100vh - 120px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background-color: #f4f6f9;
}

.register-bg-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(120, 15, 25, 0.92) 0%, rgba(80, 5, 15, 0.75) 50%, rgba(15, 23, 42, 0.6) 100%), 
                url('../assets/img/fondologin.jpg') center/cover no-repeat;
    z-index: 1;
}

.register-container {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1100px;
    display: grid;
    grid-template-columns: 0.95fr 1.05fr;
    gap: 40px;
    align-items: center;
}

/* Columna Izquierda: Mensaje Institucional */
.register-info-panel {
    color: #ffffff;
    padding-right: 15px;
}

.register-badge {
    display: inline-block;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #f87171;
    margin-bottom: 12px;
}

.register-info-panel h1 {
    font-size: 2.3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 15px;
    color: #ffffff;
}

.register-info-panel p.description {
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
    font-size: 1.15rem;
    color: #fca5a5;
    border-left: 3px solid #f87171;
    padding-left: 12px;
}

/* Columna Derecha: Tarjeta de Formulario */
.register-card-panel {
    background: #ffffff;
    border-radius: 20px;
    padding: 35px 30px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    max-height: 88vh;
    overflow-y: auto;
}

/* Scrollbar estilizada para el panel */
.register-card-panel::-webkit-scrollbar {
    width: 6px;
}
.register-card-panel::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.register-card-header {
    text-align: center;
    margin-bottom: 20px;
}

.register-logo-img {
    height: 50px;
    width: auto;
    margin-bottom: 10px;
}

.register-card-header h2 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 4px 0;
}

.register-card-header p {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0;
}

/* Formulario e Inputs */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.input-icon-group {
    position: relative;
    margin-bottom: 16px;
}

.input-icon-group label {
    display: block;
    font-size: 0.83rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 5px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper i.field-icon {
    position: absolute;
    left: 13px;
    color: #94a3b8;
    font-size: 0.95rem;
    z-index: 1;
}

.input-wrapper input,
.input-wrapper select {
    width: 100%;
    padding: 10px 12px 10px 38px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    font-size: 0.88rem;
    color: #0f172a;
    background-color: #f8fafc;
    transition: all 0.25s ease;
}

.input-wrapper input:focus,
.input-wrapper select:focus {
    outline: none;
    border-color: #800020;
    background-color: #ffffff;
    box-shadow: 0 0 0 3px rgba(128, 0, 32, 0.1);
}

/* Secciones Dinámicas */
.dynamic-section {
    background-color: #f1f5f9;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 16px;
    border: 1px dashed #cbd5e1;
}

.dynamic-section h4 {
    font-size: 0.9rem;
    font-weight: 700;
    color: #800020;
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.multiple-select {
    padding: 8px !important;
    height: auto;
    border-radius: 8px;
}

.btn-register-submit {
    width: 100%;
    padding: 12px;
    background-color: #800020;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s ease, transform 0.1s ease;
    margin-top: 10px;
}

.btn-register-submit:hover {
    background-color: #600018;
}

.btn-register-submit:active {
    transform: scale(0.98);
}

.register-footer-links {
    margin-top: 20px;
    text-align: center;
    font-size: 0.85rem;
    color: #64748b;
}

.register-footer-links a {
    color: #800020;
    font-weight: 700;
    text-decoration: none;
}

.register-footer-links a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 900px) {
    .register-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    .register-info-panel {
        text-align: center;
        padding-right: 0;
    }
    .form-grid-2 {
        grid-template-columns: 1fr;
    }
    .register-card-panel {
        max-height: none;
    }
}
</style>

<div class="register-wrapper">
    <div class="register-bg-overlay"></div>

    <div class="register-container">
        
        <!-- PANEL IZQUIERDO: Información Institucional -->
        <div class="register-info-panel">
            <span class="register-badge">— Únete a nuestra plataforma</span>
            <h1>Crea tu Cuenta Institucional</h1>
            <p class="description">Forma parte de la comunidad virtual de la Unidad Educativa Jesús de Nazareth y accede a todos nuestros servicios académicos.</p>

            <div class="features-list">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="feature-text">
                        <h4>Para Estudiantes</h4>
                        <p>Accede a tus calificaciones, tareas y seguimiento escolar en tiempo real.</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="feature-text">
                        <h4>Para Docentes</h4>
                        <p>Gestiona el registro de notas, asistencia y contenidos de tus materias asignadas.</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="feature-text">
                        <h4>Acceso seguro</h4>
                        <p>Plataforma protegida con credenciales e integración para padres de familia.</p>
                    </div>
                </div>
            </div>

            <div class="slogan-tag">
                Jesús de Nazareth - Camino, Verdad y Vida
            </div>
        </div>

        <!-- PANEL DERECHO: Formulario de Registro -->
        <div class="register-card-panel">
            <div class="register-card-header">
                <img src="../assets/img/escudo.png" alt="Escudo Colegio" class="register-logo-img" onerror="this.style.display='none'">
                <h2>Registro de Usuario</h2>
                <p>Completa tus datos para crear una nueva cuenta.</p>
            </div>

            <?php if (!empty($errores)): ?>
                <div class="alert" style="background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem;">
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="registro.php" method="POST" id="formRegistro">
                
                <!-- Cédula de Identidad -->
                <div class="input-icon-group">
                    <label for="ci">Cédula de Identidad (CI) *</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-address-card field-icon"></i>
                        <input type="text" id="ci" name="ci" required placeholder="Ej: 8493021" value="<?php echo htmlspecialchars($_POST['ci'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Nombres y Apellidos -->
                <div class="form-grid-2">
                    <div class="input-icon-group">
                        <label for="nombres">Nombres *</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user field-icon"></i>
                            <input type="text" id="nombres" name="nombres" required placeholder="Tus nombres" value="<?php echo htmlspecialchars($_POST['nombres'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="input-icon-group">
                        <label for="apellidos">Apellidos *</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user field-icon"></i>
                            <input type="text" id="apellidos" name="apellidos" required placeholder="Tus apellidos" value="<?php echo htmlspecialchars($_POST['apellidos'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Correo Electrónico -->
                <div class="input-icon-group">
                    <label for="email">Correo Electrónico</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email" placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Contraseñas -->
                <div class="form-grid-2">
                    <div class="input-icon-group">
                        <label for="password">Contraseña *</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                    </div>
                    <div class="input-icon-group">
                        <label for="confirm_password">Confirmar Contraseña *</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <!-- Selección de Rol -->
                <div class="input-icon-group">
                    <label for="id_rol">Tipo de Usuario *</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-users field-icon"></i>
                        <select id="id_rol" name="id_rol" required onchange="toggleCamposPerfil()">
                            <option value="">-- Seleccionar --</option>
                            <option value="3" <?php echo (($_POST['id_rol'] ?? '') == '3') ? 'selected' : ''; ?>>Estudiante</option>
                            <option value="2" <?php echo (($_POST['id_rol'] ?? '') == '2') ? 'selected' : ''; ?>>Docente</option>
                        </select>
                    </div>
                </div>

                <!-- Campos Específicos para Estudiante -->
                <div id="seccion_estudiante" class="dynamic-section" style="display: none;">
                    <h4><i class="fa-solid fa-graduation-cap"></i> Información del Estudiante</h4>
                    <div class="input-icon-group" style="margin-bottom: 0;">
                        <label for="id_paralelo">Curso y Paralelo *</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-school field-icon"></i>
                            <select id="id_paralelo" name="id_paralelo">
                                <option value="">-- Seleccionar Curso --</option>
                                <?php 
                                mysqli_data_seek($res_paralelos, 0);
                                while ($row = mysqli_fetch_assoc($res_paralelos)): 
                                ?>
                                    <option value="<?php echo $row['id_paralelo']; ?>" <?php echo (($_POST['id_paralelo'] ?? '') == $row['id_paralelo']) ? 'selected' : ''; ?>>
                                        <?php echo $row['grado']; ?>° de Secundaria - Paralelo "<?php echo $row['letra']; ?>"
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campos Específicos para Docente -->
                <div id="seccion_docente" class="dynamic-section" style="display: none;">
                    <h4><i class="fa-solid fa-briefcase"></i> Información del Docente</h4>
                    
                    <div class="input-icon-group">
                        <label for="titulo_academico">Título Académico *</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-certificate field-icon"></i>
                            <input type="text" id="titulo_academico" name="titulo_academico" placeholder="Ej: Lic. en Ciencias de la Educación" value="<?php echo htmlspecialchars($_POST['titulo_academico'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="input-icon-group" style="margin-bottom: 0;">
                        <label for="id_area">Área(s) Académica(s) * <br><small style="font-weight: normal; color: #64748b;">(Mantén presionado Ctrl o Cmd para elegir varias)</small></label>
                        <select id="id_area" name="id_area[]" multiple size="4" class="multiple-select" style="width: 100%;">
                            <?php 
                            mysqli_data_seek($res_areas, 0);
                            while ($row = mysqli_fetch_assoc($res_areas)): 
                            ?>
                                <option value="<?php echo $row['id_area']; ?>">
                                    <?php echo htmlspecialchars($row['nombre_area']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-register-submit">
                    <i class="fa-solid fa-user-plus"></i> Registrarse
                </button>
            </form>

            <div class="register-footer-links">
                <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>

    </div>
</div>

<!-- JS Dinámico para mostrar/ocultar secciones -->
<script>
function toggleCamposPerfil() {
    const rol = document.getElementById('id_rol').value;
    const secEstudiante = document.getElementById('seccion_estudiante');
    const secDocente = document.getElementById('seccion_docente');

    if (rol === '3') { // Estudiante
        secEstudiante.style.display = 'block';
        secDocente.style.display = 'none';
    } else if (rol === '2') { // Docente
        secEstudiante.style.display = 'none';
        secDocente.style.display = 'block';
    } else {
        secEstudiante.style.display = 'none';
        secDocente.style.display = 'none';
    }
}

// Ejecutar al cargar para mantener estado si hubo recarga por validación
document.addEventListener('DOMContentLoaded', toggleCamposPerfil);
</script>

<?php require_once "../includes/footer.php"; ?>