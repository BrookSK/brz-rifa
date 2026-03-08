<?php
/**
 * Controller para gerenciar transações financeiras no painel administrativo
 */

class TransactionController {
    private $db;
    private $transactionModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->transactionModel = new Transaction($this->db);
    }
    
    /**
     * Listar transações
     */
    public function index() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $page = $_GET['page'] ?? 1;
        $status = $_GET['status'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $transactions = $this->transactionModel->getAll($page, 20, [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            
            $statistics = $this->transactionModel->getStatistics([
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            
            $this->showTransactionList($transactions, $statistics, $page, $status, $dateFrom, $dateTo);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes da transação
     */
    public function details($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $transaction = $this->transactionModel->getById($id);
            if (!$transaction) {
                throw new Exception("Transação não encontrada");
            }
            
            $this->showTransactionDetails($transaction);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Verificar status
     */
    public function checkStatus($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $transaction = $this->transactionModel->checkStatus($id);
            
            $_SESSION['success'] = "Status atualizado: " . $transaction['payment_status'];
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/transactions");
        exit;
    }
    
    /**
     * Cancelar transação
     */
    public function cancel($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin/transactions");
            exit;
        }
        
        try {
            $this->transactionModel->cancel($id);
            $_SESSION['success'] = "Transação cancelada com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/transactions");
        exit;
    }
    
    /**
     * Reembolsar transação
     */
    public function refund($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin/transactions");
            exit;
        }
        
        try {
            $amount = $_POST['amount'] ?? null;
            $this->transactionModel->refund($id, $amount);
            $_SESSION['success'] = "Reembolso processado com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/transactions");
        exit;
    }
    
    /**
     * Conciliar com Asaas
     */
    public function reconcile() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $result = $this->transactionModel->reconcileWithAsaas($dateFrom, $dateTo);
            
            $_SESSION['success'] = "Conciliação realizada! {$result['reconciled_count']} transações atualizadas.";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/transactions");
        exit;
    }
    
    /**
     * Dashboard financeiro
     */
    public function dashboard() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $realTimeMetrics = $this->transactionModel->getRealTimeMetrics();
            $dailySummary = $this->transactionModel->getDailySummary();
            $weeklyReport = $this->transactionModel->getFinancialReport(
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d'),
                'day'
            );
            
            $problematicTransactions = $this->transactionModel->getProblematicTransactions();
            $integrityIssues = $this->transactionModel->validateIntegrity();
            
            $this->showFinancialDashboard($realTimeMetrics, $dailySummary, $weeklyReport, $problematicTransactions, $integrityIssues);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Exportar transações
     */
    public function export() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $filters = [
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'participant_id' => $_GET['participant_id'] ?? null,
            'raffle_id' => $_GET['raffle_id'] ?? null
        ];
        
        try {
            $transactions = $this->transactionModel->exportToCSV($filters);
            
            // Configurar headers para download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="transacoes_' . date('Y-m-d') . '.csv"');
            
            // Criar CSV
            $output = fopen('php://output', 'w');
            
            // Header
            fputcsv($output, [
                'ID', 'ID Pagamento', 'Valor', 'Status', 'Método', 'Data Criação', 
                'Data Confirmação', 'Data Cancelamento', 'Participante', 'E-mail', 'CPF', 
                'Rifa', 'Números'
            ]);
            
            // Dados
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['id'],
                    $transaction['payment_id'],
                    $transaction['amount'],
                    $this->getStatusLabel($transaction['payment_status']),
                    $transaction['payment_method'],
                    $transaction['created_at'],
                    $transaction['confirmed_at'],
                    $transaction['cancelled_at'],
                    $transaction['participant_name'],
                    $transaction['participant_email'],
                    $transaction['participant_cpf'],
                    $transaction['raffle_title'],
                    $transaction['numbers']
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/transactions");
            exit;
        }
    }
    
    /**
     * Exibir lista de transações
     */
    private function showTransactionList($transactions, $statistics, $page, $status, $dateFrom, $dateTo) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2.5em; font-weight: bold; margin-bottom: 10px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .stat-revenue { color: #27ae60; }
        .stat-transactions { color: #3498db; }
        .stat-confirmed { color: #2ecc71; }
        .stat-pending { color: #f39c12; }
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
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d1ecf1; color: #0c5460; }
        .amount { font-weight: bold; color: #27ae60; }
        .actions-cell { display: flex; gap: 5px; flex-wrap: wrap; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .actions { flex-direction: column; align-items: stretch; } .filter { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Transações Financeiras</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value stat-revenue">R$ ' . number_format($statistics['total_revenue'], 2, ',', '.') . '</div>
                <div class="stat-label">Receita Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-transactions">' . $statistics['total_transactions'] . '</div>
                <div class="stat-label">Total Transações</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-confirmed">' . $statistics['confirmed_transactions'] . '</div>
                <div class="stat-label">Confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-pending">' . $statistics['pending_transactions'] . '</div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $statistics['unique_participants'] . '</div>
                <div class="stat-label">Participantes Únicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ ' . number_format($statistics['avg_transaction_value'], 2, ',', '.') . '</div>
                <div class="stat-label">Ticket Médio</div>
            </div>
        </div>
        
        <div class="actions">
            <div>
                <h3>💳 Lista de Transações</h3>
            </div>
            <div>
                <a href="/admin/transactions/dashboard" class="btn btn-success">📊 Dashboard</a>
                <a href="/admin/transactions/reconcile" class="btn btn-warning">🔄 Conciliar</a>
                <a href="/admin/transactions/export" class="btn btn-primary">📥 Exportar</a>
                <a href="/admin/dashboard" class="btn">← Voltar</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="status">
                    <option value="">Todos</option>
                    <option value="pending" ' . (($status ?? '') === 'pending' ? 'selected' : '') . '>Pendentes</option>
                    <option value="confirmed" ' . (($status ?? '') === 'confirmed' ? 'selected' : '') . '>Confirmadas</option>
                    <option value="cancelled" ' . (($status ?? '') === 'cancelled' ? 'selected' : '') . '>Canceladas</option>
                    <option value="overdue" ' . (($status ?? '') === 'overdue' ? 'selected' : '') . '>Vencidas</option>
                    <option value="refunded" ' . (($status ?? '') === 'refunded' ? 'selected' : '') . '>Reembolsadas</option>
                </select>
                <input type="date" name="date_from" value="' . htmlspecialchars($dateFrom ?? '') . '" placeholder="Data Inicial">
                <input type="date" name="date_to" value="' . htmlspecialchars($dateTo ?? '') . '" placeholder="Data Final">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            </form>
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ID Pagamento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Participante</th>
                        <th>Rifa</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($transactions as $transaction) {
            echo '<tr>
                <td>#' . $transaction['id'] . '</td>
                <td>' . htmlspecialchars($transaction['payment_id']) . '</td>
                <td><span class="amount">R$ ' . number_format($transaction['amount'], 2, ',', '.') . '</span></td>
                <td>
                    <span class="status-badge status-' . $transaction['payment_status'] . '">' . $this->getStatusLabel($transaction['payment_status']) . '</span>
                </td>
                <td>' . htmlspecialchars($transaction['participant_name'] ?? '-') . '</td>
                <td>' . htmlspecialchars(substr($transaction['raffle_title'] ?? '-', 0, 30)) . '...</td>
                <td>' . date('d/m/Y H:i', strtotime($transaction['created_at'])) . '</td>
                <td>
                    <div class="actions-cell">
                        <a href="/admin/transactions/' . $transaction['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">👁️</a>';
            
            if ($transaction['payment_status'] === 'pending') {
                echo '<a href="/admin/transactions/check-status/' . $transaction['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">🔄</a>';
            }
            
            if ($transaction['payment_status'] === 'confirmed') {
                echo '<form method="POST" action="/admin/transactions/refund/' . $transaction['id'] . '" style="display: inline;">
                    <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Reembolsar transação?\')">💰</button>
                </form>';
            }
            
            if (in_array($transaction['payment_status'], ['pending', 'reserved'])) {
                echo '<form method="POST" action="/admin/transactions/cancel/' . $transaction['id'] . '" style="display: inline;">
                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Cancelar transação?\')">❌</button>
                </form>';
            }
            
            echo '</div></td></tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/transactions?page=' . max(1, $page - 1) . '" class="btn">← Anterior</a>
            <span style="margin: 0 20px;">Página ' . $page . '</span>
            <a href="/admin/transactions?page=' . ($page + 1) . '" class="btn">Próximo →</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir detalhes da transação
     */
    private function showTransactionDetails($transaction) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transação #' . $transaction['id'] . ' - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 800px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .transaction-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .transaction-header { text-align: center; margin-bottom: 30px; }
        .transaction-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .status-badge { padding: 10px 20px; border-radius: 30px; font-size: 16px; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d1ecf1; color: #0c5460; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .info-value { color: #666; }
        .amount { font-size: 1.2em; font-weight: bold; color: #27ae60; }
        .numbers { background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Detalhes da Transação</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="transaction-card">
            <div class="transaction-header">
                <div class="transaction-title">💳 Transação #' . $transaction['id'] . '</div>
                <span class="status-badge status-' . $transaction['payment_status'] . '">' . $this->getStatusLabel($transaction['payment_status']) . '</span>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ID Pagamento</div>
                    <div class="info-value">' . htmlspecialchars($transaction['payment_id']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Valor</div>
                    <div class="info-value amount">R$ ' . number_format($transaction['amount'], 2, ',', '.') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Método</div>
                    <div class="info-value">' . htmlspecialchars($transaction['payment_method']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Referência Externa</div>
                    <div class="info-value">' . htmlspecialchars($transaction['external_reference']) . '</div>
                </div>';
                
                if ($transaction['participant_name']) {
                    echo '<div class="info-item">
                        <div class="info-label">Participante</div>
                        <div class="info-value">' . htmlspecialchars($transaction['participant_name']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">CPF</div>
                        <div class="info-value">' . htmlspecialchars($transaction['participant_cpf']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">E-mail</div>
                        <div class="info-value">' . htmlspecialchars($transaction['participant_email']) . '</div>
                    </div>';
                }
                
                echo '<div class="info-item">
                    <div class="info-label">Rifa</div>
                    <div class="info-value">' . htmlspecialchars($transaction['raffle_title']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data Criação</div>
                    <div class="info-value">' . date('d/m/Y H:i:s', strtotime($transaction['created_at'])) . '</div>
                </div>';
                
                if ($transaction['confirmed_at']) {
                    echo '<div class="info-item">
                        <div class="info-label">Data Confirmação</div>
                        <div class="info-value">' . date('d/m/Y H:i:s', strtotime($transaction['confirmed_at'])) . '</div>
                    </div>';
                }
                
                if ($transaction['cancelled_at']) {
                    echo '<div class="info-item">
                        <div class="info-label">Data Cancelamento</div>
                        <div class="info-value">' . date('d/m/Y H:i:s', strtotime($transaction['cancelled_at'])) . '</div>
                    </div>';
                }
                
                echo '</div>';
                
                if (!empty($transaction['numbers'])) {
                    echo '<div class="numbers">
                        <h4 style="margin-bottom: 10px;">🎟️ Números Adquiridos</h4>
                        <div>' . implode(', ', $transaction['numbers']) . '</div>
                    </div>';
                }
                
                echo '<div style="text-align: center; margin-top: 30px;">
                    <a href="/admin/transactions" class="btn btn-primary">← Voltar</a>';
                
                if ($transaction['payment_status'] === 'pending') {
                    echo '<a href="/admin/transactions/check-status/' . $transaction['id'] . '" class="btn btn-warning" style="margin-left: 10px;">🔄 Verificar Status</a>';
                }
                
                if ($transaction['payment_status'] === 'confirmed') {
                    echo '<form method="POST" action="/admin/transactions/refund/' . $transaction['id'] . '" style="display: inline; margin-left: 10px;">
                        <button type="submit" class="btn btn-warning" onclick="return confirm(\'Reembolsar transação?\')">💰 Reembolsar</button>
                    </form>';
                }
                
                if (in_array($transaction['payment_status'], ['pending', 'reserved'])) {
                    echo '<form method="POST" action="/admin/transactions/cancel/' . $transaction['id'] . '" style="display: inline; margin-left: 10px;">
                        <button type="submit" class="btn btn-danger" onclick="return confirm(\'Cancelar transação?\')">❌ Cancelar</button>
                    </form>';
                }
                
                echo '</div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir dashboard financeiro
     */
    private function showFinancialDashboard($realTimeMetrics, $dailySummary, $weeklyReport, $problematicTransactions, $integrityIssues) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financeiro - ' . SITE_NAME . '</title>
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
        .metric-label { color: #666; }
        .metric-value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .metric-value.success { color: #27ae60; }
        .metric-value.warning { color: #f39c12; }
        .metric-value.danger { color: #e74c3c; }
        .chart { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; text-align: center; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Dashboard Financeiro</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <h2 style="color: #2c3e50; margin-bottom: 30px;">📊 Visão Geral</h2>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>💰 Hoje</h3>
                <div class="metric">
                    <span class="metric-label">Transações</span>
                    <span class="metric-value">' . $realTimeMetrics['total_transactions_today'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Confirmadas</span>
                    <span class="metric-value success">' . $realTimeMetrics['confirmed_today'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Receita</span>
                    <span class="metric-value success">R$ ' . number_format($realTimeMetrics['revenue_today'], 2, ',', '.') . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Ticket Médio</span>
                    <span class="metric-value">R$ ' . number_format($realTimeMetrics['avg_ticket_today'], 2, ',', '.') . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>📈 Últimos 30 dias</h3>
                <div class="metric">
                    <span class="metric-label">Receita Total</span>
                    <span class="metric-value success">R$ ' . number_format($realTimeMetrics['revenue_30d'], 2, ',', '.') . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Pendentes</span>
                    <span class="metric-value warning">' . $realTimeMetrics['pending_count'] . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>📋 Resumo Diário</h3>
                <div class="metric">
                    <span class="metric-label">Total</span>
                    <span class="metric-value">' . $dailySummary['total_transactions'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Confirmadas</span>
                    <span class="metric-value success">' . $dailySummary['confirmed'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Pendentes</span>
                    <span class="metric-value warning">' . $dailySummary['pending'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Participantes</span>
                    <span class="metric-value">' . $dailySummary['unique_participants'] . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>⚠️ Alertas</h3>';
                
                if (!empty($problematicTransactions)) {
                    echo '<div class="alert alert-warning">
                        <strong>' . count($problematicTransactions) . ' transações pendentes há mais de 1 hora</strong>
                    </div>';
                }
                
                if (!empty($integrityIssues)) {
                    echo '<div class="alert alert-danger">
                        <strong>' . count($integrityIssues) . ' problemas de integridade detectados</strong>
                    </div>';
                }
                
                if (empty($problematicTransactions) && empty($integrityIssues)) {
                    echo '<div style="color: #27ae60; text-align: center; padding: 20px;">
                        ✅ Sistema saudável
                    </div>';
                }
                
                echo '</div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="/admin/transactions" class="btn btn-primary">📋 Ver Transações</a>
            <a href="/admin/dashboard" class="btn btn-success">🏠 Dashboard Principal</a>
        </div>
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
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Erro</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>
        <a href="/admin/transactions" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Obter label do status
     */
    private function getStatusLabel($status) {
        $labels = [
            'pending' => 'Pendente',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'overdue' => 'Vencido',
            'refunded' => 'Reembolsado'
        ];
        
        return $labels[$status] ?? $status;
    }
}

?>
