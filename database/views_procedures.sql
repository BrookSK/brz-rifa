-- Views, Procedures e Functions para o Sistema BRZ Rifa

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
CREATE OR REPLACE VIEW realtime_metrics_view AS
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

-- View para métricas em tempo real de rifas
CREATE OR REPLACE VIEW realtime_raffle_metrics AS
SELECT 
    r.id as raffle_id,
    r.title,
    r.status,
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
GROUP BY r.id, r.title, r.status, r.end_sales_datetime;

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

-- Procedure para limpeza automática
DELIMITER //
CREATE PROCEDURE AutoCleanup()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE retention_days INT;
    
    -- Obter configuração de retenção
    SELECT CAST(policy_value AS UNSIGNED) INTO retention_days 
    FROM system_policies WHERE policy_key = 'audit_log_retention_days';
    
    IF retention_days IS NULL THEN
        SET retention_days = 365;
    END IF;
    
    -- Limpar logs antigos
    DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY);
    
    -- Limpar logs de webhook (30 dias)
    DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Limpar notificações lidas (90 dias)
    DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Limpar relatórios expirados
    DELETE FROM generated_reports WHERE expires_at < NOW();
    
    SELECT ROW_COUNT() as cleaned_rows;
END //
DELIMITER ;

-- Procedure para verificar integridade de sorteio
DELIMITER //
CREATE PROCEDURE VerifyDrawIntegrity(IN raffle_id_param INT)
BEGIN
    DECLARE draw_hash VARCHAR(64);
    DECLARE winner_number INT;
    DECLARE paid_count INT;
    
    -- Obter dados do sorteio
    SELECT draw_hash, winner_number INTO draw_hash, winner_number
    FROM raffles 
    WHERE id = raffle_id_param AND status = 'drawn';
    
    IF draw_hash IS NULL THEN
        SELECT 'Sorteio não encontrado ou não realizado' as result;
    ELSE
        -- Contar números pagos
        SELECT COUNT(*) INTO paid_count
        FROM raffle_numbers 
        WHERE raffle_id = raffle_id_param AND status = 'paid';
        
        SELECT CONCAT('Sorteio verificado - Hash: ', draw_hash, ', Vencedor: ', winner_number, ', Pagos: ', paid_count) as result;
    END IF;
END //
DELIMITER ;

-- Procedure para gerar estatísticas do sistema
DELIMITER //
CREATE PROCEDURE GetSystemStatistics()
BEGIN
    SELECT 
        'Total de Rifas' as metric,
        COUNT(*) as value,
        'raffles' as category
    FROM raffles
    
    UNION ALL
    
    SELECT 
        'Rifas Ativas' as metric,
        COUNT(*) as value,
        'raffles' as category
    FROM raffles 
    WHERE status = 'active'
    
    UNION ALL
    
    SELECT 
        'Total de Participantes' as metric,
        COUNT(*) as value,
        'participants' as category
    FROM participants
    
    UNION ALL
    
    SELECT 
        'Receita Total' as metric,
        COALESCE(SUM(payment_amount), 0) as value,
        'financial' as category
    FROM raffle_numbers 
    WHERE status = 'paid'
    
    UNION ALL
    
    SELECT 
        'Alertas Não Lidos' as metric,
        COUNT(*) as value,
        'system' as category
    FROM system_alerts 
    WHERE read_at IS NULL;
END //
DELIMITER ;

-- Criar functions

-- Function para validar CPF
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

-- Function para calcular score de fraude
DELIMITER //
CREATE FUNCTION CalculateFraudScore(cpf_param VARCHAR(11), ip_param VARCHAR(45)) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE score INT DEFAULT 0;
    DECLARE rapid_reservations INT DEFAULT 0;
    DECLARE multiple_attempts INT DEFAULT 0;
    
    -- Verificar reservas rápidas
    SELECT COUNT(*) INTO rapid_reservations
    FROM raffle_numbers 
    WHERE participant_cpf = cpf_param 
    AND status = 'reserved'
    AND reservation_expires_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    IF rapid_reservations > 3 THEN
        SET score = score + (rapid_reservations * 10);
    END IF;
    
    -- Verificar múltiplas tentativas recentes
    SELECT COUNT(*) INTO multiple_attempts
    FROM fraud_attempts 
    WHERE cpf = cpf_param OR ip_address = ip_param
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    IF multiple_attempts > 0 THEN
        SET score = score + (multiple_attempts * 15);
    END IF;
    
    -- Limitar score máximo
    IF score > 100 THEN
        SET score = 100;
    END IF;
    
    RETURN score;
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

-- Trigger para registrar métricas em tempo real
DELIMITER //
CREATE TRIGGER log_realtime_metrics
AFTER INSERT ON raffle_numbers
FOR EACH ROW
BEGIN
    -- Registrar métrica de venda
    IF NEW.status = 'paid' THEN
        INSERT INTO realtime_metrics (metric_key, metric_value, raffle_id)
        VALUES ('sale', NEW.payment_amount, NEW.raffle_id);
    END IF;
    
    -- Registrar métrica de reserva
    IF NEW.status = 'reserved' THEN
        INSERT INTO realtime_metrics (metric_key, metric_value, raffle_id)
        VALUES ('reservation', 1, NEW.raffle_id);
    END IF;
END //
DELIMITER ;

-- Criar índices adicionais para performance
CREATE INDEX idx_raffles_draw_datetime_status ON raffles(draw_datetime, status);
CREATE INDEX idx_raffle_numbers_status_cpf ON raffle_numbers(status, participant_cpf);
CREATE INDEX idx_transactions_status_created ON transactions(payment_status, created_at);
CREATE INDEX idx_participants_fraud_score_status ON participants(fraud_score, status);
CREATE INDEX idx_audit_logs_user_created ON audit_logs(user_id, created_at);
