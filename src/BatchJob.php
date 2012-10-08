<?php
/**
 * BatchJob.php
 *
 * This file is part of the Batch Base package. This Class provides an API for Handling
 * Cron Jobs through the Manager. Running Batch Jobs are logged from this class.
 * Please refer to the Documentation for more information.
 * 
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */
namespace Allproperty;

// Severity Levels
define('SEVERITY_SOFT' , 0);
define('SEVERITY_HARD' , 1);

/** 
 * https://github.com/mtdowling/cron-expression
 */
require_once 'cron.phar';

/**
 * @author      John Rocela <johnmark@allproperty.com.sg>
 * @date        June 12, 2012
 */
class BatchJob {
    
    /**
   * the output to be returned by output()
   */
    private $__OUTPUT;
    
    /**
   * an array of errors captured
   */
    private $__ERRORS = array();
    
    /**
   * The Batch info for the job
   *
   * @access Public
   */
    public $batch;

    /**
   * The Context of the Batch Job 
   *
   * @access Protected
   */
    protected $context;

    /**
   * The ID of the Batch Job 
   *
   * @access Protected
   */
    protected $id;
    
    /**
   * A descriptive identifier for the current Job
   *
   * @access Protected
   */
    protected $name;
    
    /**
   * Schedule of the current Job
   *
   * @access Protected
   */
    protected $schedule;
    
    /**
   * DateTime string of the next time this job is supposed to run
   *
   * @access Protected
   */
    protected $next_run;
    
    /**
   * A string of Parameters for the Job
   *
   * @access Protected
   */
    protected $params;
    
    /**
   * DateTime string of the last successful run this job had
   *
   * @access Protected
   */
    protected $last_success;
    
    /**
   * DateTime string of the last failure this job had
   *
   * @access Protected
   */
    protected $last_failed;
    
    /**
   * The number of seconds in float the last job ran for
   *
   * @access Protected
   */
    protected $last_duration;
    
    /**
   * Status code whether job is Active or Idle
   *
   * @access Protected
   */
    protected $status_code;
    
    /**
   * Locale identifier
   *
   * @access Protected
   */
    protected $locale_code;
    
    /**
   * The PID of the Job while it is running
   *
   * @access Protected
   */
    protected $PID;
    
    /**
   * Whether a job is exclusive or not
   *
   * @access Protected
   */
    protected $exclusive;

    /**
   * The Controller Construct
   *
   * loads configurations and do base initializations
   * for the batch file
   *
   * @params array $job the job object from a DB query
   */
    public function __construct(Array $job = null)
    {
        // Temporary Database connection
        $this->db = new \PDO('mysql:host=localhost;dbname=propertydb', 'root', '', array(
            \PDO::ATTR_PERSISTENT => true
        ));
        $this->db->exec("SET CHARACTER SET utf8");
        
        // If the job array was passed
        if ($job) {
            // This creates a new job as well. can you believe it?
            if (!isset($job['id'])) {
                $job = $this->create($job);
            }
            
            // we iterate over all the job values and attach them to
            // the protected class properties
            foreach ($job as $property => $value) {
                $this->{$property} = $value;
            }
        }
        
    }
    
    /**
   * Creates a new Job attached to a Batch record
   *
   * @params array $job the job object from a DB query
   */
    public function create($job = array())
    {
        // Boring DB queries
        $job = array_map('mysql_real_escape_string', $job);
        $this->db->exec("INSERT INTO batch_job (batch_id, `name`, `schedule`, locale_code, params, status_code, exclusive) VALUES('" . $job['batch_id'] . "', '" . $job['name'] . "', '" . $job['schedule'] . "', '" . $job['locale_code'] . "', '" . $job['params'] . "', 'IDLE', '" . $job['exclusive'] . "')");
        
        // Pass the Job array back so the object can use it
        $job['status_code'] = 'IDLE';
        $job['id'] = $this->db->lastInsertId();
        return $job;
    }
    
    /**
   * Removes the Job from the Database
   */
    public function remove()
    {   
        $this->db->exec("DELETE FROM batch_job WHERE id=" . $this->id);
        return true;
    }

    /**
   * Record the start of this Job
   *
   * @access Private
   */
    private function start()
    {
        // we record when the batch started running
        $this->__START = microtime(true);
        
        // get the next run date
        $next_run = $this->getSchedule()->getNextRunDate()->format('Y-m-d H:i:s');
        
        // do some boring db stuff
        $this->db->query("UPDATE batch_job SET status_code='ACTIVE', last_success=NOW(), next_run='" . $next_run . "' WHERE id='" . $this->getId() . "'");
        
        // chop that log down
        $this->log("[START] Batch[" . $this->getId() . "] was started at [" . date('F j, Y H:iA') . "]");
    }
    
    /**
   * Record the end of this Job
   *
   * @access Private
   */
    private function stop()
    {
        // we stop recording when the batch stopped running and get the duration
        $this->__STOP = microtime(true);
        $duration = $this->__STOP - $this->__START;
        
        // do some boring db stuff
        $this->db->query("UPDATE batch_job SET status_code='IDLE', PID=0, last_duration=$duration WHERE id='" . $this->getId() . "'");
        
        // chop that log down
        $this->log("[END] Batch[" . $this->getId() . "] ran for [" . number_format($duration, 2) . "] seconds and has finished on [" . date('F j, Y H:iA') . "].");

        // hey, did we fail?
        $status_code = (isset($this->__ERRORS['__WITH_ERRORS'])) ? 'FAILED': 'SUCCESS';
        
        // we make a record of it in the history
        $this->db->query("INSERT INTO batch_history (batch_job_id, date, output, duration, status_code) VALUES(" . $this->getId() . ",NOW(),'" . $this->__OUTPUT . "', '" . $duration . "', '" . $status_code . "')");
        
        // oh no, we failed? then we mark it on the database
        if ($status_code == 'FAILED') {
            $this->db->query("UPDATE batch_job SET last_failed=NOW() WHERE id=" . $this->getId());
        }
    }
    
    /**
   * Method to handle outputs or console generated content
   */
    public function output($output)
    {
        return $this->__OUTPUT = mysql_real_escape_string($output); // Blah!
    }

    /**
   * a function to log errors and mark it appropriately
   */
    public function error($error = null, $code = null, $severity = SEVERITY_SOFT) 
    {
        if ($code) {
            if (@$this->__ERRORS[$code]) $this->__ERRORS[$code] = $error;
        } else {
            $this->__ERRORS[] = $error;
        }
        if ($severity == SEVERITY_HARD) {
            $this->__ERRORS['__WITH_ERRORS'] = true;
        }
        $this->log('[ERROR][' . $severity . '] ' . $error);
    }

    /**
   * Get errors from the collection
   */
    public function getErrors()
    {
        return $this->__ERRORS;
    }

    /**
   * Run the Job's class and invoke the start and stop event
   */
    public function run()
    {
        // let's see if we are allowed to run this.
        if ($this->isExclusivelyRunning()) {
            $this->log("[FAILED] Batch[" . $this->getId() . "] is an exclusive process. A process with PID[" . $this->getPID() . "] is still running.");
            return false;
        }
        
        // Set Context
        $this->setContext(strtoupper(substr(strrchr($this->batch['path'], '.'), 1)));
        
        // mark the batch as active and record PID
        $this->start();
        
        // Switchy Switch
        switch ($this->getContext()) {
            case "PHP":
                // get the PID of this process
                $this->setPID(getmypid());
        
                // Include the Actual file provided
                require_once $this->batch['path'];
                $className = $this->batch['name'];
                
                // If that class exists, then we run it. else, it
                // must've already ran because we included the file.
                if (class_exists($className)) {
                    $batch = new $className();
                    $batch->run($this);
                }
            break;
            case "SH":                
                // PID Placeholder. BatchShell will take care of this.
                $this->PID = 0;
                
                // I am unsure if this will stay alive up until the script finishes
                $this->output(shell_exec('sh ' . $this->batch['path'] . ' ' . $this->getId() . ' ' . $this->getParams())); 
                
                // See the output whether the job failed or not
                if (strpos($this->__OUTPUT, '[FAILED]')) {
                    $this->__OUTPUT = str_replace('[FAILED]', '', $this->__OUTPUT);
                    $this->__ERRORS['__WITH_ERRORS'] = true;
                }
            break;
        }
        
        // mark the batch as IDLE and clear PID
        $this->stop();
    }
    
    /**
   * Set the Context for this Job
   */
    public function setContext($context = 'PHP')
    {
        return $this->context = $context;
    }
    
    /**
   * Get the Context of this Job
   */
    public function getContext()
    {
        return $this->context;
    }
    
    /**
   * Get the Job's ID
   */
    public function getId()
    {
        return $this->id;
    }
    
    /**
   * Get the Job's Process ID. Usually returned by the script itself.
   */
    public function getPID()
    {
        return $this->PID;
    }
    
    /**
   * Set's the Job's Process ID. Usually used by BatchShell.php
   */
    public function setPID($PID)
    {
        $this->db->query("UPDATE batch_job SET PID='" . $PID . "' WHERE id='" . $this->getId() . "'");
        return $this->PID = $PID;
    }
    
    /**
   * Get the Job's Descriptive Identifier
   */
    public function getName()
    {
        return $this->name;
    }
    
    /**
   * Get the Job's Parameters
   */
    public function getParams()
    {
        return $this->params;
    }
    
    /**
   * Get the Job's Status whether it is Active or Idle
   */
    public function getStatusCode()
    {
        return $this->status_code;
    }
    
    /**
   * An alias to self::getStatusCode()
   */
    public function getStatus()
    {
        return $this->getStatusCode();
    }
    
    /**
   * Get the Job's Locale Identifier
   */
    public function getLocaleCode()
    {
        return $this->locale_code;
    }
    
    /**
   * Get the Job's Exclusivity status
   */
    public function isExclusive()
    {
        return ($this->exclusive == 1) ? true: false;
    }
    
    /**
   * Get the Job's Running status
   */
    public function isRunning()
    {
        return ($this->status_code == 'ACTIVE') ? true: false;
    }
    
    /**
   * Get the Job's status whether it's running and if it is exclusive
   */
    public function isExclusivelyRunning()
    {
        if ($this->isRunning() && $this->isExclusive()) {
            return true;
        }
        return false;
    }
    
    /**
   * Get the Job's schedule in Cron String format
   */
    public function getRawSchedule()
    {
        return $this->schedule;
    } 
    
    /**
   * Get the Job's Parsed Schedule using cron.phar
   */
    public function getSchedule()
    {
        $schedule = $this->_fixCronSyntax($this->getRawSchedule());
        $cron = \Cron\CronExpression::factory($schedule);
        return $cron;
    } 
    
    /**
   * Determine whether we should run this or not
   */
    public function isDue()
    {
        return $this->getSchedule()->isDue();
    } 
    
    /**
   * Return the Next Run Date from Schedule
   */
    public function getNextRunDate()
    {
        return $this->getSchedule()->getNextRunDate();
    }
    
    /**
   * Get the Job's Next run DateTime
   */
    public function getNextRun()
    {
        if (!$this->next_run) {
            $this->next_run = $this->getSchedule()->getNextRunDate()->format('F-m-Y H:i:s');
        }
        return $this->next_run;
    }
    
    /**
   * Get the Job's DateTime where it last successfully finished
   */
    public function getLastSuccess()
    {
        return $this->last_success;
    }
    
    /**
   * Get the Job's DateTime where it last failed
   */
    public function getLastFailed()
    {
        return $this->last_failed;
    }
    
    /**
   * Get the Job's Last Duration in seconds
   */
    public function getLastDuration()
    {
        return $this->last_duration;
    }
    
    /**
   * Get the Job's Last Run Status
   */
    public function getLastRunStatus()
    {
        $status = $this->db->query("SELECT status_code FROM batch_history WHERE batch_job_id=" . $this->getId() . " ORDER BY id DESC LIMIT 1");
        if ($status = $status->fetchAll()) {
            return ($status[0]['status_code'] == 'SUCCESS') ? 'SUCCESS': 'FAILED';
        }
        return 'FAILED';
    }
    
    /**
   * Get the Job's History
   */
    public function getHistory()
    {
        $history = $this->db->query("SELECT * FROM batch_history WHERE batch_id=" . $this->getId());
        if ($history) {
            return $history;
        }
        return false;
    }
    
    /**
   * Get the Job's Logs
   */
    public function getLogs($from = null, $to = null)
    {
        if ($from || $to) {
            if ($from && !$to) {
                $logs = $this->db->query("SELECT * FROM batch_log WHERE date>=$from AND batch_id=" . $this->getId());
            } else if ($from && $to) {
                $logs = $this->db->query("SELECT * FROM batch_log WHERE date>=$from AND date<=$to AND batch_id=" . $this->getId());
            }
        } else {
            $logs = $this->db->query("SELECT * FROM batch_log WHERE batch_id=" . $this->getId());
        }
        if ($logs) {
            return $logs;
        }
        return false;
    }
    
    /**
   * Fix a Cron string syntax. Some cron string might
   * miss a couple of values.
   *
   * @params String $schedule the Cron time string
   * @access Protected
   */
    protected function _fixCronSyntax($schedule)
    {
        $schedule = explode(' ', $schedule);
        $count = count($schedule);
        $s = ($count < 5) ? 5 - $count: 0;
        return implode(' ', $schedule) . str_repeat(' *', $s);
    }
    
    /**
   * a static method that makes logging convenient in the system context
   */
    public function log($message = null)
    {
        // write to database, but we don't have that yet. so :(
        $this->db->query("INSERT INTO batch_log (batch_id, message, date) VALUES(" . $this->getId() . ", '" . mysql_real_escape_string($message) . "', NOW())");
    }

}