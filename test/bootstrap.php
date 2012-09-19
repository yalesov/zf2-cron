<?php
chdir(__DIR__.'/..');
$loader = require 'vendor/autoload.php';
$loader->add('Heartsentwined\Cron\Test', __DIR__);
