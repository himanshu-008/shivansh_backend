# Laravel 12 Deployment Guide

This project is a fresh Laravel 12 application.

## Local Development

Run the app locally with:

```bash
php artisan serve
```

The default database is SQLite. The file is already created at:

```bash
database/database.sqlite
```

If needed, run migrations again with:

```bash
php artisan migrate
```

## Server Requirements

Your server should have:

- PHP 8.2 or newer
- Composer
- Nginx or Apache
- MySQL, MariaDB, PostgreSQL, or SQLite
- The PHP extensions required by Laravel

Common Ubuntu packages:

```bash
sudo apt update
sudo apt install -y nginx mysql-server unzip curl git software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3 php8.3-zip php8.3-bcmath php8.3-intl
```

Install Composer if it is missing:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

## Upload Project

Clone or upload the project to your server, for example:

```bash
cd /var/www
sudo git clone <your-repository-url> school_backend
cd /var/www/school_backend
```

Install dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

## Environment Setup

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Update the `.env` values for your server:

```env
APP_NAME="School Backend"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Run database migrations:

```bash
php artisan migrate --force
```

## Permissions

Set the correct ownership and writable directories:

```bash
sudo chown -R www-data:www-data /var/www/school_backend
sudo chmod -R 775 storage bootstrap/cache
```

## Nginx Example

Point the web root to the `public` directory:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/school_backend/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/school_backend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Production Optimization

Run these after deployment:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you use queues later:

```bash
php artisan queue:work
```

If you use the scheduler later, add this cron entry:

```bash
* * * * * cd /var/www/school_backend && php artisan schedule:run >> /dev/null 2>&1
```

## Updating the App

For future deployments:

```bash
cd /var/www/school_backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Important Note

The web server must serve the `public` folder, not the project root.
