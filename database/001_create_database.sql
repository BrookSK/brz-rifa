-- Banco de dados para Sistema BRZ Rifa
-- Versão 1.0 - Estrutura completa

-- Criar banco de dados se não existir
CREATE DATABASE IF NOT EXISTS brz_rifa 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar o banco de dados
USE brz_rifa;

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

-- Inserir usuário administrador padrão
INSERT INTO users (name, email, password_hash, profile) VALUES 
('Administrador BRZ Rifa', 'contato@onsolutionsbrasil.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Inserir políticas padrão do sistema
INSERT INTO system_policies (policy_key, policy_value, policy_type, description) VALUES
('min_numbers_per_raffle', '100', 'integer', 'Quantidade mínima de números por rifa'),
('max_numbers_per_raffle', '10000', 'integer', 'Quantidade máxima de números por rifa'),
('max_numbers_per_cpf', '10', 'integer', 'Limite de números por CPF por rifa'),
('reservation_timeout_minutes', '10', 'integer', 'Prazo de reserva em minutos'),
('minimum_wait_hours', '24', 'integer', 'Prazo mínimo em horas entre encerramento e sorteio'),
('max_raffle_duration_days', '30', 'integer', 'Duração máxima da rifa em dias'),
('min_number_price', '1.00', 'decimal', 'Preço mínimo por número'),
('max_number_price', '1000.00', 'decimal', 'Preço máximo por número'),
('fraud_detection_enabled', 'true', 'boolean', 'Ativar detecção de fraude'),
('max_reservations_per_hour', '3', 'integer', 'Máximo de reservas por hora por CPF'),
('audit_log_retention_days', '365', 'integer', 'Dias para retenção de logs de auditoria'),
('email_notifications_enabled', 'true', 'boolean', 'Ativar notificações por e-mail'),
('alert_threshold_sales_percent', '85', 'integer', 'Percentual de vendas para alerta automática'),
('timezone', 'America/Sao_Paulo', 'string', 'Fuso horário do sistema'),
('currency', 'BRL', 'string', 'Moeda padrão'),
('language', 'pt-BR', 'string', 'Idioma padrão'),
('debug_mode', 'false', 'boolean', 'Modo debug');

-- Criar views para consultas frequentes

-- View para estatísticas das rifas
CREATE OR REPLACE VIEW raffle_stats AS
SELECT 
    r.id,
    r.title,
    r.status,
    r.number_quantity,
    r.start_sales_datetime,
    r.end_sales_datetime,
    r.draw_datetime,
    COUNT(rn.id) as total_numbers,
    SUM(CASE WHEN rn.status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
    SUM(CASE WHEN rn.status = 'reserved' THEN 1 ELSE 0 END) as reserved_numbers,
    SUM(CASE WHEN rn.status = 'available' THEN 1 ELSE 0 END) as available_numbers,
    SUM(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE 0 END) as total_revenue,
    COUNT(DISTINCT rn.participant_cpf) as unique_participants,
    ROUND((SUM(CASE WHEN rn.status = 'paid' THEN 1 ELSE 0 END) / COUNT(rn.id)) * 100, 2) as sales_percentage,
    CASE 
        WHEN r.status = 'active' AND r.end_sales_datetime > NOW() THEN 
            TIMESTAMPDIFF(MINUTE, NOW(), r.end_sales_datetime)
        ELSE NULL 
    END as minutes_until_close
FROM raffles r
LEFT JOIN raffle_numbers rn ON r.id = rn.raffle_id
GROUP BY r.id, r.title, r.status, r.number_quantity, r.start_sales_datetime, r.end_sales_datetime, r.draw_datetime;

-- View para métricas em tempo real
CREATE OR REPLACE VIEW realtime_metrics AS
SELECT 
    'active_raffles' as metric_key,
    COUNT(*) as metric_value,
    NULL as raffle_id,
    NULL as additional_data,
    NOW() as recorded_at
FROM raffles 
WHERE status = 'active'

UNION ALL

SELECT 
    'total_participants_today' as metric_key,
    COUNT(DISTINCT participant_cpf) as metric_value,
    NULL as raffle_id,
    NULL as additional_data,
    NOW() as recorded_at
FROM raffle_numbers 
WHERE status = 'paid' AND DATE(created_at) = CURDATE()

UNION ALL

SELECT 
    'total_revenue_today' as metric_key,
    COALESCE(SUM(payment_amount), 0) as metric_value,
    NULL as raffle_id,
    NULL as additional_data,
    NOW() as recorded_at
FROM raffle_numbers 
WHERE status = 'paid' AND DATE(created_at) = CURDATE();

-- Criar procedures para operações automáticas

-- Procedure para expirar reservas
DELIMITER //
CREATE PROCEDURE ExpireReservations()
BEGIN
    UPDATE raffle_numbers 
    SET status = 'available',
        participant_name = NULL,
        participant_cpf = NULL,
        participant_email = NULL,
        participant_phone = NULL,
        participant_address = NULL,
        reservation_hash = NULL,
        reservation_expires_at = NULL,
        payment_id = NULL,
        payment_amount = NULL,
        user_id = NULL
    WHERE status = 'reserved' 
    AND reservation_expires_at < NOW()
    AND reservation_hash IS NOT NULL;
    
    SELECT ROW_COUNT() as expired_count;
END //
DELIMITER ;

-- Procedure para limpar logs antigos
DELIMITER //
CREATE PROCEDURE CleanOldLogs(IN days_to_keep INT)
BEGIN
    DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    SELECT ROW_COUNT() as cleaned_rows;
END //
DELIMITER ;

-- Criar triggers para auditoria

-- Trigger para log de alterações em rifas
DELIMITER //
CREATE TRIGGER raffles_audit_insert
AFTER INSERT ON raffles
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, table_name, record_id, new_data, ip_address, user_agent)
    VALUES ('CREATE_RAFFLE', 'raffles', NEW.id, JSON_OBJECT(
        'title', NEW.title,
        'status', NEW.status,
        'number_quantity', NEW.number_quantity,
        'number_price', NEW.number_price
    ), CONNECTION_ID(), USER());
END //
DELIMITER ;

-- Trigger para log de alterações em usuários
DELIMITER //
CREATE TRIGGER users_audit_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.profile != NEW.profile THEN
        INSERT INTO audit_logs (action, table_name, record_id, old_data, new_data, ip_address, user_agent)
        VALUES ('UPDATE_USER', 'users', NEW.id, JSON_OBJECT(
            'status', OLD.status,
            'profile', OLD.profile
        ), JSON_OBJECT(
            'status', NEW.status,
            'profile', NEW.profile
        ), CONNECTION_ID(), USER());
    END IF;
END //
DELIMITER ;

-- Criar função para validar CPF
DELIMITER //
CREATE FUNCTION IsValidCPF(cpf VARCHAR(11)) RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE digit1, digit2, sum1, sum2, remainder INT;
    DECLARE i INT DEFAULT 1;
    
    -- Remover caracteres não numéricos
    SET cpf = REGEXP_REPLACE(cpf, '[^0-9]', '');
    
    -- Verificar quantidade de dígitos
    IF LENGTH(cpf) != 11 THEN
        RETURN FALSE;
    END IF;
    
    -- Verificar se todos os dígitos são iguais
    IF cpf REGEXP '^([0-9])\\1{10}$' THEN
        RETURN FALSE;
    END IF;
    
    -- Calcular primeiro dígito verificador
    SET sum1 = 0;
    WHILE i <= 9 DO
        SET sum1 = sum1 + (SUBSTRING(cpf, i, 1) * (11 - i));
        SET i = i + 1;
    END WHILE;
    
    SET remainder = sum1 % 11;
    SET digit1 = IF(remainder < 2, 0, 11 - remainder);
    
    -- Calcular segundo dígito verificador
    SET i = 1;
    SET sum2 = 0;
    WHILE i <= 10 DO
        SET sum2 = sum2 + (SUBSTRING(cpf, i, 1) * (12 - i));
        SET i = i + 1;
    END WHILE;
    
    SET remainder = sum2 % 11;
    SET digit2 = IF(remainder < 2, 0, 11 - remainder);
    
    RETURN SUBSTRING(cpf, 10, 1) = digit1 AND SUBSTRING(cpf, 11, 1) = digit2;
END //
DELIMITER ;

COMMIT;
