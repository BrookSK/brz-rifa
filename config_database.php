<?php
/**
 * Configurações de banco de dados para o servidor
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'brz_rifa');
define('DB_USER', 'root');
define('DB_PASS', 'sua_senha_aqui');
define('DB_CHARSET', 'utf8mb4');

// Configurações do site
define('SITE_NAME', 'BRZ Rifa');
define('SITE_URL', 'https://rifa.brazilianashop.com.br');
define('SITE_EMAIL', 'contato@onsolutionsbrasil.com.br');
define('SITE_PHONE', '(XX) XXXXX-XXXX');

// Configurações de sessão
define('SESSION_LIFETIME', 7200);
define('MIN_PASSWORD_LENGTH', 8);

// Configurações do Asaas
define('ASAAS_API_URL', 'https://api.asaas.com/v3');
define('ASAAS_WEBHOOK_TIMEOUT', 30);

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5242880);
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configurações de cache
define('CACHE_LIFETIME', 3600);

// Configurações de log
define('LOG_LEVEL', 'INFO');
define('LOG_MAX_SIZE', 10485760);

// Configurações de segurança
define('CSRF_TOKEN_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// Configurações de pagamento
define('PAYMENT_TIMEOUT', 600);
define('PAYMENT_RETRY_ATTEMPTS', 3);

// Configurações de notificação
define('NOTIFICATION_BATCH_SIZE', 100);
define('NOTIFICATION_RETRY_ATTEMPTS', 3);

// Configurações de relatório
define('REPORT_RETENTION_DAYS', 90);
define('REPORT_MAX_GENERATION_TIME', 300);

// Configurações de API
define('API_RATE_LIMIT', 100);
define('API_TIMEOUT', 30);

?>
