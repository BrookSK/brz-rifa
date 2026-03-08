<?php
/**
 * Controller para gerenciar integrações no painel administrativo
 */

class IntegrationController {
    private $db;
    private $integrationModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->integrationModel = new Integration($this->db);
    }
    
    /**
     * Dashboard de integrações
     */
    public function dashboard() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $status = $this->integrationModel->getAllIntegrationStatus();
            $statistics = $this->integrationModel->getIntegrationStatistics();
            $recentLogs = $this->integrationModel->getIntegrationLogs(null, null, null, null, 10);
            
            $this->showIntegrationDashboard($status, $statistics, $recentLogs);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Listar logs de integração
     */
    public function logs() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $service = $_GET['service'] ?? null;
        $action = $_GET['action'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $logs = $this->integrationModel->getIntegrationLogs($service, $action, $dateFrom, $dateTo, 50);
            
            $this->showIntegrationLogs($logs, $service, $action, $dateFrom, $dateTo);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Testar todas as integrações
     */
    public function testAll() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $results = $this->integrationModel->testAllIntegrations();
            
            $_SESSION['success'] = "Teste de integrações concluído! Status geral: " . $results['overall_status']['status'];
            
            header("Location: /admin/integrations/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/integrations/dashboard");
            exit;
        }
    }
    
    /**
     * Testar integração específica
     */
    public function test($service) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $result = $this->integrationModel->testIntegration($service);
            
            if ($result['success']) {
                $_SESSION['success'] = "Teste de {$service} realizado com sucesso!";
            } else {
                $_SESSION['error'] = "Falha no teste de {$service}: " . $result['message'];
            }
            
            header("Location: /admin/integrations/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/integrations/dashboard");
            exit;
        }
    }
    
    /**
     * Configurar integrações
     */
    public function config() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $configs = $this->integrationModel->getSavedConfigs();
            $this->showConfigForm($configs);
            return;
        }
        
        try {
            $service = $_POST['service'] ?? '';
            $config = $_POST['config'] ?? [];
            
            if (empty($service)) {
                throw new Exception("Serviço é obrigatório");
            }
            
            $this->integrationModel->updateServiceConfig($service, $config);
            
            $_SESSION['success'] = "Configuração do serviço {$service} atualizada com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/integrations/config");
        exit;
    }
    
    /**
     * Sincronizar dados
     */
    public function sync() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $service = $_GET['service'] ?? null;
        
        if (!$service) {
            $this->showSyncForm();
            return;
        }
        
        try {
            $lastSync = $_GET['last_sync'] ?? null;
            $syncData = $this->integrationModel->syncData($service, $lastSync);
            
            $_SESSION['success'] = "Dados do serviço {$service} sincronizados com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/integrations/dashboard");
        exit;
    }
    
    /**
     * Gerar relatório de integrações
     */
    public function report() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $report = $this->integrationModel->generateIntegrationReport($dateFrom, $dateTo);
            
            $this->showIntegrationReport($report);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showCleanupForm();
            return;
        }
        
        try {
            $days = intval($_POST['days'] ?? 30);
            $count = $this->integrationModel->cleanupIntegrationLogs($days);
            
            $_SESSION['success'] = "$count logs de integração antigos foram removidos (período de $days dias)";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/integrations/dashboard");
        exit;
    }
    
    /**
     * Validar webhook
     */
    public function validateWebhook() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showWebhookValidationForm();
            return;
        }
        
        try {
            $service = $_POST['service'] ?? '';
            $payload = $_POST['payload'] ?? '';
            $signature = $_POST['signature'] ?? '';
            $secret = $_POST['secret'] ?? '';
            
            if (empty($service) || empty($payload) || empty($signature)) {
                throw new Exception("Todos os campos são obrigatórios");
            }
            
            $isValid = $this->integrationModel->validateWebhook($service, $payload, $signature, $secret);
            
            if ($isValid) {
                $_SESSION['success'] = "Webhook do serviço {$service} validado com sucesso!";
            } else {
                $_SESSION['error'] = "Falha na validação do webhook do serviço {$service}";
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/integrations/dashboard");
        exit;
    }
    
    /**
     * Exibir dashboard de integrações
     */
    private function showIntegrationDashboard($status, $statistics, $recentLogs) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrações - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .status-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .status-card.active { border-left: 5px solid #27ae60; }
        .status-card.inactive { border-left: 5px solid #e74c3c; }
        .status-icon { font-size: 3em; margin-bottom: 15px; }
        .status-name { font-size: 1.2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .status-message { color: #666; font-size: 0.9em; }
        .status-time { color: #999; font-size: 0.8em; margin-top: 10px; }
        .actions { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logs-table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        .logs-table table { width: 100%; border-collapse: collapse; }
        .logs-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .logs-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .logs-table tr:hover { background: #f8f9fa; }
        .service-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .service-asaas { background: #e3f2fd; color: #1976d2; }
        .service-email { background: #e8f5e8; color: #388e3c; }
        .service-fraud { background: #fff3e0; color: #f57c00; }
        .service-sms { background: #fce4ec; color: #c2185b; }
        .service-push { background: #f3e5f5; color: #7b1fa2; }
        .success-badge { background: #d4edda; color: #155724; }
        .error-badge { background: #f8d7da; color: #721c24; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .actions { flex-direction: column; align-items: stretch; } .status-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🔗 ' . SITE_NAME . '</h1>
                <small>Sistema de Integrações</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="actions">
            <div>
                <h3>🔗 Status das Integrações</h3>
            </div>
            <div>
                <a href="/admin/integrations/test-all" class="btn btn-success">🔄 Testar Todas</a>
                <a href="/admin/integrations/config" class="btn btn-warning">⚙️ Configurar</a>
                <a href="/admin/integrations/logs" class="btn btn-primary">📋 Logs</a>
                <a href="/admin/integrations/report" class="btn">📊 Relatório</a>
                <a href="/admin/dashboard" class="btn">← Voltar</a>
            </div>
        </div>
        
        <div class="status-grid">';
        
        foreach ($status as $service => $result) {
            echo '<div class="status-card ' . ($result['success'] ? 'active' : 'inactive') . '">
                <div class="status-icon">' . ($result['success'] ? '✅' : '❌') . '</div>
                <div class="status-name">' . ucfirst($service) . '</div>
                <div class="status-message">' . $result['message'] . '</div>';
            
            if (isset($result['response_time'])) {
                echo '<div class="status-time">Tempo: ' . $result['response_time'] . 'ms</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>
        
        <div class="logs-table">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Logs Recentes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Ação</th>
                        <th>Status</th>
                        <th>Tempo</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($recentLogs as $log) {
            echo '<tr>
                <td><span class="service-badge service-' . $log['service'] . '">' . ucfirst($log['service']) . '</span></td>
                <td>' . htmlspecialchars($log['action']) . '</td>
                <td><span class="' . ($log['success'] ? 'success-badge' : 'error-badge') . '">' . ($log['success'] ? 'Sucesso' : 'Falha') . '</span></td>
                <td>' . ($log['response_time'] ? $log['response_time'] . 'ms' : '-') . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir logs de integração
     */
    private function showIntegrationLogs($logs, $service, $action, $dateFrom, $dateTo) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Integração - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .filter { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form select, .filter-form input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logs-table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .logs-table table { width: 100%; border-collapse: collapse; }
        .logs-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .logs-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .logs-table tr:hover { background: #f8f9fa; }
        .service-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .service-asaas { background: #e3f2fd; color: #1976d2; }
        .service-email { background: #e8f5e8; color: #388e3c; }
        .service-fraud { background: #fff3e0; color: #f57c00; }
        .service-sms { background: #fce4ec; color: #c2185b; }
        .service-push { background: #f3e5f5; color: #7b1fa2; }
        .success-badge { background: #d4edda; color: #155724; }
        .error-badge { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .filter-form { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>📋 ' . SITE_NAME . '</h1>
                <small>Logs de Integração</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="filter">
            <form method="GET" class="filter-form">
                <select name="service">
                    <option value="">Todos os Serviços</option>
                    <option value="asaas" ' . (($service ?? '') === 'asaas' ? 'selected' : '') . '>Asaas</option>
                    <option value="email" ' . (($service ?? '') === 'email' ? 'selected' : '') . '>Email</option>
                    <option value="fraud" ' . (($service ?? '') === 'fraud' ? 'selected' : '') . '>Fraude</option>
                    <option value="sms" ' . (($service ?? '') === 'sms' ? 'selected' : '') . '>SMS</option>
                    <option value="push" ' . (($service ?? '') === 'push' ? 'selected' : '') . '>Push</option>
                </select>
                <select name="action">
                    <option value="">Todas as Ações</option>
                    <option value="test" ' . (($action ?? '') === 'test' ? 'selected' : '') . '>Teste</option>
                    <option value="sync" ' . (($action ?? '') === 'sync' ? 'selected' : '') . '>Sincronização</option>
                    <option value="check" ' . (($action ?? '') === 'check' ? 'selected' : '') . '>Verificação</option>
                </select>
                <input type="date" name="date_from" value="' . htmlspecialchars($dateFrom) . '" placeholder="Data Inicial">
                <input type="date" name="date_to" value="' . htmlspecialchars($dateTo) . '" placeholder="Data Final">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="/admin/integrations/dashboard" class="btn">← Voltar</a>
            </form>
        </div>
        
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Ação</th>
                        <th>Status</th>
                        <th>Tempo</th>
                        <th>Data</th>
                        <th>Erro</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>
                <td><span class="service-badge service-' . $log['service'] . '">' . ucfirst($log['service']) . '</span></td>
                <td>' . htmlspecialchars($log['action']) . '</td>
                <td><span class="' . ($log['success'] ? 'success-badge' : 'error-badge') . '">' . ($log['success'] ? 'Sucesso' : 'Falha') . '</span></td>
                <td>' . ($log['response_time'] ? $log['response_time'] . 'ms' : '-') . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>
                <td>' . ($log['error_message'] ? htmlspecialchars(substr($log['error_message'], 0, 50)) : '-') . '</td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de configuração
     */
    private function showConfigForm($configs) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Integrações - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group select, .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">⚙️ Configurar Integrações</div>
            <div class="form-subtitle">Configure as chaves e endpoints dos serviços de integração</div>
        </div>
        
        <form method="POST" action="/admin/integrations/config">
            <div class="form-group">
                <label for="service">Serviço:</label>
                <select id="service" name="service" required>
                    <option value="">Selecione um serviço</option>
                    <option value="asaas">Asaas</option>
                    <option value="email">Email</option>
                    <option value="fraud">Fraude Detection</option>
                    <option value="sms">SMS</option>
                    <option value="push">Push Notifications</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="config">Configuração (JSON):</label>
                <textarea id="config" name="config" placeholder='{"api_key": "sua-chave-aqui", "timeout": 30}'></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">💾 Salvar Configuração</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/integrations/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de sincronização
     */
    private function showSyncForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizar Dados - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group select, .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🔄 Sincronizar Dados</div>
            <div class="form-subtitle">Sincronize dados com serviços externos</div>
        </div>
        
        <form method="GET" action="/admin/integrations/sync">
            <div class="form-group">
                <label for="service">Serviço:</label>
                <select id="service" name="service" required>
                    <option value="">Selecione um serviço</option>
                    <option value="asaas">Asaas</option>
                    <option value="fraud">Fraude Detection</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="last_sync">Última Sincronização (opcional):</label>
                <input type="datetime-local" id="last_sync" name="last_sync">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">🔄 Sincronizar</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/integrations/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de limpeza
     */
    private function showCleanupForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Logs - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🧹 Limpar Logs de Integração</div>
            <div class="form-subtitle">Remova logs antigos para manter o desempenho</div>
        </div>
        
        <form method="POST" action="/admin/integrations/cleanup">
            <div class="form-group">
                <label for="days">Período de retenção (dias):</label>
                <input type="number" id="days" name="days" value="30" min="1" max="365" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-danger">🧹 Limpar Logs</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/integrations/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de validação de webhook
     */
    private function showWebhookValidationForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Webhook - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group select, .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🔐 Validar Webhook</div>
            <div class="form-subtitle">Verifique a assinatura de webhooks recebidos</div>
        </div>
        
        <form method="POST" action="/admin/integrations/validate-webhook">
            <div class="form-group">
                <label for="service">Serviço:</label>
                <select id="service" name="service" required>
                    <option value="">Selecione um serviço</option>
                    <option value="asaas">Asaas</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="payload">Payload:</label>
                <textarea id="payload" name="payload" placeholder='{"id": "123", "status": "CONFIRMED"}'></textarea>
            </div>
            
            <div class="form-group">
                <label for="signature">Assinatura:</label>
                <input type="text" id="signature" name="signature" placeholder="hash_assinatura_aqui">
            </div>
            
            <div class="form-group">
                <label for="secret">Secret:</label>
                <input type="text" id="secret" name="secret" placeholder="sua_chave_secreta">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">🔐 Validar Webhook</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/integrations/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir relatório de integrações
     */
    private function showIntegrationReport($report) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Integrações - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .report-section { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .report-section h2 { color: #2c3e50; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #666; font-size: 0.9em; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>📊 ' . SITE_NAME . '</h1>
                <small>Relatório de Integrações</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="report-section">
            <h2>📋 Estatísticas Gerais</h2>
            <div class="stats-grid">';
        
        foreach ($report['statistics'] as $stat) {
            echo '<div class="stat-card">
                <div class="stat-value">' . $stat['total_requests'] . '</div>
                <div class="stat-label">' . ucfirst($stat['service']) . '</div>
                <div class="stat-label">Sucessos: ' . $stat['successful_requests'] . '</div>
                <div class="stat-label">Falhas: ' . $stat['failed_requests'] . '</div>
            </div>';
        }
        
        echo '</div>
        </div>
        
        <div class="report-section">
            <h2>📊 Logs Recentes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Ação</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($report['recent_logs'] as $log) {
            echo '<tr>
                <td>' . ucfirst($log['service']) . '</td>
                <td>' . htmlspecialchars($log['action']) . '</td>
                <td>' . ($log['success'] ? '✅' : '❌') . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div class="report-section">
            <h2>⚙️ Configurações</h2>
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Última Atualização</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($report['configs'] as $config) {
            echo '<tr>
                <td>' . ucfirst($config['service']) . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($config['updated_at'])) . '</td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="/admin/integrations/dashboard" class="btn btn-primary">← Voltar</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir página de erro
     */
    private function showError($message) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .error-icon { font-size: 4em; margin-bottom: 20px; }
        .error-title { color: #e74c3c; margin-bottom: 20px; }
        .error-message { color: #666; margin-bottom: 30px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Erro</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>
        <a href="/admin/integrations/dashboard" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
}

?>
