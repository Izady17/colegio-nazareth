-- Migración: acceso de padres de familia + sistema de avisos ampliado

-- 1. Columnas para la contraseña de padres (solo aplica a estudiantes)
ALTER TABLE usuarios
  ADD COLUMN password_padre VARCHAR(255) NULL AFTER password,
  ADD COLUMN pin_padre_plano VARCHAR(10) NULL AFTER password_padre,
  ADD COLUMN pin_padre_entregado TINYINT(1) DEFAULT 0 AFTER pin_padre_plano;

-- 2. Avisos (creados por el administrador)
CREATE TABLE IF NOT EXISTS avisos (
    id_aviso INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    destinatario ENUM('docentes', 'estudiantes', 'todos') NOT NULL DEFAULT 'todos',
    alcance ENUM('general', 'especifico') NOT NULL DEFAULT 'general',
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_admin) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Paralelos destino de un aviso específico (solo se usa si alcance = 'especifico')
CREATE TABLE IF NOT EXISTS avisos_paralelos (
    id_aviso INT NOT NULL,
    id_paralelo INT NOT NULL,
    PRIMARY KEY (id_aviso, id_paralelo),
    FOREIGN KEY (id_aviso) REFERENCES avisos(id_aviso) ON DELETE CASCADE,
    FOREIGN KEY (id_paralelo) REFERENCES paralelos(id_paralelo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
