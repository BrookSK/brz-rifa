<?php
/**
 * Controller para configurações do sistema
 */

class ConfigController {
    private $db;
    private $systemPolicy;
    
    public function __construct($database) {
        $this->db = $database;
        $this->systemPolicy = new SystemPolicy($database);
    }
    
    /**
     * Exibir página de políticas
     */
    public function policies() {
        $this->requireAuth('admin');
        
        $policies = $this->systemPolicy->getAll();
        
        include SRC_PATH . '/views/admin/config/policies.php';
    }
    
    /**
     * Salvar políticas
     */
    public function savePolicies() {
        $this->requireAuth('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        try {
            $policies = $_POST['policies'] ?? [];
            
            foreach ($policies as $key => $value) {
                // Determinar tipo do valor
                $type = 'string';
                if (is_numeric($value)) {
                    $type = is_int($value + 0) ? 'integer' : 'decimal';
                } elseif (is_bool($value) || $value === 'true' || $value === 'false') {
                    $type = 'boolean';
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $this->systemPolicy->set($key, $value, $type, "Política atualizada via painel");
            }
            
            $this->jsonResponse(['success' => true, 'message' => 'Políticas atualizadas com sucesso']);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibir página de integrações
     */
    public function integrations() {
        $this->requireAuth('admin');
        
        $integrations = $this->getIntegrations();
        $webhookUrl = $this->getWebhookUrl();
        
        include SRC_PATH . '/views/admin/config/integrations.php';
    }
    
    /**
     * Salvar integrações
     */
    public function saveIntegrations() {
        $this->requireAuth('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        try {
            // Salvar integração Asaas
            if (isset($_POST['asaas_api_key'])) {
                $this->saveAsaasIntegration($_POST['asaas_api_key'], $_POST['asaas_webhook_secret'] ?? null);
            }
            
            // Salvar outras integrações
            if (isset($_POST['email_config'])) {
                $this->saveEmailIntegration($_POST['email_config']);
            }
            
            $this->jsonResponse(['success' => true, 'message' => 'Integrações atualizadas com sucesso']);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibir página de alertas
     */
    public function alerts() {
        $this->requireAuth('admin');
        
        $alerts = $this->getAlerts();
        $alertTypes = $this->getAlertTypes();
        
        include SRC_PATH . '/views/admin/config/alerts.php';
    }
    
    /**
     * Testar integração Asaas
     */
    public function testAsaasConnection() {
        $this->requireAuth('admin');
        
        try {
            $asaasService = new AsaasService($this->db);
            $result = $asaasService->testConnection();
            
            $this->jsonResponse($result);
            
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Obter webhook URL
     */
    public function getWebhookUrl() {
        $baseUrl = Config::SITE_URL;
        return $baseUrl . '/webhook/asaas';
    }
    
    /**
     * Salvar integração Asaas
     */
    private function saveAsaasIntegration($apiKey, $webhookSecret = null) {
        $configData = [
            'api_url' => Config::ASAAS_API_URL,
            'webhook_secret' => $webhookSecret
        ];
        
        $sql = "INSERT INTO integrations (integration_name, is_active, api_key, webhook_secret, config_data) 
                VALUES ('asaas', true, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active), 
                api_key = VALUES(api_key),
                webhook_secret = VALUES(webhook_secret),
                config_data = VALUES(config_data),
                updated_at = CURRENT_TIMESTAMP";
        
        $this->db->query($sql, [$apiKey, $webhookSecret, json_encode($configData)]);
        
        // Registrar log de auditoria
        $this->logAudit('UPDATE_INTEGRATION', 'asaas', ['api_key_configured' => true]);
    }
    
    /**
     * Salvar integração de e-mail
     */
    private function saveEmailIntegration($config) {
        $configData = [
            'smtp_host' => $config['smtp_host'] ?? null,
            'smtp_port' => $config['smtp_port'] ?? 587,
            'smtp_username' => $config['smtp_username'] ?? null,
            'smtp_password' => $config['smtp_password'] ?? null,
            'smtp_encryption' => $config['smtp_encryption'] ?? 'tls',
            'from_email' => $config['from_email'] ?? null,
            'from_name' => $config['from_name'] ?? Config::SITE_NAME
        ];
        
        $sql = "INSERT INTO integrations (integration_name, is_active, config_data) 
                VALUES ('email', true, ?) 
                ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active), 
                config_data = VALUES(config_data),
                updated_at = CURRENT_TIMESTAMP";
        
        $this->db->query($sql, [json_encode($configData)]);
        
        $this->logAudit('UPDATE_INTEGRATION', 'email', ['smtp_configured' => true]);
    }
    
    /**
     * Obter integrações
     */
    private function getIntegrations() {
        $sql = "SELECT * FROM integrations ORDER BY integration_name";
        $stmt = $this->db->query($sql);
        $integrations = $stmt->fetchAll();
        
        // Organizar por nome
        $result = [];
        foreach ($integrations as $integration) {
            $result[$integration['integration_name']] = $integration;
        }
        
        return $result;
    }
    
    /**
     * Obter alertas
     */
    private function getAlerts() {
        $sql = "SELECT * FROM system_alerts 
                ORDER BY created_at DESC 
                LIMIT 100";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter tipos de alerta
     */
    private function getAlertTypes() {
        return [
            'info' => 'Informação',
            'warning' => 'Aviso',
            'error' => 'Erro',
            'critical' => 'Crítico'
        ];
    }
    
    /**
     * Limpar alertas antigos
     */
    public function clearAlerts() {
        $this->requireAuth('admin');
        
        $days = $_POST['days'] ?? 30;
        
        $sql = "DELETE FROM system_alerts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->query($sql, [$days]);
        
        $deleted = $stmt->rowCount();
        
        $this->jsonResponse([
            'success' => true, 
            'message' => "$deleted alertas removidos"
        ]);
    }
    
    /**
     * Marcar alerta como lido
     */
    public function markAlertRead() {
        $this->requireAuth('admin');
        
        $alertId = $_POST['alert_id'] ?? null;
        
        if (!$alertId) {
            $this->jsonResponse(['error' => 'ID do alerta não fornecido'], 400);
        }
        
        $sql = "UPDATE system_alerts SET read_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$alertId]);
        
        $this->jsonResponse(['success' => true]);
    }
    
    /**
     * Restaurar políticas padrão
     */
    public function restoreDefaultPolicies() {
        $this->requireAuth('admin');
        
        try {
            $this->systemPolicy->restoreDefaults();
            
            $this->jsonResponse([
                'success' => true, 
                'message' => 'Políticas restauradas com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Validar políticas
     */
    public function validatePolicies() {
        $this->requireAuth('admin');
        
        try {
            $policies = $_POST['policies'] ?? [];
            $validation = $this->systemPolicy->validatePolicies($policies);
            
            $this->jsonResponse([
                'success' => $validation['valid'],
                'errors' => $validation['errors'] ?? [],
                'warnings' => $validation['warnings'] ?? []
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Obter estatísticas do sistema
     */
    public function getSystemStats() {
        $this->requireAuth('admin');
        
        $stats = [];
        
        // Estatísticas de usuários
        $sql = "SELECT COUNT(*) as total FROM users";
        $stmt = $this->db->query($sql);
        $stats['users_total'] = $stmt->fetch()['total'];
        
        // Estatísticas de rifas
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'drawn' THEN 1 ELSE 0 END) as drawn
                FROM raffles";
        $stmt = $this->db->query($sql);
        $raffleStats = $stmt->fetch();
        $stats['raffles'] = $raffleStats;
        
        // Estatísticas financeiras
        $sql = "SELECT 
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as confirmed,
                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending
                FROM transactions";
        $stmt = $this->db->query($sql);
        $financialStats = $stmt->fetch();
        $stats['financial'] = $financialStats;
        
        // Estatísticas de alertas
        $sql = "SELECT COUNT(*) as total FROM system_alerts WHERE read_at IS NULL";
        $stmt = $this->db->query($sql);
        $stats['unread_alerts'] = $stmt->fetch()['total'];
        
        $this->jsonResponse(['success' => true, 'stats' => $stats]);
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $integration, $data = null) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'integrations', ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $integration,
            $data ? json_encode($data) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    /**
     * Verificar autenticação
     */
    private function requireAuth($requiredProfile = null) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        if ($requiredProfile) {
            $user = new User($this->db);
            if (!$user->hasPermission($_SESSION['user_id'], $requiredProfile)) {
                http_response_code(403);
                include SRC_PATH . '/views/errors/403.php';
                exit;
            }
        }
    }
    
    /**
     * Resposta JSON
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

?>
