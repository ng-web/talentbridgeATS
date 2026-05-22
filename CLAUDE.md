## Project Structure
./.claudeignore
./.git
./docker
./docker-compose.yml
./docker/nginx
./docker/nginx/default.conf
./node_modules
./package-lock.json
./package.json
./src
./src/.editorconfig
./src/.env
./src/.env.example
./src/.gitattributes
./src/.gitignore
./src/Dockerfile
./src/README.md
./src/app
./src/app/Actions
./src/app/Actions/Payments
./src/app/Actions/Payments/ActivateEntitlementFromPayment.php
./src/app/Console
./src/app/Console/Commands
./src/app/Console/Commands/ExpireEntitlements.php
./src/app/Http
./src/app/Http/Controllers
./src/app/Http/Controllers/Admin
./src/app/Http/Controllers/Admin/DashboardController.php
./src/app/Http/Controllers/Admin/EmployerProvisioningController.php
./src/app/Http/Controllers/Admin/EntitlementController.php
./src/app/Http/Controllers/Admin/JobController.php
./src/app/Http/Controllers/Admin/PaymentController.php
./src/app/Http/Controllers/Admin/PaymentReviewController.php
./src/app/Http/Controllers/Admin/UserController.php
./src/app/Http/Controllers/Auth
./src/app/Http/Controllers/Auth/AuthenticatedSessionController.php
./src/app/Http/Controllers/Auth/ConfirmablePasswordController.php
./src/app/Http/Controllers/Auth/EmailVerificationNotificationController.php
./src/app/Http/Controllers/Auth/EmailVerificationPromptController.php
./src/app/Http/Controllers/Auth/ForcedPasswordChangeController.php
./src/app/Http/Controllers/Auth/NewPasswordController.php
./src/app/Http/Controllers/Auth/PasswordController.php
./src/app/Http/Controllers/Auth/PasswordResetLinkController.php
./src/app/Http/Controllers/Auth/RegisteredUserController.php
./src/app/Http/Controllers/Auth/VerifyEmailController.php
./src/app/Http/Controllers/Controller.php
./src/app/Http/Controllers/DashboardController.php
./src/app/Http/Controllers/Employer
./src/app/Http/Controllers/Employer/ApplicantController.php
./src/app/Http/Controllers/Employer/CompanyController.php
./src/app/Http/Controllers/Employer/DashboardController.php
./src/app/Http/Controllers/Employer/EmployerJobController.php
./src/app/Http/Controllers/Employer/JobController.php
./src/app/Http/Controllers/Employer/LogoController.php
./src/app/Http/Controllers/JobSeeker
./src/app/Http/Controllers/JobSeeker/ApplicationController.php
./src/app/Http/Controllers/JobSeeker/DashboardController.php
./src/app/Http/Controllers/JobSeeker/DocumentController.php
./src/app/Http/Controllers/JobSeeker/JobController.php
./src/app/Http/Controllers/JobSeeker/ProfileController.php
./src/app/Http/Controllers/Locked
./src/app/Http/Controllers/Locked/EmployerAccessController.php
./src/app/Http/Controllers/Locked/SeekerAccessController.php
./src/app/Http/Controllers/NotificationController.php
./src/app/Http/Controllers/Payment
./src/app/Http/Controllers/Payment/CheckoutController.php
./src/app/Http/Controllers/ProfileController.php
./src/app/Http/Controllers/Public
./src/app/Http/Controllers/Public/ApplyController.php
./src/app/Http/Controllers/Public/PricingController.php
./src/app/Http/Middleware
./src/app/Http/Middleware/EnsureActiveEmployerPostingAccess.php
./src/app/Http/Middleware/EnsureActiveSeekerAccess.php
./src/app/Http/Middleware/EnsurePasswordChanged.php
./src/app/Http/Requests
./src/app/Http/Requests/Admin
./src/app/Http/Requests/Admin/StoreEntitlementRequest.php
./src/app/Http/Requests/Admin/StorePaymentRequest.php
./src/app/Http/Requests/Auth
./src/app/Http/Requests/Auth/LoginRequest.php
./src/app/Http/Requests/ProfileUpdateRequest.php
./src/app/Mail
./src/app/Mail/AccessActivatedMail.php
./src/app/Mail/EmployerNewApplicantMail.php
./src/app/Mail/EmployerProvisionedMail.php
./src/app/Mail/JobApprovedMail.php
./src/app/Mail/JobSeekerApplicationSubmittedMail.php
./src/app/Models
./src/app/Models/AdminOverride.php
./src/app/Models/Application.php
./src/app/Models/ApplicationFile.php
./src/app/Models/AuditLog.php
./src/app/Models/Employer.php
./src/app/Models/Entitlement.php
./src/app/Models/Job.php
./src/app/Models/JobSeeker.php
./src/app/Models/Payment.php
./src/app/Models/Plan.php
./src/app/Models/Program.php
./src/app/Models/User.php
./src/app/Notifications
./src/app/Notifications/ApplicationStatusChangedNotification.php
./src/app/Notifications/ApplicationSubmittedNotification.php
./src/app/Notifications/JobApprovedNotification.php
./src/app/Notifications/JobArchivedNotification.php
./src/app/Notifications/JobReturnedToPendingNotification.php
./src/app/Providers
./src/app/Providers/AppServiceProvider.php
./src/app/Providers/PaymentServiceProvider.php
./src/app/Services
./src/app/Services/Payments
./src/app/Services/Payments/Contracts
./src/app/Services/Payments/Contracts/PaymentServiceInterface.php
./src/app/Services/Payments/PaymentGatewayManager.php
./src/app/Services/Payments/WiPayPaymentService.php
./src/app/Services/WiPayHostedCheckoutService.php
./src/app/Support
./src/app/Support/Pricing
./src/app/Support/Pricing/EntitlementPricing.php
./src/app/Support/Pricing/PlanResolver.php
./src/app/View
./src/app/View/Components
./src/app/View/Components/AppLayout.php
./src/app/View/Components/GuestLayout.php
./src/artisan
./src/bootstrap
./src/bootstrap/app.php
./src/bootstrap/cache
./src/bootstrap/cache/.gitignore
./src/bootstrap/cache/packages.php
./src/bootstrap/cache/services.php
./src/bootstrap/providers.php
./src/composer.json
./src/composer.lock
./src/config
./src/config/app.php
./src/config/auth.php
./src/config/cache.php
./src/config/database.php
./src/config/filesystems.php
./src/config/logging.php
./src/config/mail.php
./src/config/permission.php
./src/config/pricing.php
./src/config/queue.php
./src/config/services.php
./src/config/session.php
./src/database
./src/database/.gitignore
./src/database/database.sqlite
./src/database/factories
./src/database/factories/UserFactory.php
./src/database/migrations
./src/database/migrations/0001_01_01_000000_create_users_table.php
./src/database/migrations/0001_01_01_000001_create_cache_table.php
./src/database/migrations/0001_01_01_000002_create_jobs_table.php
./src/database/migrations/2026_03_06_194229_create_permission_tables.php
./src/database/migrations/2026_03_06_194457_create_employers_table.php
./src/database/migrations/2026_03_06_194457_create_job_seekers_table.php
./src/database/migrations/2026_03_06_194458_create_programs_table.php
./src/database/migrations/2026_03_06_194459_create_admin_overrides_table.php
./src/database/migrations/2026_03_06_194459_create_audit_logs_table.php
./src/database/migrations/2026_03_06_194459_create_entitlements_table.php
./src/database/migrations/2026_03_06_194459_create_jobs_table.php
./src/database/migrations/2026_03_06_194459_create_payments_table.php
./src/database/migrations/2026_03_06_194500_create_applications_table.php
./src/database/migrations/2026_03_06_194501_create_application_files_table.php
./src/database/migrations/2026_03_08_165725_add_submitted_documents_to_applications_table.php
./src/database/migrations/2026_03_08_185117_alter_entitlements_table_for_access_gating.php
./src/database/migrations/2026_04_01_165142_create_plans_table.php
./src/database/migrations/2026_04_01_165524_add_plan_id_to_payments_table.php
./src/database/migrations/2026_04_02_135602_add_entitlement_activated_at_to_payments_table.php
./src/database/migrations/2026_04_05_185226_create_notifications_table.php
./src/database/migrations/2026_04_20_000001_add_must_change_password_to_users_table.php
./src/database/seeders
./src/database/seeders/DatabaseSeeder.php
./src/database/seeders/PilotDemoSeeder.php
./src/database/seeders/PlanSeeder.php
./src/database/seeders/ProgramSeeder.php
./src/database/seeders/RolesAndPermissionsSeeder.php
./src/docs
./src/docs/ARCHITECTURE_PLAYBOOK.md
./src/docs/PILOT_DEPLOYMENT_PLAYBOOK.md
./src/docs/PROJECT_CONTEXT.md
./src/docs/likeslocale-core
./src/docs/likeslocale-core/Project_Continuity.md
./src/docs/likeslocale-core/core-components-inventory.md
./src/docs/likeslocale-core/extraction-checklist.md
./src/docs/pilot-qa-checklist.md
./src/node_modules
./src/package-lock.json
./src/package.json
./src/phpunit.xml
./src/postcss.config.js
./src/public
./src/public/.htaccess
./src/public/build
./src/public/build/assets
./src/public/build/assets/app-BE9zHJE-.css
./src/public/build/assets/app-BIJoUbyE.js
./src/public/build/manifest.json
./src/public/favicon.ico
./src/public/images
./src/public/images/auth-bg.jpg
./src/public/images/auth-bg.jpg:Zone.Identifier
./src/public/images/home_meeting.jpg
./src/public/images/home_meeting.jpg:Zone.Identifier
./src/public/images/kairox-logo.png
./src/public/images/kairox-logo.png:Zone.Identifier
./src/public/index.php
./src/public/robots.txt
./src/public/storage
./src/public/vendor
./src/resources
./src/resources/css
./src/resources/css/app.css
./src/resources/js
./src/resources/js/app.js
./src/resources/js/bootstrap.js
./src/resources/views
./src/resources/views/admin
./src/resources/views/admin/dashboard.blade.php
./src/resources/views/admin/employers
./src/resources/views/admin/employers/create.blade.php
./src/resources/views/admin/entitlements
./src/resources/views/admin/entitlements/index.blade.php
./src/resources/views/admin/entitlements/partials
./src/resources/views/admin/entitlements/partials/list.blade.php
./src/resources/views/admin/jobs
./src/resources/views/admin/jobs/index.blade.php
./src/resources/views/admin/jobs/partials
./src/resources/views/admin/jobs/partials/list.blade.php
./src/resources/views/admin/payments
./src/resources/views/admin/payments/index.blade.php
./src/resources/views/admin/payments/partials
./src/resources/views/admin/payments/partials/list.blade.php
./src/resources/views/admin/users
./src/resources/views/admin/users/index.blade.php
./src/resources/views/admin/users/show.blade.php
./src/resources/views/auth
./src/resources/views/auth/confirm-password.blade.php
./src/resources/views/auth/force-change-password.blade.php
./src/resources/views/auth/forgot-password.blade.php
./src/resources/views/auth/login.blade.php
./src/resources/views/auth/register.blade.php
./src/resources/views/auth/reset-password.blade.php
./src/resources/views/auth/verify-email.blade.php
./src/resources/views/components
./src/resources/views/components/application-logo.blade.php
./src/resources/views/components/auth-session-status.blade.php
./src/resources/views/components/danger-button.blade.php
./src/resources/views/components/dropdown-link.blade.php
./src/resources/views/components/dropdown.blade.php
./src/resources/views/components/input-error.blade.php
./src/resources/views/components/input-label.blade.php
./src/resources/views/components/layouts
./src/resources/views/components/layouts/portal.blade.php
./src/resources/views/components/likeslocale
./src/resources/views/components/likeslocale/button.blade.php
./src/resources/views/components/likeslocale/info-card.blade.php
./src/resources/views/components/likeslocale/progress-card.blade.php
./src/resources/views/components/likeslocale/stat-card.blade.php
./src/resources/views/components/likeslocale/status-pill.blade.php
./src/resources/views/components/modal.blade.php
./src/resources/views/components/nav-link.blade.php
./src/resources/views/components/notification-bell.blade.php
./src/resources/views/components/primary-button.blade.php
./src/resources/views/components/responsive-nav-link.blade.php
./src/resources/views/components/secondary-button.blade.php
./src/resources/views/components/text-input.blade.php
./src/resources/views/dashboard.blade.php
./src/resources/views/emails
./src/resources/views/emails/access
./src/resources/views/emails/access/activated.blade.php
./src/resources/views/emails/employer
./src/resources/views/emails/employer/job-approved.blade.php
./src/resources/views/emails/employer/new-applicant.blade.php
./src/resources/views/emails/employer/provisioned.blade.php
./src/resources/views/emails/jobseeker
./src/resources/views/emails/jobseeker/application-submitted.blade.php
./src/resources/views/employer
./src/resources/views/employer/applicants
./src/resources/views/employer/applicants/index.blade.php
./src/resources/views/employer/company-edit.blade.php
./src/resources/views/employer/dashboard.blade.php
./src/resources/views/employer/jobs
./src/resources/views/employer/jobs/create.blade.php
./src/resources/views/employer/jobs/edit.blade.php
./src/resources/views/employer/jobs/index.blade.php
./src/resources/views/employer/jobs/partials
./src/resources/views/employer/jobs/partials/form.blade.php
./src/resources/views/jobseeker
./src/resources/views/jobseeker/applications
./src/resources/views/jobseeker/applications/apply-custom.blade.php
./src/resources/views/jobseeker/applications/apply.blade.php
./src/resources/views/jobseeker/applications/index.blade.php
./src/resources/views/jobseeker/dashboard.blade.php
./src/resources/views/jobseeker/jobs
./src/resources/views/jobseeker/jobs/index.blade.php
./src/resources/views/jobseeker/jobs/partials
./src/resources/views/jobseeker/jobs/partials/results.blade.php
./src/resources/views/jobseeker/jobs/show.blade.php
./src/resources/views/jobseeker/profile
./src/resources/views/jobseeker/profile/edit.blade.php
./src/resources/views/layouts
./src/resources/views/layouts/app.blade.php
./src/resources/views/layouts/guest.blade.php
./src/resources/views/layouts/navigation.blade.php
./src/resources/views/locked
./src/resources/views/locked/employer.blade.php
./src/resources/views/locked/seeker.blade.php
./src/resources/views/notifications
./src/resources/views/notifications/index.blade.php
./src/resources/views/payments
./src/resources/views/payments/callback.blade.php
./src/resources/views/profile
./src/resources/views/profile/edit.blade.php
./src/resources/views/profile/partials
./src/resources/views/profile/partials/delete-user-form.blade.php
./src/resources/views/profile/partials/update-password-form.blade.php
./src/resources/views/profile/partials/update-profile-information-form.blade.php
./src/resources/views/public
./src/resources/views/public/apply.blade.php
./src/resources/views/public/pricing.blade.php
./src/resources/views/welcome.blade.php
./src/routes
./src/routes/auth.php
./src/routes/console.php
./src/routes/web.php
./src/storage
./src/storage/app
./src/storage/app/.gitignore
./src/storage/app/private
./src/storage/app/private/.gitignore
./src/storage/app/public
./src/storage/app/public/.gitignore
./src/storage/app/public/applications
./src/storage/app/public/applications/cover-letters
./src/storage/app/public/applications/cover-letters/QmmLne2WwaEmwfbyFBtHDtmYikxbtpsge8sNofCr.pdf
./src/storage/app/public/applications/resumes
./src/storage/app/public/applications/resumes/81GW1kKuGgVYOWMP5pKGZKUi7hyTsihApBpfgcfP.pdf
./src/storage/app/public/demo
./src/storage/app/public/demo/seeker-new-cover-letter.txt
./src/storage/app/public/demo/seeker-new-resume.txt
./src/storage/app/public/demo/seeker-pro-cover-letter.txt
./src/storage/app/public/demo/seeker-pro-resume.txt
./src/storage/app/public/jobseekers
./src/storage/app/public/jobseekers/cover-letters
./src/storage/app/public/jobseekers/cover-letters/jtROTqW14q2vqLMEiXEJ4I23Yxdw95tE059zsiWy.pdf
./src/storage/app/public/jobseekers/resumes
./src/storage/app/public/jobseekers/resumes/hoyXIRRbnWereAe3ZJz1QgapmwHclRAZGNyH4ats.pdf
./src/storage/framework
./src/storage/framework/.gitignore
./src/storage/framework/cache
./src/storage/framework/cache/.gitignore
./src/storage/framework/cache/data
./src/storage/framework/cache/data/.gitignore
./src/storage/framework/sessions
./src/storage/framework/sessions/.gitignore
./src/storage/framework/sessions/Vhfvd5B11FmBmlUw8aZXr8UwPQQETwQ7uWI3qcll
./src/storage/framework/sessions/vABOEe7Zxge0mDLQwbJFPsWIUHfasP5u3U1wd8bP
./src/storage/framework/testing
./src/storage/framework/testing/.gitignore
./src/storage/framework/views
./src/storage/framework/views/.gitignore
./src/storage/framework/views/0a1f6f8f00fcb58add1c15ce5872841a.php
./src/storage/framework/views/19d1ca22cd8db231f88e0685e9c3a20e.php
./src/storage/framework/views/64760f1de55f79c02d0eedfe7db33b6f.blade.php
./src/storage/logs
./src/tailwind.config.js
./src/tests
./src/tests/Feature
./src/tests/Feature/Auth
./src/tests/Feature/Auth/AuthenticationTest.php
./src/tests/Feature/Auth/EmailVerificationTest.php
./src/tests/Feature/Auth/PasswordConfirmationTest.php
./src/tests/Feature/Auth/PasswordResetTest.php
./src/tests/Feature/Auth/PasswordUpdateTest.php
./src/tests/Feature/Auth/RegistrationTest.php
./src/tests/Feature/ExampleTest.php
./src/tests/Feature/ProfileTest.php
./src/tests/TestCase.php
./src/tests/Unit
./src/tests/Unit/ExampleTest.php
./src/vendor
./src/vite.config.js

## Domain States

### Job Statuses
pending_review → published → archived

### Application Statuses
new → reviewed → shortlisted → rejected

### Entitlement Statuses
active | expired | revoked

### Payment Statuses
pending | completed | failed

## Entitlement Rules (CRITICAL)
- Entitlements are NEVER derived from payment records directly
- Payment → verified callback → entitlement activation (idempotent)
- Middleware checks: type + status + expiry
- Admin can manually grant/revoke
- Track entitlement_activated_at to prevent duplicates
- job_seeker entitlement gates: /jobseeker/jobs, applications
- employer entitlement gates: /employer/jobs/create, applicants

## Never Do This
- Never check access in Blade views or controllers
- Never hardcode status strings — use model constants
- Never use sudo with npm
- Never store cover letters as reusable profile assets
- Never activate an entitlement without checking for existing active one
- Never delete gateway payment records

## Test Accounts (pilot seed data)
admin@kairox.test / password
employer.active@kairox.test / password
employer.locked@kairox.test / password  
seeker.active@kairox.test / password
seeker.locked@kairox.test / password

## Current Focus (update this regularly)
- [ ] WiPay sandbox callback not activating entitlement correctly
- [ ] Email notifications not firing on application submit
- [ ] Mobile layout broken on /jobseeker/jobs filter bar
- [x] Role redirect after login — DONE

