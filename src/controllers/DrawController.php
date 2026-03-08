<?php
/**
 * Controller para gerenciar sorteios no painel administrativo
 */

class DrawController {
    private $db;
    private $drawModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->drawModel = new Draw($this->db);
    }
    
    /**
     * Dashboard de sorteios
     */
    public function dashboard() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $statistics = $this->drawModel->getDrawStatistics();
            $recentDraws = $this->drawModel->getDrawHistory(null, 10);
            $readyRaffles = $this->drawModel->getRafflesReadyForDraw();
            
            $this->showDrawDashboard($statistics, $recentDraws, $readyRaffles);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Listar histórico de sorteios
     */
    public function history() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $raffleId = $_GET['raffle_id'] ?? null;
        
        try {
            $draws = $this->drawModel->getDrawHistory($raffleId, 50);
            
            $this->showDrawHistory($draws, $raffleId);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Realizar sorteio manual
     */
    public function perform() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showDrawForm();
            return;
        }
        
        try {
            $raffleId = intval($_POST['raffle_id'] ?? 0);
            
            if ($raffleId <= 0) {
                throw new Exception("ID da rifa é obrigatório");
            }
            
            $result = $this->drawModel->performDraw($raffleId, true);
            
            $_SESSION['success'] = "Sorteio realizado com sucesso! Número vencedor: {$result['winner_number']}";
            
            header("Location: /admin/draws/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/draws/perform");
            exit;
        }
    }
    
    /**
     * Verificar integridade do sorteio
     */
    public function verify() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $drawId = $_GET['draw_id'] ?? null;
        
        if (!$drawId) {
            $this->showVerifyForm();
            return;
        }
        
        try {
            $verification = $this->drawModel->verifyDraw($drawId);
            
            $this->showVerificationResult($verification);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Agendar sorteio
     */
    public function schedule() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showScheduleForm();
            return;
        }
        
        try {
            $raffleId = intval($_POST['raffle_id'] ?? 0);
            $drawTime = $_POST['draw_time'] ?? '';
            
            if ($raffleId <= 0 || empty($drawTime)) {
                throw new Exception("Todos os campos são obrigatórios");
            }
            
            $scheduledId = $this->drawModel->scheduleDraw($raffleId, $drawTime);
            
            $_SESSION['success'] = "Sorteio agendado com sucesso para {$drawTime}!";
            
            header("Location: /admin/draws/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/draws/schedule");
            exit;
        }
    }
    
    /**
     * Executar sorteios agendados
     */
    public function executeScheduled() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $executed = $this->drawModel->executeScheduledDraws();
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($executed as $result) {
                if (isset($result['error'])) {
                    $errorCount++;
                } else {
                    $successCount++;
                }
            }
            
            $_SESSION['success'] = "Sorteios executados: {$successCount} sucesso, {$errorCount} erros";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/draws/dashboard");
        exit;
    }
    
    /**
     * Cancelar sorteio
     */
    public function cancel() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showCancelForm();
            return;
        }
        
        try {
            $drawId = intval($_POST['draw_id'] ?? 0);
            $reason = $_POST['reason'] ?? '';
            
            if ($drawId <= 0 || empty($reason)) {
                throw new Exception("Todos os campos são obrigatórios");
            }
            
            $this->drawModel->cancelDraw($drawId, $reason);
            
            $_SESSION['success'] = "Sorteio cancelado com sucesso!";
            
            header("Location: /admin/draws/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/draws/cancel");
            exit;
        }
    }
    
    /**
     * Reexecutar sorteio
     */
    public function redraw() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showRedrawForm();
            return;
        }
        
        try {
            $raffleId = intval($_POST['raffle_id'] ?? 0);
            $reason = $_POST['reason'] ?? '';
            
            if ($raffleId <= 0 || empty($reason)) {
                throw new Exception("Todos os campos são obrigatórios");
            }
            
            $result = $this->drawModel->redraw($raffleId, $reason);
            
            $_SESSION['success'] = "Sorteio reexecutado com sucesso! Novo vencedor: {$result['winner_number']}";
            
            header("Location: /admin/draws/dashboard");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: /admin/draws/redraw");
            exit;
        }
    }
    
    /**
     * Relatório de sorteios
     */
    public function report() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $report = $this->drawModel->generateDrawReport($dateFrom, $dateTo);
            
            $this->showDrawReport($report);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Limpar sorteios antigos
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
            $count = $this->drawModel->cleanupOldDraws($days);
            
            $_SESSION['success'] = "$count sorteios antigos foram removidos (período de $days dias)";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/draws/dashboard");
        exit;
    }
    
    /**
     * Exibir dashboard de sorteios
     */
    private function showDrawDashboard($statistics, $recentDraws, $readyRaffles) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorteios - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .actions { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .draws-table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        .draws-table table { width: 100%; border-collapse: collapse; }
        .draws-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .draws-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .draws-table tr:hover { background: #f8f9fa; }
        .winner-badge { background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .manual-badge { background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .auto-badge { background: #d1ecf1; color: #0c5460; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .actions { flex-direction: column; align-items: stretch; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Sistema de Sorteios</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        ' . (!empty($readyRaffles) ? '<div class="alert alert-warning">⚠️ Existem ' . count($readyRaffles) . ' rifas prontas para sorteio!</div>' : '') . '
        
        <div class="actions">
            <div>
                <h3>🎯 Dashboard de Sorteios</h3>
            </div>
            <div>
                <a href="/admin/draws/perform" class="btn btn-success">🎲 Realizar Sorteio</a>
                <a href="/admin/draws/schedule" class="btn btn-warning">⏰ Agendar</a>
                <a href="/admin/draws/execute-scheduled" class="btn btn-primary">🔄 Executar Agendados</a>
                <a href="/admin/draws/history" class="btn btn-primary">📋 Histórico</a>
                <a href="/admin/draws/report" class="btn">📊 Relatório</a>
                <a href="/admin/dashboard" class="btn">← Voltar</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">' . ($statistics['total_draws'] ?? 0) . '</div>
                <div class="stat-label">Total de Sorteios</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($statistics['unique_raffles'] ?? 0) . '</div>
                <div class="stat-label">Rifas Sorteadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($statistics['manual_draws'] ?? 0) . '</div>
                <div class="stat-label">Sorteios Manuais</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . ($statistics['automatic_draws'] ?? 0) . '</div>
                <div class="stat-label">Sorteios Automáticos</div>
            </div>
        </div>
        
        <div class="draws-table">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Sorteios Recentes</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rifa</th>
                        <th>Número Vencedor</th>
                        <th>Vencedor</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($recentDraws as $draw) {
            echo '<tr>
                <td>#' . $draw['id'] . '</td>
                <td>' . htmlspecialchars($draw['raffle_title']) . '</td>
                <td><span class="winner-badge">' . $draw['winner_number'] . '</span></td>
                <td>' . htmlspecialchars($draw['participant_name']) . '</td>
                <td><span class="' . ($draw['manual'] ? 'manual-badge' : 'auto-badge') . '">' . ($draw['manual'] ? 'Manual' : 'Auto') . '</span></td>
                <td>' . date('d/m/Y H:i:s', strtotime($draw['created_at'])) . '</td>
                <td>
                    <a href="/admin/draws/verify?draw_id=' . $draw['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">🔍</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de sorteio
     */
    private function showDrawForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realizar Sorteio - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group select { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group select:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #2ecc71); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🎲 Realizar Sorteio</div>
            <div class="form-subtitle">Selecione a rifa para realizar o sorteio manual</div>
        </div>
        
        <form method="POST" action="/admin/draws/perform">
            <div class="form-group">
                <label for="raffle_id">Rifa:</label>
                <select id="raffle_id" name="raffle_id" required>
                    <option value="">Selecione uma rifa</option>';
        
        // Obter rifas disponíveis para sorteio
        $sql = "SELECT id, title FROM raffles WHERE status IN ('drawing', 'ready') ORDER BY title";
        $stmt = $this->db->query($sql);
        
        while ($raffle = $stmt->fetch()) {
            echo '<option value="' . $raffle['id'] . '">' . htmlspecialchars($raffle['title']) . '</option>';
        }
        
        echo '</select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success">🎲 Realizar Sorteio</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/draws/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir histórico de sorteios
     */
    private function showDrawHistory($draws, $raffleId) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Sorteios - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .filter { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .draws-table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .draws-table table { width: 100%; border-collapse: collapse; }
        .draws-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .draws-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .draws-table tr:hover { background: #f8f9fa; }
        .winner-badge { background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .manual-badge { background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .auto-badge { background: #d1ecf1; color: #0c5460; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .filter-form { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>📋 ' . SITE_NAME . '</h1>
                <small>Histórico de Sorteios</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="filter">
            <form method="GET" class="filter-form">
                <select name="raffle_id">
                    <option value="">Todas as Rifas</option>';
        
        // Obter rifas com sorteios
        $sql = "SELECT DISTINCT r.id, r.title 
                FROM raffles r 
                JOIN draws d ON r.id = d.raffle_id 
                ORDER BY r.title";
        $stmt = $this->db->query($sql);
        
        while ($raffle = $stmt->fetch()) {
            echo '<option value="' . $raffle['id'] . '" ' . (($raffleId ?? '') == $raffle['id'] ? 'selected' : '') . '>' . htmlspecialchars($raffle['title']) . '</option>';
        }
        
        echo '</select>
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                <a href="/admin/draws/dashboard" class="btn">← Voltar</a>
            </form>
        </div>
        
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="draws-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rifa</th>
                        <th>Número Vencedor</th>
                        <th>Vencedor</th>
                        <th>CPF</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($draws as $draw) {
            echo '<tr>
                <td>#' . $draw['id'] . '</td>
                <td>' . htmlspecialchars($draw['raffle_title']) . '</td>
                <td><span class="winner-badge">' . $draw['winner_number'] . '</span></td>
                <td>' . htmlspecialchars($draw['participant_name']) . '</td>
                <td>' . htmlspecialchars($draw['participant_cpf']) . '</td>
                <td>' . htmlspecialchars($draw['participant_email']) . '</td>
                <td><span class="' . ($draw['manual'] ? 'manual-badge' : 'auto-badge') . '">' . ($draw['manual'] ? 'Manual' : 'Auto') . '</span></td>
                <td>' . date('d/m/Y H:i:s', strtotime($draw['created_at'])) . '</td>
                <td>
                    <a href="/admin/draws/verify?draw_id=' . $draw['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">🔍</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de verificação
     */
    private function showVerifyForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Sorteio - ' . SITE_NAME . '</title>
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
            <div class="form-title">🔍 Verificar Sorteio</div>
            <div class="form-subtitle">Verifique a integridade do sorteio</div>
        </div>
        
        <form method="GET" action="/admin/draws/verify">
            <div class="form-group">
                <label for="draw_id">ID do Sorteio:</label>
                <input type="number" id="draw_id" name="draw_id" placeholder="Digite o ID do sorteio" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">🔍 Verificar</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/draws/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir resultado da verificação
     */
    private function showVerificationResult($verification) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado da Verificação - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .result-container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 600px; width: 100%; max-width: 90%; }
        .result-header { text-align: center; margin-bottom: 30px; }
        .result-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .result-icon { font-size: 4em; margin-bottom: 20px; }
        .valid { color: #27ae60; }
        .invalid { color: #e74c3c; }
        .result-details { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .detail-label { font-weight: 600; color: #2c3e50; }
        .detail-value { color: #666; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-header">
            <div class="result-icon ' . ($verification['valid'] ? 'valid' : 'invalid') . '">' . ($verification['valid'] ? '✅' : '❌') . '</div>
            <div class="result-title">' . ($verification['valid'] ? 'Sorteio Válido' : 'Sorteio Inválido') . '</div>
        </div>
        
        <div class="result-details">
            <div class="detail-row">
                <span class="detail-label">Vencedor Original:</span>
                <span class="detail-value">' . $verification['original_winner'] . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Vencedor Calculado:</span>
                <span class="detail-value">' . $verification['calculated_winner'] . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Seed:</span>
                <span class="detail-value" style="font-family: monospace; font-size: 12px;">' . substr($verification['seed'], 0, 20) . '...</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Data do Sorteio:</span>
                <span class="detail-value">' . date('d/m/Y H:i:s', strtotime($verification['draw_time'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Números Sorteados:</span>
                <span class="detail-value">' . $verification['numbers_count'] . '</span>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="/admin/draws/dashboard" class="btn btn-primary">← Voltar</a>
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
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Erro</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>
        <a href="/admin/draws/dashboard" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
}

?>
