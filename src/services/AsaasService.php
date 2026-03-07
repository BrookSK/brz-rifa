<?php
/**
 * Serviço de integração com Asaas para processamento de pagamentos PIX
 */

class AsaasService {
    private $db;
    private $apiKey;
    private $apiUrl;
    private $webhookSecret;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadConfig();
    }
    
    /**
     * Carregar configurações da integração
     */
    private function loadConfig() {
        $sql = "SELECT api_key, webhook_secret, config_data FROM integrations 
                WHERE integration_name = 'asaas' AND is_active = true";
        $stmt = $this->db->query($sql);
        $config = $stmt->fetch();
        
        if (!$config) {
            throw new Exception("Integração com Asaas não configurada ou inativa");
        }
        
        $this->apiKey = $config['api_key'];
        $this->webhookSecret = $config['webhook_secret'];
        $this->apiUrl = Config::ASAAS_API_URL;
        
        // Carregar configurações adicionais
        if ($config['config_data']) {
            $configData = json_decode($config['config_data'], true);
            $this->apiUrl = $configData['api_url'] ?? $this->apiUrl;
        }
    }
    
    /**
     * Criar cobrança PIX
     */
    public function createPixCharge($data) {
        $requiredFields = ['customer', 'value', 'description', 'dueDate'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Campo obrigatório ausente: $field");
            }
        }
        
        $payload = [
            'customer' => $data['customer'],
            'billingType' => 'PIX',
            'value' => (float) $data['value'],
            'description' => $data['description'],
            'dueDate' => $data['dueDate'],
            'externalReference' => $data['externalReference'] ?? null,
            'postalService' => false
        ];
        
        // Adicionar informações do cliente se não existir
        if (is_array($payload['customer'])) {
            // Criar cliente primeiro
            $customerId = $this->createCustomer($payload['customer']);
            $payload['customer'] = $customerId;
        }
        
        return $this->makeRequest('POST', '/payments', $payload);
    }
    
    /**
     * Criar cliente no Asaas
     */
    public function createCustomer($customerData) {
        $requiredFields = ['name', 'cpfCnpj', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($customerData[$field])) {
                throw new Exception("Campo obrigatório do cliente ausente: $field");
            }
        }
        
        $payload = [
            'name' => $customerData['name'],
            'cpfCnpj' => $customerData['cpfCnpj'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'] ?? null,
            'mobilePhone' => $customerData['mobilePhone'] ?? null,
            'address' => $customerData['address'] ?? null,
            'addressNumber' => $customerData['addressNumber'] ?? null,
            'complement' => $customerData['complement'] ?? null,
            'province' => $customerData['province'] ?? null,
            'postalCode' => $customerData['postalCode'] ?? null,
            'notificationDisabled' => false
        ];
        
        $response = $this->makeRequest('POST', '/customers', $payload);
        return $response['id'];
    }
    
    /**
     * Obter cobrança por ID
     */
    public function getCharge($chargeId) {
        return $this->makeRequest('GET', "/payments/$chargeId");
    }
    
    /**
     * Cancelar cobrança
     */
    public function cancelCharge($chargeId) {
        return $this->makeRequest('POST', "/payments/$chargeId/cancel");
    }
    
    /**
     * Estornar cobrança
     */
    public function refundCharge($chargeId, $value = null) {
        $payload = [];
        if ($value) {
            $payload['value'] = (float) $value;
        }
        
        return $this->makeRequest('POST', "/payments/$chargeId/refund", $payload);
    }
    
    /**
     * Listar cobranças
     */
    public function listCharges($filters = []) {
        $params = http_build_query($filters);
        return $this->makeRequest('GET', "/payments?$params");
    }
    
    /**
     * Testar conexão com API
     */
    public function testConnection() {
        try {
            $response = $this->makeRequest('GET', '/customers', ['limit' => 1]);
            return ['success' => true, 'message' => 'Conexão estabelecida com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Processar webhook do Asaas
     */
    public function processWebhook($payload, $signature) {
        // Validar assinatura do webhook
        if (!$this->validateWebhookSignature($payload, $signature)) {
            throw new Exception("Assinatura do webhook inválida");
        }
        
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['event'])) {
            throw new Exception("Payload do webhook inválido");
        }
        
        switch ($event['event']) {
            case 'PAYMENT_CONFIRMED':
                return $this->handlePaymentConfirmed($event);
            case 'PAYMENT_DELETED':
                return $this->handlePaymentDeleted($event);
            case 'PAYMENT_OVERDUE':
                return $this->handlePaymentOverdue($event);
            default:
                return ['success' => true, 'message' => 'Evento não processado'];
        }
    }
    
    /**
     * Lidar com pagamento confirmado
     */
    private function handlePaymentConfirmed($event) {
        $payment = $event['payment'];
        $paymentId = $payment['id'];
        
        // Buscar transação local
        $sql = "SELECT t.*, rn.* FROM transactions t 
                JOIN transaction_numbers tn ON t.id = tn.transaction_id
                JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.payment_id = ?";
        $stmt = $this->db->query($sql, [$paymentId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            throw new Exception("Transação não encontrada: $paymentId");
        }
        
        // Validar valor
        if (abs((float)$payment['value'] - (float)$transaction['amount']) > 0.01) {
            throw new Exception("Valor do pagamento não confere");
        }
        
        // Iniciar transação
        $this->db->beginTransaction();
        
        try {
            // Atualizar status da transação
            $sql = "UPDATE transactions SET 
                    payment_status = 'confirmed', 
                    confirmed_at = NOW(), 
                    webhook_received_at = NOW(),
                    asaas_data = ?
                    WHERE payment_id = ?";
            
            $this->db->query($sql, [json_encode($payment), $paymentId]);
            
            // Atualizar números para PAGO
            $sql = "UPDATE raffle_numbers rn 
                    JOIN transaction_numbers tn ON rn.id = tn.raffle_number_id
                    JOIN transactions t ON tn.transaction_id = t.id
                    SET rn.status = 'paid', 
                        rn.paid_at = NOW()
                    WHERE t.payment_id = ?";
            
            $this->db->query($sql, [$paymentId]);
            
            // Atualizar estatísticas do participante
            $this->updateParticipantStats($transaction['participant_id'], $transaction['amount']);
            
            // Registrar log de auditoria
            $this->logAudit('PAYMENT_CONFIRMED', null, $paymentId, null, $payment);
            
            // Enviar e-mail de confirmação
            $this->sendPaymentConfirmationEmail($transaction);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Pagamento confirmado com sucesso'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Lidar com pagamento cancelado/deletado
     */
    private function handlePaymentDeleted($event) {
        $payment = $event['payment'];
        $paymentId = $payment['id'];
        
        // Iniciar transação
        $this->db->beginTransaction();
        
        try {
            // Atualizar status da transação
            $sql = "UPDATE transactions SET 
                    payment_status = 'cancelled', 
                    cancelled_at = NOW(), 
                    webhook_received_at = NOW(),
                    asaas_data = ?
                    WHERE payment_id = ?";
            
            $this->db->query($sql, [json_encode($payment), $paymentId]);
            
            // Liberar números reservados
            $sql = "UPDATE raffle_numbers rn 
                    JOIN transaction_numbers tn ON rn.id = tn.raffle_number_id
                    JOIN transactions t ON tn.transaction_id = t.id
                    SET rn.status = 'available', 
                        rn.participant_name = NULL,
                        rn.participant_cpf = NULL,
                        rn.participant_email = NULL,
                        rn.participant_phone = NULL,
                        rn.participant_address = NULL,
                        rn.reservation_hash = NULL,
                        rn.reservation_expires_at = NULL,
                        rn.payment_id = NULL,
                        rn.payment_amount = NULL,
                        rn.user_id = NULL
                    WHERE t.payment_id = ? AND rn.status = 'reserved'";
            
            $this->db->query($sql, [$paymentId]);
            
            // Registrar log de auditoria
            $this->logAudit('PAYMENT_CANCELLED', null, $paymentId, null, $payment);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Pagamento cancelado com sucesso'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Lidar com pagamento vencido
     */
    private function handlePaymentOverdue($event) {
        // Tratar como cancelamento
        return $this->handlePaymentDeleted($event);
    }
    
    /**
     * Validar assinatura do webhook
     */
    private function validateWebhookSignature($payload, $signature) {
        if (!$this->webhookSecret) {
            return true; // Se não tiver secret, desabilitar validação
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Atualizar estatísticas do participante
     */
    private function updateParticipantStats($participantId, $amount) {
        $sql = "UPDATE participants SET 
                total_purchases = total_purchases + 1,
                total_amount = total_amount + ?,
                last_purchase_at = NOW(),
                first_purchase_at = COALESCE(first_purchase_at, NOW())
                WHERE id = ?";
        
        $this->db->query($sql, [$amount, $participantId]);
    }
    
    /**
     * Enviar e-mail de confirmação de pagamento
     */
    private function sendPaymentConfirmationEmail($transaction) {
        // Implementar envio de e-mail
        // Por enquanto, apenas registrar log
        error_log("Email de confirmação enviado para: {$transaction['participant_email']}");
    }
    
    /**
     * Fazer requisição para API do Asaas
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $this->apiKey,
            'User-Agent: BRZ-Rifa/1.0'
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro de comunicação com Asaas: $error");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = $responseData['errors'][0]['description'] ?? 'Erro desconhecido';
            throw new Exception("Erro Asaas ($httpCode): $message");
        }
        
        return $responseData;
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $userId, $recordId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'transactions', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $userId ?: null,
            $action,
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'API',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Asaas-Webhook'
        ]);
    }
    
    /**
     * Obter QR Code do PIX
     */
    public function getPixQrCode($chargeId) {
        $charge = $this->getCharge($chargeId);
        
        if (!isset($charge['pixQrCode'])) {
            throw new Exception("QR Code não disponível para esta cobrança");
        }
        
        return [
            'qrCode' => $charge['pixQrCode']['encodedImage'],
            'payload' => $charge['pixQrCode']['payload'],
            'expirationDate' => $charge['dueDate']
        ];
    }
    
    /**
     * Verificar status de múltiplas cobranças
     */
    public function checkMultipleCharges($chargeIds) {
        $results = [];
        
        foreach ($chargeIds as $chargeId) {
            try {
                $charge = $this->getCharge($chargeId);
                $results[$chargeId] = [
                    'success' => true,
                    'status' => $charge['status'],
                    'value' => $charge['value'],
                    'confirmedAt' => $charge['confirmedAt'] ?? null
                ];
            } catch (Exception $e) {
                $results[$chargeId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

?>
