<?php
/**
 * Modelo para gerenciar sorteios automáticos de rifas
 */

class Draw {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Realizar sorteio automático
     */
    public function performDraw($raffleId, $manual = false) {
        try {
            // Verificar se rifa existe e está pronta para sorteio
            $raffle = $this->getRaffle($raffleId);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            if ($raffle['status'] !== 'drawing' && !$manual) {
                throw new Exception("Rifa não está pronta para sorteio");
            }
            
            // Obter números pagos
            $paidNumbers = $this->getPaidNumbers($raffleId);
            if (empty($paidNumbers)) {
                throw new Exception("Não há números pagos para sortear");
            }
            
            // Gerar seed aleatória
            $seed = $this->generateSeed($raffleId, $paidNumbers);
            
            // Realizar sorteio com algoritmo criptográfico
            $winnerNumber = $this->cryptographicDraw($paidNumbers, $seed);
            
            // Registrar sorteio
            $drawId = $this->recordDraw($raffleId, $winnerNumber, $seed, $manual);
            
            // Atualizar status da rifa
            $this->updateRaffleStatus($raffleId, 'completed');
            
            // Marcar número como vencedor
            $this->markWinner($raffleId, $winnerNumber, $drawId);
            
            // Enviar notificações
            $this->sendDrawNotifications($raffleId, $winnerNumber, $drawId);
            
            // Registrar auditoria
            $this->logDraw($raffleId, $drawId, $winnerNumber, $seed, $manual);
            
            return [
                'success' => true,
                'draw_id' => $drawId,
                'winner_number' => $winnerNumber,
                'seed' => $seed,
                'participant' => $this->getWinnerInfo($raffleId, $winnerNumber)
            ];
            
        } catch (Exception $e) {
            // Registrar erro
            $this->logDrawError($raffleId, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Gerar seed para sorteio
     */
    private function generateSeed($raffleId, $paidNumbers) {
        // Combinar múltiplas fontes de entropia
        $sources = [
            time(), // Timestamp atual
            microtime(true), // Microtime
            uniqid(), // ID único
            $raffleId, // ID da rifa
            count($paidNumbers), // Quantidade de números
            random_bytes(32), // Bytes aleatórios
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', // IP do servidor
            gethostname(), // Nome do host
            php_uname(), // Informações do sistema
            memory_get_usage(), // Uso de memória
            getmypid(), // ID do processo
        ];
        
        // Concatenar todas as fontes
        $entropy = implode('|', $sources);
        
        // Gerar hash SHA-256 como seed
        return hash('sha256', $entropy);
    }
    
    /**
     * Algoritmo de sorteio criptográfico
     */
    private function cryptographicDraw($paidNumbers, $seed) {
        // Converter seed para array de bytes
        $seedBytes = hex2bin($seed);
        
        // Usar HMAC-SHA256 para gerar sequência pseudoaleatória
        $sequence = [];
        for ($i = 0; $i < count($paidNumbers); $i++) {
            $hmac = hash_hmac('sha256', $seedBytes . $i, $seedBytes);
            $sequence[] = hexdec(substr($hmac, 0, 8)) % 1000000;
        }
        
        // Ordenar números pela sequência pseudoaleatória
        array_multisort($sequence, SORT_ASC, $paidNumbers);
        
        // O primeiro número da sequência ordenada é o vencedor
        return $paidNumbers[0];
    }
    
    /**
     * Verificar integridade do sorteio
     */
    public function verifyDraw($drawId) {
        try {
            $draw = $this->getDraw($drawId);
            if (!$draw) {
                throw new Exception("Sorteio não encontrado");
            }
            
            // Obter números pagos na época do sorteio
            $paidNumbers = $this->getPaidNumbersAtTime($draw['raffle_id'], $draw['created_at']);
            
            // Re-executar algoritmo com a mesma seed
            $calculatedWinner = $this->cryptographicDraw($paidNumbers, $draw['seed']);
            
            // Verificar se o resultado é o mesmo
            $isValid = ($calculatedWinner == $draw['winner_number']);
            
            return [
                'valid' => $isValid,
                'original_winner' => $draw['winner_number'],
                'calculated_winner' => $calculatedWinner,
                'seed' => $draw['seed'],
                'draw_time' => $draw['created_at'],
                'numbers_count' => count($paidNumbers)
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Agendar sorteio automático
     */
    public function scheduleDraw($raffleId, $drawTime) {
        $sql = "INSERT INTO scheduled_draws (raffle_id, draw_time, status, created_at) 
                VALUES (?, ?, 'scheduled', NOW())";
        
        $this->db->query($sql, [$raffleId, $drawTime]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Executar sorteios agendados
     */
    public function executeScheduledDraws() {
        $sql = "SELECT * FROM scheduled_draws 
                WHERE status = 'scheduled' 
                AND draw_time <= NOW() 
                ORDER BY draw_time ASC";
        
        $stmt = $this->db->query($sql);
        $scheduledDraws = $stmt->fetchAll();
        
        $executed = [];
        
        foreach ($scheduledDraws as $scheduled) {
            try {
                $result = $this->performDraw($scheduled['raffle_id'], false);
                
                // Atualizar status do agendamento
                $this->updateScheduledDrawStatus($scheduled['id'], 'executed', $result['draw_id']);
                
                $executed[] = [
                    'scheduled_id' => $scheduled['id'],
                    'raffle_id' => $scheduled['raffle_id'],
                    'draw_id' => $result['draw_id'],
                    'winner' => $result['winner_number']
                ];
                
            } catch (Exception $e) {
                // Atualizar status para erro
                $this->updateScheduledDrawStatus($scheduled['id'], 'error', null, $e->getMessage());
                
                $executed[] = [
                    'scheduled_id' => $scheduled['id'],
                    'raffle_id' => $scheduled['raffle_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $executed;
    }
    
    /**
     * Obter rifas prontas para sorteio
     */
    public function getRafflesReadyForDraw() {
        $sql = "SELECT r.* FROM raffles r
                WHERE r.status = 'drawing'
                AND r.draw_datetime <= NOW()
                AND NOT EXISTS (
                    SELECT 1 FROM draws d WHERE d.raffle_id = r.id
                )
                ORDER BY r.draw_datetime ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter histórico de sorteios
     */
    public function getDrawHistory($raffleId = null, $limit = 50) {
        $sql = "SELECT d.*, r.title as raffle_title,
                       rn.participant_name, rn.participant_cpf, rn.participant_email
                FROM draws d
                JOIN raffles r ON d.raffle_id = r.id
                JOIN raffle_numbers rn ON d.winner_number = rn.number AND rn.raffle_id = r.id
                WHERE 1=1";
        
        $params = [];
        
        if ($raffleId) {
            $sql .= " AND d.raffle_id = ?";
            $params[] = $raffleId;
        }
        
        $sql .= " ORDER BY d.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de sorteios
     */
    public function getDrawStatistics($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    COUNT(*) as total_draws,
                    COUNT(DISTINCT raffle_id) as unique_raffles,
                    AVG(TIMESTAMPDIFF(SECOND, r.draw_datetime, d.created_at)) as avg_delay_seconds,
                    COUNT(CASE WHEN d.manual = 1 THEN 1 END) as manual_draws,
                    COUNT(CASE WHEN d.manual = 0 THEN 1 END) as automatic_draws
                FROM draws d
                JOIN raffles r ON d.raffle_id = r.id
                WHERE 1=1";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND d.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND d.created_at <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Cancelar sorteio
     */
    public function cancelDraw($drawId, $reason) {
        $draw = $this->getDraw($drawId);
        if (!$draw) {
            throw new Exception("Sorteio não encontrado");
        }
        
        // Verificar se pode cancelar
        $drawTime = strtotime($draw['created_at']);
        $now = time();
        $hoursDiff = ($now - $drawTime) / 3600;
        
        if ($hoursDiff > 24) {
            throw new Exception("Sorteios com mais de 24 horas não podem ser cancelados");
        }
        
        // Iniciar transação
        $this->db->beginTransaction();
        
        try {
            // Marcar sorteio como cancelado
            $sql = "UPDATE draws SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() 
                    WHERE id = ?";
            $this->db->query($sql, [$reason, $drawId]);
            
            // Desmarcar número como vencedor
            $sql = "UPDATE raffle_numbers SET is_winner = 0, winner_draw_id = NULL 
                    WHERE raffle_id = ? AND number = ?";
            $this->db->query($sql, [$draw['raffle_id'], $draw['winner_number']]);
            
            // Voltar status da rifa para drawing
            $sql = "UPDATE raffles SET status = 'drawing' WHERE id = ?";
            $this->db->query($sql, [$draw['raffle_id']]);
            
            // Registrar auditoria
            $this->logDrawCancellation($drawId, $reason);
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Reexecutar sorteio (em caso de erro)
     */
    public function redraw($raffleId, $reason) {
        // Verificar se existe sorteio anterior
        $existingDraw = $this->getLatestDraw($raffleId);
        
        if ($existingDraw) {
            // Cancelar sorteio anterior
            $this->cancelDraw($existingDraw['id'], "Redraw: " . $reason);
        }
        
        // Realizar novo sorteio
        return $this->performDraw($raffleId, true);
    }
    
    /**
     * Obter rifa
     */
    private function getRaffle($raffleId) {
        $sql = "SELECT * FROM raffles WHERE id = ?";
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetch();
    }
    
    /**
     * Obter números pagos
     */
    private function getPaidNumbers($raffleId) {
        $sql = "SELECT number FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'paid' 
                ORDER BY number ASC";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        $numbers = [];
        
        while ($row = $stmt->fetch()) {
            $numbers[] = $row['number'];
        }
        
        return $numbers;
    }
    
    /**
     * Obter números pagos em determinado tempo
     */
    private function getPaidNumbersAtTime($raffleId, $timestamp) {
        $sql = "SELECT number FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'paid' 
                AND updated_at <= ?
                ORDER BY number ASC";
        
        $stmt = $this->db->query($sql, [$raffleId, $timestamp]);
        $numbers = [];
        
        while ($row = $stmt->fetch()) {
            $numbers[] = $row['number'];
        }
        
        return $numbers;
    }
    
    /**
     * Registrar sorteio
     */
    private function recordDraw($raffleId, $winnerNumber, $seed, $manual) {
        $sql = "INSERT INTO draws (raffle_id, winner_number, seed, manual, status, created_at) 
                VALUES (?, ?, ?, ?, 'completed', NOW())";
        
        $this->db->query($sql, [$raffleId, $winnerNumber, $seed, $manual ? 1 : 0]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Atualizar status da rifa
     */
    private function updateRaffleStatus($raffleId, $status) {
        $sql = "UPDATE raffles SET status = ?, updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$status, $raffleId]);
    }
    
    /**
     * Marcar número como vencedor
     */
    private function markWinner($raffleId, $winnerNumber, $drawId) {
        $sql = "UPDATE raffle_numbers 
                SET is_winner = 1, winner_draw_id = ?, updated_at = NOW() 
                WHERE raffle_id = ? AND number = ?";
        
        $this->db->query($sql, [$drawId, $raffleId, $winnerNumber]);
    }
    
    /**
     * Obter informações do vencedor
     */
    private function getWinnerInfo($raffleId, $winnerNumber) {
        $sql = "SELECT participant_name, participant_cpf, participant_email, participant_phone 
                FROM raffle_numbers 
                WHERE raffle_id = ? AND number = ? AND status = 'paid'";
        
        $stmt = $this->db->query($sql, [$raffleId, $winnerNumber]);
        return $stmt->fetch();
    }
    
    /**
     * Enviar notificações do sorteio
     */
    private function sendDrawNotifications($raffleId, $winnerNumber, $drawId) {
        $raffle = $this->getRaffle($raffleId);
        $winner = $this->getWinnerInfo($raffleId, $winnerNumber);
        
        if (!$winner) {
            return;
        }
        
        // Enviar notificação para o vencedor
        $this->sendWinnerNotification($raffle, $winner, $drawId);
        
        // Enviar notificações para outros participantes
        $this->sendParticipantsNotification($raffleId, $winnerNumber, $drawId);
        
        // Enviar notificação para administradores
        $this->sendAdminNotification($raffle, $winner, $drawId);
    }
    
    /**
     * Enviar notificação para o vencedor
     */
    private function sendWinnerNotification($raffle, $winner, $drawId) {
        $notification = new Notification($this->db);
        
        $notification->createNotification(
            'email',
            $winner['participant_email'],
            '🎉 PARABÉNS! VOCÊ GANHOU!',
            "Parabéns {$winner['participant_name']}! Você foi o grande vencedor da rifa '{$raffle['title']}'. Seu número foi o {$winner['participant_cpf']}. Entraremos em contato em breve.",
            ['winner_name' => $winner['participant_name'], 'raffle_title' => $raffle['title']],
            'winner'
        );
    }
    
    /**
     * Enviar notificação para participantes
     */
    private function sendParticipantsNotification($raffleId, $winnerNumber, $drawId) {
        $notification = new Notification($this->db);
        
        // Obter todos os participantes (exceto o vencedor)
        $sql = "SELECT DISTINCT participant_email, participant_name 
                FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'paid' AND number != ?";
        
        $stmt = $this->db->query($sql, [$raffleId, $winnerNumber]);
        
        while ($participant = $stmt->fetch()) {
            $notification->createNotification(
                'email',
                $participant['participant_email'],
                '🎯 Sorteio Realizado',
                "O sorteio da rifa foi realizado e o número vencedor foi {$winnerNumber}. Agradecemos sua participação!",
                ['participant_name' => $participant['participant_name'], 'winner_number' => $winnerNumber],
                'draw_completed'
            );
        }
    }
    
    /**
     * Enviar notificação para administradores
     */
    private function sendAdminNotification($raffle, $winner, $drawId) {
        $notification = new Notification($this->db);
        
        $notification->createNotification(
            'email',
            'contato@onsolutionsbrasil.com.br',
            '🎯 Sorteio Realizado - BRZ Rifa',
            "Sorteio da rifa '{$raffle['title']}' realizado com sucesso. Vencedor: {$winner['participant_name']} (CPF: {$winner['participant_cpf']}).",
            ['raffle_title' => $raffle['title'], 'winner_info' => $winner],
            'admin_draw'
        );
    }
    
    /**
     * Registrar auditoria do sorteio
     */
    private function logDraw($raffleId, $drawId, $winnerNumber, $seed, $manual) {
        $audit = new AuditLog($this->db);
        
        $audit->log(
            'draw_performed',
            "Sorteio realizado - Rifa: {$raffleId}, Vencedor: {$winnerNumber}, Manual: " . ($manual ? 'Sim' : 'Não'),
            [
                'raffle_id' => $raffleId,
                'draw_id' => $drawId,
                'winner_number' => $winnerNumber,
                'seed' => $seed,
                'manual' => $manual
            ]
        );
    }
    
    /**
     * Registrar erro de sorteio
     */
    private function logDrawError($raffleId, $error) {
        $audit = new AuditLog($this->db);
        
        $audit->log(
            'draw_error',
            "Erro no sorteio - Rifa: {$raffleId}, Erro: {$error}",
            [
                'raffle_id' => $raffleId,
                'error' => $error
            ]
        );
    }
    
    /**
     * Registrar cancelamento de sorteio
     */
    private function logDrawCancellation($drawId, $reason) {
        $audit = new AuditLog($this->db);
        
        $audit->log(
            'draw_cancelled',
            "Sorteio cancelado - ID: {$drawId}, Motivo: {$reason}",
            [
                'draw_id' => $drawId,
                'reason' => $reason
            ]
        );
    }
    
    /**
     * Obter sorteio
     */
    private function getDraw($drawId) {
        $sql = "SELECT * FROM draws WHERE id = ?";
        $stmt = $this->db->query($sql, [$drawId]);
        return $stmt->fetch();
    }
    
    /**
     * Obter último sorteio da rifa
     */
    private function getLatestDraw($raffleId) {
        $sql = "SELECT * FROM draws WHERE raffle_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetch();
    }
    
    /**
     * Atualizar status de agendamento
     */
    private function updateScheduledDrawStatus($scheduledId, $status, $drawId = null, $error = null) {
        $sql = "UPDATE scheduled_draws 
                SET status = ?, draw_id = ?, error_message = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $this->db->query($sql, [$status, $drawId, $error, $scheduledId]);
    }
    
    /**
     * Limpar sorteios antigos
     */
    public function cleanupOldDraws($days = 365) {
        $sql = "DELETE FROM draws 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'completed'";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Gerar relatório de sorteios
     */
    public function generateDrawReport($dateFrom = null, $dateTo = null) {
        $draws = $this->getDrawHistory(null, 1000);
        $statistics = $this->getDrawStatistics($dateFrom, $dateTo);
        
        return [
            'draws' => $draws,
            'statistics' => $statistics,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
}

?>
