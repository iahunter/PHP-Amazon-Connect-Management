#!/bin/bash

# This adds Supervisor to monitor the Queue workers.

printf "\n###########################################################\n"
printf "Install Supervisor Script \n"


printf "Insalling Supervisor...\n"
sudo apt-get install supervisor

printf "Waiting 15 secs for install...\n"
sleep 15


echo "The current working directory: $PWD"
_mydir="$PWD"
echo $_mydir

# Create new file with the working directory.

printf "Generating Configuration File...\n"
echo "
[program:amazon-connect-agent-monitor]
process_name=%(program_name)s_%(process_num)02d

command=php $_mydir/artisan aws:agentevents
autostart=true
autorestart=true

numprocs=1
redirect_stderr=true
stdout_logfile=$_mydir/storage/logs/worker.log" > $_mydir/etc/supervisor/conf.d/amazon-connect-agent-monitor.conf


sleep 2
# Creater Symlink to the Supervisor directory in /etc.
printf "Creating Symlink to config file...\n"
ln -s $_mydir/etc/supervisor/conf.d/amazon-connect-agent-monitor.conf /etc/supervisor/conf.d/amazon-connect-agent-monitor.conf

# Create new file with the working directory.

printf "Generating Configuration File...\n"
echo "
[program:amazon-connect-queue-monitor]
process_name=%(program_name)s_%(process_num)02d

command=php $_mydir/artisan aws:get-metric-data
autostart=true
autorestart=true

numprocs=1
redirect_stderr=true
stdout_logfile=$_mydir/storage/logs/metricworker.log" > $_mydir/etc/supervisor/conf.d/amazon-connect-queue-monitor.conf


sleep 2
# Creater Symlink to the Supervisor directory in /etc.
printf "Creating Symlink to config file...\n"
ln -s $_mydir/etc/supervisor/conf.d/amazon-connect-queue-monitor.conf /etc/supervisor/conf.d/amazon-connect-queue-monitor.conf

sleep 2
printf "Readin Supervisor Worker Config...\n"
sudo supervisorctl reread


sleep 2
printf "Update Supervisor Worker Config...\n"
sudo supervisorctl update

sleep 2
printf "Starting Supervisor Task...\n"
sudo service supervisor start

sleep 2
printf "Ending Task\n"
printf "\n###########################################################\n
