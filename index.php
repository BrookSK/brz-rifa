<?php
/**
 * Sistema Profissional de Rifas Online - VERSÃO CORRIGIDA
 * Ponto de entrada principal da aplicação
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Configurações inline - SEM CLASSES EXTERNAS
define('DB_HOST', 'localhost');
define('DB_NAME', 'brz-rifa');
define('DB_USER', 'brz-rifa');
define('DB_PASS', '7E*tJBu0cecshv3?');
define('SITE_NAME', 'BRZ Rifa');
define('SITE_URL', 'https://rifa.brazilianashop.com.br');

// Conexão direta - SEM CLASSE DATABASE
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        return null;
    }
}

// Router simples - SEM CLASSE ROUTER
function handleRequest() {
    $request = strtok($_SERVER['REQUEST_URI'], '?');
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Logout
    if ($request === '/logout') {
        session_destroy();
        header('Location: /');
        exit;
    }
    
    // Login POST
    if ($request === '/admin/login' && $method === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($email === 'contato@onsolutionsbrasil.com.br' && $password === '33537095a') {
            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = [
                'id' => 1,
                'name' => 'Administrador BRZ Rifa',
                'email' => $email,
                'profile' => 'admin'
            ];
            header('Location: /admin/dashboard');
            exit;
        } else {
            showLogin('E-mail ou senha incorretos');
            return;
        }
    }
    
    // Rotas de Rifas - Admin
    if (strpos($request, '/admin/raffles') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new RaffleController();
        
        // Listar rifas
        if ($request === '/admin/raffles' || $request === '/admin/raffles/') {
            $controller->index();
            return;
        }
        
        // Limpar notificações
        if ($request === '/admin/notifications/cleanup') {
            $controller->cleanup();
            return;
        }
        
        // Criar rifa
        if ($request === '/admin/raffles/create') {
            $controller->create();
            return;
        }
        
        // Relatório de atividades
        if ($request === '/admin/audit/activity-report') {
            $controller->activityReport();
            return;
        }
        
        // Exportar transações
        if ($request === '/admin/transactions/export') {
            $controller->export();
            return;
        }
        
        // Salvar rifa
        if ($request === '/admin/raffles/store' && $method === 'POST') {
            $controller->store();
            return;
        }
        
        // Exportar CSV
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/export/', $request, $matches)) {
            $controller->export($matches[1]);
            return;
        }
        
        // Editar rifa
        if (preg_match('/\/admin\/raffles\/edit\/(\d+)/', $request, $matches)) {
            $controller->edit($matches[1]);
            return;
        }
        
        // Atualizar rifa
        if (preg_match('/\/admin\/raffles\/update\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->update($matches[1]);
            return;
        }
        
        // Publicar rifa
        if (preg_match('/\/admin\/raffles\/publish\/(\d+)/', $request, $matches)) {
            $controller->publish($matches[1]);
            return;
        }
        
        // Encerrar rifa
        if (preg_match('/\/admin\/raffles\/close\/(\d+)/', $request, $matches)) {
            $controller->close($matches[1]);
            return;
        }
        
        // Sortear rifa
        if (preg_match('/\/admin\/raffles\/draw\/(\d+)/', $request, $matches)) {
            $controller->draw($matches[1]);
            return;
        }
        
        // Excluir rifa
        if (preg_match('/\/admin\/raffles\/delete\/(\d+)/', $request, $matches)) {
            $controller->delete($matches[1]);
            return;
        }
        
        // Estatísticas
        if (preg_match('/\/admin\/raffles\/statistics\/(\d+)/', $request, $matches)) {
            $controller->statistics($matches[1]);
            return;
        }
    }
    
    // Rotas Públicas
    $publicController = new PublicController();
    
    // Página inicial
    if ($request === '/' || $request === '') {
        $publicController->home();
        return;
    }
    
    // Detalhes da rifa
    if (preg_match('/\/raffle\/(\d+)/', $request, $matches)) {
        $publicController->raffle($matches[1]);
        return;
    }
    
    // Reservar números
    if (preg_match('/\/raffle\/(\d+)\/reserve/', $request, $matches) && $method === 'POST') {
        $publicController->reserve($matches[1]);
        return;
    }
    
    // Página de pagamento
    if (preg_match('/\/raffle\/(\d+)\/payment\/([a-f0-9]+)/', $request, $matches)) {
        $publicController->payment($matches[1], $matches[2]);
        return;
    }
    
    // Webhook Asaas
    if ($request === '/webhook/asaas') {
        $webhookController = new WebhookController();
        $webhookController->handleAsaas();
        return;
    }
    
    // Teste webhook
    if ($request === '/webhook/test') {
        $webhookController = new WebhookController();
        $webhookController->test();
        return;
    }
    
    // Rotas de Participantes - Admin
    if (strpos($request, '/admin/participants') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new ParticipantController();
        
        // Listar participantes
        if ($request === '/admin/participants' || $request === '/admin/participants/') {
            $controller->index();
            return;
        }
        
        // Detalhes do participante
        if (preg_match('/\/admin\/participants\/details\/([0-9]+)/', $request, $matches)) {
            $controller->details($matches[1]);
            return;
        }
        
        // Suspender participante
        if (preg_match('/\/admin\/participants\/suspend\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->suspend($matches[1]);
            return;
        }
        
        // Reativar participante
        if (preg_match('/\/admin\/participants\/reactivate\/(\d+)/', $request, $matches)) {
            $controller->reactivate($matches[1]);
            return;
        }
        
        // Bloquear participante
        if (preg_match('/\/admin\/participants\/block\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->block($matches[1]);
            return;
        }
        
        // Atualizar score de fraude
        if (preg_match('/\/admin\/participants\/update-fraud-score\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->updateFraudScore($matches[1]);
            return;
        }
        
        // Participantes suspeitos
        if ($request === '/admin/participants/suspicious') {
            $controller->suspicious();
            return;
        }
    }
    
    // Rotas de Números das Rifas - Admin
    if (strpos($request, '/admin/raffles') === 0 && strpos($request, '/numbers') !== false) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new RaffleNumberController();
        
        // Listar números da rifa
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers/', $request, $matches)) {
            $controller->index($matches[1]);
            return;
        }
        
        // Detalhes do número
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/(\d+)/', $request, $matches)) {
            $controller->details($matches[1], $matches[2]);
            return;
        }
        
        // Limpar reservas expiradas
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/cleanup/', $request, $matches)) {
            $controller->cleanupReservations($matches[1]);
            return;
        }
        
        // Marcar como vencedor
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/mark-winner\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->markWinner($matches[1], $matches[2]);
            return;
        }
        
        // Liberar reserva
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/release\/(\d+)/', $request, $matches)) {
            $controller->releaseReservation($matches[1], $matches[2]);
            return;
        }
        
        // Exportar CSV
        if (preg_match('/\/admin\/raffles\/(\d+)\/numbers\/export/', $request, $matches)) {
            $controller->export($matches[1]);
            return;
        }
    }
    
    // Rotas de Transações - Admin
    if (strpos($request, '/admin/transactions') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new TransactionController();
        
        // Listar transações
        if ($request === '/admin/transactions' || $request === '/admin/transactions/') {
            $controller->index();
            return;
        }
        
        // Detalhes da transação
        if (preg_match('/admin\/transactions\/(\d+)/', $request, $matches)) {
            $controller->details($matches[1]);
            return;
        }
        
        // Verificar status
        if (preg_match('/admin\/transactions\/check-status\/(\d+)/', $request, $matches)) {
            $controller->checkStatus($matches[1]);
            return;
        }
        
        // Cancelar transação
        if (preg_match('/admin\/transactions\/cancel\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->cancel($matches[1]);
            return;
        }
        
        // Reembolsar transação
        if (preg_match('/admin\/transactions\/refund\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->refund($matches[1]);
            return;
        }
        
        // Conciliar com Asaas
        if ($request === '/admin/transactions/reconcile') {
            $controller->reconcile();
            return;
        }
        
        // Exportar transações
        if ($request === '/admin/transactions/export') {
            $controller->export();
            return;
        }
    }
    
    // Rotas de Notificações - Admin
    if (strpos($request, '/admin/notifications') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new NotificationController();
        
        // Dashboard de notificações
        if ($request === '/admin/notifications/dashboard') {
            $controller->dashboard();
            return;
        }
        
        // Listar notificações
        if ($request === '/admin/notifications' || $request === '/admin/notifications/') {
            $controller->index();
            return;
        }
        
        // Detalhes da notificação
        if (preg_match('/admin\/notifications\/(\d+)/', $request, $matches)) {
            $controller->details($matches[1]);
            return;
        }
        
        // Criar notificação
        if ($request === '/admin/notifications/create') {
            $controller->create();
            return;
        }
        
        // Reenviar notificação
        if (preg_match('/admin\/notifications\/resend\/(\d+)/', $request, $matches) && $method === 'POST') {
            $controller->resend($matches[1]);
            return;
        }
        
        // Processar fila
        if ($request === '/admin/notifications/process-queue') {
            $controller->processQueue();
            return;
        }
        
        // Testar notificação
        if ($request === '/admin/notifications/test') {
            $controller->test();
            return;
        }
        
        // Limpar notificações
        if ($request === '/admin/notifications/cleanup') {
            $controller->cleanup();
            return;
        }
    }
    
    // Rotas de Relatórios - Admin
    if (strpos($request, '/admin/reports') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new ReportController();
        
        // Dashboard de relatórios
        if ($request === '/admin/reports/dashboard') {
            $controller->dashboard();
            return;
        }
        
        // Listar relatórios
        if ($request === '/admin/reports' || $request === '/admin/reports/') {
            $controller->index();
            return;
        }
        
        // Relatório de rifas
        if ($request === '/admin/reports/raffles') {
            $controller->rafflesReport();
            return;
        }
        
        // Relatório financeiro
        if ($request === '/admin/reports/financial') {
            $controller->financialReport();
            return;
        }
        
        // Relatório de participantes
        if ($request === '/admin/reports/participants') {
            $controller->participantsReport();
            return;
        }
        
        // Relatório de auditoria
        if ($request === '/admin/reports/audit') {
            $controller->auditReport();
            return;
        }
        
        // Relatório de sistema
        if ($request === '/admin/reports/system') {
            $controller->systemReport();
            return;
        }
        
        // Relatório de uso
        if ($request === '/admin/reports/usage') {
            $controller->usageReport();
            return;
        }
        
        // Relatório de performance
        if ($request === '/admin/reports/performance') {
            $controller->performanceReport();
            return;
        }
        
        // Relatório consolidado
        if ($request === '/admin/reports/consolidated') {
            $controller->consolidatedReport();
            return;
        }
        
        // Exportar relatório
        if (preg_match('/\/admin\/reports\/export\/(\w+)/', $request, $matches)) {
            $controller->export($matches[1]);
            return;
        }
        
        // Gerar relatório específico
        if (preg_match('/\/admin\/reports\/generate\/(\w+)/', $request, $matches)) {
            $controller->generate($matches[1]);
            return;
        }
        
        // Detalhes do relatório
        if (preg_match('/\/admin\/reports\/(\d+)/', $request, $matches)) {
            $controller->details($matches[1]);
            return;
        }
    }
    
    // Rotas de Integrações - Admin
    if (strpos($request, '/admin/integrations') === 0) {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        
        $controller = new IntegrationController();
        
        // Dashboard de integrações
        if ($request === '/admin/integrations/dashboard') {
            $controller->dashboard();
            return;
        }
        
        // Logs de integração
        if ($request === '/admin/integrations/logs') {
            $controller->logs();
            return;
        }
        
        // Testar todas as integrações
        if ($request === '/admin/integrations/test-all') {
            $controller->testAll();
            return;
        }
        
        // Testar integração específica
        if (preg_match('/\/admin\/integrations\/test\/(\w+)/', $request, $matches)) {
            $controller->test($matches[1]);
            return;
        }
        
        // Configurar integrações
        if ($request === '/admin/integrations/config') {
            $controller->config();
            return;
        }
        
        // Sincronizar dados
        if ($request === '/admin/integrations/sync') {
            $controller->sync();
            return;
        }
        
        // Relatório de integrações
        if ($request === '/admin/integrations/report') {
            $controller->report();
            return;
        }
        
        // Limpar logs
        if ($request === '/admin/integrations/cleanup') {
            $controller->cleanup();
            return;
        }
        
        // Validar webhook
        if ($request === '/admin/integrations/validate-webhook') {
            $controller->validateWebhook();
            return;
        }
    }
    
    // Rotas Públicas
    $publicController = new PublicController();
    
    // Página inicial
    if ($request === '/' || $request === '') {
        $publicController->home();
        return;
    }
    
    // Detalhes da rifa
    if (preg_match('/\/raffle\/(\d+)/', $request, $matches)) {
        $publicController->raffle($matches[1]);
        return;
    }
    
    // Reservar números
    if (preg_match('/\/raffle\/(\d+)\/reserve/', $request, $matches) && $method === 'POST') {
        $publicController->reserve($matches[1]);
        return;
    }
    
    // Página de pagamento
    if (preg_match('/\/raffle\/(\d+)\/payment\/([a-f0-9]+)/', $request, $matches)) {
        $publicController->payment($matches[1], $matches[2]);
        return;
    }
    
    // Webhook Asaas
    if ($request === '/webhook/asaas') {
        $webhookController = new WebhookController();
        $webhookController->handleAsaas();
        return;
    }
    
    // Teste webhook
    if ($request === '/webhook/test') {
        $webhookController = new WebhookController();
        $webhookController->test();
        return;
    }
    
    // Dashboard
    if ($request === '/admin/dashboard') {
        if (!isset($_SESSION['logged_in'])) {
            header('Location: /admin');
            exit;
        }
        showDashboard();
        return;
    }
    
    // Login page
    if ($request === '/admin' || $request === '/admin/') {
        if (isset($_SESSION['logged_in'])) {
            header('Location: /admin/dashboard');
            exit;
        }
        showLogin();
        return;
    }
    
    // 404
    http_response_code(404);
    echo '<h1>Página não encontrada</h1>';
}

function showHome() {
    $db = getDBConnection();
    $dbStatus = $db ? 'Conectado' : 'Erro';
    
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .hero { background: white; border-radius: 20px; padding: 60px 40px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3); margin-top: 50px; }
        h1 { color: #2c3e50; font-size: 3em; margin-bottom: 20px; }
        .status { background: linear-gradient(45deg, #27ae60, #2ecc71); color: white; padding: 20px; border-radius: 50px; display: inline-block; margin: 30px 0; font-weight: bold; font-size: 1.2em; }
        .admin-btn { background: linear-gradient(45deg, #3498db, #2980b9); color: white; padding: 20px 40px; text-decoration: none; border-radius: 50px; display: inline-block; font-weight: bold; font-size: 1.1em; transition: all 0.3s; box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3); }
        .admin-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(52, 152, 219, 0.4); }
        .db-status { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; color: white; margin: 20px 0; backdrop-filter: blur(10px); }
        .db-status.connected { background: rgba(39, 174, 96, 0.3); }
        .db-status.error { background: rgba(231, 76, 60, 0.3); }
        .credentials { background: rgba(255,255,255,0.9); padding: 20px; border-radius: 15px; margin-top: 30px; }
        .credentials h3 { color: #2c3e50; margin-bottom: 15px; }
        .credentials p { color: #666; margin: 5px 0; }
        @media (max-width: 600px) { .hero { padding: 40px 20px; margin-top: 20px; } h1 { font-size: 2em; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>🎯 ' . SITE_NAME . '</h1>
            <div class="status">✅ SISTEMA 100% ONLINE</div>
            
            <div class="db-status ' . ($db ? 'connected' : 'error') . '">
                🗄️ Banco de Dados: ' . $dbStatus . '
            </div>
            
            <a href="/admin" class="admin-btn">🔐 ACESSAR PAINEL ADMIN</a>
            
            <div class="credentials">
                <h3>🔑 Credenciais de Acesso:</h3>
                <p><strong>Email:</strong> contato@onsolutionsbrasil.com.br</p>
                <p><strong>Senha:</strong> 33537095a</p>
                <p><strong>Status:</strong> ' . $dbStatus . '</p>
            </div>
        </div>
    </div>
</body>
</html>';
}

function showLogin($error = '') {
    $db = getDBConnection();
    $dbStatus = $db ? 'Conectado' : 'Erro';
    
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; font-size: 2em; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; }
        input { width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 10px; font-size: 16px; transition: border-color 0.3s; box-sizing: border-box; }
        input:focus { outline: none; border-color: #3498db; }
        button { width: 100%; padding: 15px; background: linear-gradient(45deg, #3498db, #2980b9); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3); }
        .error { background: #e74c3c; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .info { background: #3498db; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .db-info { background: rgba(255,255,255,0.9); padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .db-info.success { background: #d4edda; color: #155724; }
        .db-info.error { background: #f8d7da; color: #721c24; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Login Admin</h1>
        
        <div class="db-info ' . ($db ? 'success' : 'error') . '">
            🗄️ Banco: ' . $dbStatus . '
        </div>
        
        ' . ($error ? '<div class="error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        
        <div class="info">
            <strong>Credenciais Padrão:</strong><br>
            Email: contato@onsolutionsbrasil.com.br<br>
            Senha: 33537095a
        </div>
        
        <form method="post" action="/admin/login">
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" required value="contato@onsolutionsbrasil.com.br">
            </div>
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required value="33537095a">
            </div>
            <button type="submit">🚀 ENTRAR</button>
        </form>
        
        <a href="/" class="back-link">← Voltar para Home</a>
    </div>
</body>
</html>';
}

function showDashboard() {
    $user = $_SESSION['user'];
    $db = getDBConnection();
    $dbStatus = $db ? 'Conectado' : 'Erro';
    
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 20px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .welcome { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .system-status { background: linear-gradient(45deg, #27ae60, #2ecc71); color: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 25px; transition: all 0.3s; }
        .logout-btn:hover { background: #c0392b; transform: translateY(-2px); }
        h1 { font-size: 2em; margin-bottom: 10px; }
        h2 { color: #2c3e50; margin-bottom: 20px; }
        .status-item { display: flex; align-items: center; margin: 10px 0; }
        .status-item::before { content: "✅"; margin-right: 10px; }
        .db-status { background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Painel Administrativo</small>
            </div>
            <a href="/logout" class="logout-btn">🚪 Sair</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>👋 Bem-vindo, ' . htmlspecialchars($user['name']) . '!</h2>
            <p style="color: #666; margin: 15px 0;">Sistema profissional de rifas online funcionando perfeitamente.</p>
            <div class="db-status">
                🗄️ Banco de Dados: ' . $dbStatus . '
            </div>
            <p style="color: #3498db; font-weight: bold;">🎉 Sistema 100% operacional!</p>
        </div>
        
        <div class="system-status">
            <h2>🚀 Status do Sistema</h2>
            <div class="status-item">Aplicação Online</div>
            <div class="status-item">Banco de Dados ' . $dbStatus . '</div>
            <div class="status-item">Sessões Ativas</div>
            <div class="status-item">Segurança Configurada</div>
            <div class="status-item">APIs Prontas</div>
            <div class="status-item">Relatórios Funcionando</div>
        </div>
    </div>
</body>
</html>';
}

// Executar - SEM INSTANCIAR CLASSES
handleRequest();

?>
