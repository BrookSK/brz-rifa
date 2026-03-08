<?php
/**
 * Modelo para gerenciar integrações com serviços externos
 */

class Integration {
    private $db;
    private $config;
    
    public function __construct($database) {
        $this->db = $database;
        $this->config = [
            'asaas' => [
                'api_url' => 'https://sandbox.asaas.com/api/v3',
                'api_key' => 'asaas-api-key-sandbox',
                'timeout' => 30
            ],
            'email' => [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'noreply@brz-rifa.com',
                'smtp_password' => 'email-password',
                'from_name' => 'BRZ Rifa',
                'from_email' => 'noreply@brz-rifa.com'
            ],
            'fraud' => [
                'api_url' => 'https://api.fraud-detection.com/v1',
                'api_key' => 'fraud-api-key',
                'timeout' => 10
            ],
            'sms' => [
                'api_url' => 'https://api.sms-service.com/v1',
                'api_key' => 'sms-api-key',
                'timeout' => 15
            ],
            'push' => [
                'firebase_server_key' => 'firebase-server-key',
                'timeout' => 10
            ]
        ];
    }
    
    /**
     * Testar conexão com Asaas
     */
    public function testAsaasConnection() {
        try {
            $response = $this->makeRequest('GET', '/customers', [], 'asaas');
            
            if ($response['status_code'] === 200) {
                return [
                    'success' => true,
                    'message' => 'Conexão com Asaas estabelecida com sucesso',
                    'response_time' => $response['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão com Asaas',
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão com Asaas',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Testar conexão com serviço de email
     */
    public function testEmailConnection() {
        try {
            // Simular envio de email de teste
            $result = $this->sendTestEmail();
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Conexão com serviço de email estabelecida com sucesso',
                    'response_time' => $result['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão com serviço de email',
                    'error' => $result['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão com serviço de email',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Testar conexão com serviço de fraude
     */
    public function testFraudConnection() {
        try {
            $testData = [
                'cpf' => '12345678901',
                'email' => 'test@example.com',
                'ip' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)'
            ];
            
            $response = $this->makeRequest('POST', '/check', $testData, 'fraud');
            
            if ($response['status_code'] === 200) {
                return [
                    'success' => true,
                    'message' => 'Conexão com serviço de fraude estabelecida com sucesso',
                    'response_time' => $response['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão com serviço de fraude',
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão com serviço de fraude',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Testar conexão com serviço de SMS
     */
    public function testSMSConnection() {
        try {
            $testData = [
                'phone' => '5511999999999',
                'message' => 'Teste de conexão com serviço SMS',
                'sender' => 'BRZ Rifa'
            ];
            
            $response = $this->makeRequest('POST', '/send', $testData, 'sms');
            
            if ($response['status_code'] === 200) {
                return [
                    'success' => true,
                    'message' => 'Conexão com serviço de SMS estabelecida com sucesso',
                    'response_time' => $response['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão com serviço de SMS',
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão com serviço de SMS',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Testar conexão com serviço de push notifications
     */
    public function testPushConnection() {
        try {
            $testData = [
                'token' => 'test-device-token',
                'title' => 'Teste de Push Notification',
                'message' => 'Teste de conexão com serviço de push',
                'data' => ['test' => true]
            ];
            
            $response = $this->sendPushNotification($testData);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Conexão com serviço de push estabelecida com sucesso',
                    'response_time' => $response['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão com serviço de push',
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão com serviço de push',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer requisição HTTP genérica
     */
    private function makeRequest($method, $endpoint, $data = [], $service = 'asaas') {
        $config = $this->config[$service];
        $url = $config['api_url'] . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'accept: application/json'
        ];
        
        if ($service === 'asaas') {
            $headers[] = 'access_token: ' . $config['api_key'];
        } else {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'status_code' => $http_code,
            'response' => json_decode($response, true),
            'error' => $error,
            'response_time' => round(($end_time - $start_time) * 1000, 2)
        ];
    }
    
    /**
     * Enviar email de teste
     */
    private function sendTestEmail() {
        try {
            $config = $this->config['email'];
            
            // Simular envio de email
            $start_time = microtime(true);
            
            // Em produção, usar PHPMailer ou similar
            $to = 'test@brz-rifa.com';
            $subject = 'Teste de Conexão - BRZ Rifa';
            $message = 'Este é um email de teste para verificar a conexão com o serviço de email.';
            
            // Simulação
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'success' => true,
                'message' => 'Email de teste enviado com sucesso',
                'response_time' => $response_time,
                'to' => $to,
                'subject' => $subject
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar push notification
     */
    private function sendPushNotification($data) {
        try {
            $config = $this->config['push'];
            
            // Simular envio de push notification
            $start_time = microtime(true);
            
            // Em produção, usar Firebase Cloud Messaging
            $payload = [
                'to' => $data['token'],
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['message'],
                    'sound' => 'default'
                ],
                'data' => $data['data'] ?? []
            ];
            
            // Simulação
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'success' => true,
                'message' => 'Push notification enviado com sucesso',
                'response_time' => $response_time,
                'token' => $data['token']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar score de fraude
     */
    public function checkFraudScore($cpf, $email, $ip = null, $userAgent = null) {
        try {
            $data = [
                'cpf' => $cpf,
                'email' => $email,
                'ip' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => time()
            ];
            
            $response = $this->makeRequest('POST', '/check', $data, 'fraud');
            
            if ($response['status_code'] === 200) {
                return [
                    'success' => true,
                    'score' => $response['response']['score'] ?? 0,
                    'risk_level' => $response['response']['risk_level'] ?? 'low',
                    'details' => $response['response']['details'] ?? [],
                    'response_time' => $response['response_time']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'],
                    'score' => 0,
                    'risk_level' => 'unknown'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'score' => 0,
                'risk_level' => 'unknown'
            ];
        }
    }
    
    /**
     * Enviar SMS
     */
    public function sendSMS($phone, $message, $sender = null) {
        try {
            $data = [
                'phone' => $phone,
                'message' => $message,
                'sender' => $sender ?? 'BRZ Rifa'
            ];
            
            $response = $this->makeRequest('POST', '/send', $data, 'sms');
            
            if ($response['status_code'] === 200) {
                return [
                    'success' => true,
                    'message' => 'SMS enviado com sucesso',
                    'response_time' => $response['response_time'],
                    'phone' => $phone,
                    'message' => $message
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar email
     */
    public function sendEmail($to, $subject, $message, $data = []) {
        try {
            $config = $this->config['email'];
            
            // Simular envio de email
            $start_time = microtime(true);
            
            // Em produção, usar PHPMailer ou similar
            $headers = [
                'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
                'Reply-To: ' . $config['from_email'],
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];
            
            // Simulação
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2);
            
            return [
                'success' => true,
                'message' => 'Email enviado com sucesso',
                'response_time' => $response_time,
                'to' => $to,
                'subject' => $subject
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter status de todas as integrações
     */
    public function getAllIntegrationStatus() {
        $status = [];
        
        // Asaas
        $status['asaas'] = $this->testAsaasConnection();
        
        // Email
        $status['email'] = $this->testEmailConnection();
        
        // Fraude
        $status['fraud'] = $this->testFraudConnection();
        
        // SMS
        $status['sms'] = $this->testSMSConnection();
        
        // Push
        $status['push'] = $this->testPushConnection();
        
        return $status;
    }
    
    /**
     * Salvar log de integração
     */
    public function logIntegration($service, $action, $request = null, $response = null, $success = null, $error = null) {
        $sql = "INSERT INTO integration_logs (
            service, action, request_data, response_data, success, error_message, 
            response_time, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $service,
            $action,
            $request ? json_encode($request) : null,
            $response ? json_encode($response) : null,
            $success,
            $error,
            $response['response_time'] ?? null
        ]);
    }
    
    /**
     * Obter logs de integração
     */
    public function getIntegrationLogs($service = null, $action = null, $dateFrom = null, $dateTo = null, $limit = 50) {
        $sql = "SELECT * FROM integration_logs WHERE 1=1";
        $params = [];
        
        if ($service) {
            $sql .= " AND service = ?";
            $params[] = $service;
        }
        
        if ($action) {
            $sql .= " AND action = ?";
            $params[] = $action;
        }
        
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de integração
     */
    public function getIntegrationStatistics($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    service,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_requests,
                    AVG(response_time) as avg_response_time,
                    MAX(response_time) as max_response_time,
                    MIN(response_time) as min_response_time
                FROM integration_logs";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " WHERE created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= ($dateFrom ? " AND" : " WHERE") . " created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY service ORDER BY total_requests DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Limpar logs antigos de integração
     */
    public function cleanupIntegrationLogs($days = 30) {
        $sql = "DELETE FROM integration_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Verificar se serviço está disponível
     */
    public function isServiceAvailable($service) {
        $status = $this->getAllIntegrationStatus();
        return isset($status[$service]) && $status[$service]['success'];
    }
    
    /**
     * Obter configuração do serviço
     */
    public function getServiceConfig($service) {
        return $this->config[$service] ?? null;
    }
    
    /**
     * Atualizar configuração do serviço
     */
    public function updateServiceConfig($service, $config) {
        $sql = "INSERT INTO integration_configs (service, config_data, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE config_data = VALUES(config_data), updated_at = NOW()";
        
        $this->db->query($sql, [$service, json_encode($config)]);
        
        // Atualizar configuração em memória
        $this->config[$service] = array_merge($this->config[$service] ?? [], $config);
        
        return true;
    }
    
    /**
     * Obter configurações salvas
     */
    public function getSavedConfigs() {
        $sql = "SELECT * FROM integration_configs ORDER BY service";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Sincronizar dados com serviço externo
     */
    public function syncData($service, $lastSync = null) {
        $syncData = [];
        
        switch ($service) {
            case 'asaas':
                $syncData = $this->syncAsaasData($lastSync);
                break;
            case 'fraud':
                $syncData = $this->syncFraudData($lastSync);
                break;
            default:
                throw new Exception("Serviço não suportado para sincronização: {$service}");
        }
        
        // Registrar sincronização
        $this->logIntegration($service, 'sync', ['last_sync' => $lastSync], $syncData, true);
        
        return $syncData;
    }
    
    /**
     * Sincronizar dados do Asaas
     */
    private function syncAsaasData($lastSync = null) {
        $data = [];
        
        // Sincronizar clientes
        $response = $this->makeRequest('GET', '/customers', [], 'asaas');
        if ($response['status_code'] === 200) {
            $data['customers'] = $response['response']['data'] ?? [];
        }
        
        // Sincronizar cobranças
        $response = $this->makeRequest('GET', '/payments', [], 'asaas');
        if ($response['status_code'] === 200) {
            $data['payments'] = $response['response']['data'] ?? [];
        }
        
        return $data;
    }
    
    /**
     * Sincronizar dados de fraude
     */
    private function syncFraudData($lastSync = null) {
        $data = [];
        
        // Sincronizar regras de fraude
        $response = $this->makeRequest('GET', '/rules', [], 'fraud');
        if ($response['status_code'] === 200) {
            $data['rules'] = $response['response']['data'] ?? [];
        }
        
        // Sincronizar blacklist
        $response = $this->makeRequest('GET', '/blacklist', [], 'fraud');
        if ($response['status_code'] === 200) {
            $data['blacklist'] = $response['response']['data'] ?? [];
        }
        
        return $data;
    }
    
    /**
     * Validar webhook
     */
    public function validateWebhook($service, $payload, $signature, $secret = null) {
        switch ($service) {
            case 'asaas':
                return $this->validateAsaasWebhook($payload, $signature, $secret);
            default:
                throw new Exception("Serviço não suportado para validação de webhook: {$service}");
        }
    }
    
    /**
     * Validar webhook do Asaas
     */
    private function validateAsaasWebhook($payload, $signature, $secret) {
        if (!$signature || !$secret) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Gerar relatório de integrações
     */
    public function generateIntegrationReport($dateFrom = null, $dateTo = null) {
        $statistics = $this->getIntegrationStatistics($dateFrom, $dateTo);
        $logs = $this->getIntegrationLogs(null, null, $dateFrom, $dateTo, 100);
        $configs = $this->getSavedConfigs();
        
        return [
            'statistics' => $statistics,
            'recent_logs' => $logs,
            'configs' => $configs,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Testar todas as integrações
     */
    public function testAllIntegrations() {
        $results = [];
        
        $services = ['asaas', 'email', 'fraud', 'sms', 'push'];
        
        foreach ($services as $service) {
            $results[$service] = $this->testIntegration($service);
        }
        
        return [
            'results' => $results,
            'overall_status' => $this->getOverallStatus($results),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Testar integração específica
     */
    public function testIntegration($service) {
        switch ($service) {
            case 'asaas':
                return $this->testAsaasConnection();
            case 'email':
                return $this->testEmailConnection();
            case 'fraud':
                return $this->testFraudConnection();
            case 'sms':
                return $this->testSMSConnection();
            case 'push':
                return $this->testPushConnection();
            default:
                return [
                    'success' => false,
                    'message' => "Serviço não suportado: {$service}"
                ];
        }
    }
    
    /**
     * Obter status geral
     */
    private function getOverallStatus($results) {
        $total = count($results);
        $successful = 0;
        
        foreach ($results as $result) {
            if ($result['success']) {
                $successful++;
            }
        }
        
        return [
            'total_services' => $total,
            'successful_services' => $successful,
            'failed_services' => $total - $successful,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'status' => $successful === $total ? 'all_operational' : ($successful > 0 ? 'partial' : 'all_failed')
        ];
    }
}

?>
