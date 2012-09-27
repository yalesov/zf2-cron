<?php
namespace Heartsentwined\Cron\Test\Service;

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
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->cron = $this->sm->get('cron')
            ->setEm($this->em);
    }

    public function tearDown()
    {
        Registry::destroy();
        unset($this->cron);
        parent::tearDown();
    }

    public function getDummy()
    {
        $dummy = $this->getMockBuilder('Heartsentwined\Cron\Service\Cron')
            ->disableOriginalConstructor()
            ->getMock();

        return $dummy;
    }

    public function getJob($status, $scheduleTimestamp)
    {
        $now            = \DateTime::createFromFormat('U', time());
        $scheduleTime   = \DateTime::createFromFormat('U', $scheduleTimestamp);

        $job = new Entity\Job;
        $this->em->persist($job);
        $job
            ->setCode('time')
            ->setStatus($status)
            ->setCreateTime($now)
            ->setScheduleTime($scheduleTime);
        $this->em->flush();

        return $job;
    }
    public function testRun()
    {
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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

    public function testResetPending()
    {
        // inject a job into pending queue
        $this->getJob(Repository\Job::STATUS_PENDING, time());
        $this->assertCount(1, $this->cron->getPending());

        // clear it
        $this->cron->resetPending();

        $refl = new \ReflectionClass($this->cron);
        $pendingProp = $refl->getProperty('pending');
        $pendingProp->setAccessible(true);
        $pending = $pendingProp->getValue($this->cron);
        $this->assertNull($pending);
    }

    public function testProcess()
    {
        $past = time()-100;

        // only past should run
        $job = $this->getJob(Repository\Job::STATUS_PENDING, $past);
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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

        // future should not run
        $job = $this->getJob(Repository\Job::STATUS_PENDING, time()+100);
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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
        $this->assertSame(Repository\Job::STATUS_PENDING, $job->getStatus());
        $this->assertSame(null, $job->getErrorMsg());
        $this->assertSame(null, $job->getStackTrace());
        $this->assertNull($job->getExecuteTime());
        $this->assertNull($job->getFinishTime());

        // cron job throwing exceptions

        $job = $this->getJob(Repository\Job::STATUS_PENDING, $past);
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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

        $job = $this->getJob(Repository\Job::STATUS_PENDING, $past);
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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

        $job = $this->getJob(Repository\Job::STATUS_PENDING, $past);
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
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

    public function testSchedule()
    {
        // reg a 15-min for 1hr
        $this->cron->setScheduleAhead(60);
        $dummy = $this->getDummy();
        $dummy
            ->expects($this->any())
            ->method('run');

        $this->cron->register(
            'time', '*/15 * * * *', array($dummy, 'run'), array());

        // pending job set should be empty before calling schedule
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(0, $pending);

        $this->cron
            ->resetPending()
            ->schedule();
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(4, $pending);

        // re-schedule - nothing should have changed
        $this->cron
            ->resetPending()
            ->schedule();
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(4, $pending);

        // extend reg period for another hour
        $this->cron
            ->setScheduleAhead(120)
            ->resetPending()
            ->schedule();
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(8, $pending);

        // reg another job
        $this->cron->register(
            'time2', '*/30 * * * *', array($dummy, 'run'), array());

        // pending job set should not have changed yet
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(8, $pending);

        // now schedule it - for 2hrs, as per changed
        $this->cron
            ->resetPending()
            ->schedule();
        $pending = $this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getPending();
        $this->assertCount(12, $pending);
    }

    public function testCleanup()
    {
        $cron = $this->getMock(
            'Heartsentwined\Cron\Service\Cron',
            array('recoverRunning', 'cleanLog'));

        $cron
            ->expects($this->once())
            ->method('recoverRunning')
            ->will($this->returnSelf());
        $cron
            ->expects($this->once())
            ->method('cleanLog')
            ->will($this->returnSelf());

        $cron->cleanup();
    }

    public function testRecoverRunning()
    {
        $past = time()-100;
        $future = time()+100;

        $jobPastPending =
            $this->getJob(Repository\Job::STATUS_PENDING, $past);
        $jobPastRunning =
            $this->getJob(Repository\Job::STATUS_RUNNING, $past);
        $jobFuturePending =
            $this->getJob(Repository\Job::STATUS_PENDING, $future);
        $jobFutureRunning =
            $this->getJob(Repository\Job::STATUS_RUNNING, $future);

        $this->getJob(Repository\Job::STATUS_SUCCESS, $past);
        $this->getJob(Repository\Job::STATUS_MISSED, $past);
        $this->getJob(Repository\Job::STATUS_ERROR, $past);
        $this->getJob(Repository\Job::STATUS_SUCCESS, $future);
        $this->getJob(Repository\Job::STATUS_MISSED, $future);
        $this->getJob(Repository\Job::STATUS_ERROR, $future);

        $jobPastRunning
            ->setExecuteTime(\DateTime::createFromFormat('U', $past));
        $jobFutureRunning
            ->setExecuteTime(\DateTime::createFromFormat('U', $future));
        $this->em->flush();

        $this->cron
            ->setMaxRunningTime(0)
            ->setEm($this->em)
            ->recoverRunning();

        $pending = array();
        foreach ($this->cron->getPending() as $job) {
            $pending[] = $job->getId();
        }

        $expected = array(
            $jobPastPending->getId(),
            $jobPastRunning->getId(),
            $jobFuturePending->getId(),
        );
        sort($expected);
        sort($pending);

        $this->assertSame($expected, $pending);
    }

    public function testCleanLog()
    {
        $retain = array();
        $pastTimestamp = time()-100;
        $futureTimestamp = time()+100;
        $past = \DateTime::createFromFormat('U', $pastTimestamp);
        $future = \DateTime::createFromFormat('U', $futureTimestamp);

        // only past + success/missed/error should be removed
        foreach (array(
            $this->getJob(Repository\Job::STATUS_SUCCESS, $pastTimestamp),
            $this->getJob(Repository\Job::STATUS_MISSED, $pastTimestamp),
            $this->getJob(Repository\Job::STATUS_ERROR, $pastTimestamp),
        ) as $job) {
            $job->setExecuteTime($past);
        }
        // retain past + pending/running
        foreach (array(
            $this->getJob(Repository\Job::STATUS_PENDING, $pastTimestamp),
            $this->getJob(Repository\Job::STATUS_RUNNING, $pastTimestamp),
        ) as $job) {
            $job->setExecuteTime($past);
            $retain[] = $job->getId();
        }
        // retain future
        foreach (array(
            $this->getJob(Repository\Job::STATUS_PENDING, $futureTimestamp),
            $this->getJob(Repository\Job::STATUS_RUNNING, $futureTimestamp),
            $this->getJob(Repository\Job::STATUS_SUCCESS, $futureTimestamp),
            $this->getJob(Repository\Job::STATUS_MISSED, $futureTimestamp),
            $this->getJob(Repository\Job::STATUS_ERROR, $futureTimestamp),
        ) as $job) {
            $job->setExecuteTime($future);
            $retain[] = $job->getId();
        }

        $this->em->flush();

        $this->cron
            ->setSuccessLogLifetime(0)
            ->setFailureLogLifetime(0)
            ->setEm($this->em)
            ->cleanLog();

        $jobs = array();
        foreach ($this->em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->findAll() as $job) {
            $jobs[] = $job->getId();
        }

        sort($retain);
        sort($jobs);

        $this->assertSame($retain, $jobs);
    }

    public function testTryLockJob()
    {
        $now = time();

        // 'pending' -> 'running'
        $job = $this->getJob(Repository\Job::STATUS_PENDING, $now);
        $this->assertTrue($this->cron->tryLockJob($job));
        $this->assertSame(
            Repository\Job::STATUS_RUNNING, $job->getStatus());

        // 'running' -> (still 'running') but return false
        $job = $this->getJob(Repository\Job::STATUS_RUNNING, $now);
        $this->assertFalse($this->cron->tryLockJob($job));
        $this->assertSame(
            Repository\Job::STATUS_RUNNING, $job->getStatus());

        // everything else -> retain origin status; return false
        foreach (array(
            $this->getJob(Repository\Job::STATUS_SUCCESS, $now),
            $this->getJob(Repository\Job::STATUS_MISSED, $now),
            $this->getJob(Repository\Job::STATUS_ERROR, $now),
        ) as $job) {
            $prevStatus = $job->getStatus();
            $this->assertFalse($this->cron->tryLockJob($job));
            $this->assertSame(
                $prevStatus, $job->getStatus());
        }
    }
}
