<?php
/**
 * Modelo para gerenciar políticas do sistema
 */

class SystemPolicy {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Obter política por chave
     */
    public function get($key) {
        $sql = "SELECT policy_value, policy_type FROM system_policies WHERE policy_key = ?";
        $stmt = $this->db->query($sql, [$key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        return $this->castValue($result['policy_value'], $result['policy_type']);
    }
    
    /**
     * Definir política
     */
    public function set($key, $value, $type = 'string', $description = null) {
        // Validar tipo
        if (!in_array($type, ['string', 'integer', 'decimal', 'boolean', 'json'])) {
            throw new Exception("Tipo de política inválido: $type");
        }
        
        // Validar valor conforme tipo
        $this->validateValue($value, $type);
        
        // Converter valor para string
        $stringValue = $this->valueToString($value, $type);
        
        // Obter valor anterior para auditoria
        $oldValue = $this->get($key);
        
        $sql = "INSERT INTO system_policies (policy_key, policy_value, policy_type, description) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                policy_value = VALUES(policy_value), 
                policy_type = VALUES(policy_type), 
                description = VALUES(description),
                updated_at = CURRENT_TIMESTAMP";
        
        $this->db->query($sql, [$key, $stringValue, $type, $description]);
        
        // Registrar log de auditoria
        $this->logAudit('UPDATE_POLICY', $key, $oldValue, $value);
        
        return true;
    }
    
    /**
     * Obter todas as políticas
     */
    public function getAll() {
        $sql = "SELECT * FROM system_policies ORDER BY policy_key";
        $stmt = $this->db->query($sql);
        $policies = $stmt->fetchAll();
        
        $result = [];
        foreach ($policies as $policy) {
            $result[$policy['policy_key']] = [
                'policy_value' => $this->castValue($policy['policy_value'], $policy['policy_type']),
                'policy_type' => $policy['policy_type'],
                'description' => $policy['description'],
                'created_at' => $policy['created_at'],
                'updated_at' => $policy['updated_at']
            ];
        }
        
        return $result;
    }
    
    /**
     * Obter políticas por grupo
     */
    public function getByGroup($group) {
        $sql = "SELECT * FROM system_policies WHERE policy_key LIKE ? ORDER BY policy_key";
        $stmt = $this->db->query($sql, ["$group%"]);
        $policies = $stmt->fetchAll();
        
        $result = [];
        foreach ($policies as $policy) {
            $result[$policy['policy_key']] = [
                'policy_value' => $this->castValue($policy['policy_value'], $policy['policy_type']),
                'policy_type' => $policy['policy_type'],
                'description' => $policy['description']
            ];
        }
        
        return $result;
    }
    
    /**
     * Validar valor conforme tipo
     */
    private function validateValue($value, $type) {
        switch ($type) {
            case 'integer':
                if (!is_int($value) && !ctype_digit($value)) {
                    throw new Exception("Valor deve ser um número inteiro");
                }
                break;
                
            case 'decimal':
                if (!is_numeric($value)) {
                    throw new Exception("Valor deve ser um número decimal");
                }
                break;
                
            case 'boolean':
                if (!is_bool($value) && !in_array($value, ['true', 'false', '0', '1'])) {
                    throw new Exception("Valor deve ser booleano");
                }
                break;
                
            case 'json':
                if (!is_string($value) && !is_array($value) && !is_object($value)) {
                    throw new Exception("Valor deve ser JSON válido");
                }
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("JSON inválido: " . json_last_error_msg());
                    }
                }
                break;
        }
    }
    
    /**
     * Converter valor para string
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
                
            case 'json':
                return is_string($value) ? $value : json_encode($value);
                
            default:
                return (string) $value;
        }
    }
    
    /**
     * Converter valor conforme tipo
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int) $value;
                
            case 'decimal':
                return (float) $value;
                
            case 'boolean':
                return $value === 'true' || $value === '1' || $value === 1;
                
            case 'json':
                return json_decode($value, true);
                
            default:
                return $value;
        }
    }
    
    /**
     * Verificar se sistema está pronto
     */
    public function isSystemReady() {
        $requiredPolicies = [
            'min_numbers_per_raffle',
            'max_numbers_per_raffle',
            'max_numbers_per_cpf',
            'reservation_timeout_minutes',
            'minimum_wait_hours'
        ];
        
        foreach ($requiredPolicies as $policy) {
            if ($this->get($policy) === null) {
                return false;
            }
        }
        
        // Verificar se Asaas está configurado
        $sql = "SELECT COUNT(*) as count FROM integrations WHERE integration_name = 'asaas' AND is_active = true";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Validar conjunto de políticas
     */
    public function validatePolicies($policies) {
        $errors = [];
        $warnings = [];
        
        // Validar mínimos e máximos
        if (isset($policies['min_numbers_per_raffle']) && isset($policies['max_numbers_per_raffle'])) {
            $min = (int) $policies['min_numbers_per_raffle'];
            $max = (int) $policies['max_numbers_per_raffle'];
            
            if ($min >= $max) {
                $errors[] = "Quantidade mínima deve ser menor que a máxima";
            }
            
            if ($min < 10) {
                $warnings[] = "Quantidade mínima muito baixa (recomendado: 100+)";
            }
            
            if ($max > 100000) {
                $warnings[] = "Quantidade máxima muito alta (pode afetar performance)";
            }
        }
        
        // Validar limite por CPF
        if (isset($policies['max_numbers_per_cpf'])) {
            $maxPerCpf = (int) $policies['max_numbers_per_cpf'];
            
            if ($maxPerCpf < 1) {
                $errors[] = "Limite por CPF deve ser pelo menos 1";
            }
            
            if ($maxPerCpf > 100) {
                $warnings[] = "Limite por CPF muito alto (risco de fraude)";
            }
        }
        
        // Validar tempo de reserva
        if (isset($policies['reservation_timeout_minutes'])) {
            $timeout = (int) $policies['reservation_timeout_minutes'];
            
            if ($timeout < 5) {
                $errors[] = "Tempo de reserva muito baixo (mínimo: 5 minutos)";
            }
            
            if ($timeout > 60) {
                $warnings[] = "Tempo de reserva muito alto (pode afetar vendas)";
            }
        }
        
        // Validar tempo de espera
        if (isset($policies['minimum_wait_hours'])) {
            $waitHours = (int) $policies['minimum_wait_hours'];
            
            if ($waitHours < 1) {
                $errors[] = "Tempo de espera mínimo deve ser pelo menos 1 hora";
            }
            
            if ($waitHours < 24) {
                $warnings[] = "Tempo de espera abaixo de 24h (recomendado: 24h+)";
            }
        }
        
        // Validar preços
        if (isset($policies['min_number_price']) && isset($policies['max_number_price'])) {
            $minPrice = (float) $policies['min_number_price'];
            $maxPrice = (float) $policies['max_number_price'];
            
            if ($minPrice >= $maxPrice) {
                $errors[] = "Preço mínimo deve ser menor que o máximo";
            }
            
            if ($minPrice < 0.01) {
                $errors[] = "Preço mínimo muito baixo (mínimo: R$ 0,01)";
            }
            
            if ($maxPrice > 10000) {
                $warnings[] = "Preço máximo muito alto";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Restaurar políticas padrão
     */
    public function restoreDefaults() {
        $defaults = Config::getDefaults();
        
        foreach ($defaults as $key => $value) {
            $type = gettype($value);
            
            // Ajustar tipos específicos
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_float($value)) {
                $type = 'decimal';
            } elseif (is_int($value)) {
                $type = 'integer';
            }
            
            $this->set($key, $value, $type, "Política padrão restaurada");
        }
        
        return true;
    }
    
    /**
     * Obter estatísticas das políticas
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN policy_type = 'string' THEN 1 ELSE 0 END) as string_count,
                    SUM(CASE WHEN policy_type = 'integer' THEN 1 ELSE 0 END) as integer_count,
                    SUM(CASE WHEN policy_type = 'decimal' THEN 1 ELSE 0 END) as decimal_count,
                    SUM(CASE WHEN policy_type = 'boolean' THEN 1 ELSE 0 END) as boolean_count,
                    SUM(CASE WHEN policy_type = 'json' THEN 1 ELSE 0 END) as json_count
                FROM system_policies";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Exportar políticas para JSON
     */
    public function exportToJson() {
        $policies = $this->getAll();
        
        $export = [];
        foreach ($policies as $key => $policy) {
            $export[$key] = [
                'value' => $policy['policy_value'],
                'type' => $policy['policy_type'],
                'description' => $policy['description']
            ];
        }
        
        return json_encode($export, JSON_PRETTY_PRINT);
    }
    
    /**
     * Importar políticas de JSON
     */
    public function importFromJson($json) {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido: " . json_last_error_msg());
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($data as $key => $policy) {
            try {
                $this->set(
                    $key,
                    $policy['value'],
                    $policy['type'] ?? 'string',
                    $policy['description'] ?? "Importado via JSON"
                );
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Erro ao importar $key: " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    /**
     * Limpar políticas não utilizadas
     */
    public function cleanup() {
        // Obter políticas padrão
        $defaults = array_keys(Config::getDefaults());
        
        // Remover políticas que não são padrão e não têm valor
        $sql = "DELETE FROM system_policies 
                WHERE policy_key NOT IN ('" . implode("','", $defaults) . "') 
                AND (policy_value IS NULL OR policy_value = '')";
        
        $this->db->query($sql);
        
        return $this->db->getConnection()->rowCount();
    }
    
    /**
     * Registrar log de auditoria
     */
    private function logAudit($action, $policyKey, $oldValue, $newValue) {
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
                VALUES (?, ?, 'system_policies', ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $_SESSION['user_id'] ?? null,
            $action,
            $policyKey,
            $oldValue !== null ? json_encode($oldValue) : null,
            $newValue !== null ? json_encode($newValue) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    /**
     * Obter histórico de alterações de política
     */
    public function getHistory($policyKey, $limit = 50) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE al.table_name = 'system_policies' AND al.record_id = ? 
                ORDER BY al.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->query($sql, [$policyKey, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se política existe
     */
    public function exists($key) {
        $sql = "SELECT COUNT(*) as count FROM system_policies WHERE policy_key = ?";
        $stmt = $this->db->query($sql, [$key]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Remover política
     */
    public function delete($key) {
        if (!$this->exists($key)) {
            throw new Exception("Política não encontrada: $key");
        }
        
        $oldValue = $this->get($key);
        
        $sql = "DELETE FROM system_policies WHERE policy_key = ?";
        $this->db->query($sql, [$key]);
        
        $this->logAudit('DELETE_POLICY', $key, $oldValue, null);
        
        return true;
    }
}

?>
