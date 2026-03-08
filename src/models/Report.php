<?php
/**
 * Modelo para gerar relatórios completos do sistema
 */

class Report {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Gerar relatório de rifas
     */
    public function generateRaffleReport($filters = []) {
        $sql = "SELECT 
                    r.id, r.title, r.description, r.status, r.number_price, r.number_quantity,
                    r.created_at, r.published_at, r.sales_closed_at, r.drawn_at, r.winner_number,
                    r.winner_name, r.winner_cpf,
                    COUNT(rn.id) as total_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE NULL END) as avg_ticket_value,
                    COUNT(DISTINCT rn.participant_cpf) as unique_participants,
                    (SELECT COUNT(*) FROM audit_logs al WHERE al.table_name = 'raffles' AND al.record_id = r.id) as audit_count
                FROM raffles r
                LEFT JOIN raffle_numbers rn ON r.id = rn.raffle_id";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['status'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "r.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "r.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['title'])) {
            $where[] = "r.title LIKE ?";
            $params[] = "%{$filters['title']}%";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY r.id ORDER BY r.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório financeiro
     */
    public function generateFinancialReport($filters = []) {
        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN t.payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
                    SUM(CASE WHEN t.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN t.payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_transactions,
                    SUM(CASE WHEN t.payment_status = 'confirmed' THEN t.amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN t.payment_status = 'confirmed' THEN t.amount ELSE NULL END) as avg_transaction_value,
                    COUNT(DISTINCT t.participant_id) as unique_participants,
                    COUNT(DISTINCT t.raffle_id) as unique_raffles
                FROM transactions t";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['date_from'])) {
            $where[] = "t.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "t.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "t.payment_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY DATE(t.created_at) ORDER BY DATE(t.created_at) DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de participantes
     */
    public function generateParticipantReport($filters = []) {
        $sql = "SELECT 
                    p.id, p.name, p.email, p.cpf, p.phone, p.status, p.fraud_score,
                    p.created_at, p.last_purchase_at, p.first_purchase_at,
                    p.total_purchases, p.total_amount,
                    COUNT(DISTINCT rn.raffle_id) as unique_raffles,
                    COUNT(rn.id) as total_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE 0 END) as total_spent,
                    AVG(CASE WHEN rn.status = 'paid' THEN rn.payment_amount ELSE NULL END) as avg_ticket_value,
                    (SELECT COUNT(*) FROM fraud_attempts fa WHERE fa.cpf = p.cpf AND fa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_fraud_attempts
                FROM participants p
                LEFT JOIN raffle_numbers rn ON p.cpf = rn.participant_cpf";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['status'])) {
            $where[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['fraud_score_min'])) {
            $where[] = "p.fraud_score >= ?";
            $params[] = $filters['fraud_score_min'];
        }
        
        if (!empty($filters['fraud_score_max'])) {
            $where[] = "p.fraud_score <= ?";
            $params[] = $filters['fraud_score_max'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "p.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "p.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['name'])) {
            $where[] = "p.name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de auditoria
     */
    public function generateAuditReport($filters = []) {
        $sql = "SELECT 
                    DATE(al.created_at) as date,
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT al.user_id) as unique_users,
                    COUNT(DISTINCT al.table_name) as unique_tables,
                    COUNT(DISTINCT al.action) as unique_actions,
                    GROUP_CONCAT(DISTINCT al.action) as actions_list,
                    MIN(al.created_at) as first_log,
                    MAX(al.created_at) as last_log
                FROM audit_logs al";
        
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " WHERE al.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= ($filters['date_from'] ? " AND" : " WHERE") . " al.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= ($filters['date_from'] || $filters['date_to'] ? " AND" : " WHERE") . " al.action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }
        
        $sql .= " GROUP BY DATE(al.created_at) ORDER BY DATE(al.created_at) DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de transações por participante
     */
    public function generateTransactionByParticipantReport($participantId) {
        $sql = "SELECT 
                    t.id, t.payment_id, t.amount, t.payment_status, t.payment_method, t.created_at, t.confirmed_at,
                    r.title as raffle_title,
                    GROUP_CONCAT(rn.number) as numbers,
                    p.name as participant_name, p.email as participant_email, p.cpf as participant_cpf
                FROM transactions t
                LEFT JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN participants p ON t.participant_id = p.id
                LEFT JOIN transaction_numbers tn ON t.id = tn.transaction_id
                LEFT JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE t.participant_id = ?
                GROUP BY t.id
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, [$participantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de números por rifa
     */
    public function generateRaffleNumbersReport($raffleId) {
        $sql = "SELECT 
                    rn.number, rn.status, rn.participant_name, rn.participant_cpf, rn.participant_email,
                    rn.payment_amount, rn.paid_at, rn.reservation_expires_at,
                    rn.payment_id, rn.created_at,
                    p.name as participant_name_full
                FROM raffle_numbers rn
                LEFT JOIN participants p ON rn.participant_cpf = p.cpf
                WHERE rn.raffle_id = ?
                ORDER BY rn.number";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de sistema
     */
    public function generateSystemReport() {
        $sql = "SELECT 
                    'raffles' as table_name,
                    COUNT(*) as total_records,
                    MAX(created_at) as last_updated,
                    MIN(created_at) as first_created
                FROM raffles
                UNION ALL
                SELECT 
                    'participants' as table_name,
                    COUNT(*) as total_records,
                    MAX(updated_at) as last_updated,
                    MIN(created_at) as first_created
                FROM participants
                UNION ALL
                SELECT 
                    'transactions' as table_name,
                    COUNT(*) as total_records,
                    MAX(created_at) as last_updated,
                    MIN(created_at) as first_created
                FROM transactions
                UNION ALL
                SELECT 
                    'raffle_numbers' as table_name,
                    COUNT(*) as total_records,
                    MAX(updated_at) as last_updated,
                    MIN(created_at) as first_created
                FROM raffle_numbers
                UNION ALL
                SELECT 
                    'audit_logs' as table_name,
                    COUNT(*) as total_records,
                    MAX(created_at) as last_updated,
                    MIN(created_at) as first_created
                FROM audit_logs
                UNION ALL
                SELECT 
                    'notifications' as table_name,
                    COUNT(*) as total_records,
                    MAX(created_at) as last_updated,
                    MIN(created_at) as first_created
                FROM notifications
                ORDER BY table_name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de métricas em tempo real
     */
    public function generateRealTimeMetrics() {
        $now = date('Y-m-d H:i:s');
        
        // Métricas de Rifas
        $sqlRaffles = "SELECT 
                    COUNT(*) as total_raffles,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_raffles,
                    SUM(CASE WHEN status = 'sales_closed' THEN 1 ELSE 0 END) as closed_raffles,
                    SUM(CASE WHEN status = 'drawn' THEN 1 ELSE 0 END) as drawn_raffles
                FROM raffles";
        
        // Métricas de Participantes
        $sqlParticipants = "SELECT 
                    COUNT(*) as total_participants,
                    COUNT(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_participants,
                    COUNT(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_participants,
                    COUNT(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_participants,
                    AVG(fraud_score) as avg_fraud_score
                FROM participants";
        
        // Métricas de Transações
        $sqlTransactions = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                    SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_transactions,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN payment_status = 'confirmed' THEN amount ELSE NULL END) as avg_transaction_value
                FROM transactions 
                WHERE DATE(created_at) = CURDATE()";
        
        // Métricas de Números
        $sqlNumbers = "SELECT 
                    COUNT(*) as total_numbers,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_numbers,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_numbers,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_numbers,
                    SUM(CASE WHEN status = 'winner' THEN 1 ELSE 0 END) as winner_numbers
                FROM raffle_numbers";
        
        // Métricas de Notificações
        $sqlNotifications = "SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_notifications,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_notifications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_notifications
                FROM notifications";
        
        // Métricas de Auditoria
        $sqlAudit = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT table_name) as unique_tables,
                    COUNT(DISTINCT action) as unique_actions
                FROM audit_logs 
                WHERE DATE(created_at) = CURDATE()";
        
        $raffles = $this->db->query($sqlRaffles)->fetch();
        $participants = $this->db->query($sqlParticipants)->fetch();
        $transactions = $this->db->query($sqlTransactions)->fetch();
        $numbers = $this->db->query($sqlNumbers)->fetch();
        $notifications = $this->db->query($sqlNotifications)->fetch();
        $audit = $this->db->query($sqlAudit)->fetch();
        
        return [
            'timestamp' => $now,
            'raffles' => $raffles,
            'participants' => $participants,
            'transactions' => $transactions,
            'numbers' => $numbers,
            'notifications' => $notifications,
            'audit' => $audit
        ];
    }
    
    /**
     * Exportar relatório para CSV
     */
    public function exportToCSV($type, $filters = []) {
        switch ($type) {
            case 'raffles':
                $data = $this->generateRaffleReport($filters);
                $filename = "relatorio_rifas_" . date('Y-m-d') . ".csv";
                $headers = ['ID', 'Título', 'Descrição', 'Status', 'Preço', 'Quantidade', 'Criado em', 'Publicado em', 'Encerrado em', 'Sorteado em', 'Número Vencedor', 'Nome Vencedor', 'CPF Vencedor', 'Total Números', 'Números Pagos', 'Receita Total', 'Ticket Médio', 'Participantes Únicos', 'Logs de Auditoria'];
                break;
                
            case 'financial':
                $data = $this->generateFinancialReport($filters);
                $filename = "relatorio_financeiro_" . date('Y-m-d') . ".csv";
                $headers = ['Data', 'Total Transações', 'Transações Confirmadas', 'Transações Pendentes', 'Transações Canceladas', 'Receita Total', 'Ticket Médio', 'Participantes Únicos', 'Rifas Únicas'];
                break;
                
            case 'participants':
                $data = $this->generateParticipantReport($filters);
                $filename = "relatorio_participantes_" . date('T') . ".csv";
                $headers = ['ID', 'Nome', 'E-mail', 'CPF', 'Telefone', 'Status', 'Score de Fraude', 'Criado em', 'Última Compra', 'Primeira Compra', 'Total Compras', 'Total Gasto', 'Rifas Únicas', 'Total Números', 'Números Pagos', 'Total Gasto', 'Ticket Médio', 'Tentativas de Fraude'];
                break;
                
            case 'audit':
                $data = $this->generateAuditReport($filters);
                $filename = "relatorio_auditoria_" . date('Y-m-d') . ".csv";
                $headers = ['Data', 'Total Logs', 'Usuários Únicos', 'Tabelas Afetadas', 'Ações Diferentes', 'Lista de Ações', 'Primeiro Log', 'Último Log'];
                break;
                
            case 'system':
                $data = $this->generateSystemReport();
                $filename = "relatorio_sistema_" . date('Y-m-d') . ".csv";
                $headers = ['Tabela', 'Total Registros', 'Última Atualização', 'Primeira Criação'];
                break;
                
            default:
                throw new Exception("Tipo de relatório não suportado");
        }
        
        // Configurar headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Criar CSV
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, $headers);
        
        // Dados
        foreach ($data as $row) {
            $csvRow = [];
            
            switch ($type) {
                case 'raffles':
                    $csvRow = [
                        $row['id'],
                        $row['title'],
                        $row['description'],
                        $row['status'],
                        $row['number_price'],
                        $row['number_quantity'],
                        $row['created_at'],
                        $row['published_at'],
                        $row['sales_closed_at'],
                        $row['drawn_at'],
                        $row['winner_number'],
                        $row['winner_name'],
                        $row['winner_cpf'],
                        $row['total_numbers'],
                        $row['paid_numbers'],
                        $row['total_revenue'],
                        $row['avg_ticket_value'],
                        $row['unique_participants'],
                        $row['audit_count']
                    ];
                    break;
                    
                case 'financial':
                    $csvRow = [
                        $row['date'],
                        $row['total_transactions'],
                        $row['confirmed_transactions'],
                        $row['pending_transactions'],
                        $row['cancelled_transactions'],
                        $row['total_revenue'],
                        $row['avg_transaction_value'],
                        $row['unique_participants'],
                        $row['unique_raffles']
                    ];
                    break;
                    
                case 'participants':
                    $csvRow = [
                        $row['id'],
                        $row['name'],
                        $row['email'],
                        $row['cpf'],
                        $row['phone'],
                        $row['status'],
                        $row['fraud_score'],
                        $row['created_at'],
                        $row['last_purchase_at'],
                        $row['first_purchase_at'],
                        $row['total_purchases'],
                        $row['total_amount'],
                        $row['unique_raffles'],
                        $row['total_numbers'],
                        $row['paid_numbers'],
                        $row['total_spent'],
                        $row['avg_ticket_value'],
                        $row['recent_fraud_attempts']
                    ];
                    break;
                    
                case 'audit':
                    $csvRow = [
                        $row['date'],
                        $row['total_logs'],
                        $row['unique_users'],
                        $row['unique_tables'],
                        $row['unique_actions'],
                        $row['actions_list'],
                        $row['first_log'],
                        $row['last_log']
                    ];
                    break;
                    
                case 'system':
                    $csvRow = [
                        $row['table_name'],
                        $row['total_records'],
                        $row['last_updated'],
                        $row['first_created']
                    ];
                    break;
            }
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Gerar relatório consolidado completo
     */
    public function generateConsolidatedReport($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d');
        
        $report = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'raffles' => $this->generateRaffleReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'financial' => $this->generateFinancialReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'participants' => $this->generateParticipantReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'audit' => $this->generateAuditReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'system' => $this->generateSystemReport(),
            'realtime' => $this->generateRealTimeMetrics()
        ];
        
        // Adicionar resumo executivo
        $report['summary'] = [
            'period_days' => (strtotime($dateTo) - strtotime($dateFrom)) / (60 * 60 * 24),
            'total_raffles' => $report['raffles'] ? array_sum(array_column($report['raffles'], 'total_numbers')) : 0,
            'total_participants' => $report['participants'] ? count($report['participants']) : 0,
            'total_transactions' => $report['transactions']['total_transactions'] ?? 0,
            'total_revenue' => $report['transactions']['total_revenue'] ?? 0,
            'total_notifications' => $report['notifications']['total_notifications'] ?? 0
        ];
        
        return $report;
    }
    
    /**
     * Gerar relatório de performance
     */
    public function generatePerformanceReport($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d');
        
        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as total_requests,
                    AVG(CASE WHEN t.created_at > DATE_SUB(t.created_at, INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as requests_last_hour,
                    AVG(CASE WHEN t.created_at > DATE_SUB(t.created_at, INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as avg_response_time
                FROM transactions t
                WHERE t.created_at BETWEEN ? AND ?";
        
        $params = [$dateFrom, $dateTo];
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de crescimento
     */
    public function generateGrowthReport($period = 'monthly', $months = 12) {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as period,
                    COUNT(*) as count
                FROM transactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY period DESC
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$months]);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório comparativo
     */
    public function generateComparisonReport($period1, $period2) {
        $sql1 = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM transactions
                WHERE created_at BETWEEN ? AND ?";
        
        $sql2 = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM transactions
                WHERE created_at BETWEEN ? AND ?";
        
        $params1 = [$period1['from'], $period1['to']];
        $params2 = [$period2['from'], $period2['to']];
        
        $stmt1 = $this->db->query($sql1, $params1);
        $stmt2 = $this->db->query($sql2, $params2);
        
        $data1 = $stmt1->fetchAll();
        $data2 = $stmt2->fetchAll();
        
        return [
            'period1' => $period1,
            'data1' => $data1,
            'period2' => $period2,
            'data2' => $data2,
            'growth_rate' => $this->calculateGrowthRate($data1, $data2)
        ];
    }
    
    /**
     * Calcular taxa de crescimento
     */
    private function calculateGrowthRate($data1, $data2) {
        $growth = [];
        
        foreach ($data1 as $i => $row1) {
            $row2 = $data2[$i] ?? null;
            
            if ($row2) {
                $growth[] = [
                    'date' => $row1['date'],
                    'growth_rate' => $row2['count'] > 0 ? (($row2['count'] - $row1['count']) / $row1['count'] * 100) : 0
                ];
            } else {
                $growth[] = [
                    'date' => $row1['date'],
                    'growth_rate' => 0
                ];
            }
        }
        
        return $growth;
    }
    
    /**
     * Obter estatísticas de relatórios
     */
    public function getReportStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_reports,
                    COUNT(DISTINCT DATE(created_at)) as unique_dates,
                    MAX(created_at) as last_report,
                    MIN(created_at) as first_report
                FROM reports";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Salvar relatório gerado
     */
    public function saveReport($type, $filters, $data, $generatedBy = null) {
        $sql = "INSERT INTO reports (
            type, filters, data, generated_by, generated_at, file_path
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        
        $this->db->query($sql, [
            $type,
            json_encode($filters),
            json_encode($data),
            $generatedBy,
            "reports/relatorio_{$type}_" . date('Y-m-d_H-i-s') . ".json"
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Obter relatório salvo
     */
    public function getSavedReport($id) {
        $sql = "SELECT * FROM reports WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter lista de relatórios salvos
     */
    public function getSavedReports($limit = 20, $offset = 0) {
        $sql = "SELECT * FROM reports ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = [$limit, $offset];
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Excluir relatório
     */
    public function deleteReport($id) {
        $sql = "DELETE FROM reports WHERE id = ?";
        $this->db->query($sql, [$id]);
        return $this->db->getConnection()->rowCount();
    }
    
    /**
     * Gerar relatório de performance do sistema
     */
    public function generateSystemPerformanceReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $this->getDatabaseMetrics(),
            'php' => $this->getPHPMetrics(),
            'server' => $this->getServerMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'cache' => $this->getCacheMetrics()
        ];
        
        return $report;
    }
    
    /**
     * Obter métricas do banco de dados
     */
    private function getDatabaseMetrics() {
        $sql = "SHOW STATUS";
        $stmt = $this->db->query($sql);
        $status = $stmt->fetchAll();
        
        $metrics = [];
        foreach ($status as $row) {
            $metrics[$row['Variable_name']] = $row['Value'];
        }
        
        return $metrics;
    }
    
    /**
     * Obter métricas do PHP
     */
    private function getPHPMetrics() {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'allow_url_fopen' => ini_get('allow_url_fopen')
        ];
    }
    
    /**
     * Obter métricas do servidor
     */
    private function getServerMetrics() {
        return [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'request_time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Obter métricas de memória
     */
    private function getMemoryMetrics() {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }
    
    /**
     * Obter métricas de cache
     */
    private function getCacheMetrics() {
        // Implementar se houver sistema de cache
        return [
            'enabled' => false,
            'opcache' => function_exists('opcache'),
            'apc' => function_exists('apc'),
            'redis' => class_exists('Redis')
        ];
    }
    
    /**
     * Gerar relatório de erros
     */
    public function generateErrorReport($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    DATE(al.created_at) as date,
                    COUNT(*) as error_count,
                    GROUP_CONCAT(DISTINCT al.action) as error_types,
                    COUNT(DISTINCT al.table_name) as affected_tables
                FROM audit_logs al
                WHERE al.action LIKE '%ERROR%' OR al.error_message IS NOT NULL";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY DATE(al.created_at) ORDER BY DATE(al.created_at) DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de atividades de usuários
     */
    public function generateUserActivityReport($userId = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    DATE(al.created_at) as date,
                    COUNT(*) as total_actions,
                    GROUP_CONCAT(DISTINCT al.action) as actions_list,
                    COUNT(DISTINCT al.table_name) as affected_tables
                FROM audit_logs al";
        
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE al.user_id = ?";
            $params[] = $userId;
        }
        
        if ($dateFrom) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY DATE(al.created_at) ORDER BY DATE(al.created_at) DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Gerar relatório de uso do sistema
     */
    public function generateUsageReport($dateFrom = null, $dateTo = null) {
        $report = [
            'period' => [
                'from' => $dateFrom ?: date('Y-m-01'),
                'to' => $dateTo ?: date('Y-m-d')
            ],
            'raffles' => $this->generateRaffleReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'transactions' => $this->generateFinancialReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'participants' => $this->generateParticipantReport(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'notifications' => $this->getNotificationStatistics(['date_from' => $dateFrom, 'date_to' => $dateTo]),
            'audit' => $this->getAuditStatistics(['date_from' => $dateFrom, 'date_to' => $dateTo])
        ];
        
        // Calcular taxas de crescimento
        $previousPeriod = [
            'from' => date('Y-m-d', strtotime($dateFrom . ' - 1 month'),
            'to' => date('Y-m-d', strtotime($dateTo . ' - 1 month'))
        ];
        
        $previousData = $this->generateConsolidatedReport($previousPeriod['from'], $previousPeriod['to']);
        $currentData = $this->generateConsolidatedReport($dateFrom, $dateTo);
        
        $report['growth'] = [
            'raffles' => $this->calculateGrowthRate($previousData['raffles'], $currentData['raffles']),
            'transactions' => $this->calculateGrowthRate($previousData['transactions'], $currentData['transactions']),
            'revenue' => $this->calculateGrowthRate($previousData['transactions']['total_revenue'], $currentData['transactions']['total_revenue']),
            'participants' => $this->calculateGrowthRate($previousData['participants'], $currentData['participants'])
        ];
        
        return $report;
    }
    
    /**
     * Calcular taxa de crescimento entre dois períodos
     */
    private function calculateGrowthRate($previous, $current) {
        if (!$previous || !$current) return 0;
        
        return (($current - $previous) / $previous) * 100;
    }
}

?>
