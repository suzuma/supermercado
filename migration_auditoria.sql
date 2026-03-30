-- Migración: Registro de Auditoría
-- Tabla para rastrear acciones de usuarios en el sistema

CREATE TABLE auditoria (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    modulo       VARCHAR(50)  NOT NULL,
    accion       VARCHAR(50)  NOT NULL,
    descripcion  TEXT         NOT NULL,
    referencia_id INT UNSIGNED NULL,
    ip           VARCHAR(45)  NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_auditoria_modulo    ON auditoria(modulo);
CREATE INDEX idx_auditoria_usuario   ON auditoria(usuario_id);
CREATE INDEX idx_auditoria_created   ON auditoria(created_at);
