<?php
/**
 * BatchManager.php
 *
 * This file is part of the Batch Base package. This Class provides an API for Managing
 * Cron Jobs and Batches. Used closely by the BatchHandler.php and the Management Dashboard.
 * Please refer to the Documentation for more information.
 * 
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */
namespace Allproperty;

// Include the BatchJob class
include 'BatchJob.php';

// Remove the defaults
set_time_limit(0);
ini_set("memory_limit","2048M");
ini_set("error_reporting", E_ALL ^ E_NOTICE);

/**
 * @author      John Rocela <johnmark@allproperty.com.sg>
 * @date        June 12, 2012
 */
class BatchManager extends Guru {
    
    /**
   * Run a Job according to jobId
   *
   * @params Integer $jobId the ID of the job needed to run
   */
    public function runJob($jobId)
    {
        // we execute the shell like it's hot.
        if (PHP_OS == 'WINNT') {
            $unexpected_output = shell_exec("C:\cygwin\bin\bash.exe --login  -c 'cd /cygdrive/d/branches/gurubase/lib/core/Allproperty/Batch/;php BatchHandler.php " . $jobId . "'");
        } else {
            $unexpected_output = shell_exec("php /var/www/batches/BatchHandler.php " . $jobId);
        }
        
        // get an updated job object and pass it back
        return $this->getJob($jobId);
    }
    
    /**
   * Get all Batches from the Database
   */
    public function getBatches()
    {
        $batches = $this->db->query("SELECT * FROM batch")->fetchAll();
        return $batches;
    }
    
    /**
   * Get the Batch according to batchId
   *
   * @params Integer $batchId the ID of the batch
   */
    public function getBatch($batchId)
    {
        $batch = $this->db->query("SELECT * FROM batch WHERE id=" . $batchId)->fetchAll();
        if ($batch) {
            return $batch[0];
        }
        return false;
    }
    
    /**
   * Get the Batch Health according to batchId
   *
   * @params Integer $batchId the ID of the batch
   */
    public function getBatchHealth($batchId)
    {
        $batch = $this->getBatch($batchId);
        $status = $this->db->query("SELECT COUNT(id) as count, status_code FROM batch_history WHERE batch_history.batch_job_id=" . $batchId . " GROUP BY status_code")->fetchAll();
        if ($status) {
            foreach ($status as $v => $o) {
                ${strtolower($o[1])} = $o[0];
            }
            if (isset($success) && isset($failed)) {
                // batch isn't that healthy
                return array(
                    'health' => ceil(($success / ($success + $failed)) * 100),
                    'success' => $success,
                    'failed' => $failed
                );
            } else {
                // the batch hasn't run yet
                return array(
                    'health' => 100,
                    'success' => 0,
                    'failed' => 0
                );
            }
        }
        return false;
        
    }
    
    /**
   * Get the Job according to jobId
   *
   * @params Integer $jobId the ID of the job
   */
    public function getJob($jobId)
    {
        $job = $this->db->query("SELECT * FROM batch_job WHERE id=" . $jobId)->fetchAll();
        if ($job) {
            $batch = $this->db->query("SELECT * FROM batch WHERE id=" . $job[0]['batch_id'])->fetchAll();
            $job[0]['batch'] = $batch[0];
            return new BatchJob($job[0]);
        }
        return false;
    }
    
    
    /**
   * Get all Jobs
   */
    public function getJobs($batchId)
    {
        $jobs = array();
        $jobs_of_current_batch = $this->db->query("SELECT * FROM batch_job WHERE batch_id=" . $batchId);
        foreach ($jobs_of_current_batch as $job) {
            $jobs[] = new BatchJob($job);
        }
        return $jobs;
    }
    
    /**
   * Get all running jobs according to $batchId or not
   *
   * @params Integer $batchId the ID of the batch
   */
    public function getRunningJobs($batchId = null)
    {
        $jobs = array();
        
        if ($batchId) {
           $jobs_of_current_batch = $this->db->query("SELECT * FROM batch_job WHERE batch_id='" . $batchId . "' AND status_code='ACTIVE'");
        } else {
            $jobs_of_current_batch = $this->db->query("SELECT * FROM batch_job WHERE status_code='ACTIVE'");
        }
        if ($jobs_of_current_batch) {
            foreach ($jobs_of_current_batch as $job) {
                $jobs[] = new BatchJob($job);
            }
            return $jobs;
        }
        return false;
    }
    
    /**
   * Get history from every job
   */
    public function getHistory()
    {
        $history = $this->db->query("SELECT * FROM batch_history");
        if ($history) {
            return $history;
        }
        return false;
    }
    
    /**
   * Get logs from every job
   */
    public function getLogs()
    {
        $logs = $this->db->query("SELECT * FROM batch_log");
        if ($logs) {
            return $logs;
        }
        return false;
    }
    
    /**
   * Get all running jobs today
   */
    public function getRunningJobsToday()
    {
        $jobs_today = $this->db->query("SELECT * FROM batch_job");
        $jobs = array();
        $i = 0;
        foreach ($jobs_today as $job) {
            $job = $this->getJob($job['id']);
            $next_job = $job->getSchedule()->getNextRunDate()->getTimeStamp();
            $tomorrow = strtotime('+1 day', strtotime(date('F j, Y') . ' 00:00:00'));
            if ($next_job < $tomorrow) {
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    public function createJob($fields = array())
    {
        $field = array_map('mysql_real_escape_string', $fields);
        return new BatchJob($fields);
    }
    
    public function createBatch($fields = array())
    {
        $field = array_map('mysql_real_escape_string', $fields);
        $this->db->exec("INSERT INTO batch (name, description, path, status_code) VALUES('" . $field['name'] . "', '" . $field['description'] . "', '" . $field['path'] . "', 'ENABLED')");
        return $this->getBatch($this->db->lastInsertId());
    }
    
    public function updateJob($id, $fields = array())
    {
    
        return $this->getJob($id);
    }
    
    public function updateBatch($id, $fields = array())
    {
    
        return $this->getBatch($id);
    }
    
    public function removeJob($id)
    {
        $job = $this->getJob($id);
        if ($job) {
            return $job->remove();
        }
        return false;
    }
    
    public function removeBatch($id)
    {
    
        return $status;
    }
    
}

//~ EOF