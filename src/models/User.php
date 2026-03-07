<?php
/**
 * Modelo para gerenciar usuários internos do sistema
 */

class User {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Autenticar usuário
     */
    public function authenticate($email, $password) {
        $sql = "SELECT id, name, email, password_hash, profile, status 
                FROM users 
                WHERE email = ? AND status = 'active'";
        
        $stmt = $this->db->query($sql, [$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Atualizar último login
        $this->updateLastLogin($user['id']);
        
        // Criar sessão
        $this->createSession($user['id']);
        
        // Remover senha do array
        unset($user['password_hash']);
        
        return $user;
    }
    
    /**
     * Criar novo usuário
     */
    public function create($name, $email, $password, $profile = 'operator') {
        // Validar email único
        if ($this->getByEmail($email)) {
            throw new Exception("E-mail já cadastrado");
        }
        
        // Validar perfil
        if (!in_array($profile, ['admin', 'operator', 'auditor'])) {
            throw new Exception("Perfil inválido");
        }
        
        // Validar senha
        if (strlen($password) < Config::MIN_PASSWORD_LENGTH) {
            throw new Exception("Senha deve ter no mínimo " . Config::MIN_PASSWORD_LENGTH . " caracteres");
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, email, password_hash, profile) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$name, $email, $passwordHash, $profile]);
        
        $userId = $this->db->getConnection()->lastInsertId();
        
        // Registrar log de auditoria
        $this->logAudit('CREATE_USER', $_SESSION['user_id'] ?? null, $userId, null, [
            'name' => $name,
            'email' => $email,
            'profile' => $profile
        ]);
        
        return $userId;
    }
    
    /**
     * Obter usuário por ID
     */
    public function getById($id) {
        $sql = "SELECT id, name, email, profile, status, last_login_at, created_at 
                FROM users 
                WHERE id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter usuário por e-mail
     */
    public function getByEmail($email) {
        $sql = "SELECT id, name, email, profile, status 
                FROM users 
                WHERE email = ?";
        
        $stmt = $this->db->query($sql, [$email]);
        return $stmt->fetch();
    }
    
    /**
     * Listar todos os usuários
     */
    public function getAll($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, email, profile, status, last_login_at, created_at 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Atualizar usuário
     */
    public function update($id, $data) {
        $allowedFields = ['name', 'email', 'profile', 'status'];
        $updateFields = [];
        $updateValues = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("Nenhum campo válido para atualizar");
        }
        
        // Obter dados antigos para auditoria
        $oldData = $this->getById($id);
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateValues[] = $id;
        
        $this->db->query($sql, $updateValues);
        
        // Registrar log de auditoria
        $this->logAudit('UPDATE_USER', $_SESSION['user_id'] ?? null, $id, $oldData, $data);
        
        return true;
    }
    
    /**
     * Alterar senha
     */
    public function changePassword($id, $currentPassword, $newPassword) {
        $user = $this->getById($id);
        if (!$user) {
            throw new Exception("Usuário não encontrado");
        }
        
        // Verificar senha atual
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $currentHash = $stmt->fetch()['password_hash'];
        
        if (!password_verify($currentPassword, $currentHash)) {
            throw new Exception("Senha atual incorreta");
        }
        
        // Validar nova senha
        if (strlen($newPassword) < Config::MIN_PASSWORD_LENGTH) {
            throw new Exception("Nova senha deve ter no mínimo " . Config::MIN_PASSWORD_LENGTH . " caracteres");
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $this->db->query($sql, [$newHash, $id]);
        
        // Registrar log de auditoria
        $this->logAudit('CHANGE_PASSWORD', $id, $id, null, ['password_changed' => true]);
        
        return true;
    }
    
    /**
     * Bloquear/Desbloquear usuário
     */
    public function toggleStatus($id, $status) {
        if (!in_array($status, ['active', 'inactive', 'blocked'])) {
            throw new Exception("Status inválido");
        }
        
        $oldData = $this->getById($id);
        
        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $this->db->query($sql, [$status, $id]);
        
        // Remover todas as sessões do usuário se bloqueado
        if ($status === 'blocked') {
            $this->removeAllSessions($id);
        }
        
        // Registrar log de auditoria
        $this->logAudit('TOGGLE_USER_STATUS', $_SESSION['user_id'] ?? null, $id, $oldData, ['status' => $status]);
        
        return true;
    }
    
    /**
     * Verificar permissão do usuário
     */
    public function hasPermission($userId, $requiredProfile) {
        $user = $this->getById($userId);
        if (!$user || $user['status'] !== 'active') {
            return false;
        }
        
        $profiles = [
            'admin' => ['admin', 'operator', 'auditor'],
            'operator' => ['operator'],
            'auditor' => ['auditor']
        ];
        
        return in_array($user['profile'], $profiles[$requiredProfile] ?? []);
    }
    
    /**
     * Atualizar último login
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$userId]);
    }
    
    /**
     * Criar sessão do usuário
     */
    private function createSession($userId) {
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + Config::SESSION_LIFETIME);
        
        $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $userId,
            $sessionId,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            $expiresAt
        ]);
    }
    
    /**
     * Remover todas as sessões do usuário
     */
    private function removeAllSessions($userId) {
        $sql = "DELETE FROM user_sessions WHERE user_id = ?";
        $this->db->query($sql, [$userId]);
    }
    
    /**
     * Validar sessão atual
     */
    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $sessionId = session_id();
        
        $sql = "SELECT s.user_id, u.name, u.email, u.profile, u.status 
                FROM user_sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.session_id = ? AND s.expires_at > NOW() AND u.status = 'active'";
        
        $stmt = $this->db->query($sql, [$sessionId]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $this->logout();
            return false;
        }
        
        return $session;
    }
    
    /**
     * Logout do usuário
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $sessionId = session_id();
            $sql = "DELETE FROM user_sessions WHERE session_id = ?";
            $this->db->query($sql, [$sessionId]);
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Limpar sessões expiradas
     */
    public function cleanExpiredSessions() {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
        $this->db->query($sql);
        
        return $this->db->getConnection()->rowCount();
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $userId, $recordId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'users', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $userId ?: null,
            $action,
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    /**
     * Obter estatísticas dos usuários
     */
    public function getStatistics() {
        $stats = [];
        
        // Total por perfil
        $sql = "SELECT profile, COUNT(*) as count FROM users GROUP BY profile";
        $stmt = $this->db->query($sql);
        $stats['by_profile'] = $stmt->fetchAll();
        
        // Total por status
        $sql = "SELECT status, COUNT(*) as count FROM users GROUP BY status";
        $stmt = $this->db->query($sql);
        $stats['by_status'] = $stmt->fetchAll();
        
        // Logins recentes
        $sql = "SELECT COUNT(*) as recent_logins FROM users WHERE last_login_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->query($sql);
        $stats['recent_logins'] = $stmt->fetch()['recent_logins'];
        
        return $stats;
    }
}

?>
