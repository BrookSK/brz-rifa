<?php
/**
 * Controller para geração de relatórios
 */

class ReportController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Exibir página de relatórios
     */
    public function index() {
        $this->requireAuth();
        
        $raffles = $this->getRaffles();
        
        include SRC_PATH . '/views/admin/reports/index.php';
    }
    
    /**
     * Gerar relatório de rifa específica
     */
    public function raffleReport($raffleId) {
        $this->requireAuth();
        
        $raffle = $this->getRaffleDetails($raffleId);
        if (!$raffle) {
            $this->handle404();
        }
        
        $participants = $this->getRaffleParticipants($raffleId);
        $transactions = $this->getRaffleTransactions($raffleId);
        $statistics = $this->getRaffleStatistics($raffleId);
        
        include SRC_PATH . '/views/admin/reports/raffle.php';
    }
    
    /**
     * Gerar relatório financeiro
     */
    public function financialReport() {
        $this->requireAuth('admin');
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        $financialData = $this->getFinancialData($startDate, $endDate);
        $asaasData = $this->getAsaasData($startDate, $endDate);
        
        include SRC_PATH . '/views/admin/reports/financial.php';
    }
    
    /**
     * Gerar relatório de auditoria
     */
    public function auditReport() {
        $this->requireAuth('auditor');
        
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $userId = $_GET['user_id'] ?? null;
        $action = $_GET['action'] ?? null;
        
        $auditLogs = $this->getAuditLogs($startDate, $endDate, $userId, $action);
        $users = $this->getUsers();
        
        include SRC_PATH . '/views/admin/reports/audit.php';
    }
    
    /**
     * Exportar relatório em PDF
     */
    public function exportPDF($type, $id = null) {
        $this->requireAuth();
        
        switch ($type) {
            case 'raffle':
                $this->exportRafflePDF($id);
                break;
            case 'financial':
                $this->exportFinancialPDF();
                break;
            case 'audit':
                $this->exportAuditPDF();
                break;
            default:
                $this->jsonResponse(['error' => 'Tipo de relatório inválido'], 400);
        }
    }
    
    /**
     * Exportar relatório em Excel
     */
    public function exportExcel($type, $id = null) {
        $this->requireAuth();
        
        switch ($type) {
            case 'raffle':
                $this->exportRaffleExcel($id);
                break;
            case 'financial':
                $this->exportFinancialExcel();
                break;
            case 'participants':
                $this->exportParticipantsExcel($id);
                break;
            default:
                $this->jsonResponse(['error' => 'Tipo de relatório inválido'], 400);
        }
    }
    
    /**
     * Obter rifas para relatórios
     */
    private function getRaffles() {
        $sql = "SELECT id, title, status, created_at, draw_datetime 
                FROM raffles 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter detalhes da rifa
     */
    private function getRaffleDetails($raffleId) {
        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id AND status = 'paid') as paid_count,
                       (SELECT COUNT(*) FROM raffle_numbers WHERE raffle_id = r.id) as total_count
                FROM raffles r 
                WHERE r.id = ?";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetch();
    }
    
    /**
     * Obter participantes da rifa
     */
    private function getRaffleParticipants($raffleId) {
        $sql = "SELECT DISTINCT participant_name, participant_cpf, participant_email, participant_phone,
                       COUNT(*) as numbers_count, SUM(payment_amount) as total_amount
                FROM raffle_numbers 
                WHERE raffle_id = ? AND status = 'paid'
                GROUP BY participant_cpf
                ORDER BY participant_name";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter transações da rifa
     */
    private function getRaffleTransactions($raffleId) {
        $sql = "SELECT t.*, tn.raffle_number_id, rn.number
                FROM transactions t
                JOIN transaction_numbers tn ON t.id = tn.transaction_id
                JOIN raffle_numbers rn ON tn.raffle_number_id = rn.id
                WHERE rn.raffle_id = ?
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql, [$raffleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas da rifa
     */
    private function getRaffleStatistics($raffleId) {
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
        return $stmt->fetch();
    }
    
    /**
     * Obter dados financeiros
     */
    private function getFinancialData($startDate, $endDate) {
        $sql = "SELECT DATE(t.created_at) as date,
                       COUNT(*) as transactions_count,
                       SUM(t.amount) as total_amount,
                       SUM(CASE WHEN t.payment_status = 'confirmed' THEN t.amount ELSE 0 END) as confirmed_amount,
                       SUM(CASE WHEN t.payment_status = 'pending' THEN t.amount ELSE 0 END) as pending_amount
                FROM transactions t
                WHERE DATE(t.created_at) BETWEEN ? AND ?
                GROUP BY DATE(t.created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter dados do Asaas
     */
    private function getAsaasData($startDate, $endDate) {
        $sql = "SELECT DATE(created_at) as date,
                       COUNT(*) as webhooks_count,
                       SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed_count
                FROM webhook_logs
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs de auditoria
     */
    private function getAuditLogs($startDate, $endDate, $userId = null, $action = null) {
        $sql = "SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE DATE(al.created_at) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $sql .= " AND al.user_id = ?";
            $params[] = $userId;
        }
        
        if ($action) {
            $sql .= " AND al.action = ?";
            $params[] = $action;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT 1000";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter usuários para filtros
     */
    private function getUsers() {
        $sql = "SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Exportar rifa em PDF
     */
    private function exportRafflePDF($raffleId) {
        $raffle = $this->getRaffleDetails($raffleId);
        $participants = $this->getRaffleParticipants($raffleId);
        $statistics = $this->getRaffleStatistics($raffleId);
        
        // Gerar PDF (implementar com TCPDF ou similar)
        $filename = "relatorio_rifa_{$raffleId}_" . date('Y-m-d') . ".pdf";
        
        // Salvar relatório gerado
        $this->saveGeneratedReport('raffle', $raffleId, "Relatório Rifa: {$raffle['title']}", $filename);
        
        // Forçar download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Exportar financeiro em PDF
     */
    private function exportFinancialPDF() {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $financialData = $this->getFinancialData($startDate, $endDate);
        
        $filename = "relatorio_financeiro_{$startDate}_{$endDate}.pdf";
        
        // Salvar relatório gerado
        $this->saveGeneratedReport('financial', null, "Relatório Financeiro: {$startDate} a {$endDate}", $filename);
        
        // Forçar download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Exportar auditoria em PDF
     */
    private function exportAuditPDF() {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $auditLogs = $this->getAuditLogs($startDate, $endDate);
        
        $filename = "relatorio_auditoria_{$startDate}_{$endDate}.pdf";
        
        // Salvar relatório gerado
        $this->saveGeneratedReport('audit', null, "Relatório de Auditoria: {$startDate} a {$endDate}", $filename);
        
        // Forçar download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Exportar rifa em Excel
     */
    private function exportRaffleExcel($raffleId) {
        $participants = $this->getRaffleParticipants($raffleId);
        $transactions = $this->getRaffleTransactions($raffleId);
        
        $filename = "relatorio_rifa_{$raffleId}_" . date('Y-m-d') . ".xlsx";
        
        // Gerar Excel (implementar com PhpSpreadsheet)
        $this->saveGeneratedReport('raffle', $raffleId, "Relatório Rifa Excel: {$raffleId}", $filename);
        
        // Forçar download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Exportar financeiro em Excel
     */
    private function exportFinancialExcel() {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $financialData = $this->getFinancialData($startDate, $endDate);
        
        $filename = "relatorio_financeiro_{$startDate}_{$endDate}.xlsx";
        
        $this->saveGeneratedReport('financial', null, "Relatório Financeiro Excel: {$startDate} a {$endDate}", $filename);
        
        // Forçar download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Exportar participantes em Excel
     */
    private function exportParticipantsExcel($raffleId) {
        $participants = $this->getRaffleParticipants($raffleId);
        
        $filename = "participantes_rifa_{$raffleId}_" . date('Y-m-d') . ".xlsx";
        
        $this->saveGeneratedReport('participants', $raffleId, "Participantes Rifa: {$raffleId}", $filename);
        
        // Forçar download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile(UPLOADS_PATH . '/reports/' . $filename);
        exit;
    }
    
    /**
     * Salvar relatório gerado
     */
    private function saveGeneratedReport($type, $raffleId, $title, $filename) {
        $filePath = UPLOADS_PATH . '/reports/' . $filename;
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        
        $sql = "INSERT INTO generated_reports (report_type, raffle_id, title, file_path, file_size, generated_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $type,
            $raffleId,
            $title,
            $filename,
            $fileSize,
            $_SESSION['user_id'] ?? null
        ]);
    }
    
    /**
     * Verificar autenticação
     */
    private function requireAuth($requiredProfile = null) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        if ($requiredProfile) {
            // Implementar verificação de perfil
            $user = new User($this->db);
            if (!$user->hasPermission($_SESSION['user_id'], $requiredProfile)) {
                http_response_code(403);
                include SRC_PATH . '/views/errors/403.php';
                exit;
            }
        }
    }
    
    /**
     * Resposta JSON
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

?>
