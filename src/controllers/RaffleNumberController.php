<?php
/**
 * Controller para gerenciar números das rifas no painel administrativo
 */

class RaffleNumberController {
    private $db;
    private $raffleNumberModel;
    private $raffleModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->raffleNumberModel = new RaffleNumber($this->db);
        $this->raffleModel = new Raffle($this->db);
    }
    
    /**
     * Listar números de uma rifa
     */
    public function index($raffleId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        try {
            $raffle = $this->raffleModel->getById($raffleId);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $numbers = $this->raffleNumberModel->getByRaffle($raffleId, $status, $search);
            $statistics = $this->raffleNumberModel->getStatistics($raffleId);
            
            $this->showNumberList($raffle, $numbers, $statistics, $status, $search);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes de um número específico
     */
    public function details($raffleId, $numberId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $raffle = $this->raffleModel->getById($raffleId);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $number = $this->raffleNumberModel->getById($numberId);
            if (!$number || $number['raffle_id'] != $raffleId) {
                throw new Exception("Número não encontrado");
            }
            
            $this->showNumberDetails($raffle, $number);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Liberar reservas expiradas
     */
    public function cleanupReservations($raffleId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $count = $this->raffleNumberModel->cleanupExpiredReservations();
            $_SESSION['success'] = "$count reservas expiradas foram liberadas!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/raffles/$raffleId/numbers");
        exit;
    }
    
    /**
     * Marcar número como vencedor
     */
    public function markWinner($raffleId, $numberId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /admin/raffles/$raffleId/numbers");
            exit;
        }
        
        try {
            $number = $this->raffleNumberModel->getById($numberId);
            if (!$number || $number['raffle_id'] != $raffleId) {
                throw new Exception("Número não encontrado");
            }
            
            if ($number['status'] !== 'paid') {
                throw new Exception("Apenas números pagos podem ser marcados como vencedores");
            }
            
            $this->raffleNumberModel->markAsWinner($raffleId, $number['number']);
            
            $_SESSION['success'] = "Número {$number['number']} marcado como vencedor!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/raffles/$raffleId/numbers");
        exit;
    }
    
    /**
     * Liberar reserva específica
     */
    public function releaseReservation($raffleId, $numberId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $number = $this->raffleNumberModel->getById($numberId);
            if (!$number || $number['raffle_id'] != $raffleId) {
                throw new Exception("Número não encontrado");
            }
            
            if ($number['status'] !== 'reserved') {
                throw new Exception("Número não está reservado");
            }
            
            $this->raffleNumberModel->releaseReservation($raffleId, [$number['number']]);
            
            $_SESSION['success'] = "Reserva do número {$number['number']} foi liberada!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/raffles/$raffleId/numbers");
        exit;
    }
    
    /**
     * Exportar números para CSV
     */
    public function export($raffleId) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $raffle = $this->raffleModel->getById($raffleId);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $numbers = $this->raffleNumberModel->getByRaffle($raffleId);
            
            // Configurar headers para download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="numeros_rifa_' . $raffleId . '.csv"');
            
            // Criar CSV
            $output = fopen('php://output', 'w');
            
            // Header
            fputcsv($output, ['Número', 'Status', 'Participante', 'CPF', 'Email', 'Telefone', 'Valor Pago', 'Data Pagamento']);
            
            // Dados
            foreach ($numbers as $number) {
                fputcsv($output, [
                    $number['number'],
                    $this->getStatusLabel($number['status']),
                    $number['participant_name'] ?? '',
                    $number['participant_cpf'] ?? '',
                    $number['participant_email'] ?? '',
                    $number['participant_phone'] ?? '',
                    $number['payment_amount'] ?? '',
                    $number['paid_at'] ? date('d/m/Y H:i', strtotime($number['paid_at'])) : ''
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/raffles/$raffleId/numbers");
            exit;
        }
    }
    
    /**
     * Exibir lista de números
     */
    private function showNumberList($raffle, $numbers, $statistics, $status, $search) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Números - ' . htmlspecialchars($raffle['title']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .raffle-info { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .stat-value { font-size: 1.8em; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9em; color: #666; }
        .stat-available { color: #27ae60; }
        .stat-reserved { color: #f39c12; }
        .stat-paid { color: #3498db; }
        .stat-total { color: #2c3e50; }
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
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-available { background: #d4edda; color: #155724; }
        .status-reserved { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-winner { background: #f8d7da; color: #721c24; }
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
                <small>Números da Rifa</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="raffle-info">
            <h2 style="color: #2c3e50; margin-bottom: 15px;">📋 ' . htmlspecialchars($raffle['title']) . '</h2>
            <p style="color: #666;">Status: <span class="status-badge status-' . $raffle['status'] . '">' . $this->getStatusLabel($raffle['status']) . '</span></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value stat-total">' . $statistics['total_numbers'] . '</div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-available">' . $statistics['available_numbers'] . '</div>
                <div class="stat-label">Disponíveis</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-reserved">' . $statistics['reserved_numbers'] . '</div>
                <div class="stat-label">Reservados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-paid">' . $statistics['paid_numbers'] . '</div>
                <div class="stat-label">Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ ' . number_format($statistics['total_revenue'], 2, ',', '.') . '</div>
                <div class="stat-label">Receita</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $statistics['unique_participants'] . '</div>
                <div class="stat-label">Participantes</div>
            </div>
        </div>
        
        <div class="actions">
            <div>
                <h3>📊 Lista de Números</h3>
            </div>
            <div>
                <a href="/admin/raffles/' . $raffle['id'] . '/numbers/cleanup" class="btn btn-warning" onclick="return confirm(\'Liberar reservas expiradas?\')">🧹 Limpar Reservas</a>
                <a href="/admin/raffles/' . $raffle['id'] . '/numbers/export" class="btn btn-success">📥 Exportar CSV</a>
                <a href="/admin/raffles" class="btn btn-primary">← Voltar</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="raffle_id" value="' . $raffle['id'] . '">
                <select name="status">
                    <option value="">Todos</option>
                    <option value="available" ' . (($status ?? '') === 'available' ? 'selected' : '') . '>Disponíveis</option>
                    <option value="reserved" ' . (($status ?? '') === 'reserved' ? 'selected' : '') . '>Reservados</option>
                    <option value="paid" ' . (($status ?? '') === 'paid' ? 'selected' : '') . '>Pagos</option>
                    <option value="winner" ' . (($status ?? '') === 'winner' ? 'selected' : '') . '>Vencedor</option>
                </select>
                <input type="text" name="search" placeholder="Buscar participante ou CPF" value="' . htmlspecialchars($search ?? '') . '">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            </form>
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Status</th>
                        <th>Participante</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($numbers as $number) {
            echo '<tr>
                <td><strong>' . $number['number'] . '</strong></td>
                <td>
                    <span class="status-badge status-' . $number['status'] . '">' . $this->getStatusLabel($number['status']) . '</span>
                </td>
                <td>' . htmlspecialchars($number['participant_name'] ?? '-') . '</td>
                <td>' . htmlspecialchars($number['participant_cpf'] ?? '-') . '</td>
                <td>' . htmlspecialchars($number['participant_email'] ?? '-') . '</td>
                <td>' . htmlspecialchars($number['participant_phone'] ?? '-') . '</td>
                <td>R$ ' . number_format($number['payment_amount'] ?? 0, 2, ',', '.') . '</td>
                <td>' . ($number['paid_at'] ? date('d/m/Y H:i', strtotime($number['paid_at'])) : ($number['reservation_expires_at'] ? date('d/m/Y H:i', strtotime($number['reservation_expires_at'])) : '-')) . '</td>
                <td>
                    <div class="actions-cell">
                        <a href="/admin/raffles/' . $raffle['id'] . '/numbers/' . $number['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">👁️</a>';
            
            if ($number['status'] === 'reserved') {
                echo '<a href="/admin/raffles/' . $raffle['id'] . '/numbers/release/' . $number['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Liberar reserva?\')">🔓</a>';
            }
            
            if ($number['status'] === 'paid' && $raffle['status'] === 'sales_closed') {
                echo '<form method="POST" action="/admin/raffles/' . $raffle['id'] . '/numbers/mark-winner/' . $number['id'] . '" style="display: inline;">
                    <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Marcar como vencedor?\')">🏆</button>
                </form>';
            }
            
            echo '</div></td></tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir detalhes do número
     */
    private function showNumberDetails($raffle, $number) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Número ' . $number['number'] . ' - ' . htmlspecialchars($raffle['title']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .number-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .number-header { text-align: center; margin-bottom: 30px; }
        .number-title { font-size: 3em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .status-badge { padding: 10px 20px; border-radius: 30px; font-size: 16px; font-weight: 500; }
        .status-available { background: #d4edda; color: #155724; }
        .status-reserved { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-winner { background: #f8d7da; color: #721c24; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .info-value { color: #666; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
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
                <small>Detalhes do Número</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="number-card">
            <div class="number-header">
                <div class="number-title">#' . $number['number'] . '</div>
                <span class="status-badge status-' . $number['status'] . '">' . $this->getStatusLabel($number['status']) . '</span>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Rifa</div>
                    <div class="info-value">' . htmlspecialchars($raffle['title']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status da Rifa</div>
                    <div class="info-value">' . $this->getStatusLabel($raffle['status']) . '</div>
                </div>';
                
                if ($number['participant_name']) {
                    echo '<div class="info-item">
                        <div class="info-label">Participante</div>
                        <div class="info-value">' . htmlspecialchars($number['participant_name']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">CPF</div>
                        <div class="info-value">' . htmlspecialchars($number['participant_cpf']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">E-mail</div>
                        <div class="info-value">' . htmlspecialchars($number['participant_email']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Telefone</div>
                        <div class="info-value">' . htmlspecialchars($number['participant_phone']) . '</div>
                    </div>';
                }
                
                if ($number['payment_amount']) {
                    echo '<div class="info-item">
                        <div class="info-label">Valor Pago</div>
                        <div class="info-value">R$ ' . number_format($number['payment_amount'], 2, ',', '.') . '</div>
                    </div>';
                }
                
                if ($number['paid_at']) {
                    echo '<div class="info-item">
                        <div class="info-label">Data do Pagamento</div>
                        <div class="info-value">' . date('d/m/Y H:i', strtotime($number['paid_at'])) . '</div>
                    </div>';
                }
                
                if ($number['reservation_expires_at']) {
                    echo '<div class="info-item">
                        <div class="info-label">Expira em</div>
                        <div class="info-value">' . date('d/m/Y H:i', strtotime($number['reservation_expires_at'])) . '</div>
                    </div>';
                }
                
                echo '<div class="info-item">
                    <div class="info-label">Criado em</div>
                    <div class="info-value">' . date('d/m/Y H:i', strtotime($number['created_at'])) . '</div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="/admin/raffles/' . $raffle['id'] . '/numbers" class="btn btn-primary">← Voltar</a>
            </div>
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
        <a href="/admin/raffles" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Obter label do status
     */
    private function getStatusLabel($status) {
        $labels = [
            'available' => 'Disponível',
            'reserved' => 'Reservado',
            'paid' => 'Pago',
            'winner' => 'Vencedor',
            'draft' => 'Rascunho',
            'active' => 'Ativa',
            'sales_closed' => 'Encerrada',
            'drawn' => 'Sorteada'
        ];
        
        return $labels[$status] ?? $status;
    }
}

?>
