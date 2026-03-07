<?php
/**
 * Configurações globais do sistema
 */

class Config {
    // Configurações do Site
    const SITE_NAME = 'BRZ Rifa';
    const SITE_URL = 'http://localhost/brz-rifa';
    const SITE_EMAIL = 'contato@onsolutionsbrasil.com.br';
    const SITE_PHONE = '(XX) XXXXX-XXXX';
    
    // Configurações de Sessão
    const SESSION_LIFETIME = 7200; // 2 horas em segundos
    const MIN_PASSWORD_LENGTH = 8;
    
    // Configurações do Asaas
    const ASAAS_API_URL = 'https://api.asaas.com/v3';
    const ASAAS_WEBHOOK_TIMEOUT = 30; // segundos
    
    // Configurações de Upload
    const UPLOAD_MAX_SIZE = 5242880; // 5MB em bytes
    const UPLOAD_ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    // Configurações de Cache
    const CACHE_LIFETIME = 3600; // 1 hora em segundos
    
    // Configurações de Log
    const LOG_LEVEL = 'INFO';
    const LOG_MAX_SIZE = 10485760; // 10MB em bytes
    
    // Configurações de Segurança
    const CSRF_TOKEN_LIFETIME = 3600; // 1 hora
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_TIME = 900; // 15 minutos
    
    // Configurações de Pagamento
    const PAYMENT_TIMEOUT = 600; // 10 minutos
    const PAYMENT_RETRY_ATTEMPTS = 3;
    
    // Configurações de Notificação
    const NOTIFICATION_BATCH_SIZE = 100;
    const NOTIFICATION_RETRY_ATTEMPTS = 3;
    
    // Configurações de Relatório
    const REPORT_RETENTION_DAYS = 90;
    const REPORT_MAX_GENERATION_TIME = 300; // 5 minutos
    
    // Configurações de API
    const API_RATE_LIMIT = 100; // requisições por minuto
    const API_TIMEOUT = 30; // segundos
    
    /**
     * Obter configuração dinâmica do banco de dados
     */
    public static function get($key, $default = null) {
        static $cache = [];
        
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        
        try {
            $db = new Database();
            $sql = "SELECT policy_value FROM system_policies WHERE policy_key = ?";
            $stmt = $db->query($sql, [$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                $value = $result['policy_value'];
                
                // Converter tipo se necessário
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                } elseif ($value === 'true' || $value === 'false') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $cache[$key] = $value;
                return $value;
            }
        } catch (Exception $e) {
            error_log("Error getting config $key: " . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Definir configuração dinâmica
     */
    public static function set($key, $value, $description = null) {
        try {
            $db = new Database();
            
            $sql = "INSERT INTO system_policies (policy_key, policy_value, policy_type, description) 
                    VALUES (?, ?, 'string', ?) 
                    ON DUPLICATE KEY UPDATE 
                    policy_value = VALUES(policy_value), 
                    updated_at = CURRENT_TIMESTAMP";
            
            $db->query($sql, [$key, $value, $description]);
            
            // Limpar cache
            if (class_exists('Config')) {
                $reflection = new ReflectionClass('Config');
                $cacheProperty = $reflection->getProperty('cache');
                $cacheProperty->setAccessible(true);
                $cacheProperty->setValue(null, []);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error setting config $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter todas as configurações
     */
    public static function getAll() {
        static $all = null;
        
        if ($all !== null) {
            return $all;
        }
        
        try {
            $db = new Database();
            $sql = "SELECT policy_key, policy_value FROM system_policies";
            $stmt = $db->query($sql);
            $policies = $stmt->fetchAll();
            
            $all = [];
            foreach ($policies as $policy) {
                $value = $policy['policy_value'];
                
                // Converter tipo
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                } elseif ($value === 'true' || $value === 'false') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $all[$policy['policy_key']] = $value;
            }
            
            return $all;
        } catch (Exception $e) {
            error_log("Error getting all configs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar se sistema está pronto
     */
    public static function isSystemReady() {
        $requiredPolicies = [
            'min_numbers_per_raffle',
            'max_numbers_per_raffle',
            'max_numbers_per_cpf',
            'reservation_timeout_minutes',
            'minimum_wait_hours'
        ];
        
        foreach ($requiredPolicies as $policy) {
            if (self::get($policy) === null) {
                return false;
            }
        }
        
        // Verificar se Asaas está configurado
        try {
            $db = new Database();
            $sql = "SELECT COUNT(*) as count FROM integrations WHERE integration_name = 'asaas' AND is_active = true";
            $stmt = $db->query($sql);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter configurações de e-mail
     */
    public static function getEmailConfig() {
        try {
            $db = new Database();
            $sql = "SELECT config_data FROM integrations WHERE integration_name = 'email' AND is_active = true";
            $stmt = $db->query($sql);
            $result = $stmt->fetch();
            
            if ($result && $result['config_data']) {
                return json_decode($result['config_data'], true);
            }
        } catch (Exception $e) {
            error_log("Error getting email config: " . $e->getMessage());
        }
        
        return [
            'smtp_host' => null,
            'smtp_port' => 587,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => 'tls',
            'from_email' => self::SITE_EMAIL,
            'from_name' => self::SITE_NAME
        ];
    }
    
    /**
     * Obter configurações do Asaas
     */
    public static function getAsaasConfig() {
        try {
            $db = new Database();
            $sql = "SELECT api_key, webhook_secret, config_data FROM integrations WHERE integration_name = 'asaas' AND is_active = true";
            $stmt = $db->query($sql);
            $result = $stmt->fetch();
            
            if ($result) {
                $config = [
                    'api_key' => $result['api_key'],
                    'webhook_secret' => $result['webhook_secret']
                ];
                
                if ($result['config_data']) {
                    $configData = json_decode($result['config_data'], true);
                    $config = array_merge($config, $configData);
                }
                
                return $config;
            }
        } catch (Exception $e) {
            error_log("Error getting Asaas config: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Formatar valor monetário
     */
    public static function formatMoney($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
    
    /**
     * Format data
     */
    public static function formatDate($date, $format = 'd/m/Y H:i') {
        if (!$date) return '';
        
        $datetime = new DateTime($date);
        return $datetime->format($format);
    }
    
    /**
     * Gerar URL absoluta
     */
    public static function url($path = '') {
        return rtrim(self::SITE_URL, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Gerar URL para assets
     */
    public static function asset($path) {
        return self::url('assets/' . ltrim($path, '/'));
    }
    
    /**
     * Obter timezone configurado
     */
    public static function getTimezone() {
        return self::get('timezone', 'America/Sao_Paulo');
    }
    
    /**
     * Obter moeda configurada
     */
    public static function getCurrency() {
        return self::get('currency', 'BRL');
    }
    
    /**
     * Obter idioma configurado
     */
    public static function getLanguage() {
        return self::get('language', 'pt-BR');
    }
    
    /**
     * Verificar se está em modo debug
     */
    public static function isDebug() {
        return self::get('debug_mode', false);
    }
    
    /**
     * Obter limite de upload
     */
    public static function getUploadMaxSize() {
        $configured = self::get('upload_max_size');
        return $configured ? $configured : self::UPLOAD_MAX_SIZE;
    }
    
    /**
     * Obter tipos permitidos de upload
     */
    public static function getUploadAllowedTypes() {
        $configured = self::get('upload_allowed_types');
        return $configured ? explode(',', $configured) : self::UPLOAD_ALLOWED_TYPES;
    }
    
    /**
     * Validar configurações obrigatórias
     */
    public static function validateRequired() {
        $errors = [];
        
        // Verificar políticas obrigatórias
        $required = [
            'min_numbers_per_raffle' => 'integer',
            'max_numbers_per_raffle' => 'integer',
            'max_numbers_per_cpf' => 'integer',
            'reservation_timeout_minutes' => 'integer',
            'minimum_wait_hours' => 'integer'
        ];
        
        foreach ($required as $key => $type) {
            $value = self::get($key);
            if ($value === null) {
                $errors[] = "Política obrigatória ausente: $key";
            } elseif ($type === 'integer' && !is_int($value)) {
                $errors[] = "Política $key deve ser um número inteiro";
            }
        }
        
        // Verificar integração Asaas
        if (!self::getAsaasConfig()) {
            $errors[] = "Integração com Asaas não configurada";
        }
        
        return $errors;
    }
    
    /**
     * Obter configurações padrão
     */
    public static function getDefaults() {
        return [
            // Rifas
            'min_numbers_per_raffle' => 100,
            'max_numbers_per_raffle' => 10000,
            'max_numbers_per_cpf' => 10,
            'reservation_timeout_minutes' => 10,
            'minimum_wait_hours' => 24,
            'max_raffle_duration_days' => 30,
            
            // Preços
            'min_number_price' => 1.00,
            'max_number_price' => 1000.00,
            
            // Segurança
            'fraud_detection_enabled' => true,
            'max_reservations_per_hour' => 3,
            'audit_log_retention_days' => 365,
            
            // Notificações
            'email_notifications_enabled' => true,
            'alert_threshold_sales_percent' => 85,
            
            // Sistema
            'timezone' => 'America/Sao_Paulo',
            'currency' => 'BRL',
            'language' => 'pt-BR',
            'debug_mode' => false
        ];
    }
}

?>
