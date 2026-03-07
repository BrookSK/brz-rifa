-- Script para criar tabelas do Sistema BRZ Rifa
-- Banco de dados já deve existir

-- Tabela de políticas do sistema
CREATE TABLE IF NOT EXISTS system_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_key VARCHAR(100) NOT NULL UNIQUE,
    policy_value TEXT NOT NULL,
    policy_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') NOT NULL DEFAULT 'string',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_policy_key (policy_key)
) ENGINE=InnoDB;

-- Tabela de usuários internos
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile ENUM('admin', 'operator', 'auditor') NOT NULL DEFAULT 'operator',
    status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_profile (profile)
) ENGINE=InnoDB;

-- Tabela de logs de auditoria
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id VARCHAR(100) NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at),
    INDEX idx_record_id (record_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de sessões de usuários
CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de integrações
CREATE TABLE IF NOT EXISTS integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    integration_name VARCHAR(50) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    api_key VARCHAR(500) NULL,
    webhook_secret VARCHAR(500) NULL,
    config_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_integration_name (integration_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Tabela de rifas
CREATE TABLE IF NOT EXISTS raffles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    prize_description TEXT NOT NULL,
    prize_market_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    prize_images JSON NULL,
    number_price DECIMAL(10,2) NOT NULL,
    number_quantity INT NOT NULL,
    max_numbers_per_cpf INT NOT NULL DEFAULT 10,
    regulation TEXT NULL,
    start_sales_datetime TIMESTAMP NULL,
    end_sales_datetime TIMESTAMP NULL,
    draw_datetime TIMESTAMP NULL,
    minimum_wait_hours INT NOT NULL DEFAULT 24,
    delivery_method VARCHAR(100) NULL,
    delivery_deadline VARCHAR(255) NULL,
    status ENUM('draft', 'active', 'sales_closed', 'drawn', 'cancelled', 'finalized') NOT NULL DEFAULT 'draft',
    winner_number INT NULL,
    winner_cpf VARCHAR(11) NULL,
    winner_name VARCHAR(255) NULL,
    draw_hash VARCHAR(64) NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_end_sales_datetime (end_sales_datetime),
    INDEX idx_draw_datetime (draw_datetime),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Tabela de números das rifas
CREATE TABLE IF NOT EXISTS raffle_numbers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    raffle_id INT NOT NULL,
    number INT NOT NULL,
    status ENUM('available', 'reserved', 'paid', 'cancelled', 'blocked', 'winner') NOT NULL DEFAULT 'available',
    participant_name VARCHAR(255) NULL,
    participant_cpf VARCHAR(11) NULL,
    participant_email VARCHAR(255) NULL,
    participant_phone VARCHAR(20) NULL,
    participant_address TEXT NULL,
    reservation_hash VARCHAR(64) NULL,
    reservation_expires_at TIMESTAMP NULL,
    payment_id VARCHAR(100) NULL,
    payment_amount DECIMAL(10,2) NULL,
    paid_at TIMESTAMP NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_raffle_number (raffle_id, number),
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_status (status),
    INDEX idx_participant_cpf (participant_cpf),
    INDEX idx_reservation_hash (reservation_hash),
    INDEX idx_payment_id (payment_id),
    INDEX idx_reservation_expires_at (reservation_expires_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de participantes
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cpf VARCHAR(11) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    fraud_score INT NOT NULL DEFAULT 0,
    status ENUM('active', 'suspicious', 'suspended', 'blocked') NOT NULL DEFAULT 'active',
    suspension_reason TEXT NULL,
    block_reason TEXT NULL,
    suspended_at TIMESTAMP NULL,
    blocked_at TIMESTAMP NULL,
    total_purchases INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    last_purchase_at TIMESTAMP NULL,
    first_purchase_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cpf (cpf),
    INDEX idx_status (status),
    INDEX idx_fraud_score (fraud_score),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Tabela de transações
CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NULL,
    payment_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'confirmed', 'cancelled', 'expired', 'refunded') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL DEFAULT 'PIX',
    gateway_data JSON NULL,
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    webhook_received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_id (payment_id),
    INDEX idx_participant_id (participant_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de relação transações-números
CREATE TABLE IF NOT EXISTS transaction_numbers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT NOT NULL,
    raffle_number_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_transaction_number (transaction_id, raffle_number_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_raffle_number_id (raffle_number_id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (raffle_number_id) REFERENCES raffle_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de logs de webhook
CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    signature VARCHAR(500) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    processed BOOLEAN NOT NULL DEFAULT FALSE,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source),
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB;

-- Tabela de alertas do sistema
CREATE TABLE IF NOT EXISTS system_alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('info', 'warning', 'error', 'critical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    raffle_id INT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de logs de acesso
CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INT NOT NULL,
    response_time_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de verificações de integridade
CREATE TABLE IF NOT EXISTS integrity_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    raffle_id INT NOT NULL,
    check_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de logs de sorteios
CREATE TABLE IF NOT EXISTS draw_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    raffle_id INT NOT NULL,
    draw_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de notificações para participantes
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cpf VARCHAR(11) NOT NULL,
    type ENUM('winner', 'result', 'payment', 'reminder') NOT NULL,
    raffle_id INT NOT NULL,
    data JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cpf (cpf),
    INDEX idx_type (type),
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela de relatórios gerados
CREATE TABLE IF NOT EXISTS generated_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('raffle', 'financial', 'audit', 'participants') NOT NULL,
    raffle_id INT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_report_type (report_type),
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_generated_by (generated_by),
    INDEX idx_generated_at (generated_at),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabela de configurações de e-mail
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    html_content TEXT NOT NULL,
    text_content TEXT NULL,
    variables JSON NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_key (template_key),
    INDEX idx_active (active)
) ENGINE=InnoDB;

-- Tabela de tentativas de fraude
CREATE TABLE IF NOT EXISTS fraud_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NULL,
    cpf VARCHAR(11) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    attempt_type ENUM('multiple_cpf', 'rapid_reservations', 'invalid_cpf', 'suspicious_behavior') NOT NULL,
    risk_score INT NOT NULL DEFAULT 0,
    details JSON NULL,
    blocked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_participant_id (participant_id),
    INDEX idx_cpf (cpf),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempt_type (attempt_type),
    INDEX idx_risk_score (risk_score),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de métricas em tempo real
CREATE TABLE IF NOT EXISTS realtime_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    metric_key VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) NOT NULL,
    raffle_id INT NULL,
    additional_data JSON NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_key (metric_key),
    INDEX idx_raffle_id (raffle_id),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB;
