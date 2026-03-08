<?php
/**
 * Modelo para gerenciar participantes
 */

class Participant {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Criar ou obter participante
     */
    public function getOrCreate($cpf, $name, $email, $phone, $address) {
        // Validar CPF
        if (!$this->isValidCPF($cpf)) {
            throw new Exception("CPF inválido");
        }
        
        // Buscar participante existente
        $sql = "SELECT * FROM participants WHERE cpf = ?";
        $stmt = $this->db->query($sql, [$cpf]);
        $participant = $stmt->fetch();
        
        if ($participant) {
            // Atualizar dados se necessário
            $this->updateParticipantData($participant['id'], $name, $email, $phone, $address);
            return $participant;
        }
        
        // Verificar antifraude
        $fraudScore = $this->calculateFraudScore($cpf, $name, $email, $phone);
        
        if ($fraudScore > 70) {
            throw new Exception("Participante bloqueado por suspeita de fraude");
        }
        
        // Criar novo participante
        $sql = "INSERT INTO participants (cpf, name, email, phone, address, fraud_score, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $status = $fraudScore > 50 ? 'suspicious' : 'active';
        
        $this->db->query($sql, [
            $cpf,
            $name,
            $email,
            $phone,
            $address,
            $fraudScore,
            $status
        ]);
        
        $participantId = $this->db->getConnection()->lastInsertId();
        
        // Registrar tentativa
        $this->logAttempt($cpf, 'registration', $fraudScore);
        
        return $this->getById($participantId);
    }
    
    /**
     * Atualizar dados do participante
     */
    private function updateParticipantData($id, $name, $email, $phone, $address) {
        $sql = "UPDATE participants SET 
                name = COALESCE(?, name),
                email = COALESCE(?, email),
                phone = COALESCE(?, phone),
                address = COALESCE(?, address),
                updated_at = NOW()
                WHERE id = ?";
        
        $this->db->query($sql, [$name, $email, $phone, $address, $id]);
    }
    
    /**
     * Obter participante por ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM participants WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter participante por CPF
     */
    public function getByCpf($cpf) {
        $sql = "SELECT * FROM participants WHERE cpf = ?";
        $stmt = $this->db->query($sql, [$cpf]);
        return $stmt->fetch();
    }
    
    /**
     * Listar participantes
     */
    public function getAll($page = 1, $limit = 20, $status = null, $search = null) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM participants";
        $params = [];
        $where = [];
        
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $where[] = "(cpf LIKE ? OR name LIKE ? OR email LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Validar CPF
     */
    private function isValidCPF($cpf) {
        // Remover caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verificar quantidade de dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Calcular dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calcular score de fraude
     */
    private function calculateFraudScore($cpf, $name, $email, $phone) {
        $score = 0;
        
        // Verificar CPFs repetidos recentemente
        $sql = "SELECT COUNT(*) as count FROM fraud_attempts 
                WHERE cpf = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->query($sql, [$cpf]);
        $cpfAttempts = $stmt->fetch()['count'];
        
        if ($cpfAttempts > 0) {
            $score += min($cpfAttempts * 15, 30);
        }
        
        // Verificar e-mails repetidos
        $sql = "SELECT COUNT(*) as count FROM fraud_attempts 
                WHERE JSON_EXTRACT(details, '$.email') = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->query($sql, [$email]);
        $emailAttempts = $stmt->fetch()['count'];
        
        if ($emailAttempts > 0) {
            $score += min($emailAttempts * 10, 20);
        }
        
        // Verificar telefones repetidos
        $sql = "SELECT COUNT(*) as count FROM fraud_attempts 
                WHERE JSON_EXTRACT(details, '$.phone') = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->query($sql, [$phone]);
        $phoneAttempts = $stmt->fetch()['count'];
        
        if ($phoneAttempts > 0) {
            $score += min($phoneAttempts * 10, 20);
        }
        
        // Verificar padrões suspeitos no nome
        if (strlen($name) < 5) {
            $score += 10;
        }
        
        if (!preg_match('/[A-Z][a-z]+ [A-Z][a-z]+/', $name)) {
            $score += 5;
        }
        
        // Verificar e-mail descartável
        if ($this->isDisposableEmail($email)) {
            $score += 25;
        }
        
        // Verificar se já participou de rifas anteriores
        $sql = "SELECT COUNT(*) as count FROM participants WHERE cpf = ? AND status = 'active'";
        $stmt = $this->db->query($sql, [$cpf]);
        $previousParticipations = $stmt->fetch()['count'];
        
        if ($previousParticipations > 5) {
            $score += min($previousParticipations * 2, 10);
        }
        
        return min($score, 100);
    }
    
    /**
     * Verificar se e-mail é descartável
     */
    private function isDisposableEmail($email) {
        $domain = substr(strrchr($email, "@"), 1);
        
        $disposableDomains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.org',
            'throwaway.email', 'yopmail.com', 'maildrop.cc', 'temp-mail.org'
        ];
        
        return in_array(strtolower($domain), $disposableDomains);
    }
    
    /**
     * Registrar tentativa
     */
    private function logAttempt($cpf, $type, $score) {
        $details = [
            'cpf' => $cpf,
            'type' => $type,
            'score' => $score,
            'timestamp' => time()
        ];
        
        $sql = "INSERT INTO fraud_attempts (cpf, attempt_type, risk_score, details, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $cpf,
            $type,
            $score,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'CLI'
        ]);
    }
    
    /**
     * Verificar limite de compras por CPF
     */
    public function checkPurchaseLimit($cpf, $raffleId, $quantity) {
        // Obter limite configurado
        $systemPolicy = new SystemPolicy($this->db);
        $maxPerCpf = $systemPolicy->get('max_numbers_per_cpf');
        
        // Verificar compras já realizadas
        $sql = "SELECT COUNT(*) as count FROM raffle_numbers 
                WHERE participant_cpf = ? AND raffle_id = ? AND status IN ('paid', 'reserved')";
        $stmt = $this->db->query($sql, [$cpf, $raffleId]);
        $currentCount = $stmt->fetch()['count'];
        
        if (($currentCount + $quantity) > $maxPerCpf) {
            throw new Exception("Limite de $maxPerCpf números por CPF excedido. Você já tem $currentCount números.");
        }
        
        return true;
    }
    
    /**
     * Verificar reservas recentes
     */
    public function checkRecentReservations($cpf, $maxReservations = 3) {
        $systemPolicy = new SystemPolicy($this->db);
        $configuredMax = $systemPolicy->get('max_reservations_per_hour', 3);
        $maxReservations = min($maxReservations, $configuredMax);
        
        $sql = "SELECT COUNT(*) as count FROM raffle_numbers 
                WHERE participant_cpf = ? AND status = 'reserved' 
                AND reservation_expires_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->db->query($sql, [$cpf]);
        $recentReservations = $stmt->fetch()['count'];
        
        if ($recentReservations >= $maxReservations) {
            throw new Exception("Muitas reservas recentes. Aguarde algumas horas para tentar novamente.");
        }
        
        return true;
    }
    
    /**
     * Suspender participante
     */
    public function suspend($id, $reason) {
        $sql = "UPDATE participants SET status = 'suspended', suspension_reason = ?, suspended_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$reason, $id]);
        
        // Cancelar reservas ativas
        $participant = $this->getById($id);
        if ($participant) {
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
                    WHERE participant_cpf = ? AND status = 'reserved'";
            
            $this->db->query($sql, [$participant['cpf']]);
        }
        
        $this->logAudit('SUSPEND_PARTICIPANT', $id, ['status' => 'active'], ['status' => 'suspended', 'reason' => $reason]);
        
        return true;
    }
    
    /**
     * Reativar participante
     */
    public function reactivate($id) {
        $sql = "UPDATE participants SET status = 'active', suspension_reason = NULL, suspended_at = NULL WHERE id = ?";
        $this->db->query($sql, [$id]);
        
        $this->logAudit('REACTIVATE_PARTICIPANT', $id, ['status' => 'suspended'], ['status' => 'active']);
        
        return true;
    }
    
    /**
     * Bloquear participante
     */
    public function block($id, $reason) {
        $sql = "UPDATE participants SET status = 'blocked', block_reason = ?, blocked_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$reason, $id]);
        
        // Cancelar todas as transações
        $participant = $this->getById($id);
        if ($participant) {
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
                    WHERE participant_cpf = ? AND status IN ('reserved', 'paid')";
            
            $this->db->query($sql, [$participant['cpf']]);
        }
        
        $this->logAudit('BLOCK_PARTICIPANT', $id, ['status' => 'active'], ['status' => 'blocked', 'reason' => $reason]);
        
        return true;
    }
    
    /**
     * Atualizar score de fraude
     */
    public function updateFraudScore($id, $score) {
        $sql = "UPDATE participants SET fraud_score = ?, updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$score, $id]);
        
        // Atualizar status se necessário
        if ($score > 70) {
            $this->suspend($id, 'Score de fraude elevado');
        } elseif ($score > 50) {
            $sql = "UPDATE participants SET status = 'suspicious' WHERE id = ?";
            $this->db->query($sql, [$id]);
        }
        
        return true;
    }
    
    /**
     * Obter estatísticas do participante
     */
    public function getStatistics($id) {
        $participant = $this->getById($id);
        if (!$participant) {
            return null;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN rn.status = 'reserved' THEN 1 ELSE 0 END) as reserved_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE 0 END) as total_spent,
                    COUNT(DISTINCT rn.raffle_id) as unique_raffles,
                    MAX(rn.created_at) as last_activity
                FROM raffle_numbers rn 
                WHERE rn.participant_cpf = ?";
        
        $stmt = $this->db->query($sql, [$participant['cpf']]);
        $stats = $stmt->fetch();
        
        // Adicionar informações do participante
        $stats['cpf'] = $participant['cpf'];
        $stats['name'] = $participant['name'];
        $stats['email'] = $participant['email'];
        $stats['fraud_score'] = $participant['fraud_score'];
        $stats['status'] = $participant['status'];
        $stats['created_at'] = $participant['created_at'];
        
        return $stats;
    }
    
    /**
     * Listar participantes suspeitos
     */
    public function getSuspiciousParticipants($limit = 50) {
        $sql = "SELECT p.*, 
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpfs) as total_numbers
                FROM participants p 
                WHERE p.fraud_score > 50 OR p.status = 'suspicious'
                ORDER BY p.fraud_score DESC, p.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Limpar participantes antigos
     */
    public function cleanupOldParticipants($days = 365) {
        $sql = "DELETE FROM participants 
                WHERE status = 'active' 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND id NOT IN (
                    SELECT DISTINCT participant_id 
                    FROM raffle_numbers 
                    WHERE status IN ('paid', 'reserved')
                )";
        
        $this->db->query($sql, [$days]);
        
        return $this->db->getConnection()->rowCount();
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $participantId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'participants', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $participantId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    /**
     * Obter histórico de participante
     */
    public function getHistory($cpf) {
        $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                FROM raffle_numbers rn
                LEFT JOIN raffles r ON rn.raffle_id = r.id
                WHERE rn.participant_cpf = ?
                ORDER BY rn.created_at DESC";
        
        $stmt = $this->db->query($sql, [$cpf]);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se participante pode comprar
     */
    public function canPurchase($cpf, $raffleId) {
        $participant = $this->getByCpf($cpf);
        
        if (!$participant) {
            return true; // Novo participante
        }
        
        // Verificar status
        if (in_array($participant['status'], ['suspended', 'blocked'])) {
            throw new Exception("Participante não pode realizar compras: " . $participant['status']);
        }
        
        // Verificar score de fraude
        if ($participant['fraud_score'] > 70) {
            throw new Exception("Participante bloqueado por segurança");
        }
        
        return true;
    }
}

?>
