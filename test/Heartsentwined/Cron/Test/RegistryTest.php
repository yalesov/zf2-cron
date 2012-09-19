<?php
namespace Heartsentwined\Cron\Test;

use Heartsentwined\Cron\Entity;
use Heartsentwined\Cron\Service\Registry;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class RegistryTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();
    }

    public function testInstance()
    {
        $instance = Registry::getInstance();
        $this->assertInstanceOf('Heartsentwined\Cron\Service\Registry', $instance);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('test-job')
            ->setStatus('pending')
            ->setCreateTime(new \DateTime)
            ->setScheduleTime(new \DateTime);
        $this->em->flush();

        $this->assertSame($job, $this->em->find('Heartsentwined\Cron\Entity\Job', 1));
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
