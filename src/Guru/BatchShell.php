<?php
/**
 * BatchShell.php
 *
 * This file is part of the Batch Base package. This file handles all Batch logic points
 * within a Shell file. Usage examples are given below. Please refer to the Documentation
 * for more information.
 *
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */

// NAMESPACESSS!!!
namespace Guru;

// Load the Guru Framework
require_once "../../../bootstrap.php";

// We include the batch framework related files
require_once "BatchBase.php";
require_once "BatchManager.php";

/**
 * Usages
 *
 * --id         the ID of the Batch Job
 * --start      mark the Job as started
 * --log        log a message
 * --error      throw an error and record to log
 *
 * Flags
 * -h           signify the current error log as SEVERITY_HARD
 */
$opts = getopt("h", array("id:", "log:", "error:", "start:"));

// Get to work!
$jobId = $opts['id'];

// Manager's Job
$manager = new BatchManager();
$job = $manager->getJob($jobId);
if (!$job) {
    echo "[FAILED]"; // heh
    exit;
}

/**
 * --start
 *
 * Start the Process
 */
if (isset($opts['start']) && !empty($opts['start'])) {
    $job->setPID($opts['start']);
}

/**
 * --log
 *
 * Just write a log
 */
if (isset($opts['log']) && !empty($opts['log'])) {
    $job->log($opts['log']);
}

/**
 * --error
 *
 * Throw an error and log it using
 *
 * the flag -h throws an error
 */
if (isset($opts['error']) && !empty($opts['error'])) {
    if (isset($opts['h'])) {
        echo "[FAILED]"; // this lets the output handler know that the job failed
        $job->error($opts['error'], null, SEVERITY_HARD);
    } else {
        $job->error($opts['error']);
    }
}