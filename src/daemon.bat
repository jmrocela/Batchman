@echo off
:LoopStart
cls
php BatchDispatcher.php
echo "Running Cron"
TIMEOUT /T 30
GOTO LoopStart