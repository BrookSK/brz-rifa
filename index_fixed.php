<?php
/**
 * Sistema Profissional de Rifas Online
 * Ponto de entrada principal da aplicação
 */

session_start();

// Definir constantes do sistema
define('ROOT_PATH', __DIR__);
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('CACHE_PATH', ROOT_PATH . '/cache');

// Autoload das classes
require_once SRC_PATH . '/autoload.php';

// Inicializar configuração
$database = new Database();

// Router simples
$router = new Router();
$router->dispatch();

?>
