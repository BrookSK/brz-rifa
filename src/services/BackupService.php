<?php
/**
 * Serviço para backup e recuperação do sistema
 */

class BackupService {
    private $db;
    private $backupDir;
    private $maxBackups;
    
    public function __construct($database) {
        $this->db = $database;
        $this->backupDir = ROOT_PATH . '/backups';
        $this->maxBackups = 10; // Manter últimos 10 backups
        
        // Criar diretório de backups se não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Criar backup completo do sistema
     */
    public function createFullBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupDir . "/backup_full_{$timestamp}.sql";
        
        try {
            // Obter todas as tabelas
            $tables = $this->getTables();
            
            $backupContent = "-- Backup BRZ Rifa - " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Gerado automaticamente pelo sistema\n\n";
            
            // Adicionar configurações do sistema
            $backupContent .= $this->backupSystemConfig();
            
            // Backup de cada tabela
            foreach ($tables as $table) {
                $backupContent .= $this->backupTable($table);
            }
            
            // Salvar arquivo
            file_put_contents($backupFile, $backupContent);
            
            // Compactar arquivo
            $compressedFile = $this->compressBackup($backupFile);
            
            // Remover arquivo original
            unlink($backupFile);
            
            // Limpar backups antigos
            $this->cleanupOldBackups();
            
            // Registrar log
            $this->logBackup('full', $compressedFile);
            
            return [
                'success' => true,
                'file' => basename($compressedFile),
                'size' => filesize($compressedFile),
                'tables' => count($tables)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar backup apenas de dados críticos
     */
    public function createCriticalBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->backupDir . "/backup_critical_{$timestamp}.sql";
        
        try {
            $criticalTables = [
                'raffles',
                'raffle_numbers',
                'participants',
                'transactions',
                'audit_logs',
                'system_policies',
                'integrations',
                'system_alerts'
            ];
            
            $backupContent = "-- Backup Crítico BRZ Rifa - " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Apenas dados essenciais do sistema\n\n";
            
            // Adicionar configurações
            $backupContent .= $this->backupSystemConfig();
            
            // Backup de tabelas críticas
            foreach ($criticalTables as $table) {
                if ($this->tableExists($table)) {
                    $backupContent .= $this->backupTable($table);
                }
            }
            
            // Salvar arquivo
            file_put_contents($backupFile, $backupContent);
            
            // Compactar
            $compressedFile = $this->compressBackup($backupFile);
            unlink($backupFile);
            
            // Limpar backups antigos
            $this->cleanupOldBackups();
            
            // Registrar log
            $this->logBackup('critical', $compressedFile);
            
            return [
                'success' => true,
                'file' => basename($compressedFile),
                'size' => filesize($compressedFile),
                'tables' => count($criticalTables)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restaurar backup
     */
    public function restoreBackup($backupFile) {
        try {
            $filePath = $this->backupDir . '/' . $backupFile;
            
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo de backup não encontrado");
            }
            
            // Descompactar se necessário
            if (strpos($backupFile, '.gz') !== false) {
                $filePath = $this->decompressBackup($filePath);
            }
            
            // Ler conteúdo do backup
            $backupContent = file_get_contents($filePath);
            
            // Remover comentários e dividir em comandos
            $commands = $this->parseBackupCommands($backupContent);
            
            // Iniciar transação
            $this->db->beginTransaction();
            
            try {
                foreach ($commands as $command) {
                    if (!empty(trim($command))) {
                        $this->db->query($command);
                    }
                }
                
                $this->db->commit();
                
                // Registrar log
                $this->logRestore($backupFile);
                
                return [
                    'success' => true,
                    'commands' => count($commands)
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Listar backups disponíveis
     */
    public function listBackups() {
        $backups = [];
        
        if (is_dir($this->backupDir)) {
            $files = scandir($this->backupDir);
            
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && strpos($file, 'backup_') === 0) {
                    $filePath = $this->backupDir . '/' . $file;
                    $stat = stat($filePath);
                    
                    $backups[] = [
                        'file' => $file,
                        'size' => $stat['size'],
                        'created' => date('Y-m-d H:i:s', $stat['mtime']),
                        'type' => strpos($file, 'critical') !== false ? 'critical' : 'full'
                    ];
                }
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    /**
     * Excluir backup
     */
    public function deleteBackup($backupFile) {
        $filePath = $this->backupDir . '/' . $backupFile;
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado'];
        }
        
        if (unlink($filePath)) {
            $this->logBackup('delete', $backupFile);
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Erro ao excluir arquivo'];
        }
    }
    
    /**
     * Verificar integridade do backup
     */
    public function verifyBackup($backupFile) {
        try {
            $filePath = $this->backupDir . '/' . $backupFile;
            
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo de backup não encontrado");
            }
            
            // Descompactar se necessário
            if (strpos($backupFile, '.gz') !== false) {
                $filePath = $this->decompressBackup($filePath);
            }
            
            // Ler e analisar conteúdo
            $backupContent = file_get_contents($filePath);
            $commands = $this->parseBackupCommands($backupContent);
            
            $verification = [
                'valid' => true,
                'commands' => count($commands),
                'tables' => [],
                'errors' => []
            ];
            
            // Verificar sintaxe básica dos comandos
            foreach ($commands as $i => $command) {
                if (!empty(trim($command))) {
                    // Verificar se é um comando SQL válido
                    if (strpos($command, 'INSERT') === 0 || 
                        strpos($command, 'CREATE') === 0 || 
                        strpos($command, 'ALTER') === 0 ||
                        strpos($command, 'UPDATE') === 0) {
                        
                        // Extrair nome da tabela
                        if (preg_match('/(?:INSERT INTO|CREATE TABLE|UPDATE|ALTER TABLE)\s+`?(\w+)`?/i', $command, $matches)) {
                            $tableName = $matches[1];
                            if (!in_array($tableName, $verification['tables'])) {
                                $verification['tables'][] = $tableName;
                            }
                        }
                    } else {
                        $verification['errors'][] = "Comando inválido na linha " . ($i + 1);
                    }
                }
            }
            
            if (!empty($verification['errors'])) {
                $verification['valid'] = false;
            }
            
            return $verification;
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter estatísticas dos backups
     */
    public function getBackupStats() {
        $backups = $this->listBackups();
        
        $stats = [
            'total_backups' => count($backups),
            'total_size' => 0,
            'full_backups' => 0,
            'critical_backups' => 0,
            'oldest_backup' => null,
            'newest_backup' => null
        ];
        
        foreach ($backups as $backup) {
            $stats['total_size'] += $backup['size'];
            
            if ($backup['type'] === 'full') {
                $stats['full_backups']++;
            } else {
                $stats['critical_backups']++;
            }
            
            if (!$stats['oldest_backup'] || strtotime($backup['created']) < strtotime($stats['oldest_backup'])) {
                $stats['oldest_backup'] = $backup['created'];
            }
            
            if (!$stats['newest_backup'] || strtotime($backup['created']) > strtotime($stats['newest_backup'])) {
                $stats['newest_backup'] = $backup['created'];
            }
        }
        
        return $stats;
    }
    
    /**
     * Configurar backup automático
     */
    public function scheduleBackup($type = 'full', $frequency = 'daily') {
        try {
            // Registrar configuração de backup
            $sql = "INSERT INTO system_policies (policy_key, policy_value, policy_type, description) 
                    VALUES (?, ?, 'string', ?) 
                    ON DUPLICATE KEY UPDATE policy_value = VALUES(policy_value)";
            
            $config = [
                'backup_type' => $type,
                'backup_frequency' => $frequency,
                'backup_enabled' => 'true'
            ];
            
            foreach ($config as $key => $value) {
                $this->db->query($sql, [$key, $value, "Configuração de backup automático"]);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Executar backup agendado
     */
    public function runScheduledBackup() {
        try {
            // Verificar se backup automático está habilitado
            $sql = "SELECT policy_value FROM system_policies WHERE policy_key = 'backup_enabled'";
            $stmt = $this->db->query($sql);
            $enabled = $stmt->fetch();
            
            if (!$enabled || $enabled['policy_value'] !== 'true') {
                return ['success' => false, 'error' => 'Backup automático desabilitado'];
            }
            
            // Obter tipo de backup configurado
            $sql = "SELECT policy_value FROM system_policies WHERE policy_key = 'backup_type'";
            $stmt = $this->db->query($sql);
            $type = $stmt->fetch();
            
            $backupType = $type ? $type['policy_value'] : 'full';
            
            // Executar backup
            if ($backupType === 'critical') {
                return $this->createCriticalBackup();
            } else {
                return $this->createFullBackup();
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Métodos privados
     */
    
    private function getTables() {
        $sql = "SHOW TABLES";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $this->db->query($sql, [$table]);
        return $stmt->rowCount() > 0;
    }
    
    private function backupTable($table) {
        $backup = "-- Tabela: $table\n";
        
        // Obter estrutura da tabela
        $sql = "SHOW CREATE TABLE `$table`";
        $stmt = $this->db->query($sql);
        $create = $stmt->fetch();
        
        $backup .= $create['Create Table'] . ";\n\n";
        
        // Obter dados da tabela
        $sql = "SELECT * FROM `$table`";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnsStr = '`' . implode('`, `', $columns) . '`';
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }
                
                $valuesStr = implode(', ', $values);
                $backup .= "INSERT INTO `$table` ($columnsStr) VALUES ($valuesStr);\n";
            }
        }
        
        $backup .= "\n";
        return $backup;
    }
    
    private function backupSystemConfig() {
        $backup = "-- Configurações do Sistema\n";
        
        // Obter políticas
        $sql = "SELECT * FROM system_policies";
        $stmt = $this->db->query($sql);
        $policies = $stmt->fetchAll();
        
        foreach ($policies as $policy) {
            $backup .= "INSERT INTO system_policies (policy_key, policy_value, policy_type, description, created_at, updated_at) VALUES ";
            $backup .= "('{$policy['policy_key']}', '" . addslashes($policy['policy_value']) . "', '{$policy['policy_type']}', '" . addslashes($policy['description']) . "', '{$policy['created_at']}', '{$policy['updated_at']}');\n";
        }
        
        $backup .= "\n";
        return $backup;
    }
    
    private function compressBackup($file) {
        $compressedFile = $file . '.gz';
        
        // Ler arquivo original
        $content = file_get_contents($file);
        
        // Comprimir
        $compressed = gzencode($content, 9);
        
        // Salvar arquivo comprimido
        file_put_contents($compressedFile, $compressed);
        
        return $compressedFile;
    }
    
    private function decompressBackup($file) {
        $content = gzdecode(file_get_contents($file));
        
        $tempFile = tempnam(sys_get_temp_dir(), 'backup_');
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }
    
    private function parseBackupCommands($content) {
        // Remover comentários
        $content = preg_replace('/--.*$/m', '', $content);
        
        // Dividir em comandos
        $commands = preg_split('/;\s*\n/', $content);
        
        return array_filter($commands, function($command) {
            return !empty(trim($command));
        });
    }
    
    private function cleanupOldBackups() {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            // Manter apenas os mais recentes
            $toDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($toDelete as $backup) {
                $filePath = $this->backupDir . '/' . $backup['file'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }
    
    private function logBackup($type, $file) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'backups', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            'BACKUP_' . strtoupper($type),
            $file,
            null,
            json_encode(['file' => $file, 'type' => $type]),
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    private function logRestore($file) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'backups', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            'RESTORE',
            $file,
            json_encode(['file' => $file]),
            null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
}

?>
