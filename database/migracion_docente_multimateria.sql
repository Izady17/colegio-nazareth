-- Permite que un docente esté registrado en más de una materia (ej. Física y Química)
CREATE TABLE IF NOT EXISTS docente_materias (
    id_usuario INT NOT NULL,
    id_area INT NOT NULL,
    PRIMARY KEY (id_usuario, id_area),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_area) REFERENCES areas(id_area) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
