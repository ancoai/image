<?php

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/src/' . str_replace('App\\', 'App/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});
