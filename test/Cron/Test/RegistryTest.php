<?php
namespace Cron\Test;

use Cron\Service\Registry;
use Zend\Mvc\Application;

class RegistryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $application = require __DIR__ . '/../../../bootstrap.php';
        $this->sm = $application->getServiceManager();
    }

    public function testInstance()
    {
        $instance = Registry::getInstance();
        $this->assertInstanceOf('Cron\Service\Registry', $instance);
    }

    public function testSingleton()
    {
        $instance1 = Registry::getInstance();
        $instance2 = Registry::getInstance();
        $this->assertSame($instance1, $instance2);
    }

    public function testDestroy()
    {
        $instance1 = Registry::getInstance();
        Registry::destroy();
        $instance2 = Registry::getInstance();
        $this->assertNotSame($instance1, $instance2);
    }

    public function testCronRegistry()
    {
        Registry::register(
            'test',
            '* * * * *',
            array($this, 'dummy'),
            array()
        );

        $expectedCronRegistry = array(
            'test' => array(
                'frequency' => '* * * * *',
                'callback'  => array($this, 'dummy'),
                'args'      => array(),
            ),
        );
        $cronRegistry = Registry::getCronRegistry();

        $this->assertSame($expectedCronRegistry, $cronRegistry);
    }

    //dummy function to act as cron job
    public function dummy() {}
}
