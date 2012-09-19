<?php
namespace Heartsentwined\Cron\Service;

use Heartsentwined\ArgValidator\ArgValidator;
use Heartsentwined\Cron\Entity;
use Heartsentwined\Cron\Exception;
use Heartsentwined\Cron\Repository;
use Heartsentwined\Cron\Service\Registry;
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
                if ($this->trySchedule(
                    $job, $item['frequency'], $scheduleTimestamp)) {
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
     * schedule a cron job
     *
     * @param Entity\Job    $job
     * @param string        $frequency
     *      any valid cron expression, in addition supporting:
     *      range: '0-5'
     *      range + interval: '10-59/5'
     *      comma-separated combinations of these: '1,4,7,10-20'
     *      English months: 'january'
     *      English months (abbreviated to three letters): 'jan'
     *      English weekdays: 'monday'
     *      English weekdays (abbreviated to three letters): 'mon'
     *      These text counterparts can be used in all places where their
     *          numerical counterparts are allowed, e.g. 'jan-jun/2'
     *      A full example:
     *          '0-5,10-59/5 * 2-10,15-25 january-june/2 mon-fri' -
     *          every minute between minute 0-5 + every 5th min between 10-59
     *          every hour
     *          every day between day 2-10 and day 15-25
     *          every 2nd month between January-June
     *          Monday-Friday
     * @param string|int    $time
     *      timestamp or strtotime()-compatible string
     * @throws Exception\InvalidArgumentException on invalid cron expression
     * @return bool
     */
    public function trySchedule(Entity\Job $job, $frequency, $time)
    {
        ArgValidator::assert($frequency, 'string');
        ArgValidator::assert($time, array('string', 'int'));

        $cronExpr = preg_split('/\s+/', $frequency, null, PREG_SPLIT_NO_EMPTY);
        if (count($cronExpr) !== 5) {
            throw new Exception\InvalidArgumentException(sprintf(
                 'cron expression should have exactly 5 arguments, "%s" given'
            ));
        }

        if (is_string($time)) $time = $strtotime($time);

        $date = getdate($time);

        $match = $this->matchCronExpr($cronExpr[0], $date['minutes'])
                 && $this->matchCronExpr($cronExpr[1], $date['hours'])
                 && $this->matchCronExpr($cronExpr[2], $date['mday'])
                 && $this->matchCronExpr($cronExpr[3], $date['mon'])
                 && $this->matchCronExpr($cronExpr[4], $date['wday']);

        if ($match) {
            $job->setCreateTime(new \DateTime);

            $dateTime = new \DateTime();
            $dateTime->setTimestamp($time);
            $dateTime->setTime($dateTime->format('H'), $dateTime->format('i'));
            $job->setScheduleTime($dateTime);

            return true;
        }

        return false;
    }

    /**
     * match a cron expression component to a given corresponding date/time
     *
     * In the expression, * * * * *, each component
     *      *[1] *[2] *[3] *[4] *[5]
     * will correspond to a getdate() component
     * 1. $date['minutes']
     * 2. $date['hours']
     * 3. $date['mday']
     * 4. $date['mon']
     * 5. $date['wday']
     *
     * @see self::exprToNumeric() for additional valid string values
     *
     * @param string $expr
     * @param numeric $num
     * @throws Exception\InvalidArgumentException on invalid expression
     * @return bool
     */
    public function matchCronExpr($expr, $num)
    {
        ArgValidator::assert($expr, 'string');
        ArgValidator::assert($num, 'numeric');

        //handle all match
        if ($expr === '*') {
            return true;
        }

        //handle multiple options
        if (strpos($expr, ',') !== false) {
            $args = explode(',', $expr);
            foreach ($args as $arg) {
                if ($this->matchCronExpr($arg, $num)) {
                    return true;
                }
            }
            return false;
        }

        //handle modulus
        if (strpos($expr, '/') !== false) {
            $arg = explode('/', $expr);
            if (count($arg) !== 2) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'invalid cron expression component: '
                        . 'expecting match/modulus, "%s" given',
                    $expr
                ));
            }
            if (!is_numeric($arg[1])) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'invalid cron expression component: '
                        . 'expecting numeric modulus, "%s" given',
                    $expr
                ));
            }

            $expr = $arg[0];
            $mod = $arg[1];
        } else {
            $mod = 1;
        }

        //handle all match by modulus
        if ($expr === '*') {
            $from = 0;
            $to   = 60;
        }
        //handle range
        elseif (strpos($expr, '-') !== false) {
            $arg = explode('-', $expr);
            if (count($arg) !== 2) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'invalid cron expression component: '
                        . 'expecting from-to structure, "%s" given',
                    $expr
                ));
            }
            $from = $this->exprToNumeric($arg[0]);
            $to = $this->exprToNumeric($arg[1]);
        }
        //handle regular token
        else {
            $from = $this->exprToNumeric($expr);
            $to = $from;
        }

        if ($from === false || $to === false) {
            throw new Exception\InvalidArgumentException(sprintf(
                'invalid cron expression component: '
                    . 'expecting numeric or valid string, "%s" given',
                $expr
            ));
        }

        return ($num >= $from) && ($num <= $to) && ($num % $mod === 0);
    }

    /**
     * parse a string month / weekday expression to its numeric equivalent
     *
     * @param string|int $value
     * @return int|false
     */
    public function exprToNumeric($value)
    {
        ArgValidator::assert($value, array('string', 'int'));

        static $data = array(
            'jan'   => 1,
            'feb'   => 2,
            'mar'   => 3,
            'apr'   => 4,
            'may'   => 5,
            'jun'   => 6,
            'jul'   => 7,
            'aug'   => 8,
            'sep'   => 9,
            'oct'   => 10,
            'nov'   => 11,
            'dec'   => 12,

            'sun'   => 0,
            'mon'   => 1,
            'tue'   => 2,
            'wed'   => 3,
            'thu'   => 4,
            'fri'   => 5,
            'sat'   => 6,
        );

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(substr($value, 0, 3));
            if (isset($data[$value])) {
                return $data[$value];
            }
        }

        return false;
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
