<?php
/**
 * BatchBase.php
 *
 * This file is part of the Batch Base package. This Class provides an API for Cron
 * execution, status and logging. Please refer to the Documentation for more information.
 *
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */
namespace Guru;

// remove the defaults
set_time_limit(0);
ini_set("memory_limit","2048M");
ini_set("error_reporting", E_ALL ^ E_NOTICE);

// we include the interface a Batch should use
require_once "BatchInterface.php";

/**
 * @author      John Rocela <johnmark@allproperty.com.sg>
 * @date        June 12, 2012
 */
class BatchBase extends Guru {

    /**
   * the output to be returned by output()
   */
    private $output;

    /**
   * the BatchJob object of the current batch that needs to run
   */
    protected $job;

    /**
   * The Controller Construct
   *
   * loads configurations and do base initializations
   * for the batch file
   */
    public function __construct()
    {
        // we load the parent contruct and load what we need
        parent::__construct();

        // we start the object buffer
        ob_start();
    }

    /**
   * The Controller Destruct
   */
    public function __destruct()
    {
        // we clean up the object buffer
        ob_end_clean();
    }

    /**
   * The run logic of the Batch files
   * @access    Protected
   */
    protected function _run()
    {
        // we start running the batch
        $this->__start();

        // let's run the code in a try-ca;tch block so we can monitor exceptions
        try {
            // we run the action method on the child class
            $this->action();

            // we succeeded and didn't run into any exceptions or errors
            $this->success();
        } catch (GuruException $e) {
            // oops, we had an exception thrown. add them to the error array
            $this->error("Exception [" . $e->getMessage() . "] was thrown on Batch [" . $this->job->getId() . "]  at [" . date('F j, Y H:iA') . "] with PID[" . $this->job->getPID() . "].", null, SEVERITY_HARD);

            // we failed so we run a fallback method
            $this->fail($this->job->getErrors());
        }

        // we get the output of the batch file
        $this->output = ob_get_contents();

        // we let the output handler do the stuff it needs to do
        $this->_output($this->output);

        // we are officially done
        $this->__done();
    }

    /**
   * Public entry point for running a job on this batch
   *
   * @param BatchJob object $job BatchJob object
   */
    public function run(BatchJob $job)
    {
        // Yes Boss! We will run this batch boss!
        if (!$job) {
            throw new GuruException('New Instances should specify what job/schedule it needs to run.');
        }
        $this->job = $job;

        // we are in blackout mode
        if (MAINTENANCE_MODE) {
            $this->log('We cannot run this batch [' . $this->job->getId() . '] because the global flag MAINTENANCE_MODE is enabled.');
            return;
        }

        // run batch run!
        $this->_run();
    }

    /**
   * Method to handle outputs or console generated content
   *
   * @access    Protected
   * @param     string $output string the captured output into stream
   * @return    string the unmodified output
   */
    protected function _output($output)
    {
        $output = mysql_real_escape_string($output); #BLEH
        $output = $this->output = $this->output($output); // maybe the user wants to do something with the output
        $this->job->output($output);
        return $output;
    }

    /**
   * Overrideable method that handles the output
   *
   * @return must return an output
   */
    public function output($output)
    {
        return $output;
    }

    /**
   * a function to log errors and mark it appropriately
   */
    public function error($error = null, $code = null, $severity = SEVERITY_SOFT)
    {
        if ($this->job) {
            $this->job->error($error, $code, $severity);
            return true;
        }
        return false;
    }

    /**
   * an event thrown before doing the batch.
   */
    private function __start()
    {
        // run the init method from our child class
        $this->init();
    }

    /**
   * an event thrown before doing the batch. can be overridden.
   */
    public function init() {}

    /**
   * an event thrown when the batch process finishes and succeeds. can be overridden.
   *
   * @param string $output string the captured output into stream
   */
    public function success($output = null) {}

    /**
   * an event thrown when the batch process finishes and fails. can be overridden.
   *
   * @param array $errors the errors available
   */
    public function fail($errors = null) {}

    /**
   * an event thrown when the batch is completely done. can be overridden.
   */
    public function done() {}

    /**
   * an event thrown when the batch is completely done.
   */
    private function __done()
    {
        // we're done. yey!
        $this->done();
    }

    /**
   * a static method that makes logging convenient in the system context
   */
    public function log($message = null)
    {
        if ($this->job) {
            // write to database, but we don't have that yet. so :(
            $this->job->log($message);
            return true;
        }
        return false;
    }

}

//~ EOF