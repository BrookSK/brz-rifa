<?php
/**
 * Autoloader para carregar classes automaticamente
 */

spl_autoload_register(function ($class) {
    // Converter namespace para caminho de arquivo
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    
    // Possíveis locais para as classes
    $paths = [
        SRC_PATH . '/controllers/' . $class . '.php',
        SRC_PATH . '/models/' . $class . '.php',
        SRC_PATH . '/services/' . $class . '.php',
        SRC_PATH . '/middleware/' . $class . '.php',
        SRC_PATH . '/' . $class . '.php'
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

?>
