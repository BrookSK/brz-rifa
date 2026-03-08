<?php
/**
 * Modelo para gerenciar transações financeiras
 */

class Transaction {
    private $db;
    private $asaasService;
    
    public function __construct($database) {
        $this->db = $database;
        $this->asaasService = new AsaasService($database);
    }
    
    /**
     * Criar transação PIX
     */
    public function create($data) {
        // Validar dados obrigatórios
        $required = ['participant_id', 'raffle_id', 'numbers', 'amount'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Campo obrigatório: $field");
            }
        }
        
        $this->db->beginTransaction();
        
        try {
            // Obter dados do participante
            $participant = $this->getParticipant($data['participant_id']);
            
            // Criar cliente no Asaas (se não existir)
            $customerData = [
                'name' => $participant['name'],
                'cpfCnpj' => $participant['cpf'],
                'email' => $participant['email'],
                'phone' => $participant['phone'],
                'mobilePhone' => $participant['phone']
            ];
            
            $customerId = $this->asaasService->createCustomer($customerData);
            
            // Criar cobrança PIX
            $chargeData = [
                'customer' => $customerId,
                'value' => $data['amount'],
                'description' => "Rifa #{$data['raffle_id']} - Números: " . implode(', ', $data['numbers']),
                'dueDate' => date('Y-m-d', strtotime('+1 hour')),
                'externalReference' => 'RAFFLE_' . $data['raffle_id'] . '_' . time()
            ];
            
            $asaasCharge = $this->asaasService->createPixCharge($chargeData);
            
            // Inserir transação local
            $sql = "INSERT INTO transactions (
                participant_id, raffle_id, amount, payment_method, payment_status,
                payment_id, external_reference, asaas_data, created_at
            ) VALUES (?, ?, ?, 'PIX', 'pending', ?, ?, ?, NOW())";
            
            $this->db->query($sql, [
                $data['participant_id'],
                $data['raffle_id'],
                $data['amount'],
                $asaasCharge['id'],
                $chargeData['externalReference'],
                json_encode($asaasCharge)
            ]);
            
            $transactionId = $this->db->getConnection()->lastInsertId();
            
            // Associar números à transação
            foreach ($data['numbers'] as $numberId) {
                $sql = "INSERT INTO transaction_numbers (transaction_id, raffle_number_id) VALUES (?, ?)";
                $this->db->query($sql, [$transactionId, $numberId]);
            }
            
            // Obter QR Code
            $qrCode = $this->asaasService->getPixQrCode($asaasCharge['id']);
            
            $this->db->commit();
            
            return [
                'transaction_id' => $transactionId,
                'payment_id' => $asaasCharge['id'],
                'qr_code' => $qrCode['qrCode'],
                'payload' => $qrCode['payload'],
                'expiration_date' => $qrCode['expirationDate'],
                'amount' => $data['amount']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Obter transação por ID
     */
    public function getById($id) {
        $sql = "SELECT t.*, 
                       p.name as participant_name, p.email as participant_email,
                       r.title as raffle_title,
                       GROUP_CONCAT(rn.number) as numbers
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.id = ?
                GROUP BY t.id";
        
        $stmt = $this->db->query($sql, [$id]);
        $transaction = $stmt->fetch();
        
        if ($transaction && $transaction['numbers']) {
            $transaction['numbers'] = explode(',', $transaction['numbers']);
        } else {
            $transaction['numbers'] = [];
        }
        
        return $transaction;
    }
    
    /**
     * Listar transações
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT t.*, 
                       p.name as participant_name, p.email as participant_email,
                       r.title as raffle_title
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN raffles r ON t.raffle_id = r.id";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['status'])) {
            $where[] = "t.payment_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['participant_id'])) {
            $where[] = "t.participant_id = ?";
            $params[] = $filters['participant_id'];
        }
        
        if (!empty($filters['raffle_id'])) {
            $where[] = "t.raffle_id = ?";
            $params[] = $filters['raffle_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "t.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "t.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar status de transação
     */
    public function checkStatus($transactionId) {
        $transaction = $this->getById($transactionId);
        if (!$transaction) {
            throw new Exception("Transação não encontrada");
        }
        
        if ($transaction['payment_status'] === 'confirmed') {
            return $transaction;
        }
        
        // Verificar status no Asaas
        try {
            $asaasCharge = $this->asaasService->getCharge($transaction['payment_id']);
            
            // Atualizar status local se necessário
            if ($asaasCharge['status'] !== $transaction['payment_status']) {
                $this->updateStatus($transactionId, $asaasCharge['status'], $asaasCharge);
            }
            
            return $this->getById($transactionId);
            
        } catch (Exception $e) {
            // Log erro mas retornar status local
            error_log("Erro ao verificar status Asaas: " . $e->getMessage());
            return $transaction;
        }
    }
    
    /**
     * Atualizar status da transação
     */
    public function updateStatus($transactionId, $status, $asaasData = null) {
        $sql = "UPDATE transactions SET 
                payment_status = ?, 
                updated_at = NOW(),
                asaas_data = COALESCE(?, asaas_data)
                WHERE id = ?";
        
        $this->db->query($sql, [
            $status,
            $asaasData ? json_encode($asaasData) : null,
            $transactionId
        ]);
        
        // Se confirmado, atualizar números
        if ($status === 'confirmed') {
            $this->confirmTransaction($transactionId);
        }
        
        // Se cancelado, liberar números
        if ($status === 'cancelled' || $status === 'overdue') {
            $this->cancelTransaction($transactionId);
        }
        
        return true;
    }
    
    /**
     * Confirmar transação
     */
    private function confirmTransaction($transactionId) {
        $this->db->beginTransaction();
        
        try {
            // Atualizar números para pago
            $sql = "UPDATE raffle_numbers rn 
                    JOIN transaction_numbers tn ON rn.id = tn.raffle_number_id
                    SET rn.status = 'paid', 
                        rn.paid_at = NOW()
                    WHERE tn.transaction_id = ?";
            
            $this->db->query($sql, [$transactionId]);
            
            // Atualizar data de confirmação
            $sql = "UPDATE transactions SET confirmed_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$transactionId]);
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cancelar transação
     */
    private function cancelTransaction($transactionId) {
        $this->db->beginTransaction();
        
        try {
            // Liberar números reservados
            $sql = "UPDATE raffle_numbers rn 
                    JOIN transaction_numbers tn ON rn.id = tn.raffle_number_id
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
                    WHERE tn.transaction_id = ? AND rn.status = 'reserved'";
            
            $this->db->query($sql, [$transactionId]);
            
            // Atualizar data de cancelamento
            $sql = "UPDATE transactions SET cancelled_at = NOW() WHERE id = ?";
            $this->db->query($sql, [$transactionId]);
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cancelar transação no Asaas
     */
    public function cancel($transactionId) {
        $transaction = $this->getById($transactionId);
        if (!$transaction) {
            throw new Exception("Transação não encontrada");
        }
        
        if ($transaction['payment_status'] === 'confirmed') {
            throw new Exception("Transação já confirmada não pode ser cancelada");
        }
        
        // Cancelar no Asaas
        $this->asaasService->cancelCharge($transaction['payment_id']);
        
        // Atualizar status local
        $this->updateStatus($transactionId, 'cancelled');
        
        return true;
    }
    
    /**
     * Obter estatísticas de transações
     */
    public function getStatistics($filters = []) {
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_transactions,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) / COUNT(*) as avg_transaction_value,
                    COUNT(DISTINCT participant_id) as unique_participants
                FROM transactions";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Obter transação por ID do pagamento
     */
    public function getByPaymentId($paymentId) {
        $sql = "SELECT t.*, 
                       p.name as participant_name, p.email as participant_email, p.cpf as participant_cpf,
                       r.title as raffle_title,
                       GROUP_CONCAT(rn.number) as numbers
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.payment_id = ?
                GROUP BY t.id";
        
        $stmt = $this->db->query($sql, [$paymentId]);
        $transaction = $stmt->fetch();
        
        if ($transaction && $transaction['numbers']) {
            $transaction['numbers'] = explode(',', $transaction['numbers']);
        } else {
            $transaction['numbers'] = [];
        }
        
        return $transaction;
    }
    
    /**
     * Obter transações por participante
     */
    public function getByParticipant($participantId) {
        $sql = "SELECT t.*, 
                       r.title as raffle_title,
                       GROUP_CONCAT(rn.number) as numbers
                FROM transactions t
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.participant_id = ?
                GROUP BY t.id
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, [$participantId]);
        $transactions = $stmt->fetchAll();
        
        foreach ($transactions as &$transaction) {
            if ($transaction['numbers']) {
                $transaction['numbers'] = explode(',', $transaction['numbers']);
            } else {
                $transaction['numbers'] = [];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Obter transações por rifa
     */
    public function getByRaffle($raffleId, $status = null) {
        $sql = "SELECT t.*, 
                       p.name as participant_name, p.email as participant_email,
                       GROUP_CONCAT(rn.number) as numbers
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.raffle_id = ?";
        
        $params = [$raffleId];
        
        if ($status) {
            $sql .= " AND t.payment_status = ?";
            $params[] = $status;
        }
        
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        $transactions = $stmt->fetchAll();
        
        foreach ($transactions as &$transaction) {
            if ($transaction['numbers']) {
                $transaction['numbers'] = explode(',', $transaction['numbers']);
            } else {
                $transaction['numbers'] = [];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Conciliar transações com Asaas
     */
    public function reconcileWithAsaas($dateFrom = null, $dateTo = null) {
        $sql = "SELECT t.* FROM transactions t
                WHERE t.payment_status IN ('pending', 'reserved')";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->query($sql, $params);
        $transactions = $stmt->fetchAll();
        
        $reconciled = [];
        $errors = [];
        
        foreach ($transactions as $transaction) {
            try {
                $asaasCharge = $this->asaasService->getCharge($transaction['payment_id']);
                
                if ($asaasCharge['status'] !== $transaction['payment_status']) {
                    $this->updateStatus($transaction['id'], $asaasCharge['status'], $asaasCharge);
                    $reconciled[] = [
                        'transaction_id' => $transaction['id'],
                        'payment_id' => $transaction['payment_id'],
                        'old_status' => $transaction['payment_status'],
                        'new_status' => $asaasCharge['status']
                    ];
                }
                
            } catch (Exception $e) {
                $errors[] = [
                    'transaction_id' => $transaction['id'],
                    'payment_id' => $transaction['payment_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'reconciled' => $reconciled,
            'errors' => $errors,
            'total_processed' => count($transactions)
        ];
    }
    
    /**
     * Obter relatório financeiro
     */
    public function getFinancialReport($dateFrom = null, $dateTo = null, $groupBy = 'day') {
        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    DATE_FORMAT(t.created_at, '%Y-%m') as month,
                    DATE_FORMAT(t.created_at, '%Y-%u') as week,
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN t.payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
                    SUM(CASE WHEN t.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN t.payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_transactions,
                    SUM(CASE WHEN t.payment_status = 'confirmed' THEN t.amount ELSE 0 END) as revenue,
                    AVG(CASE WHEN t.payment_status = 'confirmed' THEN t.amount ELSE NULL END) as avg_transaction_value,
                    COUNT(DISTINCT t.participant_id) as unique_participants
                FROM transactions t";
        
        $params = [];
        $where = [];
        
        if ($dateFrom) {
            $where[] = "t.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $where[] = "t.created_at <= ?";
            $params[] = $dateTo;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY " . $groupBy . "(t.created_at) ORDER BY " . $groupBy . "(t.created_at)";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter métricas em tempo real
     */
    public function getRealTimeMetrics() {
        $sql = "SELECT 
                    COUNT(*) as total_transactions_today,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_today,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as revenue_today,
                    AVG(CASE WHEN payment_status = 'confirmed' THEN amount ELSE NULL END) as avg_ticket_today,
                    (SELECT COUNT(*) FROM transactions WHERE payment_status = 'pending') as pending_count,
                    (SELECT SUM(amount) FROM transactions WHERE payment_status = 'confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as revenue_30d
                FROM transactions 
                WHERE DATE(created_at) = CURDATE()";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Obter transações com problemas
     */
    public function getProblematicTransactions() {
        $sql = "SELECT t.*, 
                       p.name as participant_name, p.email as participant_email,
                       r.title as raffle_title
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN raffles r ON t.raffle_id = r.id
                WHERE t.payment_status IN ('pending', 'reserved')
                AND t.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY t.created_at ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Exportar transações para CSV
     */
    public function exportToCSV($filters = []) {
        $sql = "SELECT t.id, t.payment_id, t.amount, t.payment_status, t.payment_method,
                       t.created_at, t.confirmed_at, t.cancelled_at,
                       p.name as participant_name, p.email as participant_email, p.cpf as participant_cpf,
                       r.title as raffle_title,
                       GROUP_CONCAT(rn.number) as numbers
                FROM transactions t
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['status'])) {
            $where[] = "t.payment_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "t.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "t.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['participant_id'])) {
            $where[] = "t.participant_id = ?";
            $params[] = $filters['participant_id'];
        }
        
        if (!empty($filters['raffle_id'])) {
            $where[] = "t.raffle_id = ?";
            $params[] = $filters['raffle_id'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Validar integridade das transações
     */
    public function validateIntegrity() {
        $issues = [];
        
        // Verificar transações sem números associados
        $sql = "SELECT t.id, t.payment_id 
                FROM transactions t
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                WHERE tn.transaction_id IS NULL";
        
        $stmt = $this->db->query($sql);
        $withoutNumbers = $stmt->fetchAll();
        
        if (!empty($withoutNumbers)) {
            $issues[] = [
                'type' => 'missing_numbers',
                'count' => count($withoutNumbers),
                'transactions' => $withoutNumbers
            ];
        }
        
        // Verificar números sem transação
        $sql = "SELECT rn.id, rn.number, rn.raffle_id
                FROM raffle_numbers rn
                WHERE rn.status = 'paid'
                AND rn.payment_id IS NOT NULL
                AND rn.payment_id NOT IN (
                    SELECT DISTINCT payment_id FROM transactions
                )";
        
        $stmt = $this->db->query($sql);
        $withoutTransaction = $stmt->fetchAll();
        
        if (!empty($withoutTransaction)) {
            $issues[] = [
                'type' => 'missing_transaction',
                'count' => count($withoutTransaction),
                'numbers' => $withoutTransaction
            ];
        }
        
        // Verificar valores inconsistentes
        $sql = "SELECT t.id, t.payment_id, t.amount,
                       SUM(rn.payment_amount) as sum_amount,
                       COUNT(rn.id) as number_count
                FROM transactions t
                JOIN transaction_numbers tn ON t.id = tn.transaction_id
                JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.payment_status = 'confirmed'
                GROUP BY t.id
                HAVING ABS(t.amount - sum_amount) > 0.01";
        
        $stmt = $this->db->query($sql);
        $inconsistentValues = $stmt->fetchAll();
        
        if (!empty($inconsistentValues)) {
            $issues[] = [
                'type' => 'inconsistent_values',
                'count' => count($inconsistentValues),
                'transactions' => $inconsistentValues
            ];
        }
        
        return $issues;
    }
    
    /**
     * Obter resumo diário
     */
    public function getDailySummary($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as revenue,
                    AVG(CASE WHEN payment_status = 'confirmed' THEN amount ELSE NULL END) as avg_amount,
                    COUNT(DISTINCT participant_id) as unique_participants
                FROM transactions 
                WHERE DATE(created_at) = ?";
        
        $stmt = $this->db->query($sql, [$date]);
        return $stmt->fetch();
    }
    
    /**
     * Limpar transações antigas
     */
    public function cleanupOldTransactions($days = 365) {
        $sql = "DELETE FROM transactions 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND payment_status IN ('cancelled', 'overdue')";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Reembolsar transação
     */
    public function refund($transactionId, $value = null) {
        $transaction = $this->getById($transactionId);
        if (!$transaction) {
            throw new Exception("Transação não encontrada");
        }
        
        if ($transaction['payment_status'] !== 'confirmed') {
            throw new Exception("Apenas transações confirmadas podem ser reembolsadas");
        }
        
        // Estornar no Asaas
        $this->asaasService->refundCharge($transaction['payment_id'], $value);
        
        // Atualizar status
        $this->updateStatus($transactionId, 'refunded');
        
        return true;
    }
}

?>
