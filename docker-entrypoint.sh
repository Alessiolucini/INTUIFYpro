#!/bin/bash
# IntuiFy container entrypoint.
# config.php is part of the repo and reads every secret from environment
# variables (Dokploy → Environment). This script never writes secrets.

# Detect app directory (Dokploy may use /app instead of /var/www/html)
APP_DIR="/var/www/html"
if [ -f /app/admin/index.php ]; then
    APP_DIR="/app"
fi

if [ ! -f "${APP_DIR}/config.php" ]; then
    echo "❌ ${APP_DIR}/config.php missing — it must be part of the repository" >&2
fi

# Keep /app and /var/www/html in sync when both exist
if [ -d /app ] && [ -d /var/www/html ] && [ "/app" != "/var/www/html" ]; then
    if [ -f /var/www/html/config.php ] && [ ! -f /app/config.php ]; then
        cp /var/www/html/config.php /app/config.php
        echo "✅ config.php copied to /app"
    fi
    if [ -f /app/config.php ] && [ ! -f /var/www/html/config.php ]; then
        cp /app/config.php /var/www/html/config.php
        echo "✅ config.php copied to /var/www/html"
    fi
fi

# Warn early about missing required secrets (values are never printed)
for var in SUPABASE_SERVICE_KEY SMTP_PASSWORD OPENAI_API_KEY RECAPTCHA_SITE_KEY RECAPTCHA_SECRET_KEY; do
    if [ -z "${!var}" ]; then
        echo "⚠️  ${var} is not set" >&2
    fi
done
if [ -z "${ADMIN_PASSWORD_HASH}" ] && [ -z "${ADMIN_PASSWORD}" ]; then
    echo "⚠️  Neither ADMIN_PASSWORD_HASH nor ADMIN_PASSWORD is set — admin login is disabled" >&2
fi

# Start Apache
exec apache2-foreground
