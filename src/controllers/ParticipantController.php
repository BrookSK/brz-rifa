<?php
/**
 * Controller para gestão de participantes
 */

class ParticipantController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Exibir página de participantes
     */
    public function index() {
        // Verificar permissão
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        // Apenas Admin e Operador podem acessar
        if ($_SESSION['user_profile'] === 'auditor') {
            header('Location: /admin/reports');
            exit;
        }
        
        include SRC_PATH . '/views/admin/participants/index.php';
    }
    
    /**
     * API: Obter participantes com filtros e paginação
     */
    public function getParticipants() {
        header('Content-Type: application/json');
        
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Construir WHERE
            $where = ['1=1'];
            $params = [];
            
            // Filtro de status
            if (!empty($_GET['status'])) {
                $where[] = 'p.status = ?';
                $params[] = $_GET['status'];
            }
            
            // Filtro de rifa
            if (!empty($_GET['raffle_id'])) {
                $where[] = 'EXISTS (SELECT 1 FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.raffle_id = ?)';
                $params[] = $_GET['raffle_id'];
            }
            
            // Filtro de busca
            if (!empty($_GET['search'])) {
                $where[] = '(p.name LIKE ? OR p.cpf LIKE ? OR p.email LIKE ?)';
                $search = '%' . $_GET['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            // Filtro de score de fraude
            if (!empty($_GET['fraud_score'])) {
                $range = explode('-', $_GET['fraud_score']);
                if (count($range) == 2) {
                    $where[] = 'p.fraud_score BETWEEN ? AND ?';
                    $params[] = $range[0];
                    $params[] = $range[1];
                }
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Obter participantes com estatísticas
            $sql = "SELECT p.*, 
                           (SELECT COUNT(DISTINCT rn.raffle_id) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_raffles,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_numbers,
                           (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as total_spent,
                           (SELECT AVG(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as avg_ticket
                    FROM participants p
                    WHERE $whereClause
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->query($sql, $params);
            $participants = $stmt->fetchAll();
            
            // Obter total
            $sql = "SELECT COUNT(*) as total FROM participants p WHERE $whereClause";
            $stmt = $this->db->query($sql, array_slice($params, 0, -2));
            $total = $stmt->fetch()['total'];
            
            // Paginação
            $totalPages = ceil($total / $limit);
            
            echo json_encode([
                'success' => true,
                'participants' => $participants,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'per_page' => $limit
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter participante específico
     */
    public function getParticipant($participantId) {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT p.*, 
                           (SELECT COUNT(DISTINCT rn.raffle_id) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_raffles,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_numbers,
                           (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as total_spent,
                           (SELECT AVG(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as avg_ticket
                    FROM participants p
                    WHERE p.id = ?";
            
            $stmt = $this->db->query($sql, [$participantId]);
            $participant = $stmt->fetch();
            
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            echo json_encode(['success' => true, 'participant' => $participant]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter estatísticas de participantes
     */
    public function getParticipantStats() {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'suspicious' THEN 1 ELSE 0 END) as suspicious,
                        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
                    FROM participants";
            
            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch();
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter histórico do participante
     */
    public function getParticipantHistory($cpf) {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT rn.*, r.title as raffle_title, r.status as raffle_status
                    FROM raffle_numbers rn
                    LEFT JOIN raffles r ON rn.raffle_id = r.id
                    WHERE rn.participant_cpf = ?
                    ORDER BY rn.created_at DESC
                    LIMIT 50";
            
            $stmt = $this->db->query($sql, [$cpf]);
            $history = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'history' => $history]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Suspender participante
     */
    public function suspendParticipant($participantId) {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? '';
            
            if (empty($reason)) {
                throw new Exception("Motivo da suspensão é obrigatório");
            }
            
            // Obter participante
            $participant = $this->getParticipantById($participantId);
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            if ($participant['status'] === 'blocked') {
                throw new Exception("Participante já está bloqueado");
            }
            
            // Suspender participante
            $participantModel = new Participant($this->db);
            $participantModel->suspend($participantId, $reason);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Bloquear participante
     */
    public function blockParticipant($participantId) {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? '';
            
            if (empty($reason)) {
                throw new Exception("Motivo do bloqueio é obrigatório");
            }
            
            // Obter participante
            $participant = $this->getParticipantById($participantId);
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            // Bloquear participante
            $participantModel = new Participant($this->db);
            $participantModel->block($participantId, $reason);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Reativar participante
     */
    public function reactivateParticipant($participantId) {
        header('Content-Type: application/json');
        
        try {
            // Obter participante
            $participant = $this->getParticipantById($participantId);
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            if ($participant['status'] === 'active') {
                throw new Exception("Participante já está ativo");
            }
            
            // Reativar participante
            $participantModel = new Participant($this->db);
            $participantModel->reactivate($participantId);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter participantes suspeitos
     */
    public function getSuspiciousParticipants() {
        header('Content-Type: application/json');
        
        try {
            $limit = intval($_GET['limit'] ?? 50);
            
            $sql = "SELECT p.*, 
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_numbers
                    FROM participants p 
                    WHERE p.fraud_score > 50 OR p.status = 'suspicious'
                    ORDER BY p.fraud_score DESC, p.created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->query($sql, [$limit]);
            $participants = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'participants' => $participants]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Exportar participantes
     */
    public function exportParticipants() {
        try {
            // Construir WHERE (mesma lógica do getParticipants)
            $where = ['1=1'];
            $params = [];
            
            if (!empty($_GET['status'])) {
                $where[] = 'p.status = ?';
                $params[] = $_GET['status'];
            }
            
            if (!empty($_GET['raffle_id'])) {
                $where[] = 'EXISTS (SELECT 1 FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.raffle_id = ?)';
                $params[] = $_GET['raffle_id'];
            }
            
            if (!empty($_GET['search'])) {
                $where[] = '(p.name LIKE ? OR p.cpf LIKE ? OR p.email LIKE ?)';
                $search = '%' . $_GET['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            if (!empty($_GET['fraud_score'])) {
                $range = explode('-', $_GET['fraud_score']);
                if (count($range) == 2) {
                    $where[] = 'p.fraud_score BETWEEN ? AND ?';
                    $params[] = $range[0];
                    $params[] = $range[1];
                }
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Obter todos os participantes (sem limite)
            $sql = "SELECT p.*, 
                           (SELECT COUNT(DISTINCT rn.raffle_id) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_raffles,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf) as total_numbers,
                           (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as total_spent,
                           (SELECT AVG(rn.payment_amount) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'paid') as avg_ticket
                    FROM participants p
                    WHERE $whereClause
                    ORDER BY p.created_at DESC";
            
            $stmt = $this->db->query($sql, $params);
            $participants = $stmt->fetchAll();
            
            // Gerar CSV
            $filename = 'participantes_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Cabeçalho
            fputcsv($output, [
                'ID', 'Nome', 'CPF', 'E-mail', 'Telefone', 'Endereço',
                'Status', 'Score Fraude', 'Data de Cadastro', 'Rifas Participadas',
                'Números Comprados', 'Total Gasto', 'Ticket Médio'
            ]);
            
            // Dados
            foreach ($participants as $participant) {
                fputcsv($output, [
                    $participant['id'],
                    $participant['name'],
                    $participant['cpf'],
                    $participant['email'],
                    $participant['phone'] ?? '',
                    $participant['address'] ?? '',
                    $participant['status'],
                    $participant['fraud_score'],
                    $participant['created_at'],
                    $participant['total_raffles'] ?? 0,
                    $participant['total_numbers'] ?? 0,
                    $participant['total_spent'] ?? 0,
                    $participant['avg_ticket'] ?? 0
                ]);
            }
            
            fclose($output);
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Atualizar score de fraude
     */
    public function updateFraudScore($participantId) {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $score = intval($data['score'] ?? 0);
            
            if ($score < 0 || $score > 100) {
                throw new Exception("Score deve estar entre 0 e 100");
            }
            
            // Obter participante
            $participant = $this->getParticipantById($participantId);
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            // Atualizar score
            $participantModel = new Participant($this->db);
            $participantModel->updateFraudScore($participantId, $score);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Limpar participantes antigos
     */
    public function cleanupOldParticipants() {
        header('Content-Type: application/json');
        
        try {
            $days = intval($_GET['days'] ?? 365);
            
            $participantModel = new Participant($this->db);
            $deletedCount = $participantModel->cleanupOldParticipants($days);
            
            echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar comportamento suspeito
     */
    public function checkSuspiciousBehavior() {
        header('Content-Type: application/json');
        
        try {
            $suspicious = [];
            
            // Verificar múltiplas tentativas de reserva sem pagamento
            $sql = "SELECT p.cpf, p.name, COUNT(*) as attempts
                    FROM fraud_attempts fa
                    JOIN participants p ON fa.cpf = p.cpf
                    WHERE fa.attempt_type = 'reservation'
                    AND fa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY p.cpf, p.name
                    HAVING attempts >= 5";
            
            $stmt = $this->db->query($sql);
            $reservations = $stmt->fetchAll();
            
            foreach ($reservations as $r) {
                $suspicious[] = [
                    'type' => 'multiple_reservations',
                    'cpf' => $r['cpf'],
                    'name' => $r['name'],
                    'details' => ['attempts' => $r['attempts']]
                ];
            }
            
            // Verificar e-mails descartáveis
            $sql = "SELECT p.cpf, p.name, p.email
                    FROM participants p
                    WHERE p.email LIKE '%@tempmail%' 
                    OR p.email LIKE '%@10minutemail%'
                    OR p.email LIKE '%@guerrillamail%'
                    OR p.email LIKE '%@mailinator%'
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt = $this->db->query($sql);
            $disposables = $stmt->fetchAll();
            
            foreach ($disposables as $d) {
                $suspicious[] = [
                    'type' => 'disposable_email',
                    'cpf' => $d['cpf'],
                    'name' => $d['name'],
                    'details' => ['email' => $d['email']]
                ];
            }
            
            // Verificar CPFs com comportamento anormal
            $sql = "SELECT p.cpf, p.name, p.fraud_score,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'reserved') as reserved_count
                    FROM participants p
                    WHERE p.fraud_score > 70
                    OR (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.participant_cpf = p.cpf AND rn.status = 'reserved') > 10";
            
            $stmt = $this->db->query($sql);
            $highRisk = $stmt->fetchAll();
            
            foreach ($highRisk as $h) {
                $suspicious[] = [
                    'type' => 'high_risk',
                    'cpf' => $h['cpf'],
                    'name' => $h['name'],
                    'details' => [
                        'fraud_score' => $h['fraud_score'],
                        'reserved_count' => $h['reserved_count']
                    ]
                ];
            }
            
            echo json_encode(['success' => true, 'suspicious' => $suspicious]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter participante por ID
     */
    private function getParticipantById($id) {
        $sql = "SELECT * FROM participants WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * API: Obter participantes por rifa
     */
    public function getParticipantsByRaffle($raffleId) {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT DISTINCT p.cpf, p.name, p.email, p.phone, p.status, p.fraud_score,
                           COUNT(rn.id) as numbers_count,
                           SUM(rn.payment_amount) as total_spent
                    FROM participants p
                    JOIN raffle_numbers rn ON p.cpf = rn.participant_cpf
                    WHERE rn.raffle_id = ? AND rn.status = 'paid'
                    GROUP BY p.cpf, p.name, p.email, p.phone, p.status, p.fraud_score
                    ORDER BY total_spent DESC";
            
            $stmt = $this->db->query($sql, [$raffleId]);
            $participants = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'participants' => $participants]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar limite de compras
     */
    public function checkPurchaseLimit($cpf, $raffleId, $quantity) {
        header('Content-Type: application/json');
        
        try {
            $participantModel = new Participant($this->db);
            $participantModel->checkPurchaseLimit($cpf, $raffleId, $quantity);
            
            echo json_encode(['success' => true, 'message' => 'Limite verificado']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar reservas recentes
     */
    public function checkRecentReservations($cpf) {
        header('Content-Type: application/json');
        
        try {
            $participantModel = new Participant($this->db);
            $participantModel->checkRecentReservations($cpf);
            
            echo json_encode(['success' => true, 'message' => 'Reservas verificadas']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

?>
