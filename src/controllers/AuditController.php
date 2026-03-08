<?php
/**
 * Controller para gerenciar auditoria e logs no painel administrativo
 */

class AuditController {
    private $db;
    private $auditLogModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->auditLogModel = new AuditLog($this->db);
    }
    
    /**
     * Listar logs de auditoria
     */
    public function index() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $page = $_GET['page'] ?? 1;
        $action = $_GET['action'] ?? null;
        $table = $_GET['table'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $logs = $this->auditLogModel->search([
                'action' => $action,
                'table_name' => $table,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ], 50, ($page - 1) * 50);
            
            $statistics = $this->auditLogModel->getStatistics($dateFrom, $dateTo);
            $recentActivity = $this->auditLogModel->getRecentActivity(20);
            
            $this->showLogList($logs, $statistics, $recentActivity, $page, $action, $table, $userId, $dateFrom, $dateTo);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes do log
     */
    public function details($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $log = $this->auditLogModel->getById($id);
            if (!$log) {
                throw new Exception("Log de auditoria não encontrado");
            }
            
            // Verificar integridade
            $integrity = $this->auditLogModel->verifyIntegrity($id);
            
            $timeline = $this->auditLogModel->getRecordTimeline($log['table_name'], $log['record_id']);
            
            $this->showLogDetails($log, $integrity, $timeline);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Dashboard de auditoria
     */
    public function dashboard() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $statistics = $this->auditLogModel->getStatistics();
            $recentActivity = $this->auditLogModel->getRecentActivity(10);
            $securityLogs = $this->auditLogModel->getSecurityLogs(20);
            $criticalChanges = $this->auditLogModel->getCriticalChanges(20);
            $integrityIssues = $this->auditLogModel->detectUnloggedChanges();
            
            $this->showAuditDashboard($statistics, $recentActivity, $securityLogs, $criticalChanges, $integrityIssues);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Criar snapshot
     */
    public function createSnapshot() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showSnapshotForm();
            return;
        }
        
        try {
            $description = $_POST['description'] ?? null;
            $snapshotId = $this->auditLogModel->createSnapshot($description);
            
            $_SESSION['success'] = "Snapshot #$snapshotId criado com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/audit/dashboard");
        exit;
    }
    
    /**
     * Exportar logs
     */
    public function export() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $filters = [
            'action' => $_GET['action'] ?? null,
            'table_name' => $_GET['table'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];
        
        try {
            $logs = $this->auditLogModel->exportToCSV($filters);
            
            // Configurar headers para download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
            
            // Criar CSV
            $output = fopen('php://output', 'w');
            
            // Header
            fputcsv($output, [
                'ID', 'ID Usuário', 'Nome Usuário', 'Ação', 'Tabela', 'ID Registro', 
                'Dados Antigos', 'Dados Novos', 'IP', 'User Agent', 'Contexto', 'Data'
            ]);
            
            // Dados
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['user_id'],
                    $log['user_name'] ?? 'Sistema',
                    $log['action'],
                    $log['table_name'],
                    $log['record_id'],
                    $log['old_data'] ? substr($log['old_data'], 0, 100) : '',
                    $log['new_data'] ? substr($log['new_data'], 0, 100) : '',
                    $log['ip_address'],
                    $log['user_agent'] ? substr($log['user_agent'], 0, 100) : '',
                    $log['context'] ? substr($log['context'], 0, 200) : '',
                    $log['created_at']
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/audit");
            exit;
        }
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showCleanupForm();
            return;
        }
        
        try {
            $days = intval($_POST['days'] ?? 365);
            $count = $this->auditLogModel->cleanup($days);
            
            $_SESSION['success'] = "$count logs antigos foram removidos (período de $days dias)";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/audit/dashboard");
        exit;
    }
    
    /**
     * Verificar integridade de logs
     */
    public function verifyIntegrity() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $logId = $_GET['id'] ?? null;
        
        if (!$logId) {
            $this->showIntegrityCheckForm();
            return;
        }
        
        try {
            $result = $this->auditLogModel->verifyIntegrity($logId);
            
            $_SESSION['success'] = "Integridade do log #$logId verificada com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/audit");
        exit;
    }
    
    /**
     * Gerar relatório de atividades
     */
    public function activityReport() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $report = $this->auditLogModel->generateActivityReport($dateFrom, $dateTo);
            
            $this->showActivityReport($report, $dateFrom, $dateTo);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Exibir lista de logs
     */
    private function showLogList($logs, $statistics, $recentActivity, $page, $action, $table, $userId, $dateFrom, $dateTo) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; margin-bottom: 10px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .stat-total { color: #2c3e50; }
        .stat-users { color: #3498db; }
        .stat-tables { color: #27ae60; }
        .stat-actions { color: #e74c3c; }
        .recent-activity { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .activity-item:last-child { border-bottom: none; }
        .activity-info { flex: 1; }
        .activity-action { color: #666; font-size: 0.9em; }
        .activity-time { color: #999; font-size: 0.8em; }
        .actions { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .filter { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter select, .filter input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .table table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .actions { flex-direction: column; align-items: stretch; } .filter { flex-direction: column; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🔍 ' . SITE_NAME . '</h1>
                <small>Auditoria do Sistema</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value stat-total">' . $statistics['total_logs'] . '</div>
                <div class="stat-label">Total de Logs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-users">' . $statistics['unique_users'] . '</div>
                <div class="stat-label">Usuários Únicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-tables">' . $statistics['unique_tables'] . '</div>
                <div class="stat-label">Tabelas Afetadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-actions">' . $statistics['unique_actions'] . '</div>
                <div class="stat-label">Ações Diferentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($statistics['active_days'] ?? 0) . '</div>
                <div class="stat-label">Dias Ativos</div>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">🕐 Atividade Recente</h3>';
            
            <div class="activity-list">';
        
        foreach ($recentActivity as $activity) {
            echo '<div class="activity-item">
                <div class="activity-info">
                    <strong>' . htmlspecialchars($activity['action']) . '</strong>
                    <div class="activity-action">' . htmlspecialchars($activity['table_name'] . ' - ID #' . $activity['record_id']) . '</div>
                    <div class="activity-time">' . date('d/m/Y H:i', strtotime($activity['created_at'])) . '</div>
                </div>
                <div class="activity-time">
                    ' . htmlspecialchars($activity['user_name'] ?? 'Sistema') . '
                </div>
            </div>';
        }
        
        echo '</div>
        </div>
        
        <div class="actions">
            <div>
                <h3>📋 Logs de Auditoria</h3>
            </div>
            <div>
                <a href="/admin/audit/dashboard" class="btn btn-success">📊 Dashboard</a>
                <a href="/admin/audit/create-snapshot" class="btn btn-warning">📸 Criar Snapshot</a>
                <a href="/admin/audit/export" class="btn btn-primary">📥 Exportar</a>
                <a href="/admin/dashboard" class="btn">← Voltar</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="action">
                    <option value="">Todas as Ações</option>
                    <option value="CREATE">CREATE</option>
                    <option value="UPDATE">UPDATE</option>
                    <option value="DELETE">DELETE</option>
                    <option value="LOGIN_SUCCESS">Login Sucesso</option>
                    <option value="LOGIN_FAILED">Login Falha</option>
                    <option value="LOGOUT">Logout</option>
                </select>
                <select name="table">
                    <option value="">Todas as Tabelas</option>
                    <option value="raffles">Rifas</option>
                    <option value="participants">Participantes</option>
                    <option value="transactions">Transações</option>
                    <option value="raffle_numbers">Números</option>
                    <option value="users">Usuários</option>
                    <option value="system_policies">Políticas</option>
                </select>
                <input type="date" name="date_from" value="' . htmlspecialchars($dateFrom) . '" placeholder="Data Inicial">
                <input type="date" name="date_to" value="' . htmlspecialchars($dateTo) . '" placeholder="Data Final">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            </form>
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Tabela</th>
                        <th>Registro</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>
                <td>#' . $log['id'] . '</td>
                <td>' . htmlspecialchars($log['user_name'] ?? 'Sistema') . '</td>
                <td><span style="background: #e3f2fd; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500;">' . htmlspecialchars($log['action']) . '</span></td>
                <td>' . htmlspecialchars($log['table_name']) . '</td>
                <td>#' . $log['record_id'] . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>
                <td>
                    <a href="/admin/audit/' . $log['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">👁️</a>';
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/audit?page=' . max(1, $page - 1) . '" class="btn">← Anterior</a>
            <span style="margin: 0 20px;">Página ' . $page . '</span>
            <a href="/admin/audit?page=' . ($page + 1) . '" class="btn">Próximo →</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir detalhes do log
     */
    private function showLogDetails($log, $integrity, $timeline) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log #' . $log['id'] . ' - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 800px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .log-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .log-header { text-align: center; margin-bottom: 30px; }
        .log-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .log-id { color: #666; font-size: 1.2em; }
        .integrity { margin: 20px 0; padding: 15px; border-radius: 10px; }
        .integrity-success { background: #d4edda; color: #155724; }
        .integrity-error { background: #f8d7da; color: #721c24; }
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .info-value { color: #666; }
        .data-preview { background: #f8f9fa; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
        .timeline { margin-top: 30px; }
        .timeline-item { display: flex; align-items: flex-start; margin-bottom: 15px; }
        .timeline-marker { width: 12px; height: 12px; background: #3498db; border-radius: 50%; margin-right: 15px; flex-shrink: 0; }
        .timeline-content { flex: 1; }
        .timeline-date { color: #666; font-size: 0.9em; margin-bottom: 5px; }
        .timeline-action { font-weight: 600; color: #2c3e50; }
        .timeline-user { color: #666; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🔍 ' . SITE_NAME . '</h1>
                <small>Detalhes do Log</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="log-card">
            <div class="log-header">
                <div class="log-title">📋 Log de Auditoria</div>
                <div class="log-id">ID: #' . $log['id'] . '</div>
            </div>';
            
            <div class="integrity ' . ($integrity ? 'integrity-success' : 'integrity-error') . '">
                <strong>' . ($integrity ? '✅ Integridade Verificada' : '⚠️ Possível Problema de Integridade') . '</strong>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Ação:</div>
                    <div class="info-value">' . htmlspecialchars($log['action']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tabela:</div>
                    <div class="info-value">' . htmlspecialchars($log['table_name']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Registro:</div>
                    <div class="info-value">#' . $log['record_id'] . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Usuário:</div>
                    <div class="info-value">' . htmlspecialchars($log['user_name'] ?? 'Sistema') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">IP:</div>
                    <div class="info-value">' . htmlspecialchars($log['ip_address']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data:</div>
                    <div class="info-value">' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</div>
                </div>';
                
                if ($log['context']) {
                    $context = json_decode($log['context'], true);
                    echo '<div class="info-item">
                        <div class="info-label">Contexto:</div>
                        <div class="info-value">' . htmlspecialchars($context['description'] ?? 'N/A') . '</div>
                    </div>';
                }
                
                echo '</div>';
                
                if ($log['old_data']) {
                    echo '<div class="info-item">
                        <div class="info-label">Dados Antigos:</div>
                        <div class="data-preview">' . htmlspecialchars(substr($log['old_data'], 0, 500) . '...</div>
                    </div>';
                }
                
                if ($log['new_data']) {
                    echo '<div class="info-item">
                        <div class="info-label">Dados Novos:</div>
                        <div class="data-preview">' . htmlspecialchars(substr($log['new_data'], 0, 500) . '...</div>
                    </div>';
                }
                
                echo '</div>
                
                <div class="timeline">
                    <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Timeline do Registro</h3>';
                    
                    foreach ($timeline as $item) {
                        echo '<div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="timeline-date">' . date('d/m/Y H:i:s', strtotime($item['created_at'])) . '</div>
                                <div class="timeline-action">' . htmlspecialchars($item['action']) . '</div>';
                                <div class="timeline-user">' . htmlspecialchars($item['user_name'] ?? 'Sistema') . '</div>
                            </div>
                        </div>';
                    }
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="/admin/audit" class="btn btn-primary">← Voltar</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir dashboard de auditoria
     */
    private function showAuditDashboard($statistics, $recentActivity, $securityLogs, $criticalChanges, $integrityIssues) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Auditoria - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .card h3 { color: #2c3e50; margin-bottom: 20px; }
        .metric { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .metric-label { color: #666; font-size: 0.9em; }
        .metric-value { font-size: 1.5em; font-weight: bold; }
        .metric-value.success { color: #27ae60; }
        .metric-value.warning { color: #f39c12; }
        .metric-value.danger { color: #e74c3c; }
        .recent-logs { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .log-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .log-item:last-child { border-bottom: none; }
        .log-info { flex: 1; }
        .log-action { font-weight: 600; color: #2c3e50; }
        .log-time { color: #666; font-size: 0.9em; }
        .security-logs { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .critical-logs { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .integrity-issues { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🔍 ' . SITE_NAME . '</h1>
                <small>Dashboard de Auditoria</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <h2 style="color: #2c3e50; margin-bottom: 30px;">📊 Visão Geral da Auditoria</h2>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Estatísticas Gerais</h3>
                <div class="metric">
                    <span class="metric-label">Total de Logs</span>
                    <span class="metric-value">' . $statistics['total_logs'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Usuários Únicos</span>
                    <span class="metric-value">' . $statistics['unique_users'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Tabelas Afetadas</span>
                    <span class="metric-value">' . $statistics['unique_tables'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Ações Diferentes</span>
                    <span class="metric-value">' . $statistics['unique_actions'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Dias Ativos</span>
                    <span class="metric-value">' . ($statistics['active_days'] ?? 0) . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>🔐 Logs Recentes</h3>
                <div class="metric">
                    <span class="metric-label">Última 24h</span>
                    <span class="metric-value">' . count($recentActivity) . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>🛡️ Logs de Segurança</h3>
                <div class="metric">
                    <span class="metric-label">Últimas 24h</span>
                    <span class="metric-value">' . count($securityLogs) . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>🔄 Alterações Críticas</h3>
                <div class="metric">
                    <span class="metric-label">Últimas 24h</span>
                    <span class="metric-value">' . count($criticalChanges) . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>⚠️ Integridade</h3>
                <div class="metric">
                    <span class="metric-label">Problemas</span>
                    <span class="metric-value danger">' . count($integrityIssues) . '</span>
                </div>
            </div>
        </div>
        
        ' . (!empty($integrityIssues) ? '<div class="alert alert-danger">
            <strong>⚠️ ' . count($integrityIssues) . ' problemas de integridade detectados!</strong>
        </div>' : '') . '
        
        <div class="recent-logs">
            <h3>📋 Logs Recentes</h3>';
            
            <div class="log-list">';
        
        foreach ($recentActivity as $activity) {
            echo '<div class="log-item">
                <div class="log-info">
                    <strong>' . htmlspecialchars($activity['action']) . '</strong>
                    <span class="log-action">' . htmlspecialchars($activity['table_name']) . ' - #' . $activity['record_id'] . '</span>
                </div>
                <div class="log-time">' . date('d/m/Y H:i:s', strtotime($activity['created_at'])) . '</div>
                <div class="log-time">' . htmlspecialchars($activity['user_name'] ?? 'Sistema') . '</div>
            </div>';
        }
        
        echo '</div>
        
        <div class="security-logs">
            <h3>🔐 Logs de Segurança Recentes</h3>';
            
            <div class="log-list">';
        
        foreach ($securityLogs as $log) {
            echo '<div class="log-item">
                <div class="log-info">
                    <strong>' . htmlspecialchars($log['action']) . '</strong>
                    <span class="log-action">' . htmlspecialchars($log['table_name']) . ' - #' . $log['record_id'] . '</span>
                </div>
                <div class="log-time">' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</div>
                <div class="log-time">' . htmlspecialchars($log['user_name'] ?? 'Sistema') . '</div>
            </div>';
        }
        
        echo '</div>
        
        <div class="critical-logs">
            <h3>⚠️ Alterações Críticas Recentes</h3>';
            
            <div class="log-list">';
        
        foreach ($criticalChanges as $log) {
            echo '<div class="log-item">
                <div class="log-info">
                    <strong>' . htmlspecialchars($log['action']) . '</strong>
                    <span class="log-action">' . htmlspecialchars($log['table_name']) . ' - #' . $log['record_id'] . '</span>
                </div>
                <div class="log-time">' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</div>
                <div class="log-time">' . htmlspecialchars($log['user_name'] ?? 'Sistema') . '</div>
            </div>';
        }
        
        echo '</div>
        
        <div class="actions">
            <div>
                <h3>🔧️ Ações de Auditoria</h3>
            </div>
            <div>
                <a href="/admin/audit/create-snapshot" class="btn btn-warning">📸 Criar Snapshot</a>
                <a href="/admin/audit/export" class="btn btn-primary">📥 Exportar Logs</a>
                <a href="/admin/audit/cleanup" class="btn btn-danger">🧹 Limpar Logs Antigos</a>
                <a href="/admin/dashboard" class="btn btn-success">🏠 Dashboard Principal</a>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de snapshot
     */
    private function showSnapshotForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Snapshot - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">📸 Criar Snapshot</div>
            <div class="form-subtitle">Registro completo do estado do sistema para auditoria</div>
        </div>
        
        <form method="POST" action="/admin/audit/create-snapshot">
            <div class="form-group">
                <label for="description">Descrição do Snapshot:</label>
                <textarea id="description" name="description" rows="3" placeholder="Descreva uma descrição detalhada deste snapshot...">Criado em ' . date('d/m/Y H:i:s') . '</textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">📸 Criar Snapshot</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/audit/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de limpeza
     */
    private function showCleanupForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Logs Antigos - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-warning { background: #f39c12; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🧹 Limpar Logs Antigos</div>
            <div class="form-subtitle">Remoção permanente de logs antigos para manter o desempenho</div>
        </div>
        
        <form method="POST" action="/admin/audit/cleanup">
            <div class="form-group">
                <label for="days">Período de retenção (dias):</label>
                <input type="number" id="days" name="days" value="365" min="30" max="1825" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-warning">🧹 Limpar Logs</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/audit/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de verificação de integridade
     */
    private function showIntegrityCheckForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Integridade - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🔍 Verificar Integridade</div>
            <div class="form-subtitle">Verificação de hash de dados para garantir integridade imutável</div>
        </div>
        
        <form method="GET" action="/admin/audit/verify-integrity">
            <div class="form-group">
                <label for="id">ID do Log:</label>
                <input type="text" id="id" name="id" placeholder="Digite o ID do log" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">🔍 Verificar Integridade</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/audit/dashboard" class="btn">← Voltar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir página de erro
     */
    private function showError($message) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .error-icon { font-size: 4em; margin-bottom: 20px; }
        .error-title { color: #e74c3c; margin-bottom: 20px; }
        .error-message { color: #666; margin-bottom: 30px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Erro</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>
        <a href="/admin/audit" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
}

?>
