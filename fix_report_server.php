<?php
/**
 * Script para corrigir o problema da classe Report no servidor
 * Execute este script no servidor de produção para diagnosticar e corrigir o problema
 */

echo "<h1>🔧 CORREÇÃO AUTOMÁTICA - CLASSE REPORT</h1>\n";

// 1. Verificar o problema atual
echo "<h2>1. Diagnóstico do Problema</h2>\n";

$reportFile = __DIR__ . '/src/models/Report.php';
echo "Verificando arquivo: $reportFile<br>\n";

if (file_exists($reportFile)) {
    $content = file_get_contents($reportFile);
    $lines = explode("\n", $content);
    
    echo "✅ Arquivo existe<br>\n";
    echo "📏 Tamanho: " . filesize($reportFile) . " bytes<br>\n";
    echo "📄 Linhas: " . count($lines) . "<br>\n";
    
    // Verificar duplicatas
    $methodCount = substr_count($content, 'function calculateGrowthRate');
    $classCount = substr_count($content, 'class Report');
    
    echo "🔍 Ocorrências de 'class Report': $classCount<br>\n";
    echo "🔍 Ocorrências de 'function calculateGrowthRate': $methodCount<br>\n";
    
    if ($methodCount > 1) {
        echo "❌ PROBLEMA: Método duplicado encontrado!<br>\n";
        
        // Encontrar todas as ocorrências
        preg_match_all('/.*function\s+calculateGrowthRate.*/', $content, $matches);
        echo "<h3>Linhas com calculateGrowthRate:</h3>\n";
        echo "<pre>";
        foreach ($matches[0] as $index => $line) {
            echo ($index + 1) . ": " . htmlspecialchars($line) . "\n";
        }
        echo "</pre>";
    } else {
        echo "✅ Nenhuma duplicação encontrada no arquivo local<br>\n";
    }
} else {
    echo "❌ Arquivo não encontrado localmente<br>\n";
}

// 2. Backup do arquivo atual (se existir)
echo "<h2>2. Backup do Arquivo Atual</h2>\n";
if (file_exists($reportFile)) {
    $backupFile = $reportFile . '.backup.' . date('Y-m-d_H-i-s');
    if (copy($reportFile, $backupFile)) {
        echo "✅ Backup criado: " . basename($backupFile) . "<br>\n";
    } else {
        echo "❌ Erro ao criar backup<br>\n";
    }
}

// 3. Corrigir o arquivo
echo "<h2>3. Aplicando Correção</h2>\n";

// Conteúdo corrigido
$correctedContent = '<?php
/**
 * Model para geração de relatórios e estatísticas
 */

// Prevenir redeclaração - verificação mais rigorosa
if (!class_exists(\'Report\', false)) {

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
                       SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed
                FROM raffles";
        $stmt = $this->db->query($sql);
        $raffleStats = $stmt->fetch();
        $stats[\'raffles\'] = $raffleStats;
        
        // Total de participantes
        $sql = "SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = \'blocked\' THEN 1 ELSE 0 END) as blocked
                FROM participants";
        $stmt = $this->db->query($sql);
        $participantStats = $stmt->fetch();
        $stats[\'participants\'] = $participantStats;
        
        // Total de transações
        $sql = "SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) as paid,
                       SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
                       SUM(CASE WHEN status = \'paid\' THEN payment_amount ELSE 0 END) as total_amount
                FROM transactions";
        $stmt = $this->db->query($sql);
        $transactionStats = $stmt->fetch();
        $stats[\'transactions\'] = $transactionStats;
        
        // Métricas em tempo real
        $sql = "SELECT COUNT(*) as active_raffles,
                       SUM(CASE WHEN status = \'active\' THEN 
                           (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'paid\') 
                           ELSE 0 END) as total_paid_numbers
                FROM raffles r";
        $stmt = $this->db->query($sql);
        $realtimeStats = $stmt->fetch();
        $stats[\'realtime\'] = $realtimeStats;
        
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
                WHERE t.status = \'paid\'
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
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'paid\') as paid_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'reserved\') as reserved_numbers,
                       (SELECT SUM(rn.payment_amount) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'paid\') as total_revenue
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
                WHERE rn.raffle_id = ? AND rn.status = \'paid\'
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
                WHERE status = \'paid\'
                AND created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats[\'daily_revenue\'] = $stmt->fetchAll();
        
        // Receita total
        $sql = "SELECT SUM(payment_amount) as total_revenue,
                       COUNT(*) as total_transactions,
                       AVG(payment_amount) as avg_transaction
                FROM transactions
                WHERE status = \'paid\'
                AND created_at BETWEEN ? AND ?";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats[\'summary\'] = $stmt->fetch();
        
        // Receita por método de pagamento
        $sql = "SELECT payment_method,
                       SUM(payment_amount) as revenue,
                       COUNT(*) as count
                FROM transactions
                WHERE status = \'paid\'
                AND created_at BETWEEN ? AND ?
                GROUP BY payment_method";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats[\'by_method\'] = $stmt->fetchAll();
        
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
                WHERE " . implode(\' AND \', $where) . "
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
                       SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = \'suspended\' THEN 1 ELSE 0 END) as suspended,
                       SUM(CASE WHEN status = \'blocked\' THEN 1 ELSE 0 END) as blocked,
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
                WHERE status = \'paid\'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics[\'avg_payment_time\'] = $result[\'avg_payment_time\'] ? round($result[\'avg_payment_time\']) : 0;
        
        // Taxa de conversão
        $sql = "SELECT 
                   (SELECT COUNT(*) FROM raffle_numbers WHERE status = \'paid\') * 100.0 / 
                   (SELECT COUNT(*) FROM raffle_numbers) as conversion_rate";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics[\'conversion_rate\'] = round($result[\'conversion_rate\'], 2);
        
        // Ticket médio
        $sql = "SELECT AVG(payment_amount) as avg_ticket
                FROM transactions
                WHERE status = \'paid\'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        $metrics[\'avg_ticket\'] = $result[\'avg_ticket\'] ? round($result[\'avg_ticket\'], 2) : 0;
        
        return $metrics;
    }
    
    /**
     * Obter relatório de rifas ativas
     */
    public function getActiveRafflesReport() {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id) as total_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'paid\') as paid_numbers,
                       (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'reserved\') as reserved_numbers,
                       ROUND((SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id AND rn.status = \'paid\') * 100.0 / 
                             (SELECT COUNT(*) FROM raffle_numbers rn WHERE rn.raffle_id = r.id), 2) as completion_percentage
                FROM raffles r
                WHERE r.status = \'active\'
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
        $stats[\'daily_accesses\'] = $stmt->fetchAll();
        
        // Ações mais comuns
        $sql = "SELECT action, COUNT(*) as count
                FROM audit_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats[\'top_actions\'] = $stmt->fetchAll();
        
        // Usuários mais ativos
        $sql = "SELECT u.name, COUNT(al.id) as actions
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN ? AND ?
                GROUP BY al.user_id, u.name
                ORDER BY actions DESC
                LIMIT 10";
        
        $stmt = $this->db->query($sql, [$startDate, $endDate]);
        $stats[\'top_users\'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Exportar dados para CSV
     */
    public function exportToCSV($data, $filename, $headers = []) {
        header(\'Content-Type: text/csv\');
        header(\'Content-Disposition: attachment; filename="\' . $filename . \'"');
        
        $output = fopen(\'php://output\', \'w\');
        
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
            \'title\' => $title,
            \'headers\' => $headers,
            \'data\' => $data,
            \'generated_at\' => date(\'Y-m-d H:i:s\')
        ];
    }
}

} // Fim da verificação de classe existente

?>';

// Escrever o arquivo corrigido
if (file_put_contents($reportFile, $correctedContent)) {
    echo "✅ Arquivo corrigido com sucesso!<br>\n";
    echo "📁 Novo tamanho: " . filesize($reportFile) . " bytes<br>\n";
} else {
    echo "❌ Erro ao escrever o arquivo corrigido<br>\n";
}

// 4. Limpar cache do PHP se possível
echo "<h2>4. Limpeza de Cache</h2>\n";
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPcache resetado com sucesso<br>\n";
    } else {
        echo "⚠️ OPcache não pôde ser resetado<br>\n";
    }
} else {
    echo "ℹ️ OPcache não está disponível<br>\n";
}

// 5. Testar a classe
echo "<h2>5. Teste da Classe</h2>\n";
try {
    // Definir constantes se não existirem
    if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
    if (!defined('SRC_PATH')) define('SRC_PATH', __DIR__ . '/src');
    
    // Incluir autoload
    require_once SRC_PATH . '/autoload.php';
    
    // Incluir configuração do banco
    if (file_exists(__DIR__ . '/config/database.php')) {
        require_once __DIR__ . '/config/database.php';
        $db = new Database();
        $report = new Report($db);
        
        // Testar método
        $result = $report->calculateGrowthRate(150, 100);
        echo "✅ Classe Report carregada com sucesso!<br>\n";
        echo "✅ Método calculateGrowthRate funcionando: $result%<br>\n";
    } else {
        echo "⚠️ Arquivo de configuração do banco não encontrado<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no teste: " . $e->getMessage() . "<br>\n";
}

echo "<h2>6. Próximos Passos</h2>\n";
echo "<ol>";
echo "<li><strong>Reinicie o servidor web</strong> (Apache/Nginx)</li>";
echo "<li><strong>Limpe o cache do navegador</strong></li>";
echo "<li><strong>Teste o acesso ao painel admin</strong></li>";
echo "<li><strong>Monitore os logs de erro</strong> para verificar se o problema persiste</li>";
echo "</ol>";

echo "<h2>7. Comandos Úteis (SSH)</h2>\n";
echo "<pre>";
echo "# Reiniciar Apache\n";
echo "sudo systemctl restart apache2\n";
echo "# ou\n";
echo "sudo service apache2 restart\n\n";
echo "# Reiniciar Nginx\n";
echo "sudo systemctl restart nginx\n";
echo "# ou\n";
echo "sudo service nginx restart\n\n";
echo "# Limpar cache PHP\n";
echo "sudo rm -rf /var/lib/php/sessions/*\n";
echo "sudo rm -rf /tmp/php_*\n\n";
echo "# Verificar logs de erro\n";
echo "sudo tail -f /var/log/apache2/error.log\n";
echo "# ou\n";
echo "sudo tail -f /var/log/nginx/error.log\n";
echo "</pre>";

echo "<p><strong>🎉 CORREÇÃO CONCLUÍDA!</strong> Execute os passos acima e teste o sistema.</p>";

?>
