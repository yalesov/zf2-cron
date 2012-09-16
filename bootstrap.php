<?php
use Zend\Mvc\Application;
chdir(__DIR__);
require 'vendor/autoload.php';
return Application::init(array(
    'modules'   => array(
        'DoctrineModule',
        'DoctrineORMModule',
        'Cron',
    ),
    'module_listener_options' => array(
        'config_glob_paths' => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'module_paths' => array(
            'Cron' => __DIR__,
            'vendor',
        ),
    ),
));
