<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrações - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Integrações</h1>
                <p>Configure as integrações com serviços externos</p>
            </header>
            
            <div class="content-section">
                <!-- Integração Asaas -->
                <div class="integration-card">
                    <div class="integration-header">
                        <div class="integration-info">
                            <h3>🔗 Asaas</h3>
                            <p>Processamento de pagamentos PIX</p>
                        </div>
                        <div class="integration-status">
                            <span class="status-badge <?= $integrations['asaas']['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $integrations['asaas']['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>
                    </div>
                    
                    <form id="asaas-form" class="integration-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="asaas_api_key">Chave de API</label>
                                <input type="password" id="asaas_api_key" name="asaas_api_key" 
                                       class="form-control" placeholder="Sua chave de API do Asaas"
                                       value="<?= $integrations['asaas']['api_key'] ? '••••••••••••••••••••••••••••••••' : '' ?>">
                                <small class="form-text">Chave de API da sua conta Asaas (produção)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="asaas_webhook_secret">Secret do Webhook</label>
                                <input type="password" id="asaas_webhook_secret" name="asaas_webhook_secret" 
                                       class="form-control" placeholder="Secret para validação de webhooks"
                                       value="<?= $integrations['asaas']['webhook_secret'] ? '••••••••••••••••••••••••••••••••' : '' ?>">
                                <small class="form-text">Chave secreta para validar webhooks recebidos</small>
                            </div>
                        </div>
                        
                        <div class="webhook-info">
                            <h4>URL do Webhook</h4>
                            <div class="webhook-url-container">
                                <input type="text" readonly class="form-control" value="<?= $webhookUrl ?>">
                                <button type="button" class="btn btn-outline" onclick="copyWebhookUrl()">
                                    📋 Copiar
                                </button>
                            </div>
                            <small class="form-text">
                                Configure esta URL no painel do Asaas em: 
                                <strong>Configurações > Webhooks</strong>
                            </small>
                        </div>
                        
                        <div class="integration-actions">
                            <button type="button" class="btn btn-outline" onclick="testAsaasConnection()">
                                🧪 Testar Conexão
                            </button>
                            <button type="submit" class="btn btn-primary">
                                💾 Salvar Configuração
                            </button>
                        </div>
                    </form>
                    
                    <!-- Status da Conexão -->
                    <div id="asaas-status" class="connection-status" style="display: none;">
                        <div class="status-content">
                            <!-- Conteúdo dinâmico -->
                        </div>
                    </div>
                </div>
                
                <!-- Integração de E-mail -->
                <div class="integration-card">
                    <div class="integration-header">
                        <div class="integration-info">
                            <h3>📧 E-mail</h3>
                            <p>Envio de notificações automáticas</p>
                        </div>
                        <div class="integration-status">
                            <span class="status-badge <?= $integrations['email']['is_active'] ?? false ? 'status-active' : 'status-inactive' ?>">
                                <?= $integrations['email']['is_active'] ?? false ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>
                    </div>
                    
                    <form id="email-form" class="integration-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host">Servidor SMTP</label>
                                <input type="text" id="smtp_host" name="email_config[smtp_host]" 
                                       class="form-control" placeholder="smtp.seuprovedor.com"
                                       value="<?= $emailConfig['smtp_host'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">Porta SMTP</label>
                                <input type="number" id="smtp_port" name="email_config[smtp_port]" 
                                       class="form-control" placeholder="587" value="<?= $emailConfig['smtp_port'] ?? 587 ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_username">Usuário SMTP</label>
                                <input type="text" id="smtp_username" name="email_config[smtp_username]" 
                                       class="form-control" placeholder="seu@email.com"
                                       value="<?= $emailConfig['smtp_username'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">Senha SMTP</label>
                                <input type="password" id="smtp_password" name="email_config[smtp_password]" 
                                       class="form-control" placeholder="Sua senha SMTP">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_encryption">Criptografia</label>
                                <select id="smtp_encryption" name="email_config[smtp_encryption]" class="form-control">
                                    <option value="tls" <?= ($emailConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= ($emailConfig['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= ($emailConfig['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="from_email">E-mail Remetente</label>
                                <input type="email" id="from_email" name="email_config[from_email]" 
                                       class="form-control" placeholder="noreply@seusite.com"
                                       value="<?= $emailConfig['from_email'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="from_name">Nome Remetente</label>
                            <input type="text" id="from_name" name="email_config[from_name]" 
                                   class="form-control" placeholder="<?= Config::SITE_NAME ?>"
                                   value="<?= $emailConfig['from_name'] ?? Config::SITE_NAME ?>">
                        </div>
                        
                        <div class="integration-actions">
                            <button type="button" class="btn btn-outline" onclick="testEmailConnection()">
                                📧 Enviar Teste
                            </button>
                            <button type="submit" class="btn btn-primary">
                                💾 Salvar Configuração
                            </button>
                        </div>
                    </form>
                    
                    <!-- Status do E-mail -->
                    <div id="email-status" class="connection-status" style="display: none;">
                        <div class="status-content">
                            <!-- Conteúdo dinâmico -->
                        </div>
                    </div>
                </div>
                
                <!-- Status Geral das Integrações -->
                <div class="integration-summary">
                    <h3>📊 Status das Integrações</h3>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-icon <?= $integrations['asaas']['is_active'] ? 'status-success' : 'status-error' ?>">
                                <?= $integrations['asaas']['is_active'] ? '✅' : '❌' ?>
                            </div>
                            <div class="status-details">
                                <div class="status-title">Asaas</div>
                                <div class="status-description">
                                    <?= $integrations['asaas']['is_active'] ? 'Conectado e funcional' : 'Não configurado' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-icon <?= ($integrations['email']['is_active'] ?? false) ? 'status-success' : 'status-warning' ?>">
                                <?= ($integrations['email']['is_active'] ?? false) ? '✅' : '⚠️' ?>
                            </div>
                            <div class="status-details">
                                <div class="status-title">E-mail</div>
                                <div class="status-description">
                                    <?= ($integrations['email']['is_active'] ?? false) ? 'Configurado' : 'Opcional' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Testar conexão Asaas
        function testAsaasConnection() {
            const statusDiv = document.getElementById('asaas-status');
            const contentDiv = statusDiv.querySelector('.status-content');
            
            contentDiv.innerHTML = '<div class="loading">🔄 Testando conexão...</div>';
            statusDiv.style.display = 'block';
            
            fetch('/admin/integrations/test-asaas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    contentDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ ${data.message}
                        </div>
                    `;
                } else {
                    contentDiv.innerHTML = `
                        <div class="alert alert-error">
                            ❌ ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contentDiv.innerHTML = `
                    <div class="alert alert-error">
                        ❌ Erro ao testar conexão
                    </div>
                `;
            });
        }
        
        // Testar conexão de e-mail
        function testEmailConnection() {
            const statusDiv = document.getElementById('email-status');
            const contentDiv = statusDiv.querySelector('.status-content');
            
            contentDiv.innerHTML = '<div class="loading">🔄 Enviando e-mail de teste...</div>';
            statusDiv.style.display = 'block';
            
            // Implementar teste de e-mail
            fetch('/admin/integrations/test-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    contentDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ ${data.message}
                        </div>
                    `;
                } else {
                    contentDiv.innerHTML = `
                        <div class="alert alert-error">
                            ❌ ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contentDiv.innerHTML = `
                    <div class="alert alert-error">
                        ❌ Erro ao enviar e-mail de teste
                    </div>
                `;
            });
        }
        
        // Copiar URL do webhook
        function copyWebhookUrl() {
            const webhookUrl = '<?= $webhookUrl ?>';
            navigator.clipboard.writeText(webhookUrl).then(() => {
                showNotification('URL copiada para a área de transferência!', 'success');
            }).catch(() => {
                // Fallback para browsers antigos
                const input = document.createElement('input');
                input.value = webhookUrl;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showNotification('URL copiada para a área de transferência!', 'success');
            });
        }
        
        // Salvar configuração Asaas
        document.getElementById('asaas-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
            
            fetch('/admin/integrations', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Configuração Asaas salva com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.error || 'Erro ao salvar configuração', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao salvar configuração', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
        // Salvar configuração de e-mail
        document.getElementById('email-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
            
            fetch('/admin/integrations', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Configuração de e-mail salva com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.error || 'Erro ao salvar configuração', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao salvar configuração', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
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
    .integration-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        overflow: hidden;
    }
    
    .integration-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .integration-info h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.3rem;
        color: #2c3e50;
    }
    
    .integration-info p {
        margin: 0;
        color: #666;
    }
    
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .integration-form {
        padding: 1.5rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
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
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #666;
    }
    
    .webhook-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin: 1.5rem 0;
    }
    
    .webhook-info h4 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
    }
    
    .webhook-url-container {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .webhook-url-container .form-control {
        flex: 1;
        background: white;
        font-family: monospace;
        font-size: 0.875rem;
    }
    
    .integration-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .connection-status {
        background: #f8f9fa;
        padding: 1rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .loading {
        text-align: center;
        color: #666;
        font-style: italic;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 0;
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
    
    .integration-summary {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .integration-summary h3 {
        margin: 0 0 1.5rem 0;
        color: #2c3e50;
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .status-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .status-icon {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .status-success {
        background: #d4edda;
        color: #155724;
    }
    
    .status-error {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-title {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .status-description {
        font-size: 0.875rem;
        color: #666;
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
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .integration-header {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .integration-actions {
            flex-direction: column;
        }
        
        .webhook-url-container {
            flex-direction: column;
        }
    }
    </style>
</body>
</html>
