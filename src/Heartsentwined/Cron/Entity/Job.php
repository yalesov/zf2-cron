<?php

namespace Heartsentwined\Cron\Entity;

/**
 * Heartsentwined\Cron\Entity\Job
 */
class Job
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $code
     */
    private $code;

    /**
     * @var string $status
     */
    private $status;

    /**
     * @var string $errorMsg
     */
    private $errorMsg;

    /**
     * @var string $stackTrace
     */
    private $stackTrace;

    /**
     * @var \DateTime $createTime
     */
    private $createTime;

    /**
     * @var \DateTime $scheduleTime
     */
    private $scheduleTime;

    /**
     * @var \DateTime $executeTime
     */
    private $executeTime;

    /**
     * @var \DateTime $finishTime
     */
    private $finishTime;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set code
     *
     * @param  string $code
     * @return Job
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set status
     *
     * @param  string $status
     * @return Job
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set errorMsg
     *
     * @param  string $errorMsg
     * @return Job
     */
    public function setErrorMsg($errorMsg)
    {
        $this->errorMsg = $errorMsg;

        return $this;
    }

    /**
     * Get errorMsg
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Set stackTrace
     *
     * @param  string $stackTrace
     * @return Job
     */
    public function setStackTrace($stackTrace)
    {
        $this->stackTrace = $stackTrace;

        return $this;
    }

    /**
     * Get stackTrace
     *
     * @return string
     */
    public function getStackTrace()
    {
        return $this->stackTrace;
    }

    /**
     * Set createTime
     *
     * @param  \DateTime $createTime
     * @return Job
     */
    public function setCreateTime($createTime)
    {
        $this->createTime = $createTime;

        return $this;
    }

    /**
     * Get createTime
     *
     * @return \DateTime
     */
    public function getCreateTime()
    {
        return $this->createTime;
    }

    /**
     * Set scheduleTime
     *
     * @param  \DateTime $scheduleTime
     * @return Job
     */
    public function setScheduleTime($scheduleTime)
    {
        $this->scheduleTime = $scheduleTime;

        return $this;
    }

    /**
     * Get scheduleTime
     *
     * @return \DateTime
     */
    public function getScheduleTime()
    {
        return $this->scheduleTime;
    }

    /**
     * Set executeTime
     *
     * @param  \DateTime $executeTime
     * @return Job
     */
    public function setExecuteTime($executeTime)
    {
        $this->executeTime = $executeTime;

        return $this;
    }

    /**
     * Get executeTime
     *
     * @return \DateTime
     */
    public function getExecuteTime()
    {
        return $this->executeTime;
    }

    /**
     * Set finishTime
     *
     * @param  \DateTime $finishTime
     * @return Job
     */
    public function setFinishTime($finishTime)
    {
        $this->finishTime = $finishTime;

        return $this;
    }

    /**
     * Get finishTime
     *
     * @return \DateTime
     */
    public function getFinishTime()
    {
        return $this->finishTime;
    }
}
