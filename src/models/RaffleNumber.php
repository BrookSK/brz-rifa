<?php
/**
 * Modelo para gerenciar números das rifas
 */

class RaffleNumber {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Gerar números para uma rifa
     */
    public function generateNumbers($raffleId, $quantity) {
        $this->db->beginTransaction();
        
        try {
            // Verificar se já existem números
            $sql = "SELECT COUNT(*) as count FROM raffle_numbers WHERE raffle_id = ?";
            $stmt = $this->db->query($sql, [$raffleId]);
            $existingCount = $stmt->fetch()['count'];
            
            if ($existingCount > 0) {
                throw new Exception("Rifa já possui números gerados");
            }
            
            // Gerar números sequenciais
            $sql = "INSERT INTO raffle_numbers (raffle_id, number, status, created_at) VALUES ";
            $values = [];
            $params = [];
            
            for ($i = 1; $i <= $quantity; $i++) {
                $values[] = "(?, ?, 'available', NOW())";
                $params[] = $raffleId;
                $params[] = $i;
            }
            
            $sql .= implode(',', $values);
            $this->db->query($sql, $params);
            
            // Atualizar quantidade na rifa
            $sql = "UPDATE raffles SET number_quantity = ? WHERE id = ?";
            $this->db->query($sql, [$quantity, $raffleId]);
            
            $this->db->commit();
            
            return $quantity;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Obter número por ID
     */
    public function getById($id) {
        $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                FROM raffle_numbers rn
                LEFT JOIN raffles r ON rn.raffle_id = r.id
                WHERE rn.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter número por rifa e número
     */
    public function getByRaffleAndNumber($raffleId, $number) {
        $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                FROM raffle_numbers rn
                LEFT JOIN raffles r ON rn.raffle_id = r.id
                WHERE rn.raffle_id = ? AND rn.number = ?";
        
        $stmt = $this->db->query($sql, [$raffleId, $number]);
        return $stmt->fetch();
    }
    
    /**
     * Listar números de uma rifa
     */
    public function getByRaffle($raffleId, $status = null, $participantCpf = null) {
        $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                FROM raffle_numbers rn
                LEFT JOIN raffles r ON rn.raffle_id = r.id
                WHERE rn.raffle_id = ?";
        
        $params = [$raffleId];
        
        if ($status) {
            $sql .= " AND rn.status = ?";
            $params[] = $status;
        }
        
        if ($participantCpf) {
            $sql .= " AND rn.participant_cpf = ?";
            $params[] = $participantCpf;
        }
        
        $sql .= " ORDER BY rn.number";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar disponibilidade de números
     */
    public function checkAvailability($raffleId, $numbers) {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }
        
        $placeholders = str_repeat('?,', count($numbers) - 1) . '?';
        $params = array_merge([$raffleId], $numbers);
        
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
     * Reservar números
     */
    public function reserve($raffleId, $numbers, $participantData, $reservationHash, $expiresInMinutes = 10) {
        $this->db->beginTransaction();
        
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));
            
            foreach ($numbers as $number) {
                $sql = "UPDATE raffle_numbers 
                        SET status = 'reserved',
                            participant_name = ?,
                            participant_cpf = ?,
                            participant_email = ?,
                            participant_phone = ?,
                            participant_address = ?,
                            reservation_hash = ?,
                            reservation_expires_at = ?
                        WHERE raffle_id = ? AND number = ? AND status = 'available'";
                
                $this->db->query($sql, [
                    $participantData['name'],
                    $participantData['cpf'],
                    $participantData['email'],
                    $participantData['phone'],
                    $participantData['address'],
                    $reservationHash,
                    $expiresAt,
                    $raffleId,
                    $number
                ]);
            }
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Confirmar pagamento de números
     */
    public function confirmPayment($raffleId, $numbers, $participantData, $paymentId, $amount) {
        $this->db->beginTransaction();
        
        try {
            $paidAt = date('Y-m-d H:i:s');
            
            foreach ($numbers as $numberId) {
                $sql = "UPDATE raffle_numbers 
                        SET status = 'paid',
                            participant_name = ?,
                            participant_cpf = ?,
                            participant_email = ?,
                            participant_phone = ?,
                            participant_address = ?,
                            payment_id = ?,
                            payment_amount = ?,
                            paid_at = ?
                        WHERE raffle_id = ? AND id = ? AND status IN ('reserved', 'available')";
                
                $this->db->query($sql, [
                    $participantData['name'],
                    $participantData['cpf'],
                    $participantData['email'],
                    $participantData['phone'],
                    $participantData['address'],
                    $paymentId,
                    $amount,
                    $paidAt,
                    $raffleId,
                    $numberId
                ]);
            }
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Liberar números reservados
     */
    public function releaseReservation($raffleId, $numbers) {
        $this->db->beginTransaction();
        
        try {
            foreach ($numbers as $number) {
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
                        WHERE raffle_id = ? AND number = ? AND status = 'reserved'";
                
                $this->db->query($sql, [$raffleId, $number]);
            }
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Marcar número como vencedor
     */
    public function markAsWinner($raffleId, $number) {
        $sql = "UPDATE raffle_numbers 
                SET status = 'winner', winner_at = NOW()
                WHERE raffle_id = ? AND number = ?";
        
        $this->db->query($sql, [$raffleId, $number]);
        
        return true;
    }
    
    /**
     * Limpar reservas expiradas
     */
    public function cleanupExpiredReservations() {
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
                WHERE status = 'reserved' 
                AND reservation_expires_at < NOW()";
        
        $stmt = $this->db->query($sql);
        return $stmt->rowCount();
    }
    
    /**
     * Obter estatísticas dos números
     */
    public function getStatistics($raffleId) {
        $sql = "SELECT 
                    COUNT(*) as total_numbers,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_numbers,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_numbers,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN status = 'paid' THEN payment_amount ELSE 0 END) as total_revenue,
                    COUNT(DISTINCT participant_cpf) as unique_participants,
                    AVG(CASE WHEN status = 'paid' THEN payment_amount ELSE NULL END) as avg_ticket_price
                FROM raffle_numbers 
                WHERE raffle_id = ?";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetch();
    }
    
    /**
     * Obter números disponíveis
     */
    public function getAvailableNumbers($raffleId) {
        $sql = "SELECT number FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'available' 
                ORDER BY number";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Obter números pagos
     */
    public function getPaidNumbers($raffleId) {
        $sql = "SELECT rn.*, p.name as participant_name, p.email as participant_email
                FROM raffle_numbers rn
                LEFT JOIN participants p ON rn.participant_cpf = p.cpf
                WHERE rn.raffle_id = ? AND rn.status = 'paid'
                ORDER BY rn.number";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter números reservados
     */
    public function getReservedNumbers($raffleId) {
        $sql = "SELECT * FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'reserved' 
                ORDER BY reservation_expires_at";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se número pode ser comprado
     */
    public function canBePurchased($raffleId, $number) {
        $numberData = $this->getByRaffleAndNumber($raffleId, $number);
        
        if (!$numberData) {
            return false;
        }
        
        // Verificar se rifa está ativa
        if ($numberData['raffle_status'] !== 'active') {
            return false;
        }
        
        // Verificar status do número
        return $numberData['status'] === 'available';
    }
    
    /**
     * Obter IDs dos números
     */
    public function getNumberIds($raffleId, $numbers) {
        $placeholders = str_repeat('?,', count($numbers) - 1) . '?';
        $params = array_merge([$raffleId], $numbers);
        
        $sql = "SELECT id FROM raffle_numbers 
                WHERE raffle_id = ? AND number IN ($placeholders)";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Atualizar status em lote
     */
    public function updateStatusBatch($numberIds, $status, $additionalData = []) {
        if (empty($numberIds)) {
            return 0;
        }
        
        $placeholders = str_repeat('?,', count($numberIds) - 1) . '?';
        $params = array_merge($numberIds, [$status]);
        
        $sql = "UPDATE raffle_numbers SET status = ?";
        
        // Adicionar dados adicionais se fornecidos
        foreach ($additionalData as $field => $value) {
            $sql .= ", $field = ?";
            $params[] = $value;
        }
        
        $sql .= " WHERE id IN ($placeholders)";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Obter números por participante
     */
    public function getByParticipant($participantCpf) {
        $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                FROM raffle_numbers rn
                LEFT JOIN raffles r ON rn.raffle_id = r.id
                WHERE rn.participant_cpf = ?
                ORDER BY rn.created_at DESC";
        
        $stmt = $this->db->query($sql, [$participantCpf]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter mapa de status
     */
    public function getStatusMap($raffleId) {
        $sql = "SELECT status, COUNT(*) as count
                FROM raffle_numbers
                WHERE raffle_id = ?
                GROUP BY status";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        $results = $stmt->fetchAll();
        
        $statusMap = [];
        foreach ($results as $result) {
            $statusMap[$result['status']] = $result['count'];
        }
        
        return $statusMap;
    }
    
    /**
     * Validação de negócio para seleção de números
     */
    public function validateSelection($raffleId, $selectedNumbers, $participantCpf) {
        // Obter informações da rifa
        $raffleModel = new Raffle($this->db);
        $raffle = $raffleModel->getById($raffleId);
        
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        // Verificar se rifa está ativa
        if ($raffle['status'] !== 'active') {
            throw new Exception("Rifa não está ativa para compras");
        }
        
        // Verificar se não ultrapassa o limite
        $participantModel = new Participant($this->db);
        $participantModel->checkPurchaseLimit($participantCpf, $raffleId, count($selectedNumbers));
        
        // Verificar disponibilidade
        $availability = $this->checkAvailability($raffleId, $selectedNumbers);
        
        foreach ($selectedNumbers as $number) {
            if ($availability[$number] !== 'available') {
                throw new Exception("Número $number não está disponível");
            }
        }
        
        return true;
    }
}

?>
