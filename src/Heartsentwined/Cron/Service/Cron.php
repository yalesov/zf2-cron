<?php
namespace Heartsentwined\Cron\Service;

use Heartsentwined\Cron\Entity;
use Heartsentwined\Cron\Exception;
use Heartsentwined\Cron\Repository;
use Heartsentwined\Cron\Service\Registry;
use Heartsentwined\CronExprParser\Parser;
use Doctrine\ORM\EntityManager;

/**
 * main Cron class
 *
 * handle cron job registration, validation, scheduling, running, and cleanup
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class Cron
{
    /**
     * how long ahead to schedule cron jobs
     *
     * @var int (minute)
     */
    protected $scheduleAhead = 60;
    public function setScheduleAhead($scheduleAhead)
    {
        $this->scheduleAhead = $scheduleAhead;

        return $this;
    }
    public function getScheduleAhead()
    {
        return $this->scheduleAhead;
    }

    /**
     * how long before a scheduled job is considered missed
     *
     * @var int (minute)
     */
    protected $scheduleLifetime = 60;
    public function setScheduleLifetime($scheduleLifetime)
    {
        $this->scheduleLifetime = $scheduleLifetime;

        return $this;
    }
    public function getScheduleLifeTime()
    {
        return $this->scheduleLifeTime;
    }

    /**
     * maximum running time of each cron job
     *
     * @var int (minute)
     */
    protected $maxRunningTime = 60;
    public function setMaxRunningTime($maxRunningTime)
    {
        $this->maxRunningTime = $maxRunningTime;

        return $this;
    }
    public function getMaxRunningtime()
    {
        return $this->maxRunningTime;
    }

    /**
     * how long to keep successfully completed cron job logs
     *
     * @var int (minute)
     */
    protected $successLogLifetime = 300;
    public function setSuccessLogLifetime($successLogLifetime)
    {
        $this->successLogLifetime = $successLogLifetime;

        return $this;
    }
    public function getSuccessLogLifetime()
    {
        return $this->successLogLifetime;
    }

    /**
     * how long to keep failed (missed / error) cron job logs
     *
     * @var int (minute)
     */
    protected $failureLogLifetime = 10080;
    public function setFailureLogLifetime($failureLogLifetime)
    {
        $this->failureLogLifetime = $failureLogLifetime;

        return $this;
    }
    public function getFailureLogLifetime()
    {
        return $this->failureLogLifetime;
    }

    /**
     * the Doctrine ORM Entity Manager
     *
     * @var EntityManager
     */
    protected $em;
    public function setEm(EntityManager $em)
    {
        $this->em = $em;

        return $this;
    }
    public function getEm()
    {
        return $this->em;
    }

    /**
     * set of pending cron jobs
     *
     * wrapped the Repo function here to implement a (crude) cache feature
     *
     * @var array of Entity\Job
     */
    protected $pending;
    public function getPending()
    {
        if (!$this->pending) {
            $this->pending = $this->getEm()
                ->getRepository('Heartsentwined\Cron\Entity\Job')
                ->getPending();
        }

        return $this->pending;
    }
    public function resetPending()
    {
        $this->pending = null;

        return $this;
    }

    /**
     * main entry function
     *
     * 1. schedule new cron jobs
     * 2. process cron jobs
     * 3. cleanup old logs
     *
     * @return self
     */
    public function run()
    {
        $this
            ->schedule()
            ->process()
            ->cleanup();

        return $this;
    }

    /**
     * run cron jobs
     *
     * @return self
     */
    public function process()
    {
        $em = $this->getEm();
        $cronRegistry = Registry::getCronRegistry();
        $pending = $this->getPending();
        $scheduleLifetime = $this->scheduleLifetime * 60; //convert min to sec

        $now = new \DateTime;
        foreach ($pending as $job) {
            $scheduleTime = $job->getScheduleTime();

            if ($scheduleTime > $now) {
                continue;
            }

            try {
                $errorStatus = Repository\Job::STATUS_ERROR;

                $missedTime = clone $now;
                $timestamp = $missedTime->getTimestamp();
                $timestamp -= $scheduleLifetime;
                $missedTime->setTimestamp($timestamp);

                if ($scheduleTime < $missedTime) {
                    $errorStatus = Repository\Job::STATUS_MISSED;
                    throw new Exception\RuntimeException(
                        'too late for job'
                    );
                }

                $code = $job->getCode();

                if (!isset($cronRegistry[$code])) {
                    throw new Exception\RuntimeException(sprintf(
                        'job "%s" undefined in cron registry',
                        $code
                    ));
                }

                if (!$this->tryLockJob($job)) {
                    //another cron started this job intermittently. skip.
                    continue;
                }

                //run job now
                $callback = $cronRegistry[$code]['callback'];
                $args = $cronRegistry[$code]['args'];

                $job->setExecuteTime(new \DateTime);
                $em->persist($job);
                $em->flush();

                call_user_func_array($callback, $args);

                $job
                    ->setStatus(Repository\Job::STATUS_SUCCESS)
                    ->setFinishTime(new \DateTime);

            } catch (\Exception $e) {
                $job
                    ->setStatus($errorStatus)
                    ->setErrorMsg($e->getMessage())
                    ->setStackTrace($e->getTraceAsString());
            }

            $em->persist($job);
            $em->flush();
        }

        return $this;
    }

    /**
     * schedule cron jobs
     *
     * @return self
     */
    public function schedule()
    {
        $em = $this->getEm();
        $pending = $this->getPending();
        $exists = array();
        foreach ($pending as $job) {
            $identifier = $job->getCode();
            $identifier .= $job->getScheduleTime()->getTimeStamp();
            $exists[$identifier] = true;
        }

        $scheduleAhead = $this->getScheduleAhead() * 60;

        $cronRegistry = Registry::getCronRegistry();
        foreach ($cronRegistry as $code => $item) {
            $now = time();
            $timeAhead = $now + $scheduleAhead;

            for ($time = $now; $time < $timeAhead; $time += 60) {
                $scheduleTime = new \DateTime();
                $scheduleTime->setTimestamp($time);
                $scheduleTime->setTime(
                    $scheduleTime->format('H'),
                    $scheduleTime->format('i')
                );
                $scheduleTimestamp = $scheduleTime->getTimestamp();

                $identifier = $code . $scheduleTimestamp;
                if (isset($exists[$identifier])) {
                    //already scheduled
                    continue;
                }

                $job = new Entity\Job;
                if (Parser::matchTime(
                    $scheduleTimestamp, $item['frequency'])) {
                    $job
                        ->setCode($code)
                        ->setStatus(Repository\Job::STATUS_PENDING)
                        ->setCreateTime(new \DateTime)
                        ->setScheduleTime($scheduleTime);
                    $em->persist($job);
                    $exists[$identifier] = true;
                }
            }
        }

        $em->flush();

        return $this;
    }

    /**
     * perform various cleanup work
     *
     * @return self
     */
    public function cleanup()
    {
        $this
            ->recoverRunning()
            ->cleanLog();

        return $this;
    }

    public function recoverRunning()
    {
        $em = $this->getEm();
        $running = $em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getRunning();
        $expiryTime = time() - $this->getMaxRunningTime() * 60;

        foreach ($running as $job) {
            if ($job->getExecuteTime()
                && $job->getExecuteTime()->getTimestamp() < $expiryTime) {
                $job
                    ->setStatus(Repository\Job::STATUS_PENDING)
                    ->setErrorMsg(null)
                    ->setStackTrace(null)
                    ->setScheduleTime(new \DateTime)
                    ->setExecuteTime(null);
            }
        }

        $this->getEm()->flush();

        return $this;
    }

    /**
     * delete old cron job logs
     *
     * @return self
     */
    public function cleanLog()
    {
        $em = $this->getEm();
        $lifetime = array(
            Repository\Job::STATUS_SUCCESS  =>
                $this->getSuccessLogLifetime() * 60,
            Repository\Job::STATUS_MISSED   =>
                $this->getFailureLogLifetime() * 60,
            Repository\Job::STATUS_ERROR    =>
                $this->getFailureLogLifetime() * 60,
        );

        $history = $em->getRepository('Heartsentwined\Cron\Entity\Job')
            ->getHistory();

        $now = time();
        foreach ($history as $job) {
            if ($job->getExecuteTime()
                && $job->getExecuteTime()->getTimestamp()
                    < $now - $lifetime[$job->getStatus()]) {
                $em->remove($job);
            }
        }

        $em->flush();

        return $this;
    }

    /**
     * wrapper function
     * @see Registry::register()
     */
    public static function register(
        $code, $frequency, $callback, array $args = array())
    {
        Registry::register($code, $frequency, $callback, $args);
    }

    /**
     * try to acquire a lock on a cron job
     *
     * set a job to 'running' only if it is currently 'pending'
     *
     * @param  Entity\Job $job
     * @return bool
     */
    public function tryLockJob(Entity\Job $job)
    {
        $em = $this->getEm();
        $repo = $em->getRepository('Heartsentwined\Cron\Entity\Job');
        if ($job->getStatus() === Repository\Job::STATUS_PENDING) {
            $job->setStatus(Repository\Job::STATUS_RUNNING);
            $em->persist($job);
            $em->flush();

            // flush() succeeded if reached here;
            // otherwise an Exception would have been thrown
            return true;
        }

        return false;
    }
}
