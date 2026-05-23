# Kairox Exchange Platform — Project Context
**Last updated: May 2026 — Post-pilot MVP complete**

---

## Overview

Kairox Exchange is a role-based opportunity placement platform connecting job seekers, employers, and administrators through a structured workflow for work, study, and travel opportunities.

The system is built and maintained by Likeslocale as both a client solution and a reusable ATS/marketplace framework.

Core capabilities:
- Opportunity publishing with admin review and approval
- Applicant management and pipeline tracking
- Entitlement-based access gating (employer posting, seeker browsing)
- Payment integration via WiPay Hosted Checkout
- Administrative moderation and reference data management
- Role-based dashboards and portal UX
- In-app notification system

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11, PHP 8.4+ |
| Database | MySQL |
| Frontend | TailwindCSS, Alpine.js, Heroicons |
| Auth | Laravel Auth + Spatie Laravel Permission |
| Payment | WiPay Hosted Checkout |
| Email | Laravel Mail |
| Infrastructure | Docker, Nginx, PHP-FPM |
| Dev tunneling | ngrok (for payment callbacks during development) |

---

## User Roles

### Admin
Platform administrators responsible for moderation and management.

Capabilities:
- Approve, archive, or return jobs to pending review
- Manage user accounts and provision new employer accounts
- Record and confirm payments
- Manually grant and revoke entitlements
- Manage reference data (countries, locations, categories, employment types)
- View platform-wide metrics and Action Required notifications
- Issue temporary passwords to provisioned accounts

### Employer
Organizations posting opportunities and managing applicants.

Capabilities:
- Create and edit job listings (submitted for admin review)
- Manage company profile and logo
- Review applicants across all their listings
- Update application statuses (reviewed → shortlisted → rejected)

Access gated by: `employer_posting_access` entitlement

### Job Seeker
Applicants seeking opportunities.

Capabilities:
- Create and update profile
- Upload default resume
- Browse approved published job listings
- Apply for jobs (with resume and optional cover letter)
- Track and withdraw applications

Access gated by: `job_seeker_access` entitlement

---

## Core Domain Models

### User
Represents all authenticated platform users.

Relationships:
- `hasOne Employer`
- `hasOne JobSeeker`
- `hasMany Entitlements`
- `hasMany Payments`
- `hasMany Notifications`

### Employer
Represents companies or organizations posting opportunities.

Relationships:
- `belongsTo User`
- `hasMany Jobs`

Attributes include: `company_name`, `logo_path`, `description`, `website`

### JobSeeker
Represents applicants.

Relationships:
- `belongsTo User`
- `hasMany Applications`

Attributes include: `first_name`, `last_name`, `location`, `bio`, `default_resume_path`

### Job
Represents opportunities on the platform.

Key attributes:
- `employer_id`, `program_id`
- `title`, `slug`, `description`
- `listing_type` — from `Job::LISTING_TYPES` constant
- `category` — from `job_categories` table
- `employment_type` — from `employment_types` table
- `country` — from `countries` table
- `location` — from `locations` table (scoped to country)
- `status` — from `Job::STATUSES` constant
- `is_approved` (boolean)
- `remote_flag` (boolean)
- `salary_min`, `salary_max`, `fees`
- `duration`, `application_deadline`, `eligibility`

Relationships:
- `belongsTo Employer`
- `belongsTo Program`
- `hasMany Applications`

### Application
Represents a job seeker's submission for a specific job.

Key attributes:
- `job_id`, `job_seeker_id`
- `status` — from `Application::STATUSES` constant
- `applied_at`
- `submitted_resume_path` (snapshot at time of submission)
- `submitted_cover_letter_path` (specific to this application)

Relationships:
- `belongsTo Job`
- `belongsTo JobSeeker`

Note: submitted documents are stored on the application record, not the profile.
The application is the source of truth for what was actually submitted.

### Entitlement
Controls platform feature access.

Key attributes:
- `user_id`
- `type` — `job_seeker_access` or `employer_posting_access`
- `status` — `active`, `expired`, `revoked`
- `starts_at` (nullable — entitlement not valid before this date)
- `expires_at`
- `source` — `payment`, `admin_grant`
- `notes`

Helper method: `isActive()` — used by middleware.

Middleware checks ALL of: `type`, `status`, `expires_at >= now()`, `starts_at <= now()`.

### Payment
Represents a financial transaction.

Key attributes:
- `user_id`, `plan_id`
- `gateway` (e.g. `wipay`)
- `entitlement_type`
- `order_id`, `external_ref`
- `currency`, `amount`
- `status` — `pending`, `completed`, `failed`, `review_required`
- `raw_payload` (gateway callback snapshot)
- `paid_at`
- `entitlement_activated_at` — idempotency guard; prevents duplicate entitlement activation

### Country / Location / JobCategory / EmploymentType
Admin-managed reference data tables that back all categorical dropdowns.

- `countries`: `id`, `name` (unique), `is_active`
- `locations`: `id`, `country_id` (FK), `name`, `is_active` — unique per country
- `job_categories`: `id`, `name` (unique), `is_active`
- `employment_types`: `id`, `name` (unique), `is_active`

These tables prevent free-text inconsistency. Validation uses `Rule::exists()` against these tables. Managed at `/admin/reference-data`.

### Program
Represents work/travel programs that jobs may be associated with.

---

## Domain Status Systems

All statuses are centralized in model constants. Never hardcode status strings anywhere.

### Job Status
```
pending_review → published → archived
```
Constants: `Job::STATUS_PENDING_REVIEW`, `Job::STATUS_PUBLISHED`, `Job::STATUS_ARCHIVED`

### Application Status
```
new → reviewed → shortlisted → rejected
```
Constants: `Application::STATUS_NEW`, etc.

### Entitlement Status
```
active | expired | revoked
```

### Payment Status
```
pending | completed | failed | review_required
```

---

## Core Workflows

### Employer Job Posting Flow
```
Employer creates job (form with DB-driven dropdowns)
    ↓
Job saved as pending_review, is_approved = false
    ↓
Admin sees job in Action Required feed
    ↓
Admin approves → status = published, is_approved = true
    ↓
Job visible to job seekers with active access
```

### Application Pipeline
```
Job Seeker applies (resume snapshot + optional cover letter)
    ↓
Status: new
    ↓
Employer reviews → reviewed
    ↓
Employer shortlists → shortlisted
    ↓
Placed or Rejected
```

### Payment / Access Flow
```
User selects pricing plan
    ↓
Payment record created (status: pending)
    ↓
Redirect to WiPay hosted checkout
    ↓
User completes payment
    ↓
WiPay POSTs callback to platform
    ↓
Platform verifies signature
    ↓
Payment updated (status: completed)
    ↓
Entitlement activated (idempotent — checks entitlement_activated_at)
    ↓
Middleware grants access
```

---

## Reference Data (Seeded)

**Countries (4):** Jamaica, United States, Canada, United Kingdom

**Locations (46):**
- Jamaica (14): Kingston, Montego Bay, Ocho Rios, Negril, Spanish Town, Portmore, Mandeville, May Pen, St. Ann's Bay, Falmouth, Black River, Savanna-la-Mar, Port Antonio, Linstead
- USA (16): Orlando FL, Miami FL, Cape Cod MA, New York NY, Los Angeles CA, Chicago IL, Virginia Beach VA, Myrtle Beach SC, Ocean City MD, Williamsburg VA, Rehoboth Beach DE, Wildwood NJ, Bar Harbor ME, Aspen CO, Jackson Hole WY, Hilton Head SC
- Canada (8): Toronto ON, Vancouver BC, Whistler BC, Banff AB, Montreal QC, Ottawa ON, Niagara Falls ON, Halifax NS
- UK (8): London, Edinburgh, Manchester, Liverpool, Brighton, Oxford, Cambridge, Bristol

**Job Categories (16):** Hospitality & Hotels, Food & Beverage, Customer Service, Entertainment & Recreation, Childcare, Retail & Sales, Landscaping & Grounds, Agriculture, Amusement & Theme Parks, Administration, Transportation, Program Operations, Music & Entertainment, Sports & Fitness, Healthcare Support, Other

**Employment Types (5):** Full Time, Part Time, Seasonal, Contract, Temporary

---

## Pilot Demo Accounts

| Role | Email | Password | State |
|------|-------|----------|-------|
| Admin | admin@kairox.test | password | Full access |
| Employer | employer.active@kairox.test | password | Active posting access |
| Employer | employer.locked@kairox.test | password | No posting access (locked) |
| Job Seeker | seeker.active@kairox.test | password | Active browse access |
| Job Seeker | seeker.locked@kairox.test | password | No access (locked) |

---

## Suggested Demo Flow

1. **Admin** logs in → sees Action Required feed → approves a published job
2. **Employer (active)** logs in → creates a new job listing → sees it as Pending Review
3. **Admin** approves the job
4. **Job Seeker (active)** logs in → browses jobs → applies with resume
5. **Employer** logs in → sees new applicant → updates status to Shortlisted
6. **Job Seeker** logs in → sees status updated in applications list
7. **Employer (locked)** logs in → tries to create a job → sees locked upgrade screen
8. **Admin** manually grants entitlement → employer is immediately unlocked

---

## Development Environment

- Local: `http://localhost:8080`
- Docker container: `talentbridge-portal-app-1`
- Public testing: ngrok (required for WiPay payment callbacks)

### Key Commands
```bash
# After pulling or deploying
docker exec talentbridge-portal-app-1 php artisan migrate --force
docker exec talentbridge-portal-app-1 php artisan db:seed --class=ReferenceDataSeeder --force
docker exec talentbridge-portal-app-1 php artisan optimize:clear
docker exec talentbridge-portal-app-1 php artisan view:clear
docker exec talentbridge-portal-app-1 php artisan view:cache
docker exec talentbridge-portal-app-1 php artisan storage:link
```

---

## Key Architecture Decisions

1. **Reference data is DB-driven** — all categorical dropdowns (country, location, category, employment type) come from admin-managed tables. No free-text fields for categorical data.

2. **Entitlements are separate from payments** — access is determined by the entitlement record, never by querying payments directly.

3. **Middleware enforces all access** — `EnsureActiveEmployerPostingAccess` and `EnsureActiveSeekerAccess` check type, status, `expires_at`, and `starts_at`. UI never makes access decisions.

4. **Documents are scoped to workflows** — submitted resume and cover letter are stored on the Application record, not the JobSeeker profile. The application is the source of truth.

5. **One controller per resource per role** — `Admin\JobController`, `Employer\JobController`, `JobSeeker\JobController` are separate. No cross-role logic in a single controller.

6. **Alpine.js JSON injection** — all JSON data passed to Alpine.js `x-data` is placed in a `<script>` tag, never inside a double-quoted HTML attribute.

---

## Future Expansion

Planned for Phase 2 and 3:

- **Applicant pipeline UI** — Kanban-style view for employers
- **Messaging system** — Employer ↔ applicant communication
- **Reporting dashboard** — Admin analytics (applications per job, conversion rates, placement rates)
- **Email digest notifications** — Weekly summaries for employers and seekers
- **AI matching** — Recommend jobs to seekers based on profile, recommend candidates to employers
- **Multi-tenant SaaS** — White-label the platform for other work/travel program operators
- **Self-serve billing** — Stripe integration, subscription management, automatic entitlement renewal
- **Mobile app** — React Native or Expo companion for job seekers

---

## Using This File in Future AI Sessions

Paste this file at the start of any new development session:

```
Use the following project context for the Kairox Exchange platform:
[paste PROJECT_CONTEXT.md]

Also use the Likeslocale Master App Architecture Framework v4 for all architecture and implementation decisions.

Current task: [describe what you need to build or fix]
```
