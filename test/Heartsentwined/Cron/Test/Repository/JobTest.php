<?php
namespace Heartsentwined\Cron\Test\Repository;

use Heartsentwined\Cron\Entity;
use Heartsentwined\Cron\Repository;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class JobTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->repo =
            $this->em->getRepository('Heartsentwined\Cron\Entity\Job');
    }

    public function getJob($status)
    {
        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus($status)
            ->setCreateTime(new \DateTime)
            ->setScheduleTime(new \DateTime);
        $this->em->flush();

        return $job;
    }

    public function testGetPending()
    {
        $pending = $this->getJob(Repository\Job::STATUS_PENDING);
        $this->getJob(Repository\Job::STATUS_SUCCESS);
        $this->getJob(Repository\Job::STATUS_RUNNING);
        $this->getJob(Repository\Job::STATUS_MISSED);
        $this->getJob(Repository\Job::STATUS_ERROR);

        $this->assertSame(
            array($pending),
            $this->repo->getPending());
    }

    public function testGetRunning()
    {
        $this->getJob(Repository\Job::STATUS_PENDING);
        $this->getJob(Repository\Job::STATUS_SUCCESS);
        $running = $this->getJob(Repository\Job::STATUS_RUNNING);
        $this->getJob(Repository\Job::STATUS_MISSED);
        $this->getJob(Repository\Job::STATUS_ERROR);

        $this->assertSame(
            array($running),
            $this->repo->getRunning());
    }

    public function testGetHistory()
    {
        $this->getJob(Repository\Job::STATUS_PENDING);
        $success = $this->getJob(Repository\Job::STATUS_SUCCESS);
        $this->getJob(Repository\Job::STATUS_RUNNING);
        $missed = $this->getJob(Repository\Job::STATUS_MISSED);
        $error = $this->getJob(Repository\Job::STATUS_ERROR);

        $this->assertSame(
            array($success, $missed, $error),
            $this->repo->getHistory());
    }
}
