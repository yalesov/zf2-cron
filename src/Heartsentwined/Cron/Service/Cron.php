<?php
namespace Heartsentwined\Cron\Service;

use Heartsentwined\ArgValidator\ArgValidator;
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
    protected $scheduleAhead;
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
    protected $scheduleLifetime;
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
    protected $maxRunningTime;
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
    protected $successLogLifetime;
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
    protected $failureLogLifetime;
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
     * @var array of Entity\Job
     */
    protected $pendings;
    public function getPendings()
    {
        if (!$this->pendings) {
            $repo = $this->getEm()->getRepository('Cron\Entity\Job');
            $this->pendings = $repo->findBy(
                array('status' => Repository\Job::STATUS_PENDING));
        }
        return $this->pendings;
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
        $pendings = $this->getPendings();
        $scheduleLifetime = $this->scheduleLifetime * 60; //convert min to sec

        $now = new \DateTime;
        foreach ($pendings as $job) {
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
                    ->setStatus('success')
                    ->setFinishTime(new \DateTime);

            } catch (\Exception $e) {
                $job
                    ->setStatus($errorStatus)
                    ->setOutput($e->getMessage() . "\n"
                        . $e->getTraceAsString());
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
        $pendings = $this->getPendings();
        $exists = array();
        foreach ($pendings as $job) {
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
                if (!empty($exists[$identifier])) {
                    //already scheduled
                    continue;
                }

                $job = new Entity\Job;
                if (Parser::matchTime(
                    $scheduleTimestamp, $item['frequency'])) {
                    $job
                        ->setCode($code)
                        ->setStatus('pending')
                        ->setCreateTime(new \DateTime)
                        ->setScheduleTime($scheduleTime);
                    $em->persist($job);
                }
            }
        }

        $em->flush();

        return $this;
    }

    /**
     * delete old cron job logs; recover long-running cron jobs
     *
     * @return self
     */
    public function cleanup()
    {
        $em = $this->getEm();
        $repo = $em->getRepository('Cron\Entity\Job');
        $now = time();

        // remove old history
        $history = $repo->getHistory();

        $lifetime = array(
            'success'   => $this->getSuccessLogLifetime() * 60,
            'missed'    => $this->getFailureLogLifetime() * 60,
            'error'     => $this->getFailureLogLifetime() * 60,
        );

        foreach ($history as $job) {
            if ($executeTime = $job->getExecuteTime()) {
                if ($executeTime->getTimestamp()
                    < $now - $lifetime[$job->getStatus()]) {
                    $em->remove($job);
                }
            }
        }

        // recover jobs running for too long
        $running = $repo->getRunning();
        foreach ($running as $job) {
            if ($job->getExecuteTime()->getTimestamp()
                < $now - $this->getMaxRunningTime() * 60) {
                $job
                    ->setStatus('pending')
                    ->setOutput(null)
                    ->setScheduleTime(new \DateTime)
                    ->setExecuteTime(null);
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
     * @param Entity\Job $job
     * @return bool
     */
    public function tryLockJob(Entity\Job $job)
    {
        $em = $this->getEm();
        $repo = $em->getRepository('Cron\Entity\Job');
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
