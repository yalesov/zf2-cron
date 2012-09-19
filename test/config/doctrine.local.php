<?php
return array(
    'doctrine' => array(
        'connection' => array(
            'orm_default' => array(
                'params' => array(
                    'driverClass'   => 'Doctrine\DBAL\Driver\PDOSqlite\Driver',
                    'user'      => 'test',
                    'password'  => 'test',
                    'memory'    => true,
                ),
            ),
        ),
        'configuration' => array(
            'orm_default' => array(
                'metadata_cache'    => 'array',
                'query_cache'       => 'array',
                'result_cache'      => 'array',
                'generate_proxies'  => true,
                'proxy_dir'         => 'tmp',
                'proxy_namespace'   => 'DoctrineORMModule\Proxy'
            )
        ),
    ),
);
