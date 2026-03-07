-- Dados iniciais para o Sistema BRZ Rifa

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
