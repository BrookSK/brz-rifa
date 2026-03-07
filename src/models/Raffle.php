<?php
/**
 * Modelo para gerenciar rifas
 */

class Raffle {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Criar nova rifa
     */
    public function create($data) {
        // Validar dados obrigatórios
        $required = ['title', 'description', 'prize_description', 'number_price', 'number_quantity'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Campo obrigatório: $field");
            }
        }
        
        // Validar políticas
        $this->validateAgainstPolicies($data);
        
        $this->db->beginTransaction();
        
        try {
            // Inserir rifa
            $sql = "INSERT INTO raffles (
                title, description, prize_description, prize_market_value, prize_images,
                number_price, number_quantity, max_numbers_per_cpf, regulation,
                start_sales_datetime, end_sales_datetime, draw_datetime,
                minimum_wait_hours, delivery_method, delivery_deadline, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
            
            $this->db->query($sql, [
                $data['title'],
                $data['description'],
                $data['prize_description'],
                $data['prize_market_value'] ?? 0,
                $data['prize_images'] ? json_encode($data['prize_images']) : null,
                $data['number_price'],
                $data['number_quantity'],
                $data['max_numbers_per_cpf'],
                $data['regulation'] ?? '',
                $data['start_sales_datetime'] ?? date('Y-m-d H:i:s'),
                $data['end_sales_datetime'],
                $data['draw_datetime'],
                $data['minimum_wait_hours'] ?? 24,
                $data['delivery_method'] ?? 'Presencial',
                $data['delivery_deadline'] ?? '30 dias após o sorteio'
            ]);
            
            $raffleId = $this->db->getConnection()->lastInsertId();
            
            // Gerar números
            $this->generateNumbers($raffleId, $data['number_quantity']);
            
            // Registrar log
            $this->logAudit('CREATE_RAFFLE', $raffleId, null, $data);
            
            $this->db->commit();
            
            return $raffleId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Gerar números da rifa
     */
    private function generateNumbers($raffleId, $quantity) {
        $sql = "INSERT INTO raffle_numbers (raffle_id, number, status) VALUES ";
        $values = [];
        $params = [];
        
        for ($i = 1; $i <= $quantity; $i++) {
            $values[] = "(?, ?, 'available')";
            $params[] = $raffleId;
            $params[] = $i;
        }
        
        $sql .= implode(',', $values);
        $this->db->query($sql, $params);
        
        return $quantity;
    }
    
    /**
     * Validar dados contra políticas
     */
    private function validateAgainstPolicies($data) {
        $systemPolicy = new SystemPolicy($this->db);
        
        // Validar quantidade de números
        $minNumbers = $systemPolicy->get('min_numbers_per_raffle');
        $maxNumbers = $systemPolicy->get('max_numbers_per_raffle');
        
        if ($data['number_quantity'] < $minNumbers) {
            throw new Exception("Quantidade de números abaixo do mínimo: $minNumbers");
        }
        
        if ($data['number_quantity'] > $maxNumbers) {
            throw new Exception("Quantidade de números acima do máximo: $maxNumbers");
        }
        
        // Validar preço
        $minPrice = $systemPolicy->get('min_number_price');
        $maxPrice = $systemPolicy->get('max_number_price');
        
        if ($data['number_price'] < $minPrice) {
            throw new Exception("Preço abaixo do mínimo: R$ $minPrice");
        }
        
        if ($data['number_price'] > $maxPrice) {
            throw new Exception("Preço acima do máximo: R$ $maxPrice");
        }
        
        // Validar limite por CPF
        $maxPerCpf = $systemPolicy->get('max_numbers_per_cpf');
        if (isset($data['max_numbers_per_cpf']) && $data['max_numbers_per_cpf'] > $maxPerCpf) {
            throw new Exception("Limite por CPF acima do máximo: $maxPerCpf");
        }
    }
    
    /**
     * Obter rifa por ID
     */
    public function getById($id) {
        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'paid') as paid_count,
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'reserved') as reserved_count,
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'available') as available_count
                FROM raffles r 
                WHERE r.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Listar rifas
     */
    public function getAll($page = 1, $limit = 20, $status = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'paid') as paid_count,
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id) as total_count
                FROM raffles r";
        
        $params = [];
        
        if ($status) {
            $sql .= " WHERE r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Atualizar rifa
     */
    public function update($id, $data) {
        $raffle = $this->getById($id);
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        // Verificar se pode editar
        if ($raffle['status'] !== 'draft') {
            throw new Exception("Apenas rascunhos podem ser editados");
        }
        
        // Validar dados
        $this->validateAgainstPolicies($data);
        
        $allowedFields = [
            'title', 'description', 'prize_description', 'prize_market_value', 'prize_images',
            'number_price', 'max_numbers_per_cpf', 'regulation',
            'start_sales_datetime', 'end_sales_datetime', 'draw_datetime',
            'minimum_wait_hours', 'delivery_method', 'delivery_deadline'
        ];
        
        $updateFields = [];
        $updateValues = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $field === 'prize_images' ? json_encode($value) : $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("Nenhum campo válido para atualizar");
        }
        
        $sql = "UPDATE raffles SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateValues[] = $id;
        
        $this->db->query($sql, $updateValues);
        
        // Se quantidade de números mudou, regerar
        if (isset($data['number_quantity']) && $data['number_quantity'] != $raffle['number_quantity']) {
            $this->regenerateNumbers($id, $data['number_quantity']);
            
            // Atualizar quantidade
            $sql = "UPDATE raffles SET number_quantity = ? WHERE id = ?";
            $this->db->query($sql, [$data['number_quantity'], $id]);
        }
        
        $this->logAudit('UPDATE_RAFFLE', $id, $raffle, $data);
        
        return true;
    }
    
    /**
     * Regenerar números
     */
    private function regenerateNumbers($raffleId, $newQuantity) {
        // Verificar se há números pagos
        $sql = "SELECT COUNT(*) as count FROM raffle_numbers WHERE raffle_id = ? AND status = 'paid'";
        $stmt = $this->db->query($sql, [$raffleId]);
        $paidCount = $stmt->fetch()['count'];
        
        if ($paidCount > 0) {
            throw new Exception("Não é possível regerar números com vendas realizadas");
        }
        
        // Remover números existentes
        $sql = "DELETE FROM raffle_numbers WHERE raffle_id = ?";
        $this->db->query($sql, [$raffleId]);
        
        // Gerar novos números
        $this->generateNumbers($raffleId, $newQuantity);
    }
    
    /**
     * Publicar rifa
     */
    public function publish($id) {
        $raffle = $this->getById($id);
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        if ($raffle['status'] !== 'draft') {
            throw new Exception("Apenas rascunhos podem ser publicados");
        }
        
        // Executar checklist
        $checklist = $this->runPublishChecklist($raffle);
        
        if (!$checklist['valid']) {
            throw new Exception("Rifa não pode ser publicada: " . implode(', ', $checklist['errors']));
        }
        
        // Atualizar status
        $sql = "UPDATE raffles SET status = 'active', published_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$id]);
        
        $this->logAudit('PUBLISH_RAFFLE', $id, ['status' => 'draft'], ['status' => 'active']);
        
        return true;
    }
    
    /**
     * Executar checklist de publicação
     */
    private function runPublishChecklist($raffle) {
        $errors = [];
        $warnings = [];
        
        // Verificar se todos os números foram gerados
        $sql = "SELECT COUNT(*) as count FROM raffle_numbers WHERE raffle_id = ?";
        $stmt = $this->db->query($sql, [$raffle['id']]);
        $generatedCount = $stmt->fetch()['count'];
        
        if ($generatedCount != $raffle['number_quantity']) {
            $errors[] = "Números não gerados completamente";
        }
        
        // Verificar se prêmio está configurado
        if (empty($raffle['prize_description'])) {
            $errors[] = "Descrição do prêmio não informada";
        }
        
        if (empty($raffle['prize_images'])) {
            $warnings[] = "Nenhuma imagem do prêmio cadastrada";
        }
        
        // Verificar datas
        if (empty($raffle['end_sales_datetime'])) {
            $errors[] = "Data de encerramento não definida";
        }
        
        if (empty($raffle['draw_datetime'])) {
            $errors[] = "Data do sorteio não definida";
        }
        
        // Verificar regulamento
        if (empty($raffle['regulation'])) {
            $warnings[] = "Regulamento não informado";
        }
        
        // Verificar integração Asaas
        $sql = "SELECT COUNT(*) as count FROM integrations WHERE integration_name = 'asaas' AND is_active = true";
        $stmt = $this->db->query($sql);
        $asaasActive = $stmt->fetch()['count'] > 0;
        
        if (!$asaasActive) {
            $errors[] = "Integração Asaas não configurada";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Encerrar rifa
     */
    public function close($id) {
        $raffle = $this->getById($id);
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        if ($raffle['status'] !== 'active') {
            throw new Exception("Apenas rifas ativas podem ser encerradas");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Cancelar reservas pendentes
            $sql = "UPDATE raffle_numbers 
                    SET status = 'available', 
                        participant_name = NULL,
                        participant_cpf = NULL,
                        participant_email = NULL,
                        participant_phone = NULL,
                        participant_address = NULL,
                        reservation_hash = NULL,
                        reservation_expires_at = NULL,
                        payment_id = NULL,
                        payment_amount = NULL,
                        user_id = NULL
                    WHERE raffle_id = ? AND status = 'reserved'";
            
            $this->db->query($sql, [$id]);
            
            // Atualizar status
            $sql = "UPDATE raffles SET status = 'sales_closed', end_sales_datetime = NOW() WHERE id = ?";
            $this->db->query($sql, [$id]);
            
            $this->logAudit('CLOSE_RAFFLE', $id, ['status' => 'active'], ['status' => 'sales_closed']);
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Executar sorteio
     */
    public function draw($id) {
        $raffle = $this->getById($id);
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        if ($raffle['status'] !== 'sales_closed') {
            throw new Exception("Rifa deve estar encerrada para sorteio");
        }
        
        // Verificar período de espera
        $endSales = new DateTime($raffle['end_sales_datetime']);
        $now = new DateTime();
        $minWaitHours = $raffle['minimum_wait_hours'];
        $requiredDrawTime = (clone $endSales)->add(new DateInterval("PT{$minWaitHours}H"));
        
        if ($now < $requiredDrawTime) {
            throw new Exception("Período de espera mínimo não cumprido");
        }
        
        // Obter números pagos
        $sql = "SELECT * FROM raffle_numbers WHERE raffle_id = ? AND status = 'paid'";
        $stmt = $this->db->query($sql, [$id]);
        $paidNumbers = $stmt->fetchAll();
        
        if (empty($paidNumbers)) {
            throw new Exception("Não há números pagos para sortear");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Selecionar vencedor
            $winnerIndex = random_int(0, count($paidNumbers) - 1);
            $winner = $paidNumbers[$winnerIndex];
            
            // Gerar hash de verificação
            $drawHash = $this->generateDrawHash($id, $paidNumbers, $winnerIndex);
            
            // Atualizar rifa
            $sql = "UPDATE raffles SET 
                    status = 'drawn',
                    winner_number = ?,
                    winner_cpf = ?,
                    winner_name = ?,
                    draw_hash = ?,
                    draw_datetime = NOW()
                    WHERE id = ?";
            
            $this->db->query($sql, [
                $winner['number'],
                $winner['participant_cpf'],
                $winner['participant_name'],
                $drawHash,
                $id
            ]);
            
            // Atualizar número vencedor
            $sql = "UPDATE raffle_numbers SET status = 'winner' WHERE id = ?";
            $this->db->query($sql, [$winner['id']]);
            
            $this->logAudit('DRAW_RAFFLE', $id, null, [
                'winner_number' => $winner['number'],
                'winner_cpf' => $winner['participant_cpf'],
                'draw_hash' => $drawHash
            ]);
            
            $this->db->commit();
            
            return [
                'winner_number' => $winner['number'],
                'winner_cpf' => $winner['participant_cpf'],
                'winner_name' => $winner['participant_name'],
                'draw_hash' => $drawHash
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Gerar hash do sorteio
     */
    private function generateDrawHash($raffleId, $paidNumbers, $winnerIndex) {
        $data = [
            'raffle_id' => $raffleId,
            'paid_numbers' => array_column($paidNumbers, 'number'),
            'winner_index' => $winnerIndex,
            'timestamp' => time(),
            'server_seed' => bin2hex(random_bytes(16))
        ];
        
        return hash('sha256', json_encode($data));
    }
    
    /**
     * Obter estatísticas da rifa
     */
    public function getStatistics($id) {
        $sql = "SELECT 
                    COUNT(*) as total_numbers,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_numbers,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_numbers,
                    SUM(CASE WHEN status = 'paid' THEN payment_amount ELSE 0 END) as total_revenue,
                    COUNT(DISTINCT participant_cpf) as unique_participants,
                    AVG(CASE WHEN status = 'paid' THEN payment_amount ELSE NULL END) as avg_ticket
                FROM raffle_numbers 
                WHERE raffle_id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        $stats = $stmt->fetch();
        
        // Calcular percentual
        $stats['sales_percentage'] = $stats['total_numbers'] > 0 
            ? round(($stats['paid_numbers'] / $stats['total_numbers']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Obter números disponíveis
     */
    public function getAvailableNumbers($id) {
        $sql = "SELECT number FROM raffle_numbers WHERE raffle_id = ? AND status = 'available' ORDER BY number";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Verificar disponibilidade de números
     */
    public function checkNumberAvailability($id, $numbers) {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }
        
        $placeholders = str_repeat('?,', count($numbers) - 1) . '?';
        $params = array_merge([$id], $numbers);
        
        $sql = "SELECT number, status FROM raffle_numbers 
                WHERE raffle_id = ? AND number IN ($placeholders)";
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll();
        
        $availability = [];
        foreach ($numbers as $number) {
            $availability[$number] = 'available'; // Default
            
            foreach ($results as $result) {
                if ($result['number'] == $number) {
                    $availability[$number] = $result['status'];
                    break;
                }
            }
        }
        
        return $availability;
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $raffleId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'raffles', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $raffleId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    /**
     * Obter rifas ativas para público
     */
    public function getActiveRaffles() {
        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'paid') as paid_count,
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id) as total_count
                FROM raffles r 
                WHERE r.status = 'active' 
                AND r.start_sales_datetime <= NOW() 
                AND r.end_sales_datetime > NOW()
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se rifa pode ser comprada
     */
    public function canBePurchased($id) {
        $sql = "SELECT status, start_sales_datetime, end_sales_datetime FROM raffles WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $raffle = $stmt->fetch();
        
        if (!$raffle) {
            return false;
        }
        
        if ($raffle['status'] !== 'active') {
            return false;
        }
        
        $now = new DateTime();
        $startSales = new DateTime($raffle['start_sales_datetime']);
        $endSales = new DateTime($raffle['end_sales_datetime']);
        
        return $now >= $startSales && $now < $endSales;
    }
}

?>
