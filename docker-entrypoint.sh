#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# Generate msmtp config from environment variables.
# Set these in your .env file or docker-compose environment section:
#   SMTP_HOST     - SMTP server hostname          (default: localhost)
#   SMTP_PORT     - SMTP port                     (default: 25)
#   SMTP_FROM     - Envelope from address         (default: noreply@example.com)
#   SMTP_USER     - SMTP username (optional)
#   SMTP_PASS     - SMTP password (optional)
#   SMTP_TLS      - on/off                        (default: off)
#   SMTP_STARTTLS - on/off (STARTTLS/opportunistic)(default: off)
#   SMTP_AUTH     - on/off                        (default: off if no user set)
# ---------------------------------------------------------------------------
SMTP_HOST="${SMTP_HOST:-mailpit}"
SMTP_PORT="${SMTP_PORT:-1025}"
SMTP_FROM="${SMTP_FROM:-noreply@example.com}"
SMTP_TLS="${SMTP_TLS:-off}"
SMTP_STARTTLS="${SMTP_STARTTLS:-off}"

if [ -n "$SMTP_USER" ]; then
    SMTP_AUTH="${SMTP_AUTH:-on}"
else
    SMTP_AUTH="${SMTP_AUTH:-off}"
fi

{
    echo "defaults"
    echo "auth $SMTP_AUTH"
    echo "tls $SMTP_TLS"
    echo "tls_starttls $SMTP_STARTTLS"
    echo "tls_certcheck off"
    echo ""
    echo "account default"
    echo "host $SMTP_HOST"
    echo "port $SMTP_PORT"
    echo "from $SMTP_FROM"
    [ -n "$SMTP_USER" ] && echo "user $SMTP_USER"
    [ -n "$SMTP_PASS" ] && echo "password $SMTP_PASS"
} > /etc/msmtprc
chmod 644 /etc/msmtprc

# Install Composer dependencies if vendor dir is missing.
# composer.json lives in includes/ but the app expects vendor/ at the project root,
# so we install there and create a symlink from root/vendor -> includes/vendor.
if [ ! -d /var/www/html/saldi/vendor ] && [ -f /var/www/html/saldi/includes/composer.json ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --working-dir=/var/www/html/saldi/includes
    ln -sf /var/www/html/saldi/includes/vendor /var/www/html/saldi/vendor
fi

# Ensure required writable directories exist with correct permissions
for dir in includes logolib temp; do
    mkdir -p /var/www/html/saldi/$dir
    chmod 777 /var/www/html/saldi/$dir
done

# Create vendor symlink at /var/www/html/vendor/ so relative paths like
# ../../vendor/autoload.php resolve correctly when CWD is saldi/debitor/
ln -sf /var/www/html/saldi/includes/vendor /var/www/html/vendor 2>/dev/null || true

# Create phpmailer symlink for the legacy fallback (code looks for phpmailer/
# but the directory is named phpmailerOld/)
if [ ! -e /var/www/html/saldi/phpmailer ]; then
    ln -sf /var/www/html/saldi/phpmailerOld /var/www/html/saldi/phpmailer
fi

exec "$@"
