<?php
/**
 * Controller para autenticação e gerenciamento de usuários
 */

class AuthController {
    private $user;
    private $systemPolicy;
    
    public function __construct($database) {
        $this->user = new User($database);
        $this->systemPolicy = new SystemPolicy($database);
    }
    
    /**
     * Exibir página de login
     */
    public function showLogin() {
        // Se já estiver logado, redirecionar para o painel
        if ($this->user->validateSession()) {
            $this->redirect('/admin');
        }
        
        include SRC_PATH . '/views/auth/login.php';
    }
    
    /**
     * Processar login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->jsonResponse(['error' => 'E-mail e senha são obrigatórios'], 400);
        }
        
        try {
            $user = $this->user->authenticate($email, $password);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_profile'] = $user['profile'];
                
                $this->jsonResponse([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'profile' => $user['profile']
                    ]
                ]);
            } else {
                $this->jsonResponse(['error' => 'E-mail ou senha incorretos'], 401);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['error' => 'Erro ao autenticar: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        $this->user->logout();
        $this->redirect('/login');
    }
    
    /**
     * Exibir painel administrativo
     */
    public function showDashboard() {
        $this->requireAuth();
        
        // Verificar se o sistema está configurado
        if (!$this->systemPolicy->isSystemReady()) {
            $this->redirect('/admin/setup');
        }
        
        // Obter estatísticas
        $stats = $this->getDashboardStats();
        
        include SRC_PATH . '/views/admin/dashboard.php';
    }
    
    /**
     * Exibir página de configuração inicial
     */
    public function showSetup() {
        $this->requireAuth('admin');
        
        $policies = $this->systemPolicy->getAll();
        $integrations = $this->getIntegrations();
        
        include SRC_PATH . '/views/admin/setup.php';
    }
    
    /**
     * Salvar configurações do sistema
     */
    public function saveSetup() {
        $this->requireAuth('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        try {
            // Salvar políticas
            $policies = $_POST['policies'] ?? [];
            foreach ($policies as $key => $value) {
                $type = 'string';
                if (is_numeric($value)) {
                    $type = is_int($value + 0) ? 'integer' : 'string';
                } elseif (is_bool($value) || $value === 'true' || $value === 'false') {
                    $type = 'boolean';
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $this->systemPolicy->set($key, $value, $type);
            }
            
            // Salvar integração Asaas
            if (isset($_POST['asaas_api_key'])) {
                $this->saveAsaasIntegration($_POST['asaas_api_key']);
            }
            
            $this->jsonResponse(['success' => true, 'message' => 'Configurações salvas com sucesso']);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibir lista de usuários
     */
    public function showUsers() {
        $this->requireAuth('admin');
        
        $page = $_GET['page'] ?? 1;
        $users = $this->user->getAll($page);
        
        include SRC_PATH . '/views/admin/users.php';
    }
    
    /**
     * Criar novo usuário
     */
    public function createUser() {
        $this->requireAuth('admin');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $profile = $_POST['profile'] ?? 'operator';
        
        try {
            $userId = $this->user->create($name, $email, $password, $profile);
            $this->jsonResponse(['success' => true, 'user_id' => $userId]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibir perfil do usuário
     */
    public function showProfile() {
        $this->requireAuth();
        
        $userId = $_SESSION['user_id'];
        $user = $this->user->getById($userId);
        
        include SRC_PATH . '/views/admin/profile.php';
    }
    
    /**
     * Atualizar perfil do usuário
     */
    public function updateProfile() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        $userId = $_SESSION['user_id'];
        $data = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        
        try {
            $this->user->update($userId, $data);
            
            // Atualizar sessão
            $_SESSION['user_name'] = $data['name'];
            $_SESSION['user_email'] = $data['email'];
            
            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Alterar senha
     */
    public function changePassword() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Método não permitido'], 405);
        }
        
        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        try {
            $this->user->changePassword($userId, $currentPassword, $newPassword);
            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Verificar autenticação
     */
    private function requireAuth($requiredProfile = null) {
        $session = $this->user->validateSession();
        
        if (!$session) {
            $this->redirect('/login');
        }
        
        if ($requiredProfile && !$this->user->hasPermission($session['user_id'], $requiredProfile)) {
            http_response_code(403);
            include SRC_PATH . '/views/errors/403.php';
            exit;
        }
        
        return $session;
    }
    
    /**
     * Obter estatísticas do dashboard
     */
    private function getDashboardStats() {
        $stats = [];
        
        // Estatísticas de usuários
        $stats['users'] = $this->user->getStatistics();
        
        // Estatísticas de políticas
        $stats['policies'] = $this->systemPolicy->getStatistics();
        
        // Estatísticas de rifas
        $stats['raffles'] = $this->getRaffleStats();
        
        return $stats;
    }
    
    /**
     * Obter estatísticas de rifas
     */
    private function getRaffleStats() {
        $db = $this->user->db;
        
        $stats = [];
        
        // Rifas por status
        $sql = "SELECT status, COUNT(*) as count FROM raffles GROUP BY status";
        $stmt = $db->query($sql);
        $stats['by_status'] = $stmt->fetchAll();
        
        // Rifas ativas
        $sql = "SELECT COUNT(*) as active FROM raffles WHERE status = 'active'";
        $stmt = $db->query($sql);
        $stats['active'] = $stmt->fetch()['active'];
        
        return $stats;
    }
    
    /**
     * Obter integrações
     */
    private function getIntegrations() {
        $sql = "SELECT * FROM integrations ORDER BY integration_name";
        $stmt = $this->user->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Salvar integração Asaas
     */
    private function saveAsaasIntegration($apiKey) {
        $sql = "INSERT INTO integrations (integration_name, is_active, api_key, config_data) 
                VALUES ('asaas', true, ?, '{}') 
                ON DUPLICATE KEY UPDATE 
                is_active = VALUES(is_active), 
                api_key = VALUES(api_key),
                updated_at = CURRENT_TIMESTAMP";
        
        $this->user->db->query($sql, [$apiKey]);
    }
    
    /**
     * Redirecionar para URL
     */
    private function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * Resposta JSON
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

?>
