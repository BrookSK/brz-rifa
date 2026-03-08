<?php
/**
 * Modelo para gerenciar logs de auditoria imutáveis
 */

class AuditLog {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Registrar log de auditoria
     */
    public function log($action, $tableName, $recordId, $oldData = null, $newData = null, $userId = null, $context = []) {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO audit_logs (
                user_id, action, table_name, record_id, old_data, new_data, 
                ip_address, user_agent, context, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $this->db->query($sql, [
                $userId ?? ($_SESSION['user_id'] ?? null),
                $action,
                $tableName,
                $recordId,
                $oldData ? json_encode($oldData) : null,
                $newData ? json_encode($newData) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'CLI',
                $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                $context ? json_encode($context) : null
            ]);
            
            $this->db->commit();
            
            return $this->db->getConnection()->lastInsertId();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Registrar log com hash para integridade
     */
    public function logWithHash($action, $tableName, $recordId, $oldData = null, $newData = null, $userId = null, $context = []) {
        // Gerar hash dos dados para integridade
        $dataHash = $this->generateDataHash($oldData, $newData);
        
        $contextWithHash = array_merge($context, [
            'data_hash' => $dataHash,
            'timestamp' => time(),
            'server_seed' => bin2hex(random_bytes(16))
        ]);
        
        return $this->log($action, $tableName, $recordId, $oldData, $newData, $userId, $contextWithHash);
    }
    
    /**
     * Obter logs por tabela
     */
    public function getByTable($tableName, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM audit_logs 
                WHERE table_name = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$tableName, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs por usuário
     */
    public function getByUser($userId, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM audit_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs por ação
     */
    public function getByAction($action, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM audit_logs 
                WHERE action = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$action, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs por período
     */
    public function getByPeriod($dateFrom, $dateTo, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM audit_logs 
                WHERE created_at BETWEEN ? AND ?
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$dateFrom, $dateTo, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs por registro
     */
    public function getByRecord($tableName, $recordId, $limit = 50, $offset = 0) {
        $sql = "SELECT * FROM audit_logs 
                WHERE table_name = ? AND record_id = ?
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->audit_db->query($sql, [$tableName, $recordId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Pesquisar logs
     */
    public function search($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['action'])) {
            $sql .= " AND action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND table_name LIKE ?";
            $params[] = "%{$filters['table_name']}%";
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['record_id'])) {
            $sql .= " AND record_id = ?";
            $params[] = $filters['record_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de auditoria
     */
    public function getStatistics($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT table_name) as unique_tables,
                    COUNT(DISTINCT action) as unique_actions,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    MIN(created_at) as first_log,
                    MAX(created_at) as last_log
                FROM audit_logs";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " WHERE created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= ($dateFrom ? " AND" : " WHERE") . " created_at <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Obter atividades recentes
     */
    public function getRecentActivity($limit = 50, $hours = 24) {
        $sql = "SELECT al.*, 
                       u.name as user_name,
                       DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') as formatted_date
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY al.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$hours, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter timeline de um registro
     */
    public function getRecordTimeline($tableName, $recordId) {
        $sql = "SELECT al.*, 
                       u.name as user_name,
                       DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') as formatted_date
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = ? AND al.record_id = ?
                ORDER BY al.created_at ASC";
        
        $stmt = $this->db->query($sql, [$tableName, $recordId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar integridade dos logs
     */
    public function verifyIntegrity($logId) {
        $sql = "SELECT * FROM audit_logs WHERE id = ?";
        $stmt = $this->db->query($sql, [$logId]);
        $log = $stmt->fetch();
        
        if (!$log) {
            throw new Exception("Log não encontrado");
        }
        
        // Verificar hash se existir
        if ($log['context']) {
            $context = json_decode($log['context'], true);
            if (isset($context['data_hash'])) {
                $storedHash = $context['data_hash'];
                $currentHash = $this->generateDataHash(
                    json_decode($log['old_data'], true),
                    json_decode($log['new_data'], true)
                );
                
                if ($storedHash !== $currentHash) {
                    throw new Exception("Hash de integridade não confere - possível adulteração");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Gerar hash dos dados para integridade
     */
    private function generateDataHash($oldData, $newData) {
        $data = [
            'old_data' => $oldData,
            'new_data' => $newData,
            'timestamp' => time()
        ];
        
        return hash('sha256', json_encode($data));
    }
    
    /**
     * Exportar logs para CSV
     */
    public function exportToCSV($filters = []) {
        $sql = "SELECT al.id, al.user_id, al.action, al.table_name, al.record_id,
                       al.old_data, al.new_data, al.ip_address, al.user_agent, al.context,
                       al.created_at,
                       u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND al.table_name LIKE ?";
            $params[] = "%{$filters['table_name']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY al.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup($days = 365) {
        $sql = "DELETE FROM audit_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Criar snapshot para auditoria
     */
    public function createSnapshot($description = null) {
        $this->db->beginTransaction();
        
        try {
            $snapshotData = [
                'description' => $description ?: 'Snapshot ' . date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'tables' => []
            ];
            
            // Obter contagem de registros por tabela
            $tables = [
                'raffles', 'participants', 'transactions', 'raffle_numbers', 
                'users', 'system_policies', 'email_templates'
            ];
            
            foreach ($tables as $table) {
                $sql = "SELECT COUNT(*) as count, MAX(updated_at) as last_updated FROM $table";
                $stmt = $this->db->query($sql);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $snapshotData['tables'][] = [
                        'table' => $table,
                        'count' => $result['count'],
                        'last_updated' => $result['last_updated']
                    ];
                }
            }
            
            // Registrar snapshot
            $this->log('CREATE_SNAPSHOT', 'audit_snapshots', null, null, $snapshotData, null, [
                'snapshot_type' => 'full_system',
                'tables_count' => count($snapshotData['tables'])
            ]);
            
            $this->db->commit();
            
            return $this->db->getConnection()->lastInsertId();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Restaurar a partir de snapshot
     */
    public function restoreFromSnapshot($snapshotId) {
        $sql = "SELECT * FROM audit_logs WHERE id = ? AND action = 'CREATE_SNAPSHOT'";
        $stmt = $this->db->query($sql, [$snapshotId]);
        $snapshot = $stmt->fetch();
        
        if (!$snapshot) {
            throw new Exception("Snapshot não encontrado");
        }
        
        $snapshotData = json_decode($snapshot['new_data'], true);
        
        // Aqui você pode implementar lógica para restaurar o estado
        // Por enquanto, apenas retornar os dados do snapshot
        
        return $snapshotData;
    }
    
    /**
     * Obter logs de segurança
     */
    public function getSecurityLogs($limit = 100, $offset = 0) {
        $securityActions = [
            'LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT', 'PASSWORD_CHANGE', 
            'PERMISSION_DENIED', 'SUSPENSION', 'BLOCK_USER', 'UNBLOCK_USER'
        ];
        
        $placeholders = str_repeat('?,', count($securityActions) - 1) . '?';
        $params = array_merge($securityActions, [$limit, $offset]);
        
        $sql = "SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.action IN ($placeholders)
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs de acesso
     */
    public function getAccessLogs($limit = 100, $offset = 0) {
        $sql = "SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.action IN ('LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT')
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs de alterações críticas
     */
    public function getCriticalChanges($limit = 100, $offset = 0) {
        $criticalTables = ['raffles', 'transactions', 'participants', 'system_policies'];
        
        $placeholders = str_repeat('?,', count($criticalTables) - 1) . '?';
        $params = array_merge($criticalTables, ['DELETE', 'UPDATE', 'CREATE'], [$limit, $offset]);
        
        $sql = "SELECT al.*, u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name IN ($placeholders)
                AND al.action IN ('DELETE', 'UPDATE', 'CREATE')
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se houve alterações não registradas
     */
    public function detectUnloggedChanges() {
        $issues = [];
        
        // Comparar timestamps das tabelas com o último log
        $tables = ['raffles', 'participants', 'transactions', 'raffle_numbers'];
        
        foreach ($tables as $table) {
            $sql = "SELECT MAX(updated_at) as last_update FROM $table";
            $stmt = $this->db->query($sql);
            $lastUpdate = $stmt->fetch()['last_update'];
            
            if ($lastUpdate) {
                // Verificar se há logs após o último update
                $sql = "SELECT COUNT(*) as count FROM audit_logs 
                        WHERE table_name = ? AND created_at > ?";
                $stmt = $this->db->query($sql, [$table, $lastUpdate]);
                $logCount = $stmt->fetch()['count'];
                
                if ($logCount === 0) {
                    $issues[] = [
                        'table' => $table,
                        'last_update' => $lastUpdate,
                        'issue' => 'Nenhum log registrado após última atualização'
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Gerar relatório de atividades
     */
    public function generateActivityReport($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    DATE(al.created_at) as date,
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(DISTINCT table_name) as affected_tables,
                    GROUP_CONCAT(DISTINCT action) as actions_list,
                    MIN(al.created_at) as first_activity,
                    MAX(al.created_at) as last_activity
                FROM audit_logs al";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " WHERE al.created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= ($dateFrom ? " AND" : " WHERE") . " al.created_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY DATE(al.created_at) ORDER BY DATE(al.created_at)";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
}

?>
