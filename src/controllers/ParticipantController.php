<?php
/**
 * Controller para gerenciar participantes no painel administrativo
 */

class ParticipantController {
    private $db;
    private $participantModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->participantModel = new Participant($this->db);
    }
    
    /**
     * Listar participantes
     */
    public function index() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $page = $_GET['page'] ?? 1;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        try {
            $participants = $this->participantModel->getAll($page, 20, $status, $search);
            $this->showParticipantList($participants, $page, $status, $search);
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes do participante
     */
    public function details($cpf) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $participant = $this->participantModel->getByCpf($cpf);
            if (!$participant) {
                throw new Exception("Participante não encontrado");
            }
            
            $statistics = $this->participantModel->getStatistics($participant['id']);
            $history = $this->participantModel->getHistory($cpf);
            
            $this->showParticipantDetails($participant, $statistics, $history);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Suspender participante
     */
    public function suspend($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/participants');
            exit;
        }
        
        try {
            $reason = $_POST['reason'] ?? 'Motivo não informado';
            $this->participantModel->suspend($id, $reason);
            
            $_SESSION['success'] = "Participante suspenso com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/participants');
        exit;
    }
    
    /**
     * Reativar participante
     */
    public function reactivate($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $this->participantModel->reactivate($id);
            $_SESSION['success'] = "Participante reativado com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/participants');
        exit;
    }
    
    /**
     * Bloquear participante
     */
    public function block($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/participants');
            exit;
        }
        
        try {
            $reason = $_POST['reason'] ?? 'Motivo não informado';
            $this->participantModel->block($id, $reason);
            
            $_SESSION['success'] = "Participante bloqueado com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/participants');
        exit;
    }
    
    /**
     * Atualizar score de fraude
     */
    public function updateFraudScore($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/participants');
            exit;
        }
        
        try {
            $score = intval($_POST['fraud_score'] ?? 0);
            $this->participantModel->updateFraudScore($id, $score);
            
            $_SESSION['success'] = "Score de fraude atualizado com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/participants');
        exit;
    }
    
    /**
     * Listar participantes suspeitos
     */
    public function suspicious() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $participants = $this->participantModel->getSuspiciousParticipants(50);
            $this->showSuspiciousParticipants($participants);
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Exibir lista de participantes
     */
    private function showParticipantList($participants, $page, $status, $search) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participantes - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .actions { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .filter { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter input, .filter select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .table table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #fff3cd; color: #856404; }
        .status-blocked { background: #f8d7da; color: #721c24; }
        .status-suspicious { background: #d1ecf1; color: #0c5460; }
        .fraud-score { font-weight: bold; }
        .fraud-low { color: #27ae60; }
        .fraud-medium { color: #f39c12; }
        .fraud-high { color: #e74c3c; }
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
                <small>Gestão de Participantes</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="actions">
            <div>
                <h2>👥 Lista de Participantes</h2>
            </div>
            <div>
                <a href="/admin/participants/suspicious" class="btn btn-warning">⚠️ Suspeitos</a>
                <a href="/admin/dashboard" class="btn">📊 Dashboard</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Buscar CPF, nome ou e-mail" value="' . htmlspecialchars($search ?? '') . '">
                <select name="status">
                    <option value="">Todos</option>
                    <option value="active" ' . (($status ?? '') === 'active' ? 'selected' : '') . '>Ativos</option>
                    <option value="suspended" ' . (($status ?? '') === 'suspended' ? 'selected' : '') . '>Suspensos</option>
                    <option value="blocked" ' . (($status ?? '') === 'blocked' ? 'selected' : '') . '>Bloqueados</option>
                    <option value="suspicious" ' . (($status ?? '') === 'suspicious' ? 'selected' : '') . '>Suspeitos</option>
                </select>
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            </form>
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>CPF</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Score Fraude</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($participants as $participant) {
            $fraudClass = $participant['fraud_score'] <= 30 ? 'fraud-low' : 
                          ($participant['fraud_score'] <= 60 ? 'fraud-medium' : 'fraud-high');
            
            echo '<tr>
                <td>' . htmlspecialchars($participant['cpf']) . '</td>
                <td>' . htmlspecialchars($participant['name']) . '</td>
                <td>' . htmlspecialchars($participant['email']) . '</td>
                <td>' . htmlspecialchars($participant['phone'] ?? '-') . '</td>
                <td><span class="fraud-score ' . $fraudClass . '">' . $participant['fraud_score'] . '</span></td>
                <td>
                    <span class="status-badge status-' . $participant['status'] . '">' . $this->getStatusLabel($participant['status']) . '</span>
                </td>
                <td>' . date('d/m/Y', strtotime($participant['created_at'])) . '</td>
                <td>
                    <div class="actions-cell">
                        <a href="/admin/participants/details/' . $participant['cpf'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">📊</a>';
            
            if ($participant['status'] === 'active') {
                echo '<a href="/admin/participants/suspend/' . $participant['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Suspender participante?\')">⏸️</a>';
            }
            
            if (in_array($participant['status'], ['suspended', 'suspicious'])) {
                echo '<a href="/admin/participants/reactivate/' . $participant['id'] . '" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">✅</a>';
            }
            
            if ($participant['status'] !== 'blocked') {
                echo '<a href="/admin/participants/block/' . $participant['id'] . '" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Bloquear participante?\')">🚫</a>';
            }
            
            echo '</div></td></tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/participants?page=' . max(1, $page - 1) . '" class="btn">← Anterior</a>
            <span style="margin: 0 20px;">Página ' . $page . '</span>
            <a href="/admin/participants?page=' . ($page + 1) . '" class="btn">Próximo →</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir detalhes do participante
     */
    private function showParticipantDetails($participant, $statistics, $history) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($participant['name']) . ' - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .participant-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .info-value { color: #666; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; color: #3498db; margin-bottom: 10px; }
        .stat-label { color: #666; }
        .history-table { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .history-table table { width: 100%; border-collapse: collapse; }
        .history-table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #2c3e50; }
        .history-table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #fff3cd; color: #856404; }
        .status-blocked { background: #f8d7da; color: #721c24; }
        .fraud-score { font-weight: bold; }
        .fraud-low { color: #27ae60; }
        .fraud-medium { color: #f39c12; }
        .fraud-high { color: #e74c3c; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Detalhes do Participante</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="participant-card">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">👥 ' . htmlspecialchars($participant['name']) . '</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">CPF</div>
                    <div class="info-value">' . htmlspecialchars($participant['cpf']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">E-mail</div>
                    <div class="info-value">' . htmlspecialchars($participant['email']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Telefone</div>
                    <div class="info-value">' . htmlspecialchars($participant['phone'] ?? '-') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Endereço</div>
                    <div class="info-value">' . htmlspecialchars($participant['address'] ?? '-') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Score de Fraude</div>
                    <div class="info-value fraud-score ' . ($statistics['fraud_score'] <= 30 ? 'fraud-low' : ($statistics['fraud_score'] <= 60 ? 'fraud-medium' : 'fraud-high')) . '">' . $statistics['fraud_score'] . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-' . $participant['status'] . '">' . $this->getStatusLabel($participant['status']) . '</span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Cadastro</div>
                    <div class="info-value">' . date('d/m/Y H:i', strtotime($participant['created_at'])) . '</div>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="/admin/participants" class="btn btn-primary">← Voltar</a>';
                
                if ($participant['status'] === 'active') {
                    echo '<a href="/admin/participants/suspend/' . $participant['id'] . '" class="btn btn-warning" style="margin-left: 10px;" onclick="return confirm(\'Suspender participante?\')">⏸️ Suspender</a>';
                }
                
                if (in_array($participant['status'], ['suspended', 'suspicious'])) {
                    echo '<a href="/admin/participants/reactivate/' . $participant['id'] . '" class="btn btn-success" style="margin-left: 10px;">✅ Reativar</a>';
                }
                
                if ($participant['status'] !== 'blocked') {
                    echo '<a href="/admin/participants/block/' . $participant['id'] . '" class="btn btn-danger" style="margin-left: 10px;" onclick="return confirm(\'Bloquear participante?\')">🚫 Bloquear</a>';
                }
                
                echo '</div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">' . $statistics['total_numbers'] . '</div>
                <div class="stat-label">Total de Números</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $statistics['paid_numbers'] . '</div>
                <div class="stat-label">Números Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $statistics['reserved_numbers'] . '</div>
                <div class="stat-label">Números Reservados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ ' . number_format($statistics['total_spent'], 2, ',', '.') . '</div>
                <div class="stat-label">Total Gasto</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . $statistics['unique_raffles'] . '</div>
                <div class="stat-label">Rifas Participadas</div>
            </div>
        </div>
        
        <div class="history-table">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Histórico de Participações</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Rifa</th>
                        <th>Número</th>
                        <th>Status</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($history as $item) {
            echo '<tr>
                <td>' . date('d/m/Y H:i', strtotime($item['created_at'])) . '</td>
                <td>' . htmlspecialchars($item['raffle_title']) . '</td>
                <td>' . $item['number'] . '</td>
                <td>
                    <span class="status-badge status-' . $item['status'] . '">' . $this->getStatusLabel($item['status']) . '</span>
                </td>
                <td>R$ ' . number_format($item['payment_amount'] ?? 0, 2, ',', '.') . '</td>
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
     * Exibir participantes suspeitos
     */
    private function showSuspiciousParticipants($participants) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participantes Suspeitos - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .alert { background: #fff3cd; color: #856404; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #f39c12; }
        .table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .table table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .fraud-score { font-weight: bold; }
        .fraud-medium { color: #f39c12; }
        .fraud-high { color: #e74c3c; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Participantes Suspeitos</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="alert">
            <strong>⚠️ Atenção:</strong> Lista de participantes com score de fraude elevado ou comportamento suspeito.
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>CPF</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Score Fraude</th>
                        <th>Status</th>
                        <th>Total Números</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($participants as $participant) {
            echo '<tr>
                <td>' . htmlspecialchars($participant['cpf']) . '</td>
                <td>' . htmlspecialchars($participant['name']) . '</td>
                <td>' . htmlspecialchars($participant['email']) . '</td>
                <td><span class="fraud-score ' . ($participant['fraud_score'] <= 60 ? 'fraud-medium' : 'fraud-high') . '">' . $participant['fraud_score'] . '</span></td>
                <td>' . htmlspecialchars($participant['status']) . '</td>
                <td>' . $participant['total_numbers'] . '</td>
                <td>
                    <a href="/admin/participants/details/' . $participant['cpf'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">📊</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/participants" class="btn btn-primary">← Voltar</a>
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
        <a href="/admin/participants" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
    
    /**
     * Obter label do status
     */
    private function getStatusLabel($status) {
        $labels = [
            'active' => 'Ativo',
            'suspended' => 'Suspenso',
            'blocked' => 'Bloqueado',
            'suspicious' => 'Suspeito'
        ];
        
        return $labels[$status] ?? $status;
    }
}

?>
