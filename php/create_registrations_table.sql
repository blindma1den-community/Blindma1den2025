-- Tabla para registros de Stripe (pagos del evento)
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temp_id VARCHAR(50) UNIQUE NOT NULL,
    stripe_session_id VARCHAR(255),
    stripe_payment_intent_id VARCHAR(255),

    -- Datos del participante
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(50), -- username_x del formulario
    pais VARCHAR(100) NOT NULL,

    -- Datos del evento
    modalidad ENUM('CTF', 'Hackathon') NOT NULL,
    team_name VARCHAR(100),
    team_members INT NOT NULL DEFAULT 1,

    -- Datos de pago
    amount_paid DECIMAL(10,2),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_temp_id (temp_id),
    INDEX idx_session_id (stripe_session_id),
    INDEX idx_payment_status (payment_status)
);

-- Para desarrollo: USE blindma1den;
-- Para producción: USE danioasy_blindma1den;