<?php
/**
 * BatchHandler.php
 *
 * This file is part of the Batch Base package. This file is responsible in
 * loading PHP batches and starting them as needed. Please refer to the
 * Documentation for more information.
 * 
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */
// Load the Guru Framework
require_once "../../../bootstrap.php";

// We include the batch framework related files
require_once "BatchBase.php";
require_once "BatchManager.php";

// define some constants
define('MAINTENANCE_MODE', false); #THIS SHOULDN'T BE HERE

// change the current working directory
#chdir(getcwd());

// Where's our manager?
$manager = new Allproperty\BatchManager();
$className = null;
$jobID = (isset($argv[1])) ? $argv[1]: null;

// If we are at shell
if (isset($argv)) {
    // RUN!
    $manager->getJob($jobID)->run();
}

//~ EOF