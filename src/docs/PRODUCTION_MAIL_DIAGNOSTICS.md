# Production Mail Diagnostics

Kairox application mail currently uses Laravel's configured transport synchronously. A queue worker is not required for these mailables, although the production stack still runs one for queued work.

## Required environment

```env
APP_URL=https://portal.kairoxexchange.com
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=<verified Kairox sender>
MAIL_FROM_NAME="Kairox Exchange"
MAIL_ADMIN_ADDRESS=info@kairoxexchange.com
RESEND_API_KEY=<secret>
```

Never commit the actual Resend key. The sender address/domain must be verified with Resend.
Generated links in application and administrator emails use `APP_URL`; verify it uses the production HTTPS domain before caching configuration.

After changing production environment values:

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml restart app queue
```

## Health check

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan kairox:test-mail
```

Successful dispatch means Laravel handed the message to the configured transport. It does not prove inbox delivery. Check the Resend dashboard for accepted, delivered, bounced, suppressed, and rejected events.

## Diagnostics

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan about
docker compose -f docker-compose.prod.yml exec -T app php artisan queue:failed
docker compose -f docker-compose.prod.yml exec -T app php artisan queue:monitor database:default --max=100
docker compose -f docker-compose.prod.yml logs --tail=200 app queue
docker compose -f docker-compose.prod.yml exec -T app tail -n 200 storage/logs/laravel.log
```

Application notification logs include the application ID, applicant user ID, recipient, notification type, and exception details when an attempt fails. Credentials are never logged.
