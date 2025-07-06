# SubTrack Buddy (subtrack-buddy-20250706_054158)

A Laravel 10 PHP web application to centralize software subscription management for individuals and teams.  
Secure authentication, per-user and per-team data isolation, automated renewal reminders (email & WhatsApp), always-visible sidebar widget, and Stripe-based freemium billing. Containerized with Docker Compose, CI/CD-enabled, and integrated logging & monitoring.

Project Doc: https://docs.google.com/document/d/1FraC0AtX4IYP2vIxUJ47htzDNo7BPqB8ZoktezHuJj4/

---

## Table of Contents

- [Overview](#overview)  
- [Features](#features)  
- [Architecture](#architecture)  
- [Installation](#installation)  
- [Configuration](#configuration)  
- [Usage](#usage)  
- [Components](#components)  
- [Dependencies](#dependencies)  
- [Testing](#testing)  
- [CI/CD](#cicd)  
- [Logging & Monitoring](#logging--monitoring)  
- [Contributing](#contributing)  
- [License](#license)  

---

## Overview

SubTrack Buddy helps you:

- Track and manage all software subscriptions in one place  
- Receive automated reminders at 30d, 7d, 3d, 1d before renewal via email (Mailgun/SES) and WhatsApp (Twilio)  
- View an always-visible sidebar widget with countdowns and action buttons  
- Collaborate in teams with roles and shared subscriptions  
- Export renewal history (CSV/XLS) for Pro users  
- Upgrade/downgrade via Stripe Checkout, with webhooks to sync status  

---

## Features

- User registration, email verification, password reset  
- Team management: invites, roles, shared subscriptions  
- Subscription CRUD with custom fields, categories, tags, notes  
- Configurable multi-frequency reminders (email & WhatsApp)  
- Global sidebar Vue 3 widget showing next 5 renewals  
- Reminder actions: snooze, reschedule, mark as paid  
- Renewal history log with filters, sorting, CSV/XLS export (Pro)  
- Freemium Stripe billing: free tier (5 subs), Pro tier (unlimited)  
- Pricing page with upgrade/downgrade flow  
- Responsive UI (Tailwind CSS + Vue 3)  
- Containerized (Docker Compose)  
- CI/CD (GitHub Actions): lint, tests (PHPUnit, Dusk), asset build, deploy  
- Logging: Sentry & Papertrail; Monitoring: Prometheus & Grafana  

---

## Architecture

Microservices-style stack via Docker Compose:

- php-fpm  
- nginx  
- mysql  
- redis  
- queue-worker  
- scheduler

Laravel MVC structure with Controllers, Eloquent Models, Services, Repositories, Events & Listeners.  
Task scheduling (Laravel Scheduler) and Redis queues for background jobs.  
Blade templates + Vue 3 components for UI.  

---

## Installation

Prerequisites: Docker & Docker Compose, Git

```bash
# Clone repo
git clone https://github.com/your-org/subtrack-buddy-20250706_054158.git
cd subtrack-buddy-20250706_054158

# Copy environment
cp .env.example .env
# Generate key
docker run --rm -v $(pwd):/app -w /app laravelphp/php-fpm php artisan key:generate

# Build & start containers
docker-compose up -d --build

# Install PHP & JS deps
docker-compose exec php-fpm composer install
docker-compose exec node npm install
docker-compose exec node npm run build

# Run migrations & seeders
docker-compose exec php-fpm php artisan migrate --seed
```

---

## Configuration

Edit `.env` with your credentials:

```
APP_ENV=local
APP_URL=http://localhost
DB_HOST=mysql
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
REDIS_HOST=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_USERNAME=?
MAIL_PASSWORD=?
MAIL_FROM_ADDRESS=?

# Twilio
TWILIO_SID=?
TWILIO_TOKEN=?
TWILIO_WHATSAPP_FROM=whatsapp:+123456789

# Stripe
STRIPE_KEY=pk_test_?
STRIPE_SECRET=sk_test_?
STRIPE_WEBHOOK_SECRET=whsec_?

# Sentry
SENTRY_LARAVEL_DSN=?

# Papertrail, Prometheus, Grafana config?
```

---

## Usage

- Visit `http://localhost` to access the marketing/pricing page  
- Sign up, verify email, complete onboarding wizard  
- Add subscriptions, view dashboard and sidebar widget  
- Manage reminders and renewal history  
- Invite team members under **Account ? Teams**  
- Upgrade/downgrade plan under **Account ? Billing**  

Run scheduler & queue (in separate terminals or as daemons):

```bash
# Schedule reminders (cron every minute)
docker-compose exec php-fpm php artisan schedule:run
# Process queue jobs
docker-compose exec php-fpm php artisan queue:work
```

---

## Components

Controllers  
- **AuthController.php**: registration, login, logout, email verify, password reset  
- **SubscriptionController.php**: CRUD subscriptions, schedule reminders  
- **ReminderController.php**: snooze, reschedule, mark paid  
- **HistoryController.php**: view/export renewal history  
- **TeamController.php**: create teams, invites, roles, sharing  
- **BillingWebhookController.php**: handle Stripe webhooks  
- **SettingsController.php**: profile, password, notifications, billing portal  
- **PricingController.php**: show pricing, upgrade/downgrade  

Services  
- **ReminderService.php**: compute & schedule reminder jobs  
- **NotificationService.php**: send email & WhatsApp notifications  
- **BillingService.php**: enforce tier limits, Stripe sessions & webhook handling  
- **TeamService.php**: team CRUD and membership  

Console Command  
- **ScheduleReminders.php**: dispatch reminder jobs via ReminderService  

Front-end  
- **widget.js**: Vue widget for sidebar  
- **app.js** / **app.css**: main SPA assets  
- Blade templates: `navbar.php`, `footer.php`, `dashboard.php`, `login.php`, `signup.php`, `pricing.php`, `settings.php`, `widget.php`  

Models  
- **User.php**, **Team.php**, **Subscription.php**  

Routing & Bootstrap  
- **index.php**, **web.php**, **api.php**, **app.php**  

Config & Tooling  
- **composer.json**, **package.json**, **tailwind.js**, **webpack.js**  

Tests  
- **AuthTest.php**  
- **SubscriptionTest.php**  

---

## Dependencies

- PHP 8.1+ & Laravel 10  
- MySQL 8.x  
- Redis  
- Docker & Docker Compose  
- Node.js 16+ & npm  
- Composer  
- Stripe PHP SDK  
- Twilio SDK  
- Mailgun or AWS SES  
- Sentry PHP SDK  
- Papertrail (log shipping)  
- Prometheus & Grafana (monitoring)  
- GitHub Actions  

---

## Testing

```bash
# PHPUnit
docker-compose exec php-fpm php artisan test

# Dusk (browser tests)
docker-compose exec php-fpm php artisan dusk
```

---

## CI/CD

Configured via **.github/workflows/ci.yml** to:

- Run PHP linting & PHPStan  
- Run PHPUnit & Dusk tests  
- Build front-end assets  
- Deploy to staging/production on push to `main` (configure secrets)  

---

## Logging & Monitoring

- Errors/exceptions ? Sentry  
- App logs ? Papertrail  
- Metrics ? Prometheus exporters  
- Dashboards & alerts ? Grafana  

---

## Contributing

1. Fork the repository  
2. Create a feature branch (`git checkout -b feature/XYZ`)  
3. Commit your changes & push  
4. Open a Pull Request  

Please follow PSR-12 coding standard and write tests for new features.

---

## License

MIT License  
? 2025 Your Company Name