<?php
/**
 * Controller para gerenciamento de backup e recuperação
 */

class BackupController {
    private $db;
    private $backupService;
    
    public function __construct() {
        $this->db = new Database();
        $this->backupService = new BackupService($this->db);
    }
    
    /**
     * Exibir página de backup
     */
    public function index() {
        // Verificar permissão (apenas Admin)
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        if ($_SESSION['user_profile'] !== 'admin') {
            header('Location: /admin');
            exit;
        }
        
        include SRC_PATH . '/views/admin/backup/index.php';
    }
    
    /**
     * API: Criar backup
     */
    public function createBackup() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'] ?? 'full';
            
            if ($type === 'critical') {
                $result = $this->backupService->createCriticalBackup();
            } else {
                $result = $this->backupService->createFullBackup();
            }
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Listar backups
     */
    public function listBackups() {
        header('Content-Type: application/json');
        
        try {
            $backups = $this->backupService->listBackups();
            echo json_encode(['success' => true, 'backups' => $backups]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter estatísticas dos backups
     */
    public function getBackupStats() {
        header('Content-Type: application/json');
        
        try {
            $stats = $this->backupService->getBackupStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Restaurar backup
     */
    public function restoreBackup($backupFile) {
        header('Content-Type: application/json');
        
        try {
            $result = $this->backupService->restoreBackup($backupFile);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar backup
     */
    public function verifyBackup($backupFile) {
        header('Content-Type: application/json');
        
        try {
            $verification = $this->backupService->verifyBackup($backupFile);
            echo json_encode(['success' => true, 'verification' => $verification]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Excluir backup
     */
    public function deleteBackup($backupFile) {
        header('Content-Type: application/json');
        
        try {
            $result = $this->backupService->deleteBackup($backupFile);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Download de backup
     */
    public function downloadBackup($backupFile) {
        try {
            $backupDir = ROOT_PATH . '/backups';
            $filePath = $backupDir . '/' . $backupFile;
            
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo não encontrado");
            }
            
            // Configurar headers para download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backupFile . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Enviar arquivo
            readfile($filePath);
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter informações de backup
     */
    public function getBackupInfo($backupFile) {
        header('Content-Type: application/json');
        
        try {
            $backupDir = ROOT_PATH . '/backups';
            $filePath = $backupDir . '/' . $backupFile;
            
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo não encontrado");
            }
            
            $stat = stat($filePath);
            
            $info = [
                'file' => $backupFile,
                'size' => $stat['size'],
                'created' => date('Y-m-d H:i:s', $stat['mtime']),
                'type' => strpos($backupFile, 'critical') !== false ? 'critical' : 'full'
            ];
            
            echo json_encode(['success' => true, 'info' => $info]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Testar backup
     */
    public function testBackup() {
        header('Content-Type: application/json');
        
        try {
            // Criar backup de teste
            $result = $this->backupService->createCriticalBackup();
            
            if ($result['success']) {
                // Verificar se foi criado corretamente
                $verification = $this->backupService->verifyBackup($result['file']);
                
                if ($verification['valid']) {
                    // Excluir backup de teste
                    $this->backupService->deleteBackup($result['file']);
                    
                    echo json_encode(['success' => true, 'message' => 'Backup de teste executado com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Backup criado mas com erros de verificação']);
                }
            } else {
                echo json_encode($result);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verificar todos os backups
     */
    public function verifyAllBackups() {
        header('Content-Type: application/json');
        
        try {
            $backups = $this->backupService->listBackups();
            $verified = 0;
            $errors = [];
            
            foreach ($backups as $backup) {
                $verification = $this->backupService->verifyBackup($backup['file']);
                
                if ($verification['valid']) {
                    $verified++;
                } else {
                    $errors[] = [
                        'file' => $backup['file'],
                        'error' => $verification['error'] ?? 'Backup inválido'
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'verified' => $verified,
                'total' => count($backups),
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Limpar backups antigos
     */
    public function cleanupOldBackups() {
        header('Content-Type: application/json');
        
        try {
            // Obter backups antes da limpeza
            $backupsBefore = $this->backupService->listBackups();
            
            // Forçar limpeza (o método já é chamado internamente, mas vamos garantir)
            $backupDir = ROOT_PATH . '/backups';
            $maxBackups = 10;
            
            if (count($backupsBefore) > $maxBackups) {
                $toDelete = array_slice($backupsBefore, $maxBackups);
                $deleted = 0;
                
                foreach ($toDelete as $backup) {
                    $filePath = $backupDir . '/' . $backup['file'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                        $deleted++;
                    }
                }
                
                echo json_encode(['success' => true, 'deleted' => $deleted]);
            } else {
                echo json_encode(['success' => true, 'deleted' => 0, 'message' => 'Nenhum backup antigo para excluir']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter configuração de backup
     */
    public function getBackupConfig() {
        header('Content-Type: application/json');
        
        try {
            $sql = "SELECT policy_key, policy_value FROM system_policies WHERE policy_key IN ('backup_enabled', 'backup_type', 'backup_frequency')";
            $stmt = $this->db->query($sql);
            $policies = $stmt->fetchAll();
            
            $config = [];
            foreach ($policies as $policy) {
                $config[$policy['policy_key']] = $policy['policy_value'];
            }
            
            echo json_encode(['success' => true, 'config' => $config]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Salvar configuração de backup
     */
    public function saveBackupConfig() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            foreach ($data as $key => $value) {
                $sql = "INSERT INTO system_policies (policy_key, policy_value, policy_type, description) 
                        VALUES (?, ?, 'string', 'Configuração de backup automático') 
                        ON DUPLICATE KEY UPDATE policy_value = VALUES(policy_value)";
                
                $this->db->query($sql, [$key, $value]);
            }
            
            // Registrar log
            $this->logAudit('UPDATE_BACKUP_CONFIG', 0, null, $data);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Executar backup agendado
     */
    public function runScheduledBackup() {
        header('Content-Type: application/json');
        
        try {
            $result = $this->backupService->runScheduledBackup();
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Agendar backup
     */
    public function scheduleBackup() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'] ?? 'full';
            $frequency = $data['frequency'] ?? 'daily';
            
            $result = $this->backupService->scheduleBackup($type, $frequency);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Obter status do sistema de backup
     */
    public function getBackupSystemStatus() {
        header('Content-Type: application/json');
        
        try {
            $stats = $this->backupService->getBackupStats();
            $config = $this->getBackupConfigData();
            
            $status = [
                'enabled' => $config['backup_enabled'] === 'true',
                'last_backup' => $stats['newest_backup'],
                'total_backups' => $stats['total_backups'],
                'total_size' => $stats['total_size'],
                'disk_usage' => $this->getDiskUsage(),
                'next_scheduled' => $this->getNextScheduledTime($config['backup_frequency'])
            ];
            
            echo json_encode(['success' => true, 'status' => $status]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter dados de configuração
     */
    private function getBackupConfigData() {
        $sql = "SELECT policy_key, policy_value FROM system_policies WHERE policy_key IN ('backup_enabled', 'backup_type', 'backup_frequency')";
        $stmt = $this->db->query($sql);
        $policies = $stmt->fetchAll();
        
        $config = [
            'backup_enabled' => 'false',
            'backup_type' => 'full',
            'backup_frequency' => 'daily'
        ];
        
        foreach ($policies as $policy) {
            $config[$policy['policy_key']] = $policy['policy_value'];
        }
        
        return $config;
    }
    
    /**
     * Obter uso de disco
     */
    private function getDiskUsage() {
        $backupDir = ROOT_PATH . '/backups';
        
        if (!is_dir($backupDir)) {
            return [
                'total' => 0,
                'used' => 0,
                'free' => 0,
                'percentage' => 0
            ];
        }
        
        $total = disk_total_space($backupDir);
        $free = disk_free_space($backupDir);
        $used = $total - $free;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * Obter próximo agendamento
     */
    private function getNextScheduledTime($frequency) {
        $now = new DateTime();
        
        switch ($frequency) {
            case 'daily':
                $next = (clone $now)->add(new DateInterval('P1D'));
                $next->setTime(2, 0, 0); // 2 da manhã
                break;
            case 'weekly':
                $next = (clone $now)->add(new DateInterval('P7D'));
                $next->setTime(2, 0, 0); // 2 da manhã
                break;
            case 'monthly':
                $next = (clone $now)->add(new DateInterval('P1M'));
                $next->setTime(2, 0, 0); // 2 da manhã
                break;
            default:
                return null;
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $recordId, $oldData, $newData) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'backups', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
}

?>
