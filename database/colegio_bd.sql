-- Crear base de datos
CREATE DATABASE IF NOT EXISTS colegio_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE colegio_bd;

-- 1. Tabla de Roles
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
);

INSERT INTO roles (id_rol, nombre) VALUES 
(1, 'administrador'),
(2, 'docente'),
(3, 'estudiante');

-- 2. Tabla de Usuarios
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    ci VARCHAR(15) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    estado TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE CASCADE
);

-- 3. Tabla de Cursos y Paralelos
CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    grado VARCHAR(30) NOT NULL, -- Ej: '1ro Secundaria', '6to Secundaria'
    paralelo CHAR(1) NOT NULL    -- Ej: 'A', 'B'
);

INSERT INTO cursos (grado, paralelo) VALUES 
('1ro Secundaria', 'A'),
('1ro Secundaria', 'B'),
('6to Secundaria', 'A');

-- 4. Asignación de Estudiantes a Cursos
CREATE TABLE estudiante_curso (
    id_estudiante INT PRIMARY KEY,
    id_curso INT NOT NULL,
    FOREIGN KEY (id_estudiante) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE
);

-- 5. Tabla de Materias / Áreas
CREATE TABLE materias (
    id_materia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    area VARCHAR(60) NOT NULL -- Ej: 'Matemáticas', 'Humanidades', 'Ciencias Exactas'
);

INSERT INTO materias (nombre, area) VALUES 
('Matemáticas', 'Ciencias Exactas'),
('Física', 'Ciencias Exactas'),
('Lenguaje y Literatura', 'Humanidades'),
('Psicología', 'Humanidades');

-- 6. Tabla de Horarios
CREATE TABLE horarios (
    id_horario INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    id_materia INT NOT NULL,
    dia VARCHAR(15) NOT NULL, -- Ej: 'Lunes', 'Martes'
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON DELETE CASCADE
);

-- 7. Tabla de Tareas (Publicadas por Docentes)
CREATE TABLE tareas (
    id_tarea INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_curso INT NOT NULL,
    id_materia INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_entrega DATE NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_docente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON DELETE CASCADE
);

-- 8. Tabla de Recordatorios / Avisos
CREATE TABLE recordatorios (
    id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_curso INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_docente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE
);

-- 9. Tabla de Consultas Psicopedagógicas (Gabinete Psicológico)
CREATE TABLE consultas_psicologicas (
    id_consulta INT AUTO_INCREMENT PRIMARY KEY,
    nombre_solicitante VARCHAR(100) NOT NULL,
    relacion VARCHAR(30) NOT NULL, -- 'Estudiante', 'Tutor / Padre de familia'
    telefono VARCHAR(20) NOT NULL,
    motivo TEXT NOT NULL,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'Pendiente' -- 'Pendiente', 'Atendido'
);

-- INSERCIÓN DE USUARIOS DE PRUEBA
-- Contraseña de prueba para todos: '123456' (encriptada con password_hash de PHP)
INSERT INTO usuarios (ci, nombres, apellidos, email, password, id_rol) VALUES
('1111111', 'Admin', 'Sistema', 'admin@jesusdenazareth.edu.bo', '$2y$10$ru2oM.ZqaQsmnKgVcO.lDO.NAM9leBqu8iS2P15jlX09kVVwaR7d2', 1),
('2222222', 'Carlos', 'Mendoza', 'cmendoza@jesusdenazareth.edu.bo', '$2y$10$ru2oM.ZqaQsmnKgVcO.lDO.NAM9leBqu8iS2P15jlX09kVVwaR7d2', 2),
('3333333', 'Jordy', 'Lopez', 'jlopez@jesusdenazareth.edu.bo', '$2y$10$ru2oM.ZqaQsmnKgVcO.lDO.NAM9leBqu8iS2P15jlX09kVVwaR7d2', 3);

-- Asignar al estudiante (ID: 3) al curso 6to Secundaria 'A' (ID: 3)
INSERT INTO estudiante_curso (id_estudiante, id_curso) VALUES (3, 3);
-- 1. Tabla para categorizar las áreas académicas del colegio
CREATE TABLE IF NOT EXISTS areas (
    id_area INT AUTO_INCREMENT PRIMARY KEY,
    nombre_area VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO areas (id_area, nombre_area) VALUES
(1, 'Humanidades y Ciencias Sociales'),
(2, 'Ciencias Exactas y Naturales'),
(3, 'Técnica y Tecnología'),
(4, 'Educación Física y Deportes');

-- 2. Perfil complementario para usuarios con rol de docente
CREATE TABLE IF NOT EXISTS docentes_perfil (
    id_docente INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    id_area INT NOT NULL,
    titulo_academico VARCHAR(150) NOT NULL,
    foto VARCHAR(255) DEFAULT 'default_docente.png',
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_area) REFERENCES areas(id_area) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla para el catálogo de logros institucionales y estudiantiles
CREATE TABLE IF NOT EXISTS logros (
    id_logro INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    categoria ENUM('Deportivo', 'Académico', 'Artístico') NOT NULL,
    fecha DATE NOT NULL,
    destacado TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DATOS DE PRUEBA: Logros institucionales
INSERT INTO logros (titulo, descripcion, categoria, fecha) VALUES
('1er Lugar en Olimpiadas de Matemática Oruro', 'Estudiantes de secundaria obtuvieron la medalla de oro en la fase departamental.', 'Académico', '2025-10-15'),
('Campeones Sub-17 de Fútbol Estudiantil', 'El equipo varonil se coronó campeón en el torneo intercolegial de la zona Sud Este.', 'Deportivo', '2025-11-20'),
('Premio Departamental de Danza Folklórica', 'El elenco de danza fue galardonado en el encuentro de talentos juveniles de Oruro.', 'Artístico', '2025-09-05');