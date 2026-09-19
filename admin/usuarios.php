<?php
session_start();
$ruta_base = "../";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: " . $ruta_base . "auth/login.php");
    exit();
}

require_once "../includes/conexion.php";

$mensaje = "";

// Activar / Desactivar cuenta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $id = intval($_POST['id_usuario'] ?? 0);
    $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
    $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $mensaje = $nuevo_estado ? "Cuenta activada correctamente." : "Cuenta desactivada.";
}

// Reasignar paralelo de un estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reasignar_paralelo') {
    $id_estudiante = intval($_POST['id_usuario'] ?? 0);
    $id_paralelo = intval($_POST['id_paralelo'] ?? 0);
    if ($id_estudiante > 0 && $id_paralelo > 0) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO estudiante_paralelo (id_estudiante, id_paralelo) VALUES (?, ?)
                                            ON DUPLICATE KEY UPDATE id_paralelo = VALUES(id_paralelo)");
        mysqli_stmt_bind_param($stmt, "ii", $id_estudiante, $id_paralelo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Paralelo reasignado correctamente.";
    }
}

// Reasignar área de un docente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reasignar_area') {
    $id_docente = intval($_POST['id_usuario'] ?? 0);
    $id_area = intval($_POST['id_area'] ?? 0);
    if ($id_docente > 0 && $id_area > 0) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO docentes_perfil (id_usuario, id_area, titulo_academico) VALUES (?, ?, '')
                                            ON DUPLICATE KEY UPDATE id_area = VALUES(id_area)");
        mysqli_stmt_bind_param($stmt, "ii", $id_docente, $id_area);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $mensaje = "Área reasignada correctamente.";
    }
}

// Mapeo de conteos para las tarjetas superiores (KPIs)
$c_total = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios"))['total'];
$c_admin = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios WHERE id_rol = 1"))['total'];
$c_docente = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios WHERE id_rol = 2"))['total'];
$c_estudiante = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios WHERE id_rol = 3"))['total'];

// Filtro por rol
$filtro_rol = $_GET['rol'] ?? 'todos';
$where_rol = "";
if ($filtro_rol === 'docente') $where_rol = "WHERE u.id_rol = 2";
elseif ($filtro_rol === 'estudiante') $where_rol = "WHERE u.id_rol = 3";
elseif ($filtro_rol === 'administrador') $where_rol = "WHERE u.id_rol = 1";

$sql = "SELECT u.id_usuario, u.ci, u.nombres, u.apellidos, u.email, u.foto_perfil, u.estado, u.id_rol, r.nombre AS rol_nombre,
               p.id_paralelo, p.grado, p.letra, dp.id_area
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        LEFT JOIN estudiante_paralelo ep ON u.id_usuario = ep.id_estudiante
        LEFT JOIN paralelos p ON ep.id_paralelo = p.id_paralelo
        LEFT JOIN docentes_perfil dp ON u.id_usuario = dp.id_usuario
        $where_rol
        ORDER BY u.id_rol ASC, u.apellidos ASC";
$res = mysqli_query($conexion, $sql);
$usuarios = mysqli_fetch_all($res, MYSQLI_ASSOC);

// Datos para desplegables
$paralelos = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_paralelo, grado, letra FROM paralelos ORDER BY grado, letra"), MYSQLI_ASSOC);
$areas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_area, nombre_area FROM areas ORDER BY nombre_area"), MYSQLI_ASSOC);

require_once "../includes/header_panel.php";
?>

<style>
/* Estilos para igualar el diseño de la imagen de referencia */
.hero-banner-users {
  position: relative;
  background: linear-gradient(rgba(120, 20, 20, 0.85), rgba(120, 20, 20, 0.85)), 
              url('../assets/img/colegio_bg.jpg') center/cover no-repeat;
  color: white;
  padding: 35px 30px;
  border-radius: 12px;
  margin-bottom: 25px;
}
.hero-banner-users span.sub-tag {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  opacity: 0.8;
  display: block;
  margin-bottom: 5px;
}
.hero-banner-users h2 {
  font-size: 2rem;
  margin: 0 0 8px 0;
  font-weight: 700;
}
.hero-banner-users p {
  margin: 0;
  opacity: 0.9;
  font-size: 0.95rem;
}

/* Tarjetas Estadísticas KPI */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}
.kpi-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  border: 1px solid #f0f0f0;
}
.kpi-icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: white;
}
.kpi-icon.red { background-color: #d32f2f; }
.kpi-icon.blue { background-color: #1976d2; }
.kpi-icon.green { background-color: #2e7d32; }
.kpi-icon.purple { background-color: #7b1fa2; }

.kpi-info h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: #222; }
.kpi-info span { font-size: 0.82rem; color: #666; }

/* Barra de Filtros, Búsqueda y Botón */
.top-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.tab-filters {
  display: flex;
  gap: 6px;
  background: #f5f5f5;
  padding: 4px;
  border-radius: 8px;
}
.tab-btn {
  text-decoration: none;
  padding: 8px 16px;
  font-size: 0.85rem;
  color: #555;
  border-radius: 6px;
  font-weight: 500;
  transition: all 0.2s;
}
.tab-btn.active {
  background: #a82424;
  color: white;
}
.search-add-box {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  justify-content: flex-end;
}
.search-input-wrapper {
  position: relative;
  min-width: 260px;
}
.search-input-wrapper i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #888;
  font-size: 0.9rem;
}
.search-input-wrapper input {
  width: 100%;
  padding: 8px 12px 8px 34px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 0.88rem;
  outline: none;
}
.btn-add-user {
  background: #a82424;
  color: white;
  padding: 9px 16px;
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

/* Tabla Estilizada */
.table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  border: 1px solid #f0f0f0;
  overflow: hidden;
}
.custom-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.custom-table th {
  padding: 14px 16px;
  background: #fafafa;
  color: #555;
  font-size: 0.82rem;
  font-weight: 700;
  border-bottom: 1px solid #eee;
}
.custom-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f5f5f5;
  vertical-align: middle;
  font-size: 0.88rem;
}
.user-profile-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}
.avatar-img {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  background: #eee;
}
.user-info-text strong {
  display: block;
  color: #222;
  font-size: 0.9rem;
}
.user-info-text span {
  font-size: 0.78rem;
  color: #888;
}

/* Badges de Roles y Estado */
.role-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.role-admin { background: #fee2e2; color: #991b1b; }
.role-docente { background: #e0f2fe; color: #0369a1; }
.role-estudiante { background: #f3e8ff; color: #6b21a8; }

.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
}
.status-active { background: #dcfce7; color: #15803d; }
.status-inactive { background: #fee2e2; color: #991b1b; }

/* Botones de acción limpia */
.btn-icon-action {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 6px;
  font-size: 1rem;
  color: #666;
  transition: color 0.2s;
}
.btn-icon-action.edit:hover { color: #0284c7; }
.btn-icon-action.delete:hover { color: #dc2626; }

/* Controles de paginación */
.table-footer {
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.82rem;
  color: #666;
  border-top: 1px solid #f0f0f0;
}
.pagination {
  display: flex;
  gap: 5px;
}
.page-btn {
  padding: 4px 10px;
  border: 1px solid #e0e0e0;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.8rem;
}
.page-btn.active {
  background: #a82424;
  color: white;
  border-color: #a82424;
}
</style>

<!-- Banner Superior -->
<div class="hero-banner-users">
    <span class="sub-tag">ADMINISTRACIÓN</span>
    <h2>Gestión de Usuarios</h2>
    <p>Administra los usuarios del sistema y asigna sus roles y permisos de acceso.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="background-color: #d1e7dd; color: #0f5132; padding:12px; border-radius:8px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<!-- Tarjetas KPI -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon red"><i class="fa-solid fa-users"></i></div>
        <div class="kpi-info">
            <h3><?php echo $c_total; ?></h3>
            <span>Usuarios registrados</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon blue"><i class="fa-solid fa-user-gear"></i></div>
        <div class="kpi-info">
            <h3><?php echo $c_admin; ?></h3>
            <span>Administradores</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon green"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="kpi-info">
            <h3><?php echo $c_docente; ?></h3>
            <span>Docentes</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon purple"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="kpi-info">
            <h3><?php echo $c_estudiante; ?></h3>
            <span>Estudiantes</span>
        </div>
    </div>
</div>

<!-- Barra Superior con Filtros, Buscador y Botón Agregar -->
<div class="top-actions-bar">
    <div class="tab-filters">
        <a href="usuarios.php?rol=todos" class="tab-btn <?php echo $filtro_rol==='todos'?'active':''; ?>">Todos</a>
        <a href="usuarios.php?rol=administrador" class="tab-btn <?php echo $filtro_rol==='administrador'?'active':''; ?>">Administradores</a>
        <a href="usuarios.php?rol=docente" class="tab-btn <?php echo $filtro_rol==='docente'?'active':''; ?>">Docentes</a>
        <a href="usuarios.php?rol=estudiante" class="tab-btn <?php echo $filtro_rol==='estudiante'?'active':''; ?>">Estudiantes</a>
    </div>

    <div class="search-add-box">
        <div class="search-input-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="userInputSearch" placeholder="Buscar por nombre, CI o rol...">
        </div>
        <a href="crear_usuario.php" class="btn-add-user">
            <i class="fa-solid fa-user-plus"></i> Agregar Usuario
        </a>
    </div>
</div>

<!-- Tabla Principal de Usuarios -->
<div class="table-container">
    <div class="table-responsive">
        <table class="custom-table" id="usersTable">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                    <th>Nombre</th>
                    <th>CI</th>
                    <th>Rol</th>
                    <th>Asignación</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): 
                    $foto = !empty($u['foto_perfil']) ? "../" . $u['foto_perfil'] : "../assets/img/default-avatar.png";
                    $email = !empty($u['email']) ? $u['email'] : strtolower(str_replace(' ', '.', $u['nombres'])) . "@jesusdenazareth.edu.bo";
                ?>
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="user-select"></td>
                        <td>
                            <div class="user-profile-cell">
                                <img src="<?php echo htmlspecialchars($foto); ?>" class="avatar-img" alt="Avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u['nombres'].' '.$u['apellidos']); ?>&background=random';">
                                <div class="user-info-text">
                                    <strong><?php echo htmlspecialchars($u['apellidos'] . " " . $u['nombres']); ?></strong>
                                    <span><?php echo htmlspecialchars($email); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($u['ci']); ?></td>
                        <td>
                            <?php if ($u['id_rol'] == 1): ?>
                                <span class="role-badge role-admin"><i class="fa-solid fa-user-shield"></i> Administrador</span>
                            <?php elseif ($u['id_rol'] == 2): ?>
                                <span class="role-badge role-docente"><i class="fa-solid fa-chalkboard-user"></i> Docente</span>
                            <?php else: ?>
                                <span class="role-badge role-estudiante"><i class="fa-solid fa-graduation-cap"></i> Estudiante</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['id_rol'] == 3): ?>
                                <form action="usuarios.php?rol=<?php echo $filtro_rol; ?>" method="POST" style="display:flex; gap:5px; align-items:center;">
                                    <input type="hidden" name="accion" value="reasignar_paralelo">
                                    <input type="hidden" name="id_usuario" value="<?php echo $u['id_usuario']; ?>">
                                    <select name="id_paralelo" style="padding:5px 8px; border-radius:6px; border:1px solid #ccc; font-size:0.82rem;">
                                        <?php foreach ($paralelos as $p): ?>
                                            <option value="<?php echo $p['id_paralelo']; ?>" <?php echo ($u['id_paralelo'] == $p['id_paralelo']) ? 'selected' : ''; ?>>
                                                <?php echo $p['grado']; ?>° "<?php echo $p['letra']; ?>"
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" style="background:#0d6efd; color:white; border:none; padding:5px 10px; border-radius:6px; cursor:pointer; font-size:0.78rem;">Guardar</button>
                                </form>
                            <?php elseif ($u['id_rol'] == 2): ?>
                                <form action="usuarios.php?rol=<?php echo $filtro_rol; ?>" method="POST" style="display:flex; gap:5px; align-items:center;">
                                    <input type="hidden" name="accion" value="reasignar_area">
                                    <input type="hidden" name="id_usuario" value="<?php echo $u['id_usuario']; ?>">
                                    <select name="id_area" style="padding:5px 8px; border-radius:6px; border:1px solid #ccc; font-size:0.82rem;">
                                        <?php foreach ($areas as $a): ?>
                                            <option value="<?php echo $a['id_area']; ?>" <?php echo ($u['id_area'] == $a['id_area']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($a['nombre_area']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" style="background:#0d6efd; color:white; border:none; padding:5px 10px; border-radius:6px; cursor:pointer; font-size:0.78rem;">Guardar</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($u['estado']): ?>
                                <span class="status-badge status-active">Activo</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display:flex; justify-content:center; gap:6px;">
                                <form action="usuarios.php?rol=<?php echo $filtro_rol; ?>" method="POST" onsubmit="return confirm('¿Confirmas cambiar el estado de este usuario?');">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id_usuario" value="<?php echo $u['id_usuario']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $u['estado'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn-icon-action delete" title="<?php echo $u['estado'] ? 'Desactivar' : 'Activar'; ?>">
                                        <i class="fa-solid <?php echo $u['estado'] ? 'fa-trash-can' : 'fa-user-check'; ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación Inferior -->
    <div class="table-footer">
        <div>Mostrando 1 a <?php echo count($usuarios); ?> de <?php echo count($usuarios); ?> usuarios</div>
        <div class="pagination">
            <button class="page-btn">&lt;</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">&gt;</button>
        </div>
    </div>
</div>

<script>
// Filtro de búsqueda en vivo con Javascript
document.getElementById('userInputSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#usersTable tbody tr');

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Seleccionar todos los checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    let checkboxes = document.querySelectorAll('.user-select');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once "../includes/footer_panel.php"; ?>
