#!/bin/sh
set -e
# Ensure log directory exists for supervisor-managed workers
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true

# Run supervisor in foreground (nodaemon) so the container stays up
exec /usr/bin/supervisord -c /etc/supervisord.conf
