# Switch & Save CRM

A complete production-grade enterprise CRM platform built with Laravel 11 and Vue 3.

## Tech Stack

### Backend
- Laravel 11
- PHP 8.3+
- PostgreSQL
- Redis (cache + queues)
- Laravel Sanctum (authentication)
- Laravel Queues

### Frontend
- Vue 3 (Composition API)
- Vite
- Pinia (state management)
- Tailwind CSS 4
- Vue Router 4

## Features

### 1. Authentication & Role System
- Role-based access control (Admin, Manager, Sales, CallAgent, Support, HR, Customer)
- Persistent login sessions
- Remember me functionality
- Password reset flow
- Profile management

**Default Admin Credentials:**
- Email: `admin@switchsave.com`
- Password: `Admin@123`

### 2. CRM Lead Pipeline
- Pipeline stages: Follow Up → Lead → Hot Lead → Quotation → Won → Lost
- Lead assignment to agents
- Notes and reminders
- Pipeline board view
- Stage history tracking
- Lost reason tracking
- Lead source tracking (floor/callcenter/web)

### 3. Customer Management
- Complete customer profiles
- Address and location data (with lat/lng for maps)
- Purchase history
- Communication timeline
- Unified view with all interactions

### 4. Unified Communication Engine
- WhatsApp (Meta Cloud API ready)
- Email (SMTP) — HTML templates and merge tags: [docs/EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md](docs/EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md)
- SMS (provider integration ready)
- Unified timeline view
- Delivery status tracking
- Inbound webhook handlers

### 5. Ticket System
- Customer ticket creation
- POS integration for automatic ticket creation
- Agent assignment
- Priority levels (low, medium, high, urgent)
- SLA tracking
- Internal chat
- Status tracking

### 6. Invoice System
- GBP currency support
- UK VAT calculation (20% default)
- Invoice items management
- PDF generation
- Outstanding payment tracking
- Multiple statuses (draft, sent, partially_paid, paid, overdue)

### 7. HR Module
- Attendance tracking (check in/out)
- Work hours calculation
- Salary management
- Payroll with deductions
- Monthly records

### 8. POS Integration API
- `POST /api/pos/customer` - Create/update customer
- `POST /api/pos/ticket` - Create ticket from POS
- `POST /api/pos/sale` - Record sale and create invoice

### 9. Reporting & Analytics
- Executive dashboard with KPIs
- Funnel reports with conversion rates
- Geo map analytics (customer distribution, heatmaps)
- Communication analytics
- Agent performance reports
- Advanced filtering (date range, agent, city, postcode, source)

### 10. Import/Export System
- CSV/XLSX import with column mapping
- Validation preview
- Duplicate detection
- Export reports to Excel/CSV

### 11. Real-time Notifications
- New lead assignments
- Ticket creation
- Message received
- Follow-up reminders
- POS issues

### 12. Customer Portal
- Customer self-service portal
- View tickets
- View invoices
- Communication history

## Installation

### Prerequisites
- PHP 8.3+
- Composer
- Node.js 20+ (use `nvm use 20`)
- PostgreSQL
- Redis

### Setup Steps

1. **Clone and install dependencies:**
```bash
composer install
npm install
```

2. **Environment configuration:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure `.env`:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=crm
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

4. **Run migrations and seeders:**
```bash
php artisan migrate --seed
```

This will create:
- All database tables
- Default roles
- Admin user (admin@switchsave.com / Admin@123)

5. **Build frontend:**
```bash
npm run build
```

6. **Start development servers:**
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev

# Terminal 3: Queue worker
php artisan queue:work

# Terminal 4: WebSockets (if using)
php artisan websockets:serve
```

## Project Structure

```
app/
├── Modules/
│   ├── CRM/              # Customer & Lead management
│   ├── Communication/    # Unified messaging
│   ├── Ticket/          # Support tickets
│   ├── Invoice/         # Invoicing system
│   ├── HR/              # Attendance & Salary
│   ├── POS/             # POS integration
│   └── Reporting/       # Analytics & reports
├── Services/            # Shared services
├── Traits/              # Reusable traits
└── Http/
    └── Controllers/
        └── Auth/        # Authentication

resources/
└── js/
    ├── components/      # Vue components
    ├── views/          # Page views
    ├── layouts/        # Layout components
    ├── router/         # Vue Router
    ├── stores/         # Pinia stores
    └── utils/          # Utilities
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Get current user
- `POST /api/auth/logout` - Logout

### CRM
- `GET /api/customers` - List customers
- `GET /api/customers/{id}` - Get customer with timeline
- `POST /api/customers` - Create customer
- `GET /api/leads` - List leads
- `GET /api/leads/pipeline/board` - Pipeline board
- `POST /api/leads/{id}/activity` - Add activity
- `POST /api/leads/{id}/followup` - Set follow-up

### Communications
- `GET /api/communications` - List communications
- `POST /api/communications` - Send message

### Tickets
- `GET /api/tickets` - List tickets
- `POST /api/tickets` - Create ticket
- `POST /api/tickets/{id}/message` - Add message

### Invoices
- `GET /api/invoices` - List invoices
- `POST /api/invoices` - Create invoice
- `GET /api/invoices/{id}/pdf` - Generate PDF

### HR
- `POST /api/hr/attendance/check-in` - Check in
- `POST /api/hr/attendance/check-out` - Check out
- `GET /api/hr/attendance` - List attendance
- `GET /api/hr/salaries` - List salaries
- `POST /api/hr/salaries` - Create salary record

### Reporting
- `GET /api/reporting/executive` - Executive dashboard
- `GET /api/reporting/funnel` - Funnel report
- `GET /api/reporting/geo` - Geo analytics
- `GET /api/reporting/communications` - Communication analytics
- `GET /api/reporting/agents` - Agent performance

### POS Integration
- `POST /api/pos/customer` - Create/update customer
- `POST /api/pos/ticket` - Create ticket
- `POST /api/pos/sale` - Record sale

## Deployment (Hostinger VPS)

### Server Requirements
- PHP 8.3+
- PostgreSQL
- Redis
- Node.js 20+
- Nginx/Apache

### Deployment Steps

1. **Clone repository:**
```bash
git clone <repository-url>
cd crm
```

2. **Install dependencies:**
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

3. **Configure environment:**
```bash
cp .env.example .env
# Edit .env with production values
php artisan key:generate
```

4. **Run migrations (existing DB: use `migrate` only, never `migrate:fresh`):**
```bash
php artisan migrate --force --seed
```
See [DEPLOYMENT.md](DEPLOYMENT.md) for deploying on an existing database.

5. **Optimize:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. **Setup Supervisor for queues:**
Create `/etc/supervisor/conf.d/crm-queue.conf`:
```ini
[program:crm-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/crm/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/crm/storage/logs/queue.log
```

7. **Nginx configuration:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/crm/public;

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
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

8. **Set permissions:**
```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Security

- Role-based permissions
- Audit logging for all model changes
- Rate limiting on API routes
- Webhook signature verification (implement in production)
- CSRF protection
- SQL injection protection (Eloquent ORM)
- XSS protection

## License

Proprietary - Internal use only

## Support

For issues or questions, contact the development team.
