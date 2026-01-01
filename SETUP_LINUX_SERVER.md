# Linux Server Setup Guide for Rupkeep App

This guide will help you set up your Laravel application on a Linux server (Ubuntu/Debian).

## Prerequisites

- PHP 8.2+ with required extensions
- Composer installed
- Node.js and npm (for building assets)
- Web server (Apache or Nginx)
- Database server (MySQL/MariaDB or PostgreSQL)

## Step 1: Connect to Your Server

```bash
ssh your-user@your-server-ip
```

## Step 2: Navigate to Your Application Directory

```bash
cd /var/www/rupkeep-app
```

## Step 3: Set Up File Permissions

This is the most critical step to fix the permission denied error.

### Option A: Set ownership to web server user (Recommended)

First, identify your web server user:
- **Apache**: Usually `www-data` or `apache`
- **Nginx**: Usually `www-data` or `nginx`

```bash
# For Apache
sudo chown -R www-data:www-data /var/www/rupkeep-app

# For Nginx (if different)
# sudo chown -R nginx:nginx /var/www/rupkeep-app
```

### Option B: Set group ownership and permissions

If you need to maintain your user ownership but allow web server access:

```bash
# Add your user to the web server group
sudo usermod -a -G www-data $USER

# Set group ownership
sudo chgrp -R www-data /var/www/rupkeep-app

# Set directory permissions (755 for directories, 644 for files)
find /var/www/rupkeep-app -type d -exec chmod 755 {} \;
find /var/www/rupkeep-app -type f -exec chmod 644 {} \;

# Make storage and bootstrap/cache writable
sudo chmod -R 775 /var/www/rupkeep-app/storage
sudo chmod -R 775 /var/www/rupkeep-app/bootstrap/cache

# Set group ownership on storage and cache
sudo chgrp -R www-data /var/www/rupkeep-app/storage
sudo chgrp -R www-data /var/www/rupkeep-app/bootstrap/cache
```

## Step 4: Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm install

# Build assets for production
npm run build
```

## Step 5: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env file with your settings
nano .env
```

Required `.env` settings:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` (your domain)
- Database credentials
- Mail configuration
- Any other service credentials

## Step 6: Run Database Migrations

```bash
php artisan migrate --force
```

## Step 7: Create Storage Link

```bash
php artisan storage:link
```

## Step 8: Clear and Cache Configuration

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 9: Set Up Queue Worker (if using queues)

If your application uses queues (for emails, etc.), set up a supervisor or systemd service:

### Using Supervisor (Recommended)

Create `/etc/supervisor/conf.d/rupkeep-worker.conf`:

```ini
[program:rupkeep-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/rupkeep-app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/rupkeep-app/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rupkeep-worker:*
```

## Step 10: Web Server Configuration

### Apache Configuration

Ensure your virtual host has proper permissions. Example `/etc/apache2/sites-available/rupkeep.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/rupkeep-app/public

    <Directory /var/www/rupkeep-app/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/rupkeep-error.log
    CustomLog ${APACHE_LOG_DIR}/rupkeep-access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite rupkeep.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Nginx Configuration

Example `/etc/nginx/sites-available/rupkeep`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/rupkeep-app/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/rupkeep /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Step 11: Verify Permissions

After setup, verify permissions are correct:

```bash
# Check storage permissions
ls -la /var/www/rupkeep-app/storage
ls -la /var/www/rupkeep-app/bootstrap/cache

# Should show www-data (or your web server user) as owner/group
```

## Troubleshooting

### Permission Denied Errors

If you still get permission errors:

```bash
# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs

# Set permissions again
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### SELinux Issues (if enabled)

If SELinux is enabled, you may need to set contexts:

```bash
sudo chcon -R -t httpd_sys_rw_content_t /var/www/rupkeep-app/storage
sudo chcon -R -t httpd_sys_rw_content_t /var/www/rupkeep-app/bootstrap/cache
```

### Check Logs

```bash
# Laravel logs
tail -f /var/www/rupkeep-app/storage/logs/laravel.log

# Web server error logs
# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/error.log
```

## Quick Fix Script

If you just need to fix permissions quickly:

```bash
#!/bin/bash
cd /var/www/rupkeep-app
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

Save as `fix-permissions.sh`, make executable (`chmod +x fix-permissions.sh`), and run it.

## Post-Deployment Checklist

- [ ] Permissions set correctly on storage and bootstrap/cache
- [ ] .env file configured with production settings
- [ ] Application key generated
- [ ] Database migrations run
- [ ] Storage link created
- [ ] Assets built (npm run build)
- [ ] Config and routes cached
- [ ] Queue worker running (if needed)
- [ ] Web server configured and restarted
- [ ] SSL certificate installed (if using HTTPS)
