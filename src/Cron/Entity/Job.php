<?php

namespace Cron\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Cron\Entity\Job
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
     * @var string $error
     */
    private $error;

    /**
     * @var \DateTime $create_time
     */
    private $create_time;

    /**
     * @var \DateTime $schedule_time
     */
    private $schedule_time;

    /**
     * @var \DateTime $execute_time
     */
    private $execute_time;

    /**
     * @var \DateTime $finish_time
     */
    private $finish_time;


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
     * @param string $code
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
     * @param string $status
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
     * Set error
     *
     * @param string $error
     * @return Job
     */
    public function setError($error)
    {
        $this->error = $error;
    
        return $this;
    }

    /**
     * Get error
     *
     * @return string 
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set create_time
     *
     * @param \DateTime $createTime
     * @return Job
     */
    public function setCreateTime($createTime)
    {
        $this->create_time = $createTime;
    
        return $this;
    }

    /**
     * Get create_time
     *
     * @return \DateTime 
     */
    public function getCreateTime()
    {
        return $this->create_time;
    }

    /**
     * Set schedule_time
     *
     * @param \DateTime $scheduleTime
     * @return Job
     */
    public function setScheduleTime($scheduleTime)
    {
        $this->schedule_time = $scheduleTime;
    
        return $this;
    }

    /**
     * Get schedule_time
     *
     * @return \DateTime 
     */
    public function getScheduleTime()
    {
        return $this->schedule_time;
    }

    /**
     * Set execute_time
     *
     * @param \DateTime $executeTime
     * @return Job
     */
    public function setExecuteTime($executeTime)
    {
        $this->execute_time = $executeTime;
    
        return $this;
    }

    /**
     * Get execute_time
     *
     * @return \DateTime 
     */
    public function getExecuteTime()
    {
        return $this->execute_time;
    }

    /**
     * Set finish_time
     *
     * @param \DateTime $finishTime
     * @return Job
     */
    public function setFinishTime($finishTime)
    {
        $this->finish_time = $finishTime;
    
        return $this;
    }

    /**
     * Get finish_time
     *
     * @return \DateTime 
     */
    public function getFinishTime()
    {
        return $this->finish_time;
    }
}
