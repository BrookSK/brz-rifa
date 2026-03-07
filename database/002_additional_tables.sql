-- Tabelas adicionais para complementar o sistema BRZ Rifa
-- Versão 1.0 - Estrutura complementar

USE brz_rifa;

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

-- Inserir templates de e-mail padrão
INSERT INTO email_templates (template_key, subject, html_content, text_content, variables) VALUES
('winner_notification', '🎉 Parabéns! Você foi o vencedor da rifa {{raffle_title}}', 
'<h2>🎉 Parabéns, {{participant_name}}!</h2>
<p>Você foi o vencedor da rifa <strong>{{raffle_title}}</strong> com o número <strong>{{winner_number}}</strong>!</p>
<h3>Detalhes do Prêmio:</h3>
<ul>
    <li><strong>Prêmio:</strong> {{prize_description}}</li>
    <li><strong>Valor estimado:</strong> R$ {{prize_value}}</li>
    <li><strong>Forma de entrega:</strong> {{delivery_method}}</li>
    <li><strong>Prazo de entrega:</strong> {{delivery_deadline}}</li>
</ul>
<h3>Próximos Passos:</h3>
<ol>
    <li>Entre em contato conosco para combinar a entrega</li>
    <li>Apresente um documento com foto e o CPF</li>
    <li>Receba seu prêmio!</li>
</ol>
<p><strong>Contato para entrega:</strong> contato@onsolutionsbrasil.com.br</p>
<hr>
<p><small>Hash de verificação do sorteio: {{draw_hash}}</small></p>
<p><small>Data do sorteio: {{draw_date}}</small></p>',
'Parabéns, {{participant_name}}! Você foi o vencedor da rifa {{raffle_title}} com o número {{winner_number}}.',
'{"raffle_title": "Título da Rifa", "participant_name": "Nome do Participante", "winner_number": "Número Vencedor", "prize_description": "Descrição do Prêmio", "prize_value": "Valor do Prêmio", "delivery_method": "Forma de Entrega", "delivery_deadline": "Prazo de Entrega", "draw_hash": "Hash do Sorteio", "draw_date": "Data do Sorteio"}'),

('payment_confirmation', '✅ Pagamento confirmado - Rifa {{raffle_title}}',
'<h2>Pagamento Confirmado!</h2>
<p>Seu pagamento para a rifa <strong>{{raffle_title}}</strong> foi confirmado.</p>
<p><strong>Números:</strong> {{numbers}}</p>
<p><strong>Valor:</strong> R$ {{amount}}</p>',
'Seu pagamento para a rifa {{raffle_title}} foi confirmado. Números: {{numbers}}. Valor: R$ {{amount}}.',
'{"raffle_title": "Título da Rifa", "numbers": "Números Comprados", "amount": "Valor Pago"}'),

('draw_result', '🎯 Resultado do sorteio - {{raffle_title}}',
'<h2>Resultado do Sorteio</h2>
<p>O sorteio da rifa <strong>{{raffle_title}}</strong> foi realizado.</p>
<p><strong>Número vencedor:</strong> {{winner_number}}</p>
<p><strong>Vencedor:</strong> {{winner_name}}</p>',
'O sorteio da rifa {{raffle_title}} foi realizado. Número vencedor: {{winner_number}}. Vencedor: {{winner_name}}.',
'{"raffle_title": "Título da Rifa", "winner_number": "Número Vencedor", "winner_name": "Nome do Vencedor"}'),

('raffle_reminder', '⏰ Não perca! Rifa {{raffle_title}} encerra em {{hours}} horas',
'<h2>Atenção!</h2>
<p>A rifa <strong>{{raffle_title}}</strong> encerra em <strong>{{hours}}</strong> horas.</p>
<p>Ainda há {{available_numbers}} números disponíveis!</p>',
'A rifa {{raffle_title}} encerra em {{hours}} horas. Ainda há {{available_numbers}} números disponíveis!',
'{"raffle_title": "Título da Rifa", "hours": "Horas Restantes", "available_numbers": "Números Disponíveis"}');

-- Criar índices adicionais para performance
CREATE INDEX idx_raffles_draw_datetime_status ON raffles(draw_datetime, status);
CREATE INDEX idx_raffle_numbers_status_cpf ON raffle_numbers(status, participant_cpf);
CREATE INDEX idx_transactions_status_created ON transactions(payment_status, created_at);
CREATE INDEX idx_participants_fraud_score_status ON participants(fraud_score, status);
CREATE INDEX idx_audit_logs_user_created ON audit_logs(user_id, created_at);

-- Criar view para métricas em tempo real
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

-- Criar procedures adicionais

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
        'Alertas Não Lidas' as metric,
        COUNT(*) as value,
        'system' as category
    FROM system_alerts 
    WHERE read_at IS NULL;
END //
DELIMITER ;

-- Criar evento para limpeza automática (MySQL 8.0+)
-- CREATE EVENT IF NOT EXISTS daily_cleanup
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP + INTERVAL 1 HOUR
-- DO CALL AutoCleanup();

COMMIT;
