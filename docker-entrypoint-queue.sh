#!/bin/sh
set -e
# Ensure log directory exists for supervisor and workers
mkdir -p /var/www/html/storage/logs /var/log/supervisor
chown -R www-data:www-data /var/www/html/storage/logs
# Start Supervisor (runs queue:work critical/default/low + horizon)
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
