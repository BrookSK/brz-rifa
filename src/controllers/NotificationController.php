<?php
/**
 * Controller para gerenciar notificações no painel administrativo
 */

class NotificationController {
    private $db;
    private $notificationModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->notificationModel = new Notification($this->db);
    }
    
    /**
     * Listar notificações
     */
    public function index() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $page = $_GET['page'] ?? 1;
        $type = $_GET['type'] ?? null;
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        try {
            $notifications = $this->notificationModel->getAll($page, 20, [
                'type' => $type,
                'status' => $status,
                'priority' => $priority,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            
            $statistics = $this->notificationModel->getStatistics($dateFrom, $dateTo);
            
            $this->showNotificationList($notifications, $statistics, $page, $type, $status, $priority, $dateFrom, $dateTo);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes da notificação
     */
    public function details($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $notification = $this->notificationModel->getById($id);
            if (!$notification) {
                throw new Exception("Notificação não encontrada");
            }
            
            $channels = $this->notificationModel->getChannels($id);
            
            $this->showNotificationDetails($notification, $channels);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Criar notificação
     */
    public function create() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showCreateForm();
            return;
        }
        
        try {
            $type = $_POST['type'] ?? 'custom';
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $recipientType = $_POST['recipient_type'] ?? 'admin';
            $recipientId = $_POST['recipient_id'] ?? null;
            $priority = $_POST['priority'] ?? 'normal';
            $channels = $_POST['channels'] ?? ['email'];
            
            // Validar campos obrigatórios
            if (empty($title) || empty($message)) {
                throw new Exception("Título e mensagem são obrigatórios");
            }
            
            $notificationId = $this->notificationModel->create(
                $type,
                $title,
                $message,
                $recipientId,
                $recipientType,
                null,
                $priority,
                $channels
            );
            
            $_SESSION['success'] = "Notificação #$notificationId criada com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/notifications");
        exit;
    }
    
    /**
     * Reenviar notificação
     */
    public function resend($id) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showResendForm($id);
            return;
        }
        
        try {
            $channels = $_POST['channels'] ?? null;
            $this->notificationModel->resend($id, $channels);
            
            $_SESSION['success'] = "Notificação reenviada com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/notifications");
        exit;
    }
    
    /**
     * Processar fila de notificações
     */
    public function processQueue() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $limit = $_GET['limit'] ?? 10;
            $processed = $this->notificationModel->processQueue($limit);
            
            $_SESSION['success'] = "$processed notificações processadas com sucesso!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/notifications/dashboard");
        exit;
    }
    
    /**
     * Dashboard de notificações
     */
    public function dashboard() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        try {
            $statistics = $this->notificationModel->getStatistics();
            $pendingNotifications = $this->notificationModel->getPendingNotifications();
            
            $this->showNotificationDashboard($statistics, $pendingNotifications);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Limpar notificações antigas
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
            $days = intval($_POST['days'] ?? 90);
            $count = $this->notificationModel->cleanup($days);
            
            $_SESSION['success'] = "$count notificações antigas foram removidas (período de $days dias)";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/notifications/dashboard");
        exit;
    }
    
    /**
     * Testar notificação
     */
    public function test() {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showTestForm();
            return;
        }
        
        try {
            $type = $_POST['type'] ?? 'test';
            $title = $_POST['title'] ?? 'Teste de Notificação';
            $message = $_POST['message'] ?? 'Esta é uma mensagem de teste do sistema de notificações.';
            $channels = $_POST['channels'] ?? ['email'];
            $recipientId = $_SESSION['user_id'] ?? 1;
            
            $notificationId = $this->notificationModel->create(
                $type,
                $title,
                $message,
                $recipientId,
                'user',
                null,
                'normal',
                $channels
            );
            
            $_SESSION['success'] = "Notificação de teste #$notificationId enviada!";
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: /admin/notifications/dashboard");
        exit;
    }
    
    /**
     * Exibir lista de notificações
     */
    private function showNotificationList($notifications, $statistics, $page, $type, $status, $priority, $dateFrom, $dateTo) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - ' . SITE_NAME . '</title>
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
        .stat-recipients { color: #3498db; }
        .stat-types { color: #27ae60; }
        .stat-priority { color: #e74c3c; }
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
        .status-pending { background: #fff3cd; color: #856404; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-processed { background: #d1ecf1; color: #0c5460; }
        .priority-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-normal { background: #d1ecf1; color: #0c5460; }
        .priority-low { background: #e3f2fd; color: #0c5460; }
        .type-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; background: #e3f2fd; color: #0c5460; }
        .actions-cell { display: flex; gap: 5px; flex-wrap: wrap; }
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
                <h1>🔔 ' . SITE_NAME . '</h1>
                <small>Sistema de Notificações</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value stat-total">' . $statistics['total_notifications'] . '</div>
                <div class="stat-label">Total de Notificações</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-recipients">' . $statistics['unique_recipients'] . '</div>
                <div class="stat-label">Destinatários Únicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-types">' . $statistics['unique_types'] . '</div>
                <div class="stat-label">Tipos Diferentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value stat-priority">' . $statistics['high_priority'] . '</div>
                <div class="stat-label">Alta Prioridade</div>
            </div>
        </div>
        
        <div class="actions">
            <div>
                <h3>📋 Lista de Notificações</h3>
            </div>
            <div>
                <a href="/admin/notifications/dashboard" class="btn btn-success">📊 Dashboard</a>
                <a href="/admin/notifications/create" class="btn btn-primary">➕ Criar</a>
                <a href="/admin/notifications/process-queue" class="btn btn-warning">🔄 Processar Fila</a>
                <a href="/admin/notifications/test" class="btn">🧪 Testar</a>
                <a href="/admin/dashboard" class="btn">← Voltar</a>
            </div>
        </div>
        
        <div class="filter">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="type">
                    <option value="">Todos os Tipos</option>
                    <option value="payment_confirmed" ' . (($type ?? '') === 'payment_confirmed' ? 'selected' : '') . '>Pagamento Confirmado</option>
                    <option value="raffle_drawn" ' . (($type ?? '') === 'raffle_drawn' ? 'selected' : '') . '>Sorteio Realizado</option>
                    <option value="raffle_closed" ' . (($type ?? '') === 'raffle_closed' ? 'selected' : '') . '>Rifa Encerrada</option>
                    <option value="security_alert" ' . (($type ?? '') === 'security_alert' ? 'selected' : '') . '>Alerta de Segurança</option>
                    <option value="system_alert" ' . (($type ?? '') === 'system_alert' ? 'selected' : '') . '>Alerta do Sistema</option>
                    <option value="custom" ' . (($type ?? '') === 'custom' ? 'selected' : '') . '>Personalizado</option>
                </select>
                <select name="status">
                    <option value="">Todos os Status</option>
                    <option value="pending" ' . (($status ?? '') === 'pending' ? 'selected' : '') . '>Pendente</option>
                    <option value="sent" ' . (($status ?? '') === 'sent' ? 'selected' : '') . '>Enviada</option>
                    <option value="failed" ' . (($status ?? '') === 'failed' ? 'selected' : '') . '>Falha</option>
                    <option value="processed" ' . (($status ?? '') === 'processed' ? 'selected' : '') . '>Processada</option>
                </select>
                <select name="priority">
                    <option value="">Todas as Prioridades</option>
                    <option value="high" ' . (($priority ?? '') === 'high' ? 'selected' : '') . '>Alta</option>
                    <option value="normal" ' . (($priority ?? '') === 'normal' ? 'selected' : '') . '>Normal</option>
                    <option value="low" ' . (($priority ?? '') === 'low' ? 'selected' : '') . '>Baixa</option>
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
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Enviados</th>
                        <th>Falhas</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($notifications as $notification) {
            echo '<tr>
                <td>#' . $notification['id'] . '</td>
                <td>' . htmlspecialchars(substr($notification['title'], 0, 50)) . '</td>
                <td><span class="type-badge">' . htmlspecialchars($notification['type']) . '</span></td>
                <td><span class="priority-badge priority-' . $notification['priority'] . '">' . $this->getPriorityLabel($notification['priority']) . '</span></td>
                <td><span class="status-badge status-' . $notification['status'] . '">' . $this->getStatusLabel($notification['status']) . '</span></td>
                <td>' . $notification['sent_count'] . '</td>
                <td>' . $notification['failed_count'] . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($notification['created_at'])) . '</td>
                <td>
                    <div class="actions-cell">
                        <a href="/admin/notifications/' . $notification['id'] . '" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">👁️</a>';
            
            if ($notification['status'] === 'failed' || $notification['status'] === 'pending') {
                echo '<a href="/admin/notifications/resend/' . $notification['id'] . '" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">🔄</a>';
            }
            
            echo '</div></td></tr>';
        }
        
        echo '</tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin/notifications?page=' . max(1, $page - 1) . '" class="btn">← Anterior</a>
            <span style="margin: 0 20px;">Página ' . $page . '</span>
            <a href="/admin/notifications?page=' . ($page + 1) . '" class="btn">Próximo →</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir detalhes da notificação
     */
    private function showNotificationDetails($notification, $channels) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificação #' . $notification['id'] . ' - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 800px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .notification-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .notification-header { text-align: center; margin-bottom: 30px; }
        .notification-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .notification-id { color: #666; font-size: 1.2em; }
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .info-value { color: #666; }
        .message-preview { background: #f8f9fa; padding: 15px; border-radius: 10px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
        .channels-table { background: white; border-radius: 10px; overflow: hidden; margin-bottom: 20px; }
        .channels-table table { width: 100%; border-collapse: collapse; }
        .channels-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; }
        .channels-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-warning { background: #f39c12; color: white; }
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
                <h1>🔔 ' . SITE_NAME . '</h1>
                <small>Detalhes da Notificação</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="notification-card">
            <div class="notification-header">
                <div class="notification-title">📋 Notificação</div>
                <div class="notification-id">ID: #' . $notification['id'] . '</div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Título:</div>
                    <div class="info-value">' . htmlspecialchars($notification['title']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tipo:</div>
                    <div class="info-value">' . htmlspecialchars($notification['type']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Prioridade:</div>
                    <div class="info-value">' . $this->getPriorityLabel($notification['priority']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status:</div>
                    <div class="info-value">' . $this->getStatusLabel($notification['status']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Destinatário:</div>
                    <div class="info-value">' . htmlspecialchars($notification['recipient_type']) . ' - #' . $notification['recipient_id'] . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data Criação:</div>
                    <div class="info-value">' . date('d/m/Y H:i:s', strtotime($notification['created_at'])) . '</div>
                </div>';
                
                if ($notification['data']) {
                    echo '<div class="info-item">
                        <div class="info-label">Dados Adicionais:</div>
                        <div class="info-value">' . htmlspecialchars(substr($notification['data'], 0, 200)) . '...</div>
                    </div>';
                }
                
                echo '</div>';
                
                echo '<div class="message-preview">
                    <h4 style="margin-bottom: 10px;">📝 Mensagem:</h4>
                    <div>' . htmlspecialchars($notification['message']) . '</div>
                </div>';
                
                echo '</div>
                
                <div class="channels-table">
                    <h3 style="color: #2c3e50; margin-bottom: 20px;">📡 Canais de Envio</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Canal</th>
                                <th>Status</th>
                                <th>Data Envio</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody>';
                    
                    foreach ($channels as $channel) {
                        echo '<tr>
                            <td>' . htmlspecialchars($channel['channel']) . '</td>
                            <td><span class="status-badge status-' . $channel['status'] . '">' . $this->getStatusLabel($channel['status']) . '</span></td>
                            <td>' . ($channel['sent_at'] ? date('d/m/Y H:i:s', strtotime($channel['sent_at'])) : '-') . '</td>
                            <td>' . htmlspecialchars($channel['error_message'] ?? '-') . '</td>
                        </tr>';
                    }
                    
                    echo '</tbody>
                    </table>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="/admin/notifications" class="btn btn-primary">← Voltar</a>';
                
                if ($notification['status'] === 'failed' || $notification['status'] === 'pending') {
                    echo '<a href="/admin/notifications/resend/' . $notification['id'] . '" class="btn btn-warning" style="margin-left: 10px;">🔄 Reenviar</a>';
                }
                
                echo '</div>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir dashboard de notificações
     */
    private function showNotificationDashboard($statistics, $pendingNotifications) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Notificações - ' . SITE_NAME . '</title>
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
        .pending-notifications { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .notification-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .notification-item:last-child { border-bottom: none; }
        .notification-info { flex: 1; }
        .notification-title { font-weight: 600; color: #2c3e50; }
        .notification-time { color: #666; font-size: 0.9em; }
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
                <h1>🔔 ' . SITE_NAME . '</h1>
                <small>Dashboard de Notificações</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <h2 style="color: #2c3e50; margin-bottom: 30px;">📊 Visão Geral das Notificações</h2>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Estatísticas Gerais</h3>
                <div class="metric">
                    <span class="metric-label">Total de Notificações</span>
                    <span class="metric-value">' . $statistics['total_notifications'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Destinatários Únicos</span>
                    <span class="metric-value">' . $statistics['unique_recipients'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Tipos Diferentes</span>
                    <span class="metric-value">' . $statistics['unique_types'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Alta Prioridade</span>
                    <span class="metric-value danger">' . $statistics['high_priority'] . '</span>
                </div>
            </div>
            
            <div class="card">
                <h3>🔄 Fila de Processamento</h3>
                <div class="metric">
                    <span class="metric-label">Pendentes</span>
                    <span class="metric-value warning">' . count($pendingNotifications) . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Normal</span>
                    <span class="metric-value">' . $statistics['normal_priority'] . '</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Baixa</span>
                    <span class="metric-value">' . $statistics['low_priority'] . '</span>
                </div>
            </div>
        </div>
        
        ' . (!empty($pendingNotifications) ? '<div class="pending-notifications">
            <h3>📋 Notificações Pendentes</h3>
            <div class="notification-list">';
        
        foreach ($pendingNotifications as $notification) {
            echo '<div class="notification-item">
                <div class="notification-info">
                    <strong>' . htmlspecialchars($notification['title']) . '</strong>
                    <div class="notification-time">' . date('d/m/Y H:i:s', strtotime($notification['created_at'])) . '</div>
                </div>
                <div class="notification-time">
                    ' . htmlspecialchars($notification['type']) . '
                </div>
            </div>';
        }
        
        echo '</div>
        </div>' : '') . '
        
        <div class="actions">
            <div>
                <h3>🔧️ Ações do Sistema</h3>
            </div>
            <div>
                <a href="/admin/notifications/create" class="btn btn-primary">➕ Criar Notificação</a>
                <a href="/admin/notifications/process-queue" class="btn btn-warning">🔄 Processar Fila</a>
                <a href="/admin/notifications/test" class="btn btn-success">🧪 Testar Notificação</a>
                <a href="/admin/notifications/cleanup" class="btn btn-danger">🧹 Limpar Antigas</a>
                <a href="/admin/dashboard" class="btn btn-success">🏠 Dashboard Principal</a>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de criação
     */
    private function showCreateForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Notificação - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .checkbox-group { display: flex; gap: 15px; margin-bottom: 20px; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input { margin-right: 8px; }
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
            <div class="form-title">➕ Criar Notificação</div>
            <div class="form-subtitle">Envie notificações para usuários, participantes ou administradores</div>
        </div>
        
        <form method="POST" action="/admin/notifications/create">
            <div class="form-group">
                <label for="type">Tipo de Notificação:</label>
                <select id="type" name="type">
                    <option value="custom">Personalizado</option>
                    <option value="payment_confirmed">Pagamento Confirmado</option>
                    <option value="raffle_drawn">Sorteio Realizado</option>
                    <option value="raffle_closed">Rifa Encerrada</option>
                    <option value="security_alert">Alerta de Segurança</option>
                    <option value="system_alert">Alerta do Sistema</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Título:</label>
                <input type="text" id="title" name="title" placeholder="Digite o título da notificação" required>
            </div>
            
            <div class="form-group">
                <label for="message">Mensagem:</label>
                <textarea id="message" name="message" placeholder="Digite a mensagem completa..." required></textarea>
            </div>
            
            <div class="form-group">
                <label for="recipient_type">Destinatário:</label>
                <select id="recipient_type" name="recipient_type">
                    <option value="admin">Administradores</option>
                    <option value="user">Usuário Específico</option>
                    <option value="participant">Participante Específico</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority">Prioridade:</label>
                <select id="priority" name="priority">
                    <option value="low">Baixa</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">Alta</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Canais de Envio:</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_email" name="channels[]" value="email" checked>
                        <label for="channel_email">📧 Email</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_sms" name="channels[]" value="sms">
                        <label for="channel_sms">📱 SMS</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_push" name="channels[]" value="push">
                        <label for="channel_push">📱 Push Notification</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">➕ Criar Notificação</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/notifications" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de reenvio
     */
    private function showResendForm($id) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar Notificação - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .checkbox-group { display: flex; gap: 15px; margin-bottom: 20px; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input { margin-right: 8px; }
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
            <div class="form-title">🔄 Reenviar Notificação</div>
            <div class="form-subtitle">Selecione os canais para reenvio</div>
        </div>
        
        <form method="POST" action="/admin/notifications/resend/' . $id . '">
            <div class="form-group">
                <label>Canais de Envio:</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_email" name="channels[]" value="email" checked>
                        <label for="channel_email">📧 Email</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_sms" name="channels[]" value="sms">
                        <label for="channel_sms">📱 SMS</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_push" name="channels[]" value="push">
                        <label for="channel_push">📱 Push Notification</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-warning">🔄 Reenviar Notificação</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/notifications" class="btn">← Cancelar</a>
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
    <title>Limpar Notificações Antigas - ' . SITE_NAME . '</title>
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
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🧹 Limpar Notificações Antigas</div>
            <div class="form-subtitle">Remoção permanente de notificações antigas para manter o desempenho</div>
        </div>
        
        <form method="POST" action="/admin/notifications/cleanup">
            <div class="form-group">
                <label for="days">Período de retenção (dias):</label>
                <input type="number" id="days" name="days" value="90" min="30" max="365" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-danger">🧹 Limpar Notificações</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/notifications/dashboard" class="btn">← Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>';
    }
    
    /**
     * Exibir formulário de teste
     */
    private function showTestForm() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testar Notificação - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; max-width: 90%; }
        .form-header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .form-subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .checkbox-group { display: flex; gap: 15px; margin-bottom: 20px; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input { margin-right: 8px; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-success { background: #27ae60; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <div class="form-title">🧪 Testar Notificação</div>
            <div class="form-subtitle">Envie uma notificação de teste para verificar o funcionamento</div>
        </div>
        
        <form method="POST" action="/admin/notifications/test">
            <div class="form-group">
                <label for="type">Tipo de Teste:</label>
                <select id="type" name="type">
                    <option value="test">Teste Simples</option>
                    <option value="payment_confirmed">Pagamento Confirmado</option>
                    <option value="raffle_drawn">Sorteio Realizado</option>
                    <option value="security_alert">Alerta de Segurança</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Título:</label>
                <input type="text" id="title" name="title" value="Teste de Notificação" placeholder="Digite o título">
            </div>
            
            <div class="form-group">
                <label for="message">Mensagem:</label>
                <textarea id="message" name="message" placeholder="Digite a mensagem de teste...">Esta é uma mensagem de teste do sistema de notificações. Verifique se você recebeu corretamente.</textarea>
            </div>
            
            <div class="form-group">
                <label>Canais de Envio:</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_email" name="channels[]" value="email" checked>
                        <label for="channel_email">📧 Email</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_sms" name="channels[]" value="sms">
                        <label for="channel_sms">📱 SMS</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="channel_push" name="channels[]" value="push">
                        <label for="channel_push">📱 Push Notification</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success">🧪 Enviar Teste</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/admin/notifications/dashboard" class="btn">← Cancelar</a>
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
        <a href="/admin/notifications" class="btn btn-primary">Voltar</a>
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
            'sent' => 'Enviada',
            'failed' => 'Falha',
            'processed' => 'Processada'
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Obter label da prioridade
     */
    private function getPriorityLabel($priority) {
        $labels = [
            'low' => 'Baixa',
            'normal' => 'Normal',
            'high' => 'Alta'
        ];
        
        return $labels[$priority] ?? $priority;
    }
}

?>
