<?php
namespace Heartsentwined\Cron\Test;

use Heartsentwined\Cron\Entity;
use Heartsentwined\Cron\Repository;
use Heartsentwined\Cron\Service\Cron;
use Heartsentwined\Cron\Service\Registry;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class CronTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->cron = $this->sm->get('cron')
            ->setEm($this->em);
    }

    public function tearDown()
    {
        unset($this->cron);
        parent::tearDown();
    }

    public function getDummy()
    {
        $dummy = $this->getMockBuilder('Heartsentwined\\Cron\\Service\\Cron')
            ->disableOriginalConstructor()
            ->getMock();
        return $dummy;
    }

    public function getJobPastPending()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $past   = \DateTime::createFromFormat('U', time()-3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_PENDING)
            ->setCreateTime($now)
            ->setScheduleTime($past);
        $this->em->flush();

        return $job;
    }

    public function getJobPastRunning()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $past   = \DateTime::createFromFormat('U', time()-3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_RUNNING)
            ->setCreateTime($now)
            ->setScheduleTime($past);
        $this->em->flush();

        return $job;
    }

    public function getJobPastMissed()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $past   = \DateTime::createFromFormat('U', time()-3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_MISSED)
            ->setCreateTime($now)
            ->setScheduleTime($past);
        $this->em->flush();

        return $job;
    }

    public function getJobPastError()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $past   = \DateTime::createFromFormat('U', time()-3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_ERROR)
            ->setCreateTime($now)
            ->setScheduleTime($past);
        $this->em->flush();

        return $job;
    }

    public function getJobFuturePending()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $future = \DateTime::createFromFormat('U', time()+3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_PENDING)
            ->setCreateTime($now)
            ->setScheduleTime($future);
        $this->em->flush();

        return $job;
    }

    public function getJobFutureRunning()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $future = \DateTime::createFromFormat('U', time()+3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_RUNNING)
            ->setCreateTime($now)
            ->setScheduleTime($future);
        $this->em->flush();

        return $job;
    }

    public function getJobFutureMissed()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $future = \DateTime::createFromFormat('U', time()+3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_MISSED)
            ->setCreateTime($now)
            ->setScheduleTime($future);
        $this->em->flush();

        return $job;
    }

    public function getJobFutureError()
    {
        $now    = \DateTime::createFromFormat('U', time());
        $future = \DateTime::createFromFormat('U', time()+3600);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus(Repository\Job::STATUS_ERROR)
            ->setCreateTime($now)
            ->setScheduleTime($future);
        $this->em->flush();

        return $job;
    }

    public function testRun()
    {
        $cron = $this->getMock(
            'Heartsentwined\\Cron\\Service\\Cron',
            array('schedule', 'process', 'cleanup'));

        $cron
            ->expects($this->once())
            ->method('schedule')
            ->will($this->returnSelf());
        $cron
            ->expects($this->once())
            ->method('process')
            ->will($this->returnSelf());
        $cron
            ->expects($this->once())
            ->method('cleanup')
            ->will($this->returnSelf());

        $cron->run();
    }

    public function testGetPending()
    {
        $jobPastPending = $this->getJobPastPending();
        $jobFuturePending = $this->getJobFuturePending();

        $this->getJobPastRunning();
        $this->getJobPastMissed();
        $this->getJobPastError();
        $this->getJobFutureRunning();
        $this->getJobFutureMissed();
        $this->getJobFutureError();

        $pending = array();
        foreach ($this->cron->getPending() as $job) {
            $pending[] = $job->getId();
        }

        $this->assertSame(
            array(
                $jobPastPending->getId(),
                $jobFuturePending->getId(),
            ),
            $pending
        );
    }

    public function testProcess()
    {
        // only past + pending should run

        $job = $this->getJobPastPending();
        $cron = $this->getMock(
            'Heartsentwined\\Cron\\Service\\Cron',
            array('getPending'))
            ->setEm($this->em);
        $cron
            ->expects($this->any())
            ->method('getPending')
            ->will($this->returnValue(array($job)));
        $dummy = $this->getDummy();
        $dummy
            ->expects($this->once())
            ->method('run');
        $cron->register('time', '* * * * *', array($dummy, 'run'), array());
        $cron->process();
        $this->assertSame(Repository\Job::STATUS_SUCCESS, $job->getStatus());
        $this->assertSame(null, $job->getErrorMsg());
        $this->assertSame(null, $job->getStackTrace());
        $this->assertNotEmpty($job->getExecuteTime());
        $this->assertNotEmpty($job->getFinishTime());

        // past + (not pending) and all future

        foreach (array(
            $this->getJobPastRunning(),
            $this->getJobPastMissed(),
            $this->getJobPastError(),
            $this->getJobFuturePending(),
            $this->getJobFutureRunning(),
            $this->getJobFutureMissed(),
            $this->getJobFutureError(),
        ) as $job) {
            $prevStatus = $job->getStatus();
            $cron = $this->getMock(
                'Heartsentwined\\Cron\\Service\\Cron',
                array('getPending'))
                ->setEm($this->em);
            $cron
                ->expects($this->any())
                ->method('getPending')
                ->will($this->returnValue(array($job)));
            $dummy = $this->getDummy();
            $dummy
                ->expects($this->never())
                ->method('run');
            $cron->register('time', '* * * * *', array($dummy, 'run'), array());
            $cron->process();
            $this->assertSame($prevStatus, $job->getStatus());
            $this->assertSame(null, $job->getErrorMsg());
            $this->assertSame(null, $job->getStackTrace());
            $this->assertNull($job->getExecuteTime());
            $this->assertNull($job->getFinishTime());
        }

        // cron job throwing exceptions

        $job = $this->getJobPastPending();
        $cron = $this->getMock(
            'Heartsentwined\\Cron\\Service\\Cron',
            array('getPending'))
            ->setEm($this->em);
        $cron
            ->expects($this->any())
            ->method('getPending')
            ->will($this->returnValue(array($job)));
        $dummy = $this->getDummy();
        $dummy
            ->expects($this->once())
            ->method('run')
            ->will($this->throwException(new \RuntimeException(
                'foo runtime exception'
            )));
        $cron->register('time', '* * * * *', array($dummy, 'run'), array());
        $cron->process();
        $this->assertSame(Repository\Job::STATUS_ERROR, $job->getStatus());
        $this->assertSame('foo runtime exception', $job->getErrorMsg());
        $this->assertNotEmpty($job->getStackTrace());
        $this->assertNotEmpty($job->getExecuteTime());
        $this->assertNull($job->getFinishTime());

        // too late for job

        $job = $this->getJobPastPending();
        $cron = $this->getMock(
            'Heartsentwined\\Cron\\Service\\Cron',
            array('getPending'))
            ->setScheduleLifetime(0)
            ->setEm($this->em);
        $cron
            ->expects($this->any())
            ->method('getPending')
            ->will($this->returnValue(array($job)));
        $dummy = $this->getDummy();
        $dummy
            ->expects($this->never())
            ->method('run');
        $cron->register('time', '* * * * *', array($dummy, 'run'), array());
        $cron->process();
        $this->assertSame(Repository\Job::STATUS_MISSED, $job->getStatus());
        $this->assertSame('too late for job', $job->getErrorMsg());
        $this->assertNotEmpty($job->getStackTrace());
        $this->assertNull($job->getExecuteTime());
        $this->assertNull($job->getFinishTime());

        // job not registered

        $job = $this->getJobPastPending();
        $cron = $this->getMock(
            'Heartsentwined\\Cron\\Service\\Cron',
            array('getPending'))
            ->setEm($this->em);
        $cron
            ->expects($this->any())
            ->method('getPending')
            ->will($this->returnValue(array($job)));
        $dummy = $this->getDummy();
        $dummy
            ->expects($this->never())
            ->method('run');
        Registry::destroy();
        $cron->process();
        $this->assertSame(Repository\Job::STATUS_ERROR, $job->getStatus());
        $this->assertSame('job "time" undefined in cron registry', $job->getErrorMsg());
        $this->assertNotEmpty($job->getStackTrace());
        $this->assertNull($job->getExecuteTime());
        $this->assertNull($job->getFinishTime());
    }
}
