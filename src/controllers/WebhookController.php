<?php
/**
 * Controller para processar webhooks do Asaas
 */

class WebhookController {
    private $db;
    private $asaasService;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->asaasService = new AsaasService($this->db);
    }
    
    /**
     * Processar webhook do Asaas
     */
    public function handleAsaas() {
        // Obter payload do webhook
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_ASAAS_SIGNATURE'] ?? '';
        
        // Log do webhook recebido
        $this->logWebhook($payload, $signature);
        
        try {
            // Processar webhook
            $result = $this->asaasService->processWebhook($payload, $signature);
            
            // Retornar resposta
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
                'result' => $result
            ]);
            
        } catch (Exception $e) {
            // Log erro
            error_log("Erro ao processar webhook: " . $e->getMessage());
            
            // Retornar erro
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Testar webhook
     */
    public function test() {
        $testPayload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_test_' . time(),
                'value' => 100.00,
                'status' => 'CONFIRMED',
                'confirmedAt' => date('Y-m-d\TH:i:s\Z'),
                'customer' => 'cus_test_' . time()
            ]
        ];
        
        try {
            $result = $this->asaasService->processWebhook(
                json_encode($testPayload),
                'test_signature'
            );
            
            echo '<h1>✅ Teste de Webhook</h1>';
            echo '<p><strong>Evento:</strong> ' . htmlspecialchars($testPayload['event']) . '</p>';
            echo '<p><strong>Resultado:</strong> ' . htmlspecialchars($result['message']) . '</p>';
            echo '<pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . '</pre>';
            
        } catch (Exception $e) {
            echo '<h1>❌ Erro no Teste</h1>';
            echo '<p><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * Log de webhook
     */
    private function logWebhook($payload, $signature) {
        $sql = "INSERT INTO webhook_logs (payload, signature, ip_address, user_agent, received_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $payload,
            $signature,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Asaas-Webhook'
        ]);
    }
}

?>
