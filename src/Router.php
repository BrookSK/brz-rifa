<?php
/**
 * Router simples para direcionar requisições
 */

class Router {
    private $routes = [];
    
    public function __construct() {
        $this->defineRoutes();
    }
    
    /**
     * Definir rotas da aplicação
     */
    private function defineRoutes() {
        // Rotas públicas
        $this->routes['GET']['/'] = 'PublicController@showHome';
        $this->routes['GET']['/raffa/{id}'] = 'PublicController@showRaffle';
        $this->routes['POST']['/raffa/{id}/reserve'] = 'PublicController@reserveNumbers';
        $this->routes['POST']['/raffa/{id}/payment'] = 'PublicController@createPayment';
        $this->routes['GET']['/webhook/asaas'] = 'WebhookController@handleAsaas';
        
        // Rotas de autenticação
        $this->routes['GET']['/login'] = 'AuthController@showLogin';
        $this->routes['POST']['/login'] = 'AuthController@login';
        $this->routes['GET']['/logout'] = 'AuthController@logout';
        
        // Rotas administrativas
        $this->routes['GET']['/admin'] = 'AuthController@showDashboard';
        $this->routes['GET']['/admin/setup'] = 'AuthController@showSetup';
        $this->routes['POST']['/admin/setup'] = 'AuthController@saveSetup';
        $this->routes['GET']['/admin/users'] = 'AuthController@showUsers';
        $this->routes['POST']['/admin/users'] = 'AuthController@createUser';
        $this->routes['GET']['/admin/profile'] = 'AuthController@showProfile';
        $this->routes['POST']['/admin/profile'] = 'AuthController@updateProfile';
        $this->routes['POST']['/admin/change-password'] = 'AuthController@changePassword';
        
        // Rotas de rifas (admin)
        $this->routes['GET']['/admin/raffles'] = 'RaffleController@index';
        $this->routes['GET']['/admin/raffles/create'] = 'RaffleController@create';
        $this->routes['POST']['/admin/raffles'] = 'RaffleController@store';
        $this->routes['GET']['/admin/raffles/{id}'] = 'RaffleController@show';
        $this->routes['GET']['/admin/raffles/{id}/edit'] = 'RaffleController@edit';
        $this->routes['POST']['/admin/raffles/{id}'] = 'RaffleController@update';
        $this->routes['POST']['/admin/raffles/{id}/publish'] = 'RaffleController@publish';
        $this->routes['POST']['/admin/raffles/{id}/close'] = 'RaffleController@close';
        $this->routes['POST']['/admin/raffles/{id}/draw'] = 'RaffleController@draw';
        $this->routes['GET']['/admin/raffles/{id}/numbers'] = 'RaffleController@showNumbers';
        $this->routes['GET']['/admin/raffles/{id}/stats'] = 'RaffleController@getStats';
        
        // Rotas de relatórios
        $this->routes['GET']['/admin/reports'] = 'ReportController@index';
        $this->routes['GET']['/admin/reports/raffle/{id}'] = 'ReportController@raffleReport';
        $this->routes['GET']['/admin/reports/financial'] = 'ReportController@financialReport';
        $this->routes['GET']['/admin/reports/audit'] = 'ReportController@auditReport';
        
        // Rotas de configuração
        $this->routes['GET']['/admin/policies'] = 'ConfigController@policies';
        $this->routes['POST']['/admin/policies'] = 'ConfigController@savePolicies';
        $this->routes['GET']['/admin/integrations'] = 'ConfigController@integrations';
        $this->routes['POST']['/admin/integrations'] = 'ConfigController@saveIntegrations';
        $this->routes['GET']['/admin/alerts'] = 'ConfigController@alerts';
    }
    
    /**
     * Disparar rota correspondente
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getRequestUri();
        
        // Tentar encontrar rota exata
        if (isset($this->routes[$method][$uri])) {
            $this->executeRoute($this->routes[$method][$uri]);
            return;
        }
        
        // Tentar encontrar rota com parâmetros
        foreach ($this->routes[$method] as $route => $handler) {
            if ($this->matchRoute($route, $uri, $params)) {
                $this->executeRoute($handler, $params);
                return;
            }
        }
        
        // Rota não encontrada
        $this->handle404();
    }
    
    /**
     * Obter URI da requisição
     */
    private function getRequestUri() {
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remover barra final
        $uri = rtrim($uri, '/');
        
        // Adicionar barra para raiz
        if (empty($uri)) {
            $uri = '/';
        }
        
        return $uri;
    }
    
    /**
     * Verificar se rota corresponde à URI
     */
    private function matchRoute($route, $uri, &$params) {
        // Converter parâmetros da rota para regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $route);
        $pattern = "#^$pattern$#";
        
        if (preg_match($pattern, $uri, $matches)) {
            // Extrair nomes dos parâmetros
            preg_match_all('/\{([^}]+)\}/', $route, $paramNames);
            $paramNames = $paramNames[1];
            
            // Associar valores aos parâmetros
            $params = [];
            for ($i = 0; $i < count($paramNames); $i++) {
                $params[$paramNames[$i]] = $matches[$i + 1] ?? null;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Executar handler da rota
     */
    private function executeRoute($handler, $params = []) {
        list($controllerName, $methodName) = explode('@', $handler);
        
        $controllerFile = SRC_PATH . "/controllers/{$controllerName}.php";
        
        if (!file_exists($controllerFile)) {
            $this->handle500("Controller não encontrado: $controllerName");
            return;
        }
        
        require_once $controllerFile;
        
        $database = new Database();
        $controller = new $controllerName($database);
        
        if (!method_exists($controller, $methodName)) {
            $this->handle500("Método não encontrado: $methodName");
            return;
        }
        
        try {
            // Definir parâmetros no $_GET para compatibilidade
            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }
            
            $controller->$methodName();
        } catch (Exception $e) {
            $this->handle500($e->getMessage());
        }
    }
    
    /**
     * Tratar erro 404
     */
    private function handle404() {
        http_response_code(404);
        
        if (file_exists(SRC_PATH . '/views/errors/404.php')) {
            include SRC_PATH . '/views/errors/404.php';
        } else {
            echo "<h1>404 - Página não encontrada</h1>";
        }
        exit;
    }
    
    /**
     * Tratar erro 500
     */
    private function handle500($message) {
        http_response_code(500);
        
        if (file_exists(SRC_PATH . '/views/errors/500.php')) {
            $error = $message;
            include SRC_PATH . '/views/errors/500.php';
        } else {
            echo "<h1>500 - Erro interno do servidor</h1>";
            echo "<p>$message</p>";
        }
        exit;
    }
}

?>
