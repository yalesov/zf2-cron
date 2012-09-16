<?php
chdir(__DIR__.'/..');
$loader = require 'vendor/autoload.php';
$loader->add('Cron\Test', __DIR__);
