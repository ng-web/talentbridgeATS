Likeslocale Pilot Deployment Playbook
Standard Deployment & Launch Framework for SaaS Platforms

Author: Likeslocale
Purpose: Provide a repeatable, safe deployment process for pilot-ready platforms.

This playbook defines the standard approach for moving a system from:

Local Development
→ Pilot Testing
→ Production Deployment

This framework ensures platforms are:

deployable

testable externally

stable for early users

scalable for future growth

Deployment Philosophy

Likeslocale platforms should always follow three phases:

Phase 1 — Local Development
Phase 2 — Pilot Deployment
Phase 3 — Production Infrastructure

Never jump directly from local to production.

Pilot deployments allow:

real user testing

workflow validation

integration verification

UI feedback

bug discovery

Standard Development Environment

Local environments should use containerized development.

Preferred stack:

Docker
Nginx
PHP-FPM
MySQL
Node

Example local access:

http://localhost:8080
Local Development Commands

Typical commands used during development:

Clear caches

php artisan optimize:clear

Clear views

php artisan view:clear

Run migrations

php artisan migrate

Seed demo data

php artisan db:seed

Reset database for testing

php artisan migrate:fresh --seed

Restart containers

docker compose restart
Storage Setup

Ensure file uploads work locally and in production.

Create storage link:

php artisan storage:link

Verify uploads appear in:

/storage/app/public
External Testing (Tunnel Testing)

Before deploying publicly, platforms should support external testers.

Recommended tool:

ngrok

Example command:

ngrok http 8080

Example result:

https://abcd-1234.ngrok-free.app

Update .env:

APP_URL=https://abcd-1234.ngrok-free.app
ASSET_URL=https://abcd-1234.ngrok-free.app

Then clear config cache:

php artisan optimize:clear
Common Tunnel Testing Issues
Mixed Content Errors

Occurs when assets load via HTTP instead of HTTPS.

Fix by aligning:

APP_URL
ASSET_URL
CSS / JS Not Loading

Often caused by Vite dev server.

Solution:

npm run build

Ensure assets exist in:

public/build
Payment Gateway Testing

External payment gateways require public callback URLs.

During testing:

ngrok URL must be used

Example callback:

https://abcd.ngrok-free.app/payments/wipay/response
Payment Sandbox Testing

Always test payments using sandbox mode first.

Verify:

request payload

gateway response

redirect flow

callback handling

payment record creation

entitlement activation

Log requests for debugging.

Pilot Deployment Infrastructure

Pilot deployments should use simple cloud infrastructure.

Recommended providers:

Vultr
DigitalOcean

Typical pilot server size:

2 CPU
4 GB RAM
80 GB SSD

Approximate monthly cost:

$20–$24
Pilot Server Architecture

Recommended stack:

Ubuntu
Docker
Nginx
MySQL
Laravel

Containers may include:

app
nginx
db
redis (optional)
queue-worker (optional)
Domain Setup

Example structure:

platform.domain.com

Examples:

kairoxexchange.com
portal.kairoxexchange.com
app.kairoxexchange.com

DNS records should point to server IP.

SSL Setup

Use:

Let's Encrypt

If using Nginx directly:

certbot

If using container reverse proxy:

Traefik
Production Deployment Commands

Typical deployment process:

Pull latest code

git pull

Install dependencies

composer install --no-dev

Run migrations

php artisan migrate --force

Build frontend

npm run build

Clear caches

php artisan optimize

Restart containers

docker compose restart
Queue Workers

For scalable systems, enable queues.

Laravel queue driver:

database

Run workers:

php artisan queue:work

Workers handle:

email

notifications

heavy background tasks

Email Configuration

Configure SMTP for production.

Example providers:

Mailgun
SendGrid
Amazon SES

Verify:

email delivery

notification workflows

Database Backups

Every production system must include automated backups.

Recommended tools:

mysqldump

Schedule backups using:

cron

Example backup schedule:

daily backups
Logging Strategy

Laravel logs should remain enabled.

Logs stored in:

storage/logs

Monitor logs for:

gateway errors

failed jobs

runtime exceptions

Monitoring

Production systems should include basic monitoring.

Recommended tools:

UptimeRobot
BetterStack

Monitor:

server uptime

API responses

SSL status

Security Checklist

Before going live:

Verify:

debug mode disabled

.env not publicly accessible

strong admin passwords

file upload validation

role access protection

payment callbacks validated

Example:

APP_DEBUG=false
Pilot Testing Checklist

Before public pilot:

Verify workflows for:

Admin

login

job approval

payment review

entitlement management

Employer

create opportunity

edit opportunity

review applicants

Job seeker

create profile

apply to opportunity

track application status

Payment

sandbox transaction

entitlement activation

Scaling Strategy

When pilot usage increases:

Upgrade infrastructure.

Typical upgrades:

More RAM
Redis caching
Queue workers
Load balancer

Cloud providers like:

AWS

support advanced scaling.

Future SaaS Readiness

If platform evolves into SaaS:

Consider adding:

multi-tenant architecture
subscription billing
tenant isolation
usage metering
analytics
Likeslocale Deployment Model

Every platform follows this path:

Local Development
↓
Tunnel Testing
↓
Pilot Deployment
↓
Production Infrastructure
↓
SaaS Expansion

This approach ensures low risk and fast iteration.

Final Principle

Likeslocale platforms should always be built to move smoothly from:

Idea
→ Prototype
→ Pilot
→ Platform
→ Product

