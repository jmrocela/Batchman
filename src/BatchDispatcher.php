<?php

// Load the Guru Framework
require_once "../../../bootstrap.php";

// We include the batch framework related files
require_once "BatchBase.php";
require_once "BatchManager.php";

$manager = new Allproperty\BatchManager();

$jobs_today = $manager->db->query("SELECT * FROM batch_job");
$jobs = array();
$i = 0;
foreach ($jobs_today as $job) {
    $job = $manager->getJob($job['id']);
    if ($job->isDue()) { 
        echo "Running " . $job->getId() . " at " . date('H:iA') . "\r\n";
        $job->run(); // will wait for the last one to finish, don't know how to circumvent this yet
    }
}
