#!/bin/bash
echo "Running Dispatcher...";
while true
do
date
    php BatchDispatcher.php
    sleep 5
done