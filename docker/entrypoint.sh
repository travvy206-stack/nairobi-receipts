#!/bin/bash
set -e

# Run DB initialization if sqlite db does not exist
if [ ! -f /var/www/html/db/nairobi_traders.sqlite ]; then
    echo "[NTA-Pay] Initializing SQLite database schema and seeds..."
    php /var/www/html/db/init_db.php
fi

# Run any schema migrations (like payment_status and mpesa till settings)
if [ -f /var/www/html/db/migrate.php ]; then
    php /var/www/html/db/migrate.php || true
fi
if [ -f /var/www/html/db/migrate_mpesa.php ]; then
    php /var/www/html/db/migrate_mpesa.php || true
fi

# Ensure storage directory exists with write permissions
mkdir -p /var/www/html/service_pdf/storage
chmod -R 777 /var/www/html/service_pdf/storage /var/www/html/db

echo "[NTA-Pay] Starting Python microservice (port 5000) and PHP server (port ${PORT:-8000})..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
