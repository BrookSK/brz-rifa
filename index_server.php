<?php
/**
 * Sistema Profissional de Rifas Online - Versão Servidor
 * Ponto de entrada principal da aplicação
 */

// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Definir constantes do sistema
define('ROOT_PATH', __DIR__);
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('CACHE_PATH', ROOT_PATH . '/cache');

// Configurações básicas
define('SITE_NAME', 'BRZ Rifa');
define('SITE_URL', 'https://rifa.brazilianashop.com.br');
define('SITE_EMAIL', 'contato@onsolutionsbrasil.com.br');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'brz_rifa');
define('DB_USER', 'root');
define('DB_PASS', ''); // ALTERAR PARA SENHA REAL

// Verificar se pastas existem
$required_dirs = [SRC_PATH, CONFIG_PATH, UPLOADS_PATH, LOGS_PATH, CACHE_PATH];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Função simples de autoload
spl_autoload_register(function ($class) {
    $file = SRC_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Classe Database simples
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// Router simples
class Router {
    public function dispatch() {
        $request = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Remover query string
        $request = strtok($request, '?');
        
        // Rotas básicas
        if ($request === '/' || $request === '') {
            $this->showHomePage();
        } elseif ($request === '/admin' || $request === '/admin/') {
            $this->showLoginPage();
        } elseif ($request === '/admin/login' && $method === 'POST') {
            $this->handleLogin();
        } else {
            // Verificar se é arquivo de assets
            if (strpos($request, '/assets/') === 0) {
                $this->serveAsset($request);
            } else {
                http_response_code(404);
                echo "<h1>Página não encontrada</h1>";
            }
        }
    }
    
    private function showHomePage() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; }
        .status { background: #27ae60; color: white; padding: 10px; border-radius: 5px; text-align: center; margin: 20px 0; }
        .admin-link { display: block; text-align: center; background: #3498db; color: white; padding: 15px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .admin-link:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 ' . SITE_NAME . '</h1>
        <div class="status">✅ Sistema Online</div>
        <p>Sistema profissional de rifas online funcionando perfeitamente!</p>
        <a href="/admin" class="admin-link">🔐 Acessar Painel Administrativo</a>
        <p><small>Login: contato@onsolutionsbrasil.com.br | Senha: 33537095a</small></p>
    </div>
</body>
</html>';
    }
    
    private function showLoginPage() {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #2980b9; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Login</h1>
        <form method="post" action="/admin/login">
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
        <a href="/" class="back-link">← Voltar para Home</a>
    </div>
</body>
</html>';
    }
    
    private function handleLogin() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Verificação simples
        if ($email === 'contato@onsolutionsbrasil.com.br' && $password === '33537095a') {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_email'] = $email;
            header('Location: /admin/dashboard');
            exit;
        } else {
            echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error { background: #e74c3c; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error">❌ E-mail ou senha incorretos!</div>
        <a href="/admin">← Tentar novamente</a>
    </div>
</body>
</html>';
        }
    }
    
    private function serveAsset($path) {
        $file = ROOT_PATH . $path;
        if (file_exists($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'ico' => 'image/x-icon'
            ];
            
            if (isset($mimeTypes[$ext])) {
                header('Content-Type: ' . $mimeTypes[$ext]);
                readfile($file);
                exit;
            }
        }
        
        http_response_code(404);
        echo 'Asset not found';
    }
}

// Inicializar o sistema
try {
    $router = new Router();
    $router->dispatch();
} catch (Exception $e) {
    echo '<h1>Erro do Sistema</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="/">← Voltar</a>';
}

?>
