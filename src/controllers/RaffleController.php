<?php
/**
 * Controller para gerenciar rifas no painel administrativo
 */

class RaffleController {
    private $db;
    private $raffleModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->raffleModel = new Raffle($this->db);
    }
    
    /**
     * Listar rifas
     */
    public function index() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $page = $_GET['page'] ?? 1;
        $status = $_GET['status'] ?? null;
        
        try {
            $raffles = $this->raffleModel->getAll($page, 20, $status);
            $this->showRaffleList($raffles, $page, $status);
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Formulário de criação
     */
    public function create() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $this->showRaffleForm();
    }
    
    /**
     * Salvar nova rifa
     */
    public function store() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/raffles');
            exit;
        }
        
        try {
            $data = $this->sanitizeRaffleData($_POST);
            $raffleId = $this->raffleModel->create($data);
            
            $_SESSION['success'] = "Rifa #{$raffleId} criada com sucesso!";
            header('Location: /admin/raffles');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->showRaffleForm($_POST);
        }
    }
    
    /**
     * Editar rifa
     */
    public function edit($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $raffle = $this->raffleModel->getById($id);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $this->showRaffleForm($raffle, true);
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/raffles');
            exit;
        }
    }
    
    /**
     * Atualizar rifa
     */
    public function update($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/raffles');
            exit;
        }
        
        try {
            $data = $this->sanitizeRaffleData($_POST);
            $this->raffleModel->update($id, $data);
            
            $_SESSION['success'] = "Rifa #{$id} atualizada com sucesso!";
            header('Location: /admin/raffles');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $raffle = $this->raffleModel->getById($id);
            $this->showRaffleForm(array_merge($_POST, $raffle), true);
        }
    }
    
    /**
     * Publicar rifa
     */
    public function publish($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $this->raffleModel->publish($id);
            $_SESSION['success'] = "Rifa #{$id} publicada com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/raffles');
        exit;
    }
    
    /**
     * Encerrar rifa
     */
    public function close($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $this->raffleModel->close($id);
            $_SESSION['success'] = "Rifa #{$id} encerrada com sucesso!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/raffles');
        exit;
    }
    
    /**
     * Sortear rifa
     */
    public function draw($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $result = $this->raffleModel->draw($id);
            $_SESSION['success'] = "Sorteio realizado! Vencedor: {$result['winner_name']} - Número: {$result['winner_number']}";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/raffles');
        exit;
    }
    
    /**
     * Excluir rifa (apenas rascunhos)
     */
    public function delete($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $raffle = $this->raffleModel->getById($id);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            if ($raffle['status'] !== 'draft') {
                throw new Exception("Apenas rascunhos podem ser excluídos");
            }
            
            // Excluir números
            $sql = "DELETE FROM raffle_numbers WHERE raffle_id = ?";
            $this->db->query($sql, [$id]);
            
            // Excluir rifa
            $sql = "DELETE FROM raffles WHERE id = ?";
            $this->db->query($sql, [$id]);
            
            $_SESSION['success'] = "Rifa #{$id} excluída com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: /admin/raffles');
        exit;
    }
    
    /**
     * Ver estatísticas
     */
    public function statistics($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $raffle = $this->raffleModel->getById($id);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $stats = $this->raffleModel->getStatistics($id);
            $this->showStatistics($raffle, $stats);
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/raffles');
            exit;
        }
    }
    
    /**
     * Limpar dados do formulário
     */
    private function sanitizeRaffleData($data) {
        return [
            'title' => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'prize_description' => trim($data['prize_description'] ?? ''),
            'prize_market_value' => floatval($data['prize_market_value'] ?? 0),
            'prize_images' => isset($data['prize_images']) ? json_decode($data['prize_images']) : [],
            'number_price' => floatval($data['number_price'] ?? 0),
            'number_quantity' => intval($data['number_quantity'] ?? 0),
            'max_numbers_per_cpf' => intval($data['max_numbers_per_cpf'] ?? 10),
            'regulation' => trim($data['regulation'] ?? ''),
            'start_sales_datetime' => $data['start_sales_datetime'] ?? date('Y-m-d H:i:s'),
            'end_sales_datetime' => $data['end_sales_datetime'] ?? '',
            'draw_datetime' => $data['draw_datetime'] ?? '',
            'minimum_wait_hours' => intval($data['minimum_wait_hours'] ?? 24),
            'delivery_method' => trim($data['delivery_method'] ?? 'Presencial'),
            'delivery_deadline' => trim($data['delivery_deadline'] ?? '30 dias após o sorteio')
        ];
    }
    
    /**
     * Exibir lista de rifas
     */
    private function showRaffleList($raffles, $page, $status) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifas - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .actions { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .filter { display: flex; gap: 10px; align-items: center; }
        .filter select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }
        .table { background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .table table { width: 100%; border-collapse: collapse; }
        .table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .table td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .table tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-draft { background: #f8f9fa; color: #6c757d; }
        .status-active { background: #d4edda; color: #155724; }
        .status-sales_closed { background: #fff3cd; color: #856404; }
        .status-drawn { background: #d1ecf1; color: #0c5460; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        .actions-cell { display: flex; gap: 5px; flex-wrap: wrap; }
        .progress-bar { width: 100px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #27ae60; transition: width 0.3s; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .actions { flex-direction: column; gap: 15px; } .filter { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Gestão de Rifas</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="actions">
            <div>
                <h2>📋 Lista de Rifas</h2>
            </div>
            <div>
                <a href="/admin/raffles/create" class="btn btn-primary">➕ Nova Rifa</a>
                <a href="/admin/dashboard" class="btn">📊 Dashboard</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label>Filtrar:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="draft" ' . ($status === 'draft' ? 'selected' : '') . '>Rascunhos</option>
                    <option value="active" ' . ($status === 'active' ? 'selected' : '') . '>Ativas</option>
                    <option value="sales_closed" ' . ($status === 'sales_closed' ? 'selected' : '') . '>Encerradas</option>
                    <option value="drawn" ' . ($status === 'drawn' ? 'selected' : '') . '>Sorteadas</option>
                </select>
            </form>
        </div>
        
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Prêmio</th>
                        <th>Números</th>
                        <th>Vendas</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($raffles as $raffle) {
            $salesPercentage = $raffle['total_count'] > 0 
                ? round(($raffle['paid_count'] / $raffle['total_count']) * 100, 1) 
                : 0;
            
            echo '<tr>
                <td>#' . $raffle['id'] . '</td>
                <td>
                    <strong>' . htmlspecialchars($raffle['title']) . '</strong><br>
                    <small style="color: #666;">' . date('d/m/Y', strtotime($raffle['created_at'])) . '</small>
                </td>
                <td>' . htmlspecialchars(substr($raffle['prize_description'], 0, 30)) . '...</td>
                <td>' . $raffle['total_count'] . '</td>
                <td>
                    <div>' . $raffle['paid_count'] . '/' . $raffle['total_count'] . ' (' . $salesPercentage . '%)</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ' . $salesPercentage . '%;"></div>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-' . $raffle['status'] . '">' . $this->getStatusLabel($raffle['status']) . '</span>
                </td>
                <td>
                    <div class="actions-cell">
                        <a href="/admin/raffles/statistics/' . $raffle['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">📊</a>';
            
            if ($raffle['status'] === 'draft') {
                echo '<a href="/admin/raffles/edit/' . $raffle['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">✏️</a>
                      <a href="/admin/raffles/publish/' . $raffle['id'] . '" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">🚀</a>
                      <a href="/admin/raffles/delete/' . $raffle['id'] . '" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Tem certeza?\')">🗑️</a>';
            }
            
            if ($raffle['status'] === 'active') {
                echo '<a href="/admin/raffles/close/' . $raffle['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">🔒</a>';
            }
            
            if ($raffle['status'] === 'sales_closed') {
                echo '<a href="/admin/raffles/draw/' . $raffle['id'] . '" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm(\'Realizar sorteio agora?\')">🎲</a>';
            }
            
            echo '</div></td></tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/raffles?page=' . max(1, $page - 1) . '" class="btn">← Anterior</a>
            <span style="margin: 0 20px;">Página ' . $page . '</span>
            <a href="/admin/raffles?page=' . ($page + 1) . '" class="btn">Próximo →</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de rifa
     */
    private function showRaffleForm($data = [], $edit = false) {
        $error = $_SESSION['error'] ?? '';
        unset($_SESSION['error']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . ($edit ? 'Editar' : 'Nova') . ' Rifa - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .form-row { grid-template-columns: 1fr; } .form-actions { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>' . ($edit ? 'Editar' : 'Nova') . ' Rifa</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        
        <div class="form-card">
            <form method="POST" action="' . ($edit ? '/admin/raffles/update/' . $data['id'] : '/admin/raffles/store') . '">
                <div class="form-group">
                    <label for="title">Título da Rifa *</label>
                    <input type="text" id="title" name="title" value="' . htmlspecialchars($data['title'] ?? '') . '" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição *</label>
                    <textarea id="description" name="description" required>' . htmlspecialchars($data['description'] ?? '') . '</textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prize_description">Descrição do Prêmio *</label>
                        <input type="text" id="prize_description" name="prize_description" value="' . htmlspecialchars($data['prize_description'] ?? '') . '" required>
                    </div>
                    <div class="form-group">
                        <label for="prize_market_value">Valor de Mercado (R$)</label>
                        <input type="number" id="prize_market_value" name="prize_market_value" value="' . htmlspecialchars($data['prize_market_value'] ?? 0) . '" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="number_price">Preço por Número (R$) *</label>
                        <input type="number" id="number_price" name="number_price" value="' . htmlspecialchars($data['number_price'] ?? '') . '" step="0.01" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="number_quantity">Quantidade de Números *</label>
                        <input type="number" id="number_quantity" name="number_quantity" value="' . htmlspecialchars($data['number_quantity'] ?? '') . '" min="100" max="10000" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_numbers_per_cpf">Limite por CPF</label>
                        <input type="number" id="max_numbers_per_cpf" name="max_numbers_per_cpf" value="' . htmlspecialchars($data['max_numbers_per_cpf'] ?? 10) . '" min="1" max="50">
                    </div>
                    <div class="form-group">
                        <label for="delivery_method">Forma de Entrega</label>
                        <select id="delivery_method" name="delivery_method">
                            <option value="Presencial" ' . (($data['delivery_method'] ?? '') === 'Presencial' ? 'selected' : '') . '>Presencial</option>
                            <option value="Sedex" ' . (($data['delivery_method'] ?? '') === 'Sedex' ? 'selected' : '') . '>Sedex</option>
                            <option value="Retirada" ' . (($data['delivery_method'] ?? '') === 'Retirada' ? 'selected' : '') . '>Retirada</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_sales_datetime">Início das Vendas</label>
                        <input type="datetime-local" id="start_sales_datetime" name="start_sales_datetime" value="' . htmlspecialchars($data['start_sales_datetime'] ?? date('Y-m-d\TH:i')) . '">
                    </div>
                    <div class="form-group">
                        <label for="end_sales_datetime">Término das Vendas *</label>
                        <input type="datetime-local" id="end_sales_datetime" name="end_sales_datetime" value="' . htmlspecialchars($data['end_sales_datetime'] ?? '') . '" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="draw_datetime">Data do Sorteio *</label>
                        <input type="datetime-local" id="draw_datetime" name="draw_datetime" value="' . htmlspecialchars($data['draw_datetime'] ?? '') . '" required>
                    </div>
                    <div class="form-group">
                        <label for="minimum_wait_hours">Prazo de Espera (horas)</label>
                        <input type="number" id="minimum_wait_hours" name="minimum_wait_hours" value="' . htmlspecialchars($data['minimum_wait_hours'] ?? 24) . '" min="1" max="168">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="regulation">Regulamento</label>
                    <textarea id="regulation" name="regulation" rows="5">' . htmlspecialchars($data['regulation'] ?? '') . '</textarea>
                </div>
                
                <div class="form-group">
                    <label for="delivery_deadline">Prazo de Entrega</label>
                    <input type="text" id="delivery_deadline" name="delivery_deadline" value="' . htmlspecialchars($data['delivery_deadline'] ?? '30 dias após o sorteio') . '">
                </div>
                
                <div class="form-actions">
                    <a href="/admin/raffles" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">' . ($edit ? 'Atualizar' : 'Criar') . ' Rifa</button>
                </div>
            </form>
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
            'draft' => 'Rascunho',
            'active' => 'Ativa',
            'sales_closed' => 'Encerrada',
            'drawn' => 'Sorteada'
        ];
        
        return $labels[$status] ?? $status;
    }
}

?>
