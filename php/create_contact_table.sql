-- Script para crear tabla de contactos (sin IP tracking)
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Para la base de datos de desarrollo
-- USE blindma1den;

-- Para la base de datos de producción
-- USE danioasy_blindma1den;