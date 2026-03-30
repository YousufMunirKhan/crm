# Installation Guide

## Important: PHP Version Requirement

This CRM system requires **PHP 8.3+**. Your current PHP version is 7.2.34, which is not compatible.

## Step 1: Switch PHP Version in Laragon

1. Open **Laragon**
2. Click on **Menu** → **PHP** → Select **PHP 8.3** (or latest available 8.x version)
3. Restart Laragon if needed

## Step 2: Verify PHP Version

Open a new terminal and verify:
```bash
php -v
```

You should see PHP 8.3.x or higher.

## Step 3: Install Composer Dependencies

Once PHP 8.3 is active, run:
```bash
composer install
```

This will install:
- Laravel Sanctum (for authentication)
- Predis (for Redis)
- Maatwebsite Excel (for import/export)
- DomPDF (for invoice PDFs)

## Step 4: Publish Sanctum Configuration

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## Step 5: Run Migrations

```bash
php artisan migrate --seed
```

This creates all tables and the default admin user:
- Email: `admin@switchsave.com`
- Password: `Admin@123`

## Step 6: Install Frontend Dependencies

```bash
npm install
```

## Step 7: Build Frontend

```bash
npm run build
```

Or for development:
```bash
npm run dev
```

## Step 8: Start Development Server

```bash
php artisan serve
```

Visit: `http://localhost:8000`

## Troubleshooting

### If composer still shows PHP 7.2:
- Make sure you're using the terminal that Laragon opens (it uses the correct PHP version)
- Or manually set PHP path: `C:\laragon\bin\php\php-8.3.x\php.exe composer install`

### If you get "Class not found" errors:
- Run: `composer dump-autoload`

### If migrations fail:
- Make sure PostgreSQL/MySQL is running
- Check `.env` database configuration
- Ensure database exists

