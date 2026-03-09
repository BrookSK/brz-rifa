<?php
/**
 * Controller para gerenciamento de alertas
 */

class AlertController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Exibir página de alertas
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
        
        include SRC_PATH . '/views/admin/alerts/index.php';
    }
    
    /**
     * API: Obter alertas com filtros e paginação
     */
    public function getAlerts() {
        header('Content-Type: application/json');
        
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Construir WHERE
            $where = ['1=1'];
            $params = [];
            
            // Filtro de severidade
            if (!empty($_GET['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $_GET['severity'];
            }
            
            // Filtro de status
            if (!empty($_GET['status'])) {
                $where[] = 'status = ?';
                $params[] = $_GET['status'];
            }
            
            // Filtro de data
            if (!empty($_GET['date'])) {
                switch ($_GET['date']) {
                    case '1h':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)';
                        break;
                    case '24h':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
                        break;
                    case '7d':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                        break;
                    case '30d':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                        break;
                }
            }
            
            // Filtro de busca
            if (!empty($_GET['search'])) {
                $where[] = '(title LIKE ? OR message LIKE ?)';
                $search = '%' . $_GET['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Obter alertas
            $sql = "SELECT sa.*, r.title as raffle_title, u.name as dismissed_by_name
                    FROM system_alerts sa
                    LEFT JOIN raffles r ON sa.raffle_id = r.id
                    LEFT JOIN users u ON sa.dismissed_by = u.id
                    WHERE $whereClause
                    ORDER BY sa.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->query($sql, $params);
            $alerts = $stmt->fetchAll();
            
            // Obter total
            $sql = "SELECT COUNT(*) as total FROM system_alerts WHERE $whereClause";
            $stmt = $this->db->query($sql, array_slice($params, 0, -2));
            $total = $stmt->fetch()['total'];
            
            // Obter estatísticas
            $statsSql = "SELECT 
                            SUM(CASE WHEN severity = 'critical' AND status = 'active' THEN 1 ELSE 0 END) as critical,
                            SUM(CASE WHEN severity = 'high' AND status = 'active' THEN 1 ELSE 0 END) as high,
                            SUM(CASE WHEN severity = 'medium' AND status = 'active' THEN 1 ELSE 0 END) as medium,
                            SUM(CASE WHEN severity = 'low' AND status = 'active' THEN 1 ELSE 0 END) as low
                        FROM system_alerts 
                        WHERE status = 'active'";
            
            $stmt = $this->db->query($statsSql);
            $stats = $stmt->fetch();
            
            // Paginação
            $totalPages = ceil($total / $limit);
            
            echo json_encode([
                'success' => true,
                'alerts' => $alerts,
                'stats' => $stats,
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
     * API: Obter alerta específico
     */
    public function getAlert($alertId) {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT sa.*, r.title as raffle_title, u.name as dismissed_by_name
                    FROM system_alerts sa
                    LEFT JOIN raffles r ON sa.raffle_id = r.id
                    LEFT JOIN users u ON sa.dismissed_by = u.id
                    WHERE sa.id = ?";
            
            $stmt = $this->db->query($sql, [$alertId]);
            $alert = $stmt->fetch();
            
            if (!$alert) {
                throw new Exception("Alerta não encontrado");
            }
            
            echo json_encode(['success' => true, 'alert' => $alert]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Criar novo alerta
     */
    public function createAlert() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar campos obrigatórios
            $required = ['title', 'message', 'severity'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório: $field");
                }
            }
            
            // Validar severidade
            if (!in_array($data['severity'], ['low', 'medium', 'high', 'critical'])) {
                throw new Exception("Severidade inválida");
            }
            
            // Validar JSON dos detalhes
            $details = null;
            if (!empty($data['details'])) {
                $decoded = json_decode($data['details'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("JSON inválido nos detalhes");
                }
                $details = $data['details'];
            }
            
            // Inserir alerta
            $sql = "INSERT INTO system_alerts (title, message, severity, raffle_id, details, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            
            $this->db->query($sql, [
                $data['title'],
                $data['message'],
                $data['severity'],
                $data['raffle_id'] ?? null,
                $details
            ]);
            
            $alertId = $this->db->getConnection()->lastInsertId();
            
            // Registrar log
            $this->logAudit('CREATE_ALERT', $alertId, null, $data);
            
            echo json_encode(['success' => true, 'alert_id' => $alertId]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Dispensar alerta
     */
    public function dismissAlert($alertId) {
        header('Content-Type: application/json');
        
        try {
            // Verificar se alerta existe e está ativo
            $sql = "SELECT * FROM system_alerts WHERE id = ? AND status = 'active'";
            $stmt = $this->db->query($sql, [$alertId]);
            $alert = $stmt->fetch();
            
            if (!$alert) {
                throw new Exception("Alerta não encontrado ou já processado");
            }
            
            // Atualizar status
            $sql = "UPDATE system_alerts 
                    SET status = 'dismissed', 
                        dismissed_by = ?, 
                        dismissed_at = NOW() 
                    WHERE id = ?";
            
            $this->db->query($sql, [$_SESSION['user_id'], $alertId]);
            
            // Registrar log
            $this->logAudit('DISMISS_ALERT', $alertId, $alert, ['status' => 'dismissed']);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Resolver alerta
     */
    public function resolveAlert($alertId) {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resolution = $data['resolution'] ?? '';
            
            if (empty($resolution)) {
                throw new Exception("Resolução não informada");
            }
            
            // Verificar se alerta existe e está ativo
            $sql = "SELECT * FROM system_alerts WHERE id = ? AND status = 'active'";
            $stmt = $this->db->query($sql, [$alertId]);
            $alert = $stmt->fetch();
            
            if (!$alert) {
                throw new Exception("Alerta não encontrado ou já processado");
            }
            
            // Atualizar status
            $sql = "UPDATE system_alerts 
                    SET status = 'resolved', 
                        resolved_by = ?, 
                        resolution = ?, 
                        resolved_at = NOW() 
                    WHERE id = ?";
            
            $this->db->query($sql, [$_SESSION['user_id'], $resolution, $alertId]);
            
            // Registrar log
            $this->logAudit('RESOLVE_ALERT', $alertId, $alert, [
                'status' => 'resolved',
                'resolution' => $resolution
            ]);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Dispensar múltiplos alertas
     */
    public function batchDismiss() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $alertIds = $data['alert_ids'] ?? [];
            
            if (empty($alertIds)) {
                throw new Exception("Nenhum alerta selecionado");
            }
            
            // Verificar se todos os alertas existem e estão ativos
            $placeholders = str_repeat('?,', count($alertIds) - 1) . '?';
            $sql = "SELECT id FROM system_alerts WHERE id IN ($placeholders) AND status = 'active'";
            $stmt = $this->db->query($sql, $alertIds);
            $validAlerts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($validAlerts)) {
                throw new Exception("Nenhum alerta válido para dispensar");
            }
            
            // Atualizar em lote
            $placeholders = str_repeat('?,', count($validAlerts) - 1) . '?';
            $sql = "UPDATE system_alerts 
                    SET status = 'dismissed', 
                        dismissed_by = ?, 
                        dismissed_at = NOW() 
                    WHERE id IN ($placeholders)";
            
            $params = array_merge([$_SESSION['user_id']], $validAlerts);
            $this->db->query($sql, $params);
            
            $dismissedCount = $this->db->getConnection()->rowCount();
            
            // Registrar log
            $this->logAudit('BATCH_DISMISS_ALERTS', 0, null, [
                'alert_ids' => $validAlerts,
                'dismissed_count' => $dismissedCount
            ]);
            
            echo json_encode(['success' => true, 'dismissed_count' => $dismissedCount]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Exportar alertas
     */
    public function exportAlerts() {
        try {
            // Construir WHERE (mesma lógica do getAlerts)
            $where = ['1=1'];
            $params = [];
            
            if (!empty($_GET['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $_GET['severity'];
            }
            
            if (!empty($_GET['status'])) {
                $where[] = 'status = ?';
                $params[] = $_GET['status'];
            }
            
            if (!empty($_GET['date'])) {
                switch ($_GET['date']) {
                    case '1h':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)';
                        break;
                    case '24h':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
                        break;
                    case '7d':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                        break;
                    case '30d':
                        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                        break;
                }
            }
            
            if (!empty($_GET['search'])) {
                $where[] = '(title LIKE ? OR message LIKE ?)';
                $search = '%' . $_GET['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Obter todos os alertas (sem limite)
            $sql = "SELECT sa.*, r.title as raffle_title, u.name as dismissed_by_name
                    FROM system_alerts sa
                    LEFT JOIN raffles r ON sa.raffle_id = r.id
                    LEFT JOIN users u ON sa.dismissed_by = u.id
                    WHERE $whereClause
                    ORDER BY sa.created_at DESC";
            
            $stmt = $this->db->query($sql, $params);
            $alerts = $stmt->fetchAll();
            
            // Gerar CSV
            $filename = 'alertas_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Cabeçalho
            fputcsv($output, [
                'ID', 'Título', 'Mensagem', 'Severidade', 'Status', 'Rifa',
                'Criado em', 'Dispensado por', 'Data do dispensamento', 'Resolução'
            ]);
            
            // Dados
            foreach ($alerts as $alert) {
                fputcsv($output, [
                    $alert['id'],
                    $alert['title'],
                    $alert['message'],
                    $alert['severity'],
                    $alert['status'],
                    $alert['raffle_title'] ?? '',
                    $alert['created_at'],
                    $alert['dismissed_by_name'] ?? '',
                    $alert['dismissed_at'] ?? '',
                    $alert['resolution'] ?? ''
                ]);
            }
            
            fclose($output);
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Criar alerta automaticamente
     */
    public function createAutomaticAlert($title, $message, $severity, $raffleId = null, $details = null) {
        try {
            $sql = "INSERT INTO system_alerts (title, message, severity, raffle_id, details, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            
            $this->db->query($sql, [
                $title,
                $message,
                $severity,
                $raffleId,
                $details ? json_encode($details) : null
            ]);
            
            return $this->db->getConnection()->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Error creating automatic alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar e criar alertas automáticos
     */
    public function checkAutomaticAlerts() {
        try {
            // Verificar rifas próximas do encerramento
            $sql = "SELECT * FROM raffles 
                    WHERE status = 'active' 
                    AND end_sales_datetime <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    AND end_sales_datetime > NOW()";
            
            $stmt = $this->db->query($sql);
            $raffles = $stmt->fetchAll();
            
            foreach ($raffles as $raffle) {
                // Verificar se já existe alerta para esta rifa
                $sql = "SELECT COUNT(*) as count FROM system_alerts 
                        WHERE raffle_id = ? AND title LIKE '%encerramento%' AND status = 'active'";
                $stmt = $this->db->query($sql, [$raffle['id']]);
                $existingAlert = $stmt->fetch()['count'];
                
                if ($existingAlert == 0) {
                    $this->createAutomaticAlert(
                        'Rifa próximo do encerramento',
                        "A rifa '{$raffle['title']}' encerra em menos de 24 horas.",
                        'high',
                        $raffle['id'],
                        ['raffle_id' => $raffle['id'], 'end_date' => $raffle['end_sales_datetime']]
                    );
                }
            }
            
            // Verificar rifas com vendas altas
            $sql = "SELECT r.*, 
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as paid_count,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id) as total_count
                    FROM raffles r 
                    WHERE r.status = 'active'";
            
            $stmt = $this->db->query($sql);
            $raffles = $stmt->fetchAll();
            
            foreach ($raffles as $raffle) {
                $percentage = $raffle['total_count'] > 0 
                    ? ($raffle['paid_count'] / $raffle['total_count']) * 100 
                    : 0;
                
                if ($percentage >= 85 && $percentage < 95) {
                    // Verificar se já existe alerta
                    $sql = "SELECT COUNT(*) as count FROM system_alerts 
                            WHERE raffle_id = ? AND title LIKE '%vendas%' AND status = 'active'";
                    $stmt = $this->db->query($sql, [$raffle['id']]);
                    $existingAlert = $stmt->fetch()['count'];
                    
                    if ($existingAlert == 0) {
                        $this->createAutomaticAlert(
                            'Alta taxa de vendas',
                            "A rifa '{$raffle['title']}' atingiu " . round($percentage) . "% das vendas.",
                            'medium',
                            $raffle['id'],
                            ['raffle_id' => $raffle['id'], 'percentage' => $percentage]
                        );
                    }
                } elseif ($percentage >= 95) {
                    // Verificar se já existe alerta crítico
                    $sql = "SELECT COUNT(*) as count FROM system_alerts 
                            WHERE raffle_id = ? AND title LIKE '%esgotando%' AND status = 'active'";
                    $stmt = $this->db->query($sql, [$raffle['id']]);
                    $existingAlert = $stmt->fetch()['count'];
                    
                    if ($existingAlert == 0) {
                        $this->createAutomaticAlert(
                            'Rifa esgotando',
                            "A rifa '{$raffle['title']}' está com " . round($percentage) . "% vendido. Quase esgotada!",
                            'critical',
                            $raffle['id'],
                            ['raffle_id' => $raffle['id'], 'percentage' => $percentage]
                        );
                    }
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error checking automatic alerts: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $recordId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'system_alerts', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
}

?>
