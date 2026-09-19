<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

// Estadísticas reales
$total_estudiantes = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuarios WHERE id_rol = 3 AND estado = 1"))['n'] ?? 0;
$total_docentes = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuarios WHERE id_rol = 2 AND estado = 1"))['n'] ?? 0;
$total_cursos = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM paralelos"))['n'] ?? 0;
$total_usuarios = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuarios"))['n'] ?? 0;

// Solicitudes de atención psicopedagógica pendientes (solo lectura por ahora)
$consultas = [];
$res_consultas = @mysqli_query($conexion, "SELECT * FROM consultas_psicologicas ORDER BY 1 DESC LIMIT 10");
if ($res_consultas) {
    $consultas = mysqli_fetch_all($res_consultas, MYSQLI_ASSOC);
}

require_once "../includes/header_panel.php";
?>

<style>
  :root {
    --bg-main: #f3f6fc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
    --blue-primary: #1d4ed8;
    --green-primary: #059669;
    --purple-primary: #7c3aed;
    --orange-primary: #ea580c;
    --pink-primary: #db2777;
  }

  /* Banner de Bienvenida */
  .welcome-banner {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #e0e7ff 100%);
    border-radius: 16px;
    padding: 24px 30px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
  }

  .welcome-content h2 {
    margin: 0 0 6px 0;
    color: #0f172a;
    font-size: 1.6rem;
    font-weight: 700;
  }

  .welcome-content p {
    margin: 0 0 16px 0;
    color: #475569;
    font-size: 0.95rem;
  }

  .welcome-tags {
    display: flex;
    gap: 12px;
  }

  .tag-pill {
    background: #ffffff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.82rem;
    color: #334155;
    font-weight: 500;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Grid de Quicklinks (5 tarjetas superiores) */
  .grid-quicklinks {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 18px;
    margin-bottom: 25px;
  }

  .card-quicklink {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid var(--border-color);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .card-quicklink:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
  }

  .ql-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    margin-bottom: 14px;
  }

  .card-quicklink h3 {
    font-size: 1.02rem;
    margin: 0 0 6px 0;
    color: var(--text-dark);
  }

  .card-quicklink p {
    font-size: 0.83rem;
    color: var(--text-muted);
    margin: 0 0 16px 0;
    line-height: 1.35;
  }

  .btn-ql {
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
    text-align: center;
    text-decoration: none;
    display: block;
    box-sizing: border-box;
    transition: opacity 0.2s;
  }

  .btn-ql:hover {
    opacity: 0.9;
  }

  /* Secciones Inferiores Grid (3 Columnas) */
  .dashboard-columns {
    display: grid;
    grid-template-columns: 1fr 1fr 1.2fr;
    gap: 20px;
    margin-bottom: 25px;
  }

  @media (max-width: 1024px) {
    .dashboard-columns {
      grid-template-columns: 1fr;
    }
  }

  .section-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
  }

  .section-header h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-header a {
    font-size: 0.78rem;
    color: var(--blue-primary);
    text-decoration: none;
    font-weight: 600;
  }

  /* Resumen General Stats */
  .grid-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .stat-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 14px;
    text-align: center;
    border: 1px solid #f1f5f9;
  }

  .stat-box .icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
  }

  .stat-box .number {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 2px 0;
  }

  .stat-box .label {
    font-size: 0.78rem;
    color: var(--text-muted);
  }

  /* Accesos Rápido Grid */
  .grid-shortcuts {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
  }

  .shortcut-btn {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    text-decoration: none;
    color: var(--text-dark);
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
  }

  .shortcut-btn:hover {
    background: #f1f5f9;
  }

  /* Solicitudes y Actividades */
  .activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.82rem;
  }

  .activity-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #eff6ff;
    color: var(--blue-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
  }

  .activity-details {
    flex-grow: 1;
  }

  .activity-title {
    font-weight: 600;
    color: var(--text-dark);
  }

  .activity-sub {
    color: var(--text-muted);
    font-size: 0.75rem;
  }

  /* Banner Inferior Sugerencia */
  .suggestion-banner {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .suggestion-text {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #1e40af;
    font-size: 0.88rem;
  }

  .btn-outline-blue {
    background: #ffffff;
    border: 1px solid #bfdbfe;
    color: var(--blue-primary);
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
  }
</style>

<!-- Banner de Bienvenida -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>¡Hola, <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Administrador'); ?>!</h2>
        <p>Gestiona y administra todas las áreas del establecimiento de manera rápida y segura.</p>
        <div class="welcome-tags">
            <span class="tag-pill">📅 Hoy es <?php echo date('d/m/Y'); ?></span>
            <span class="tag-pill">🏫 Gestión: 2026</span>
        </div>
    </div>
</div>

<!-- Tarjetas de Acceso Principal (Fila de 5) -->
<div class="grid-quicklinks">
    <div class="card-quicklink">
        <div>
            <div class="ql-icon-box" style="background: var(--blue-primary);">📅</div>
            <h3>Horarios Académicos</h3>
            <p>Gestiona los horarios, periodos y materias.</p>
        </div>
        <a href="horarios.php" class="btn-ql" style="background: var(--blue-primary);">Gestionar Horarios →</a>
    </div>

    <div class="card-quicklink">
        <div>
            <div class="ql-icon-box" style="background: var(--green-primary);">✅</div>
            <h3>Control de Asistencia</h3>
            <p>Revisa la asistencia de estudiantes y docentes.</p>
        </div>
        <a href="asistencia.php" class="btn-ql" style="background: var(--green-primary);">Ver Reportes →</a>
    </div>

    <div class="card-quicklink">
        <div>
            <div class="ql-icon-box" style="background: var(--purple-primary);">👥</div>
            <h3>Gestión de Usuarios</h3>
            <p>Activa, desactiva y administra usuarios del sistema.</p>
        </div>
        <a href="usuarios.php" class="btn-ql" style="background: var(--purple-primary);">Administrar Usuarios →</a>
    </div>

    <div class="card-quicklink">
        <div>
            <div class="ql-icon-box" style="background: var(--orange-primary);">📢</div>
            <h3>Avisos Institucionales</h3>
            <p>Publica comunicados y avisos importantes.</p>
        </div>
        <a href="avisos.php" class="btn-ql" style="background: var(--orange-primary);">Crear Aviso →</a>
    </div>

    <div class="card-quicklink">
        <div>
            <div class="ql-icon-box" style="background: var(--pink-primary);">🔑</div>
            <h3>PINs de Padres</h3>
            <p>Consulta y gestiona los datos de los padres de familia.</p>
        </div>
        <a href="pines_padres.php" class="btn-ql" style="background: var(--pink-primary);">Ver PINs →</a>
    </div>
</div>

<!-- Bloque de 3 Columnas: Resumen General, Accesos Rápido y Solicitudes -->
<div class="dashboard-columns">
    <!-- Resumen General -->
    <div class="section-card">
        <div class="section-header">
            <h3>⚽ Resumen General</h3>
            <a href="usuarios.php">Ver más →</a>
        </div>
        <div class="grid-stats">
            <div class="stat-box">
                <div class="icon">🎓</div>
                <div class="number" style="color: var(--blue-primary);"><?php echo $total_estudiantes; ?></div>
                <div class="label">Estudiantes</div>
            </div>
            <div class="stat-box">
                <div class="icon">👨‍🏫</div>
                <div class="number" style="color: var(--green-primary);"><?php echo $total_docentes; ?></div>
                <div class="label">Docentes</div>
            </div>
            <div class="stat-box">
                <div class="icon">📚</div>
                <div class="number" style="color: var(--purple-primary);"><?php echo $total_cursos; ?></div>
                <div class="label">Cursos</div>
            </div>
            <div class="stat-box">
                <div class="icon">👥</div>
                <div class="number" style="color: var(--orange-primary);"><?php echo $total_usuarios; ?></div>
                <div class="label">Usuarios</div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="section-card">
        <div class="section-header">
            <h3>⚡ Accesos Rápidos</h3>
        </div>
        <div class="grid-shortcuts">
            <a href="horarios.php" class="shortcut-btn">
                <span>📅</span>
                <span>Crear Horario</span>
            </a>
            <a href="asistencia.php" class="shortcut-btn">
                <span>📋</span>
                <span>Registrar Asistencia</span>
            </a>
            <a href="usuarios.php" class="shortcut-btn">
                <span>👤</span>
                <span>Gestionar Usuarios</span>
            </a>
            <a href="reportes.php" class="shortcut-btn">
                <span>📊</span>
                <span>Generar Reporte</span>
            </a>
            <a href="avisos.php" class="shortcut-btn">
                <span>📢</span>
                <span>Publicar Aviso</span>
            </a>
            <a href="configuracion.php" class="shortcut-btn">
                <span>⚙️</span>
                <span>Configuración</span>
            </a>
        </div>
    </div>

    <!-- Solicitudes Psicopedagógicas Recientes -->
    <div class="section-card">
        <div class="section-header">
            <h3>🧠 Atención Psicopedagógica</h3>
            <span style="font-size: 0.75rem; color: var(--text-muted);">(Últimas 10)</span>
        </div>
        <div class="activity-list">
            <?php if (!empty($consultas)): ?>
                <?php foreach (array_slice($consultas, 0, 5) as $c): ?>
                    <div class="activity-item">
                        <div class="activity-icon">🧠</div>
                        <div class="activity-details">
                            <div class="activity-title"><?php echo htmlspecialchars($c['motivo'] ?? $c['estudiante'] ?? 'Solicitud recibida'); ?></div>
                            <div class="activity-sub"><?php echo htmlspecialchars($c['fecha'] ?? 'Reciente'); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 10px 0;">
                    No hay solicitudes registradas.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Banner Inferior de Sugerencia -->
<div class="suggestion-banner">
    <div class="suggestion-text">
        <span style="font-size: 1.2rem;">💡</span>
        <div>
            <strong>Sugerencia del sistema:</strong>
            <span style="color: #3b82f6;">Mantén la información de los docentes y cursos siempre actualizada para un mejor funcionamiento del portal.</span>
        </div>
    </div>
    <a href="configuracion.php" class="btn-outline-blue">Ir a Configuración →</a>
</div>

<?php require_once "../includes/footer_panel.php"; ?>