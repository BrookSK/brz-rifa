<?php
/**
 * Model para geração de relatórios e estatísticas
 */

// Prevenir redeclaração da classe
if (!class_exists('Report')) {

class Report {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Calcular taxa de crescimento
     */
    public function calculateGrowthRate($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
    
    /**
     * Obter estatísticas gerais do sistema
     */
    public function getSystemStats() {
        $stats = [];
        
        // Total de rifas
        $sql = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM raffles";
        $stmt = $this->db->query($sql);
        $raffleStats = $stmt->fetch();
        $stats['raffles'] = $raffleStats;
        
        // Total de participantes
        $sql = "SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
                FROM participants";
        $stmt = $this->db->query($sql);
        $participantStats = $stmt->fetch();
        $stats['participants'] = $participantStats;
        
        // Total de transações
        $sql = "SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                       SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                       SUM(CASE WHEN status = 'paid' THEN payment_amount ELSE 0 END) as total_amount
                FROM transactions";
        $stmt = $this->db->query($sql);
        $transactionStats = $stmt->fetch();
        $stats['transactions'] = $transactionStats;
        
        // Métricas em tempo real
        $sql = "SELECT COUNT(*) as active_raffles,
                       SUM(CASE WHEN status = 'active' THEN 
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') 
                           ELSE 0 END) as total_paid_numbers
                FROM raffles r";
        $stmt = $this->db->query($sql);
        $realtimeStats = $stmt->fetch();
        $stats['realtime'] = $realtimeStats;
        
        return $stats;
    }
    
    /**
     * Obter estatísticas de vendas por período
     */
    public function getSalesStats($startDate, $endDate) {
        $sql = "SELECT DATE(t.created_at) as date,
                       COUNT(*) as transactions,
                       SUM(t.payment_amount) as total_amount,
                       COUNT(DISTINCT t.participant_cpf) as unique_participants
                FROM transactions t
                WHERE t.status = 'paid'
                AND t.created_at BETWEEN ? AND ?
                GROUP BY DATE(t.created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter relatório de rifa específica
     */
    public function getRaffleReport($raffleId) {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id) as total_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as paid_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'reserved') as reserved_numbers,
                       (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as total_revenue
                FROM raffles r
                WHERE r.id = ?";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetch();
    }
    
    /**
     * Obter participantes de uma rifa
     */
    public function getRaffleParticipants($raffleId) {
        $sql = "SELECT DISTINCT p.*,
                       COUNT(rn.id) as numbers_count,
                       SUM(rn.payment_amount) as total_spent
                FROM participants p
                JOIN raffle_numbers rn ON p.cpf = rn.participant_cpf
                WHERE rn.raffle_id = ? AND rn.status = 'paid'
                GROUP BY p.id
                ORDER BY total_spent DESC";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter transações de uma rifa
     */
    public function getRaffleTransactions($raffleId) {
        $sql = "SELECT t.*, p.name as participant_name
                FROM transactions t
                LEFT JOIN participants p ON t.participant_cpf = p.cpf
                WHERE t.raffle_id = ?
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas financeiras
     */
    public function getFinancialStats($startDate, $endDate) {
        $stats = [];
        
        // Receita por período
        $sql = "SELECT DATE(created_at) as date,
                       SUM(payment_amount) as daily_revenue,
                       COUNT(*) as transactions_count
                FROM transactions
                WHERE status = 'paid'
                AND created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['daily_revenue'] = $stmt->fetchAll();
        
        // Receita total
        $sql = "SELECT SUM(payment_amount) as total_revenue,
                       COUNT(*) as total_transactions,
                       AVG(payment_amount) as avg_transaction
                FROM transactions
                WHERE status = 'paid'
                AND created_at BETWEEN ? AND ?";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['summary'] = $stmt->fetch();
        
        // Receita por método de pagamento
        $sql = "SELECT payment_method,
                       SUM(payment_amount) as revenue,
                       COUNT(*) as count
                FROM transactions
                WHERE status = 'paid'
                AND created_at BETWEEN ? AND ?
                GROUP BY payment_method";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['by_method'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Obter relatório de auditoria
     */
    public function getAuditReport($startDate, $endDate, $userId = null, $action = null) {
        $where = ["created_at BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where[] = "user_id = ?";
            $params[] = $userId;
        }
        
        if ($action) {
            $where[] = "action = ?";
            $params[] = $action;
        }
        
        $sql = "SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.created_at DESC
                LIMIT 1000";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de participantes
     */
    public function getParticipantStats() {
        $sql = "SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                       SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
                       AVG(fraud_score) as avg_fraud_score
                FROM participants";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Obter relatório de fraudes
     */
    public function getFraudReport($startDate, $endDate) {
        $sql = "SELECT fa.*, p.name as participant_name
                FROM fraud_attempts fa
                LEFT JOIN participants p ON fa.cpf = p.cpf
                WHERE fa.created_at BETWEEN ? AND ?
                ORDER BY fa.created_at DESC";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter métricas de performance
     */
    public function getPerformanceMetrics() {
        $metrics = [];
        
        // Tempo médio de pagamento
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_payment_time
                FROM transactions
                WHERE status = 'paid'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics['avg_payment_time'] = $result['avg_payment_time'] ? round($result['avg_payment_time']) : 0;
        
        // Taxa de conversão
        $sql = "SELECT 
                   (SELECT COUNT(*) FROM raffle_numbers WHERE status = 'paid') * 100.0 / 
                   (SELECT COUNT(*) FROM raffle_numbers) as conversion_rate";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics['conversion_rate'] = round($result['conversion_rate'], 2);
        
        // Ticket médio
        $sql = "SELECT AVG(payment_amount) as avg_ticket
                FROM transactions
                WHERE status = 'paid'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics['avg_ticket'] = $result['avg_ticket'] ? round($result['avg_ticket'], 2) : 0;
        
        return $metrics;
    }
    
    /**
     * Obter relatório de rifas ativas
     */
    public function getActiveRafflesReport() {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id) as total_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') as paid_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'reserved') as reserved_numbers,
                       ROUND((SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = 'paid') * 100.0 / 
                             (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id), 2) as completion_percentage
                FROM raffles r
                WHERE r.status = 'active'
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter relatório de sorteios
     */
    public function getDrawReport($startDate, $endDate) {
        $sql = "SELECT dl.*, r.title as raffle_title, u.name as drawn_by_name
                FROM draw_logs dl
                LEFT JOIN raffles r ON dl.raffle_id = r.id
                LEFT JOIN users u ON dl.drawn_by = u.id
                WHERE dl.drawn_at BETWEEN ? AND ?
                ORDER BY dl.drawn_at DESC";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de uso do sistema
     */
    public function getSystemUsageStats($startDate, $endDate) {
        $stats = [];
        
        // Acessos por dia
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as accesses
                FROM audit_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['daily_accesses'] = $stmt->fetchAll();
        
        // Ações mais comuns
        $sql = "SELECT action, COUNT(*) as count
                FROM audit_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['top_actions'] = $stmt->fetchAll();
        
        // Usuários mais ativos
        $sql = "SELECT u.name, COUNT(al.id) as actions
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN ? AND ?
                GROUP BY al.user_id, u.name
                ORDER BY actions DESC
                LIMIT 10";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats['top_users'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Exportar dados para CSV
     */
    public function exportToCSV($data, $filename, $headers = []) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalho
        if (!empty($headers)) {
            fputcsv($output, $headers);
        } elseif (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Gerar relatório em PDF (básico)
     */
    public function generatePDFReport($title, $data, $headers = []) {
        // Aqui você pode implementar a geração de PDF usando uma biblioteca como TCPDF ou FPDF
        // Por enquanto, vamos retornar um array com os dados
        return [
            'title' => $title,
            'headers' => $headers,
            'data' => $data,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

} // Fim da verificação de classe existente

?>
