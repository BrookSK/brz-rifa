<?php
/**
 * Controller para monitoramento em tempo real
 */

class MonitoringController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Exibir página principal de monitoramento
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
        
        include SRC_PATH . '/views/admin/monitoring/index.php';
    }
    
    /**
     * API: Obter rifas ativas
     */
    public function getActiveRaffles() {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT r.*, 
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as paid_count,
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id) as total_count,
                           (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as revenue
                    FROM raffles r 
                    WHERE r.status = 'active' 
                    AND r.start_sales_datetime <= NOW() 
                    AND r.end_sales_datetime > NOW()
                    ORDER BY r.created_at DESC";
            
            $stmt = $this->db->query($sql);
            $raffles = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'raffles' => $raffles]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter métricas gerais
     */
    public function getGeneralMetrics() {
        header('Content-Type: application/json');
        
        try {
            // Faturamento do dia
            $sql = "SELECT SUM(rn.payment_amount) as daily_revenue 
                    FROM raffle_numbers rn 
                    WHERE rn.status = 'paid' 
                    AND DATE(rn.created_at) = CURDATE()";
            $stmt = $this->db->query($sql);
            $dailyRevenue = $stmt->fetch()['daily_revenue'] ?? 0;
            
            // Participantes únicos do dia
            $sql = "SELECT COUNT(DISTINCT rn.participant_cpf) as unique_participants 
                    FROM raffle_numbers rn 
                    WHERE rn.status = 'paid' 
                    AND DATE(rn.created_at) = CURDATE()";
            $stmt = $this->db->query($sql);
            $uniqueParticipants = $stmt->fetch()['unique_participants'] ?? 0;
            
            // Reservas pendentes
            $sql = "SELECT COUNT(*) as pending_reservations 
                    FROM raffle_numbers rn 
                    WHERE rn.status = 'reserved' 
                    AND rn.reservation_expires_at > NOW()";
            $stmt = $this->db->query($sql);
            $pendingReservations = $stmt->fetch()['pending_reservations'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'daily_revenue' => $dailyRevenue,
                'unique_participants' => $uniqueParticipants,
                'pending_reservations' => $pendingReservations
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter alertas críticos
     */
    public function getCriticalAlerts() {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT * FROM system_alerts 
                    WHERE severity IN ('critical', 'high') 
                    AND status = 'active'
                    ORDER BY severity DESC, created_at DESC 
                    LIMIT 10";
            
            $stmt = $this->db->query($sql);
            $alerts = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'alerts' => $alerts]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter dados do gráfico de vendas
     */
    public function getSalesChart() {
        header('Content-Type: application/json');
        
        try {
            // Vendas das últimas 24 horas
            $sql = "SELECT HOUR(created_at) as hour, 
                           SUM(payment_amount) as revenue,
                           COUNT(*) as sales_count
                    FROM raffle_numbers 
                    WHERE status = 'paid' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY HOUR(created_at)
                    ORDER BY hour";
            
            $stmt = $this->db->query($sql);
            $salesData = $stmt->fetchAll();
            
            // Preencher horas vazias
            $completeData = [];
            for ($i = 0; $i < 24; $i++) {
                $hour = ($i + date('H')) % 24;
                $found = false;
                
                foreach ($salesData as $data) {
                    if ($data['hour'] == $hour) {
                        $completeData[] = [
                            'hour' => $hour,
                            'revenue' => $data['revenue'],
                            'sales_count' => $data['sales_count']
                        ];
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $completeData[] = [
                        'hour' => $hour,
                        'revenue' => 0,
                        'sales_count' => 0
                    ];
                }
            }
            
            echo json_encode(['success' => true, 'data' => $completeData]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter eventos recentes
     */
    public function getRecentEvents() {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT al.*, u.name as user_name
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY al.created_at DESC
                    LIMIT 20";
            
            $stmt = $this->db->query($sql);
            $logs = $stmt->fetchAll();
            
            $events = [];
            foreach ($logs as $log) {
                $events[] = [
                    'id' => $log['id'],
                    'type' => $this->getEventType($log['action']),
                    'message' => $this->formatEventMessage($log),
                    'created_at' => $log['created_at']
                ];
            }
            
            echo json_encode(['success' => true, 'events' => $events]);
            
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
            $sql = "UPDATE system_alerts 
                    SET status = 'dismissed', 
                        dismissed_by = ?, 
                        dismissed_at = NOW() 
                    WHERE id = ?";
            
            $this->db->query($sql, [$_SESSION['user_id'], $alertId]);
            
            // Registrar log
            $this->logAudit('DISMISS_ALERT', $alertId, null, null);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter métricas de uma rifa específica
     */
    public function getRaffleMetrics($raffleId) {
        header('Content-Type: application/json');
        
        try {
            // Verificar se rifa existe
            $sql = "SELECT * FROM raffles WHERE id = ?";
            $stmt = $this->db->query($sql, [$raffleId]);
            $raffle = $stmt->fetch();
            
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            // Métricas detalhadas
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
            
            $stmt = $this->db->query($sql, [$raffleId]);
            $metrics = $stmt->fetch();
            
            // Percentual de vendas
            $metrics['sales_percentage'] = $metrics['total_numbers'] > 0 
                ? round(($metrics['paid_numbers'] / $metrics['total_numbers']) * 100, 2) 
                : 0;
            
            // Tempo restante
            $now = new DateTime();
            $endTime = new DateTime($raffle['end_sales_datetime']);
            $metrics['time_remaining'] = $endTime > $now ? $endTime->getTimestamp() - $now->getTimestamp() : 0;
            
            echo json_encode(['success' => true, 'metrics' => $metrics]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter participantes em tempo real
     */
    public function getLiveParticipants($raffleId) {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT DISTINCT participant_cpf, participant_name, participant_email,
                           COUNT(*) as numbers_count, SUM(payment_amount) as total_spent
                    FROM raffle_numbers 
                    WHERE raffle_id = ? AND status = 'paid'
                    GROUP BY participant_cpf
                    ORDER BY total_spent DESC
                    LIMIT 20";
            
            $stmt = $this->db->query($sql, [$raffleId]);
            $participants = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'participants' => $participants]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar integridade do sistema
     */
    public function checkSystemIntegrity() {
        header('Content-Type: application/json');
        
        try {
            $checks = [];
            
            // Verificar conexão com Asaas
            $asaasConfig = Config::getAsaasConfig();
            $checks['asaas_connection'] = [
                'status' => $asaasConfig ? 'ok' : 'error',
                'message' => $asaasConfig ? 'Conectado' : 'Não configurado'
            ];
            
            // Verificar se há rifas ativas sem números gerados
            $sql = "SELECT COUNT(*) as count 
                    FROM raffles r 
                    LEFT JOIN raffle_numbers rn ON r.id = rn.raffle_id 
                    WHERE r.status = 'active' AND rn.id IS NULL";
            $stmt = $this->db->query($sql);
            $count = $stmt->fetch()['count'];
            
            $checks['numbers_generated'] = [
                'status' => $count == 0 ? 'ok' : 'error',
                'message' => $count == 0 ? 'Todos os números gerados' : "$count rifas sem números"
            ];
            
            // Verificar pagamentos pendentes há muito tempo
            $sql = "SELECT COUNT(*) as count 
                    FROM raffle_numbers 
                    WHERE status = 'reserved' 
                    AND reservation_expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $this->db->query($sql);
            $count = $stmt->fetch()['count'];
            
            $checks['expired_reservations'] = [
                'status' => $count == 0 ? 'ok' : 'warning',
                'message' => $count == 0 ? 'Sem reservas expiradas' : "$count reservas expiradas"
            ];
            
            // Verificar se há logs recentes
            $sql = "SELECT COUNT(*) as count 
                    FROM audit_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $this->db->query($sql);
            $count = $stmt->fetch()['count'];
            
            $checks['audit_logs'] = [
                'status' => $count > 0 ? 'ok' : 'warning',
                'message' => $count > 0 ? "$count logs recentes" : 'Sem logs recentes'
            ];
            
            // Status geral
            $allOk = true;
            foreach ($checks as $check) {
                if ($check['status'] === 'error') {
                    $allOk = false;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'overall_status' => $allOk ? 'healthy' : 'issues',
                'checks' => $checks
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Determinar tipo de evento
     */
    private function getEventType($action) {
        if (strpos($action, 'PAYMENT') !== false) {
            return 'payment';
        } elseif (strpos($action, 'RESERVATION') !== false) {
            return 'reservation';
        } elseif (strpos($action, 'ALERT') !== false) {
            return 'alert';
        } else {
            return 'system';
        }
    }
    
    /**
     * Formatar mensagem de evento
     */
    private function formatEventMessage($log) {
        $userName = $log['user_name'] ?: 'Sistema';
        
        switch ($log['action']) {
            case 'CREATE_RAFFLE':
                return "$userName criou a rifa #{$log['record_id']}";
            case 'PUBLISH_RAFFLE':
                return "$userName publicou a rifa #{$log['record_id']}";
            case 'PAYMENT_CONFIRMED':
                return "Pagamento confirmado para rifa #{$log['record_id']}";
            case 'RESERVATION_CREATED':
                return "Nova reserva na rifa #{$log['record_id']}";
            case 'RESERVATION_EXPIRED':
                return "Reserva expirada na rifa #{$log['record_id']}";
            case 'ALERT_CREATED':
                return "Novo alerta: {$log['new_data']}";
            default:
                return "$userName executou: {$log['action']}";
        }
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $recordId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            'system_alerts',
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
}

?>
