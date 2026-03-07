<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas do Sistema - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Políticas do Sistema</h1>
                <p>Configure as regras globais para todas as rifas</p>
            </header>
            
            <div class="content-section">
                <!-- Formulário de Políticas -->
                <form id="policies-form" class="policy-form">
                    <div class="policy-grid">
                        <!-- Configurações de Rifas -->
                        <div class="policy-section">
                            <h3>Configurações de Rifas</h3>
                            
                            <div class="form-group">
                                <label for="min_numbers_per_raffle">Quantidade Mínima de Números</label>
                                <input type="number" id="min_numbers_per_raffle" name="policies[min_numbers_per_raffle]" 
                                       class="form-control" value="<?= $policies['min_numbers_per_raffle']['policy_value'] ?? 100 ?>" 
                                       min="10" max="10000" required>
                                <small class="form-text">Número mínimo de números que uma rifa pode ter</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_numbers_per_raffle">Quantidade Máxima de Números</label>
                                <input type="number" id="max_numbers_per_raffle" name="policies[max_numbers_per_raffle]" 
                                       class="form-control" value="<?= $policies['max_numbers_per_raffle']['policy_value'] ?? 10000 ?>" 
                                       min="100" max="100000" required>
                                <small class="form-text">Número máximo de números que uma rifa pode ter</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_numbers_per_cpf">Limite de Números por CPF</label>
                                <input type="number" id="max_numbers_per_cpf" name="policies[max_numbers_per_cpf]" 
                                       class="form-control" value="<?= $policies['max_numbers_per_cpf']['policy_value'] ?? 10 ?>" 
                                       min="1" max="100" required>
                                <small class="form-text">Máximo de números que um CPF pode comprar por rifa</small>
                            </div>
                        </div>
                        
                        <!-- Configurações de Tempo -->
                        <div class="policy-section">
                            <h3>Configurações de Tempo</h3>
                            
                            <div class="form-group">
                                <label for="reservation_timeout_minutes">Prazo de Reserva (minutos)</label>
                                <input type="number" id="reservation_timeout_minutes" name="policies[reservation_timeout_minutes]" 
                                       class="form-control" value="<?= $policies['reservation_timeout_minutes']['policy_value'] ?? 10 ?>" 
                                       min="5" max="60" required>
                                <small class="form-text">Tempo que um número fica reservado aguardando pagamento</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="minimum_wait_hours">Prazo Mínimo para Sorteio (horas)</label>
                                <input type="number" id="minimum_wait_hours" name="policies[minimum_wait_hours]" 
                                       class="form-control" value="<?= $policies['minimum_wait_hours']['policy_value'] ?? 24 ?>" 
                                       min="1" max="168" required>
                                <small class="form-text">Tempo mínimo entre encerramento e sorteio</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_raffle_duration_days">Duração Máxima da Rifa (dias)</label>
                                <input type="number" id="max_raffle_duration_days" name="policies[max_raffle_duration_days]" 
                                       class="form-control" value="<?= $policies['max_raffle_duration_days']['policy_value'] ?? 30 ?>" 
                                       min="1" max="365" required>
                                <small class="form-text">Tempo máximo que uma rifa pode ficar ativa</small>
                            </div>
                        </div>
                        
                        <!-- Configurações de Preço -->
                        <div class="policy-section">
                            <h3>Configurações de Preço</h3>
                            
                            <div class="form-group">
                                <label for="min_number_price">Preço Mínimo por Número (R$)</label>
                                <input type="number" id="min_number_price" name="policies[min_number_price]" 
                                       class="form-control" value="<?= $policies['min_number_price']['policy_value'] ?? 1.00 ?>" 
                                       min="0.01" max="1000" step="0.01" required>
                                <small class="form-text">Valor mínimo que pode ser cobrado por número</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_number_price">Preço Máximo por Número (R$)</label>
                                <input type="number" id="max_number_price" name="policies[max_number_price]" 
                                       class="form-control" value="<?= $policies['max_number_price']['policy_value'] ?? 1000.00 ?>" 
                                       min="1" max="10000" step="0.01" required>
                                <small class="form-text">Valor máximo que pode ser cobrado por número</small>
                            </div>
                        </div>
                        
                        <!-- Configurações de Segurança -->
                        <div class="policy-section">
                            <h3>Configurações de Segurança</h3>
                            
                            <div class="form-group">
                                <label for="fraud_detection_enabled">Detecção de Fraude</label>
                                <select id="fraud_detection_enabled" name="policies[fraud_detection_enabled]" class="form-control">
                                    <option value="true" <?= ($policies['fraud_detection_enabled']['policy_value'] ?? 'true') === 'true' ? 'selected' : '' ?>>Ativada</option>
                                    <option value="false" <?= ($policies['fraud_detection_enabled']['policy_value'] ?? 'true') === 'false' ? 'selected' : '' ?>>Desativada</option>
                                </select>
                                <small class="form-text">Ativar detecção automática de comportamento suspeito</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_reservations_per_hour">Máximo de Reservas por Hora (por CPF)</label>
                                <input type="number" id="max_reservations_per_hour" name="policies[max_reservations_per_hour]" 
                                       class="form-control" value="<?= $policies['max_reservations_per_hour']['policy_value'] ?? 3 ?>" 
                                       min="1" max="20" required>
                                <small class="form-text">Limite de tentativas de reserva por CPF em uma hora</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="audit_log_retention_days">Retenção de Logs (dias)</label>
                                <input type="number" id="audit_log_retention_days" name="policies[audit_log_retention_days]" 
                                       class="form-control" value="<?= $policies['audit_log_retention_days']['policy_value'] ?? 365 ?>" 
                                       min="30" max="2555" required>
                                <small class="form-text">Tempo que os logs de auditoria são mantidos</small>
                            </div>
                        </div>
                        
                        <!-- Configurações de Notificação -->
                        <div class="policy-section">
                            <h3>Configurações de Notificação</h3>
                            
                            <div class="form-group">
                                <label for="email_notifications_enabled">Notificações por E-mail</label>
                                <select id="email_notifications_enabled" name="policies[email_notifications_enabled]" class="form-control">
                                    <option value="true" <?= ($policies['email_notifications_enabled']['policy_value'] ?? 'true') === 'true' ? 'selected' : '' ?>>Ativadas</option>
                                    <option value="false" <?= ($policies['email_notifications_enabled']['policy_value'] ?? 'true') === 'false' ? 'selected' : '' ?>>Desativadas</option>
                                </select>
                                <small class="form-text">Enviar notificações automáticas por e-mail</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="alert_threshold_sales_percent">Alerta de Vendas (%)</label>
                                <input type="number" id="alert_threshold_sales_percent" name="policies[alert_threshold_sales_percent]" 
                                       class="form-control" value="<?= $policies['alert_threshold_sales_percent']['policy_value'] ?? 85 ?>" 
                                       min="50" max="95" required>
                                <small class="form-text">Percentual de vendas para gerar alerta automático</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ações -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="validatePolicies()">
                            Validar Configurações
                        </button>
                        <button type="button" class="btn btn-outline" onclick="restoreDefaults()">
                            Restaurar Padrão
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Salvar Políticas
                        </button>
                    </div>
                </form>
                
                <!-- Status de Validação -->
                <div id="validation-status" class="validation-status" style="display: none;">
                    <div class="status-header">
                        <h4>Resultado da Validação</h4>
                    </div>
                    <div class="status-content" id="validation-content">
                        <!-- Conteúdo dinâmico -->
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Validação de políticas
        function validatePolicies() {
            const formData = new FormData(document.getElementById('policies-form'));
            const policies = {};
            
            // Converter FormData para objeto
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('policies[')) {
                    const policyKey = key.match(/policies\[(.*?)\]/)[1];
                    policies[policyKey] = value;
                }
            }
            
            fetch('/admin/policies/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ policies: policies })
            })
            .then(response => response.json())
            .then(data => {
                showValidationResult(data);
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao validar políticas', 'error');
            });
        }
        
        // Mostrar resultado da validação
        function showValidationResult(data) {
            const statusDiv = document.getElementById('validation-status');
            const contentDiv = document.getElementById('validation-content');
            
            let html = '';
            
            if (data.success) {
                html += '<div class="alert alert-success">✅ Todas as políticas são válidas</div>';
            } else {
                html += '<div class="alert alert-error">❌ Foram encontrados problemas:</div>';
                
                if (data.errors && data.errors.length > 0) {
                    html += '<ul class="error-list">';
                    data.errors.forEach(error => {
                        html += `<li>⚠️ ${error}</li>`;
                    });
                    html += '</ul>';
                }
            }
            
            if (data.warnings && data.warnings.length > 0) {
                html += '<div class="alert alert-warning">⚠️ Avisos:</div>';
                html += '<ul class="warning-list">';
                data.warnings.forEach(warning => {
                    html += `<li>${warning}</li>`;
                });
                html += '</ul>';
            }
            
            contentDiv.innerHTML = html;
            statusDiv.style.display = 'block';
            
            // Rolar para o resultado
            statusDiv.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Restaurar políticas padrão
        function restoreDefaults() {
            if (!confirm('Tem certeza que deseja restaurar as políticas padrão? Isso substituirá todas as configurações atuais.')) {
                return;
            }
            
            fetch('/admin/policies/restore', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Políticas restauradas com sucesso', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.error || 'Erro ao restaurar políticas', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao restaurar políticas', 'error');
            });
        }
        
        // Salvar políticas
        document.getElementById('policies-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Desabilitar botão
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
            
            fetch('/admin/policies', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Políticas salvas com sucesso!', 'success');
                    // Esconder status de validação
                    document.getElementById('validation-status').style.display = 'none';
                } else {
                    showNotification(data.error || 'Erro ao salvar políticas', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao salvar políticas', 'error');
            })
            .finally(() => {
                // Reabilitar botão
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
        // Validação em tempo real
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('change', function() {
                validateField(this);
            });
        });
        
        // Validar campo individual
        function validateField(field) {
            const value = parseFloat(field.value);
            const min = parseFloat(field.min);
            const max = parseFloat(field.max);
            
            if (value < min || value > max) {
                field.classList.add('is-invalid');
                showFieldError(field, `Valor deve estar entre ${min} e ${max}`);
            } else {
                field.classList.remove('is-invalid');
                hideFieldError(field);
            }
        }
        
        // Mostrar erro do campo
        function showFieldError(field, message) {
            let errorDiv = field.parentNode.querySelector('.field-error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                field.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }
        
        // Esconder erro do campo
        function hideFieldError(field) {
            const errorDiv = field.parentNode.querySelector('.field-error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
        // Notificação
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>
    
    <style>
    .policy-form {
        max-width: 800px;
    }
    
    .policy-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .policy-section {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .policy-section h3 {
        margin: 0 0 1.5rem 0;
        color: #2c3e50;
        font-size: 1.2rem;
        border-bottom: 2px solid #3498db;
        padding-bottom: 0.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .form-control.is-invalid {
        border-color: #e74c3c;
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #666;
    }
    
    .field-error {
        color: #e74c3c;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .validation-status {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-top: 2rem;
    }
    
    .status-header h4 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .error-list, .warning-list {
        margin: 1rem 0;
        padding-left: 1.5rem;
    }
    
    .error-list li {
        color: #721c24;
        margin-bottom: 0.5rem;
    }
    
    .warning-list li {
        color: #856404;
        margin-bottom: 0.5rem;
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
    }
    
    .notification-success {
        background: #27ae60;
    }
    
    .notification-error {
        background: #e74c3c;
    }
    
    .notification-info {
        background: #3498db;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    </style>
</body>
</html>
