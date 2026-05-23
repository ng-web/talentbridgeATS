# Likeslocale SaaS Architecture Playbook
**v2 — Updated from live production build: Kairox Exchange**

Author: Likeslocale
Purpose: Standardize the design and development of scalable SaaS platforms.

This document defines the architecture patterns, development rules, reusable modules, and hard-won lessons used when building digital platforms under the Likeslocale ecosystem.

---

## Core Philosophy

Likeslocale builds solutions, not websites.

Every platform must be:
- Scalable from pilot to production
- Modular enough to reuse across projects
- Maintainable by any Likeslocale engineer
- Pilot-ready from day one
- Architected toward SaaS productization

Every system should be evaluated as if it could become:
- A reusable internal framework
- A white-label product
- A future SaaS platform

---

## Standard SaaS Platform Layers

Every platform follows this six-layer architecture.

### Layer 1 — Public Layer
Marketing or entry layer. Lightweight, branded, clear CTA.

Components: landing page, pricing, feature highlights, login/register entry.

### Layer 2 — Authentication Layer
Secure access control using Laravel Auth + Spatie Laravel Permission.

Roles: `admin`, `employer`, `job_seeker`, `vendor`, `manager`, `client`, `operator` (use what applies).

Always implement:
- Role-based redirect after login (never a generic `/dashboard` for all roles)
- Forced password change for provisioned/admin-created accounts
- Session-based auth (social login optional at Phase 2)

### Layer 3 — Role Portal Layer
After login, users enter a role-specific portal.

Routes: `/admin/dashboard`, `/employer/dashboard`, `/jobseeker/dashboard`

The portal shell is a single reusable Blade component parameterized by `portalRole`. Navigation links are conditional inside the shared shell. Never build a separate layout per role — parameterize one.

### Layer 4 — Business Domain Layer
Core entities, statuses, workflows, ownership rules. All domain logic centralized in model constants.

### Layer 5 — Integration Layer
Payment gateways, email, AI services, external APIs. Always implemented as Service classes under `App\Services\`. Never put API logic in controllers.

### Layer 6 — Infrastructure Layer
Docker, Nginx, MySQL, Redis (optional), queue workers. Config via `.env` only. Never hardcode environment-specific behavior.

---

## Domain Status Registry Pattern

**The most important architecture rule.**

Status strings must never be scattered across controllers, views, forms, or seeders.

Every domain entity with a status must define:

```php
class Job extends Model
{
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_PUBLISHED      = 'published';
    const STATUS_ARCHIVED       = 'archived';

    const STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    const STATUS_LABELS = [
        self::STATUS_PENDING_REVIEW => 'Pending Review',
        self::STATUS_PUBLISHED      => 'Published',
        self::STATUS_ARCHIVED       => 'Archived',
    ];

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public static function toneFor(string $status): string
    {
        return match($status) {
            self::STATUS_PUBLISHED      => 'green',
            self::STATUS_PENDING_REVIEW => 'yellow',
            self::STATUS_ARCHIVED       => 'gray',
            default                     => 'gray',
        };
    }
}
```

| Consumer | Uses |
|----------|------|
| Views | `Job::STATUS_LABELS`, `Job::labelFor()`, `Job::toneFor()` |
| Controllers | `Rule::in(Job::STATUSES)` |
| Seeders | `Job::STATUS_PENDING_REVIEW` |
| Middleware | `Job::STATUS_PUBLISHED` |

This prevents status drift. If a status string changes, it changes in one place.

---

## Reference Data Architecture

**Lesson from production:** Free-text categorical fields cause permanent data inconsistency. "St. Elizabeth" ≠ "st elizabeth" in filters, reports, and validation. This is unfixable without a migration.

### The Rule
Any field where values must be consistent and selectable must be backed by a database table with admin CRUD management.

Never use free-text inputs for: countries, regions/cities, categories, industry sectors, types, plan names.

### Standard Schema
```php
// Simple lookup table
$table->id();
$table->string('name')->unique();
$table->boolean('is_active')->default(true);
$table->timestamps();

// Hierarchical lookup (e.g. locations under countries)
$table->foreignId('country_id')->constrained()->cascadeOnDelete();
$table->string('name');
$table->unique(['country_id', 'name']);
```

### Validation Pattern
```php
'category' => ['nullable', Rule::exists('job_categories', 'name')->where('is_active', true)],
'country'  => ['required', Rule::exists('countries', 'name')->where('is_active', true)],
'location' => ['nullable', 'string', 'max:255'], // validated separately against country
```

For hierarchical validation (location must belong to selected country):
```php
if (!empty($validated['location'])) {
    $countryId = Country::where('name', $validated['country'])->value('id');
    $valid = Location::where('country_id', $countryId)
        ->where('name', $validated['location'])
        ->where('is_active', true)
        ->exists();
    if (!$valid) {
        return back()->withErrors(['location' => 'Invalid location for selected country.'])->withInput();
    }
}
```

### Admin CRUD
A single `Admin\ReferenceDataController` manages all lookup tables. One view at `/admin/reference-data` with add/remove panels per table.

---

## Alpine.js + Blade Integration Standards

**Lesson from production:** `@json()` inside double-quoted HTML attributes embeds literal `"` characters that break HTML attribute parsing. The browser sees the first `"` inside the JSON as the closing delimiter. Alpine.js never receives the full data. Silent failure — no error shown.

### JSON Injection Rule

```blade
{{-- NEVER: double-quotes in JSON break the HTML attribute --}}
<div x-data="{ items: @json($items) }">

{{-- ALWAYS: script tag is safe for JSON --}}
<script>const __myData = @json($data);</script>
<div x-data="{ items: __myData }">
```

Use a descriptive `__double_underscore_prefixed` const to avoid naming collisions.

### Cascading Dropdown Pattern

Minimize the Alpine payload — pluck only what the template needs:

```php
// Controller — produces {"Jamaica": ["Kingston", "Montego Bay"], ...}
'locations' => Location::where('is_active', true)
    ->with('country')
    ->orderBy('name')
    ->get()
    ->groupBy('country.name')
    ->map(fn($g) => $g->pluck('name')),
```

```blade
{{-- View --}}
<script>const __jobLocations = @json($locations);</script>
<div
    x-data="{
        country: '{{ old('country') }}',
        allLocations: __jobLocations,
        get filteredLocations() {
            return this.allLocations[this.country] ?? [];
        }
    }"
>
    <select name="country" x-model="country">
        @foreach($countries as $c)
            <option value="{{ $c->name }}" @selected(old('country') === $c->name)>
                {{ $c->name }}
            </option>
        @endforeach
    </select>

    <select name="location">
        <option value="">Select location</option>
        {{-- loc is a plain string since we pluck('name') --}}
        <template x-for="loc in filteredLocations" :key="loc">
            <option :value="loc" :selected="loc === '{{ old('location') }}'" x-text="loc"></option>
        </template>
    </select>
</div>
```

### Blade Expression Syntax

Always use double closing braces. A single `}` causes a 500 compilation error.

```blade
{{ old('field') }}          ✅ correct
{{ old('field') }           ✗ causes "Unclosed '(' does not match '}'" error
```

After Blade changes, verify compilation:
```bash
docker exec app php artisan view:clear
docker exec app php artisan view:cache  # no output = clean
```

---

## Payment, Access, and Gating Architecture

### The Access Flow

```
Payment Record → Gateway → Callback Verification → Entitlement Activation → Middleware → UX Access
```

**Never:** Payment → UI access directly. UI hiding is not access control.

### Rules

- Entitlements are separate database records from payments
- Access is never determined from payment records
- Middleware enforces access — not controllers, not views
- All entitlement fields checked: `type`, `status`, `expires_at >= now()`, `starts_at <= now()`
- Entitlement activation is idempotent via `entitlement_activated_at`

### Race Condition Prevention

```php
DB::transaction(function () use ($payment) {
    $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();

    if ($locked->entitlement_activated_at !== null) {
        return; // Already activated — idempotent exit
    }

    Entitlement::create([...]);
    $locked->update(['entitlement_activated_at' => now()]);
});
```

### Middleware Template

```php
$hasAccess = Entitlement::where('user_id', $user->id)
    ->where('type', 'employer_posting_access')
    ->where('status', 'active')
    ->where('expires_at', '>=', now())
    ->where(function ($q) {
        $q->whereNull('starts_at')
          ->orWhere('starts_at', '<=', now());
    })
    ->exists();
```

Missing `starts_at` check is a common gap — always include it.

---

## Controller and Route Hygiene

**Lesson from production:** A dead controller with dangerous auto-approve logic existed alongside the real controller. It was never wired to routes but represented a significant future risk.

### Rules

1. One controller per resource per role. Never two controllers handling the same resource.
2. Run `php artisan route:list` and audit every route→controller mapping before adding features.
3. Delete dead controllers, methods, and views immediately. Never leave unreachable code.
4. Admin-only state transitions (approve, archive) belong exclusively in admin controllers.
5. Employer/user-facing controllers must never auto-approve or change admin-controlled statuses.

### Naming Convention
```
Admin\JobController      → approve, archive, setPending
Employer\JobController   → create, store, edit, update (status set to pending_review only)
JobSeeker\JobController  → index, show
```

---

## Model and Database Standards

### Mass Assignment Audit

When adding a migration column, update `$fillable` AND `$casts` in the same commit.

Missing `$fillable` silently drops fields — form submits successfully but data never saves. No error is thrown.

```php
protected $fillable = [
    // Add new fields here at the same time as the migration
    'salary_min', 'salary_max', 'fees',
];

protected $casts = [
    'salary_min'  => 'integer',
    'salary_max'  => 'integer',
    'is_active'   => 'boolean',
    'remote_flag' => 'boolean',
    'expires_at'  => 'datetime',
];
```

### Soft Deletes

Use soft deletes on records with financial, audit, or referential significance: payments, entitlements, applications, user profiles.

### Migration Discipline

```bash
# Always use --force in Docker production containers
docker exec app php artisan migrate --force
```

Verify migration ran: check row counts or use `php artisan db:table tablename`.

---

## Admin Dashboard — Action Required Pattern

Generic notification counts are useless in production. Replace with an actionable feed:

```
🔴  3 payments not yet activating entitlements   → /admin/payments?unactivated=1
🟡  5 jobs awaiting approval                      → /admin/jobs?status=pending_review
🟡  2 payments requiring manual review            → /admin/payments?status=review_required
🟡  4 entitlements expiring this week             → /admin/entitlements?expiring=1
```

Each item is a clickable `<a>` tag. Zero items = "All clear" state.

### Preserving Custom Filter Params in AJAX Forms

Custom params like `?expiring=1` must persist when other filter controls are used:

```blade
@if($filters['expiring'] ?? false)
    <input type="hidden" name="expiring" value="1">
@endif
```

Without this, clicking any other filter drops the custom param and reloads the unfiltered list.

---

## Reusable Dashboard Component System

All platforms share a standard dashboard component system.

| Component | Usage |
|-----------|-------|
| `x-likeslocale.stat-card` | Metric with icon and optional trend |
| `x-likeslocale.info-card` | Tips, instructions, announcements |
| `x-likeslocale.progress-card` | Profile completion, onboarding |
| `x-likeslocale.status-pill` | Status indicator with color from `toneFor()` |
| `x-likeslocale.button` | Primary, outline, accent, danger variants |

Status pills consume `toneFor()` from model constants — never hardcode colors in views.

---

## Document and Asset Ownership

| Type | Stored On | Label |
|------|-----------|-------|
| Default resume | User/JobSeeker profile | "Default Resume" |
| Submitted resume | Application record | "Submitted Resume" |
| Cover letter | Application record (always) | "Cover Letter" |
| Company logo | Employer profile | "Company Logo" |
| Bid attachment | Bid/quote record | "Bid Attachment" |

The workflow record is the source of truth for submitted documents. Do not assume a profile document is what was submitted. Store a copy at submission time.

---

## Observability and Logging

Log all critical flows:

| Event | Log Level |
|-------|-----------|
| Payment callback received | info |
| Payment callback verified/rejected | info/warning |
| Entitlement activated | info |
| Duplicate activation blocked | warning |
| Entitlement denied by middleware | info |
| Admin grant/revoke | info (AuditLog model) |
| Destructive admin action | info (AuditLog model) |

Each entry must include: `user_id`, resource ID, action, status before/after, timestamp.

---

## Pilot Data Requirements

Every platform must ship with a seeder that covers all roles and all states.

**Standard accounts:**
```
admin@[platform].test           / password
employer.active@[platform].test / password  (with entitlement)
employer.locked@[platform].test / password  (without entitlement)
seeker.active@[platform].test   / password  (with entitlement)
seeker.locked@[platform].test   / password  (without entitlement)
```

**Records to seed:**
- Domain records in all statuses
- Completed and pending workflows
- Active and expired entitlements
- Completed and pending payments
- Reference data (all lookup tables)
- Edge cases (expired access, rejected applications)

---

## Deployment Readiness

| Concern | Rule |
|---------|------|
| APP_URL | Must match serving URL exactly |
| Payment callbacks | Must be publicly accessible HTTPS (ngrok in dev) |
| Storage links | `php artisan storage:link` on every fresh environment |
| Config cache | `optimize:clear` after `.env` changes |
| Migrations | `--force` flag in Docker |
| Queue workers | Required for mail, scheduled jobs |
| Asset builds | `npm run build` before deployment |

### Standard Post-Deploy Sequence
```bash
docker exec app php artisan migrate --force
docker exec app php artisan db:seed --class=ReferenceDataSeeder --force
docker exec app php artisan optimize:clear
docker exec app php artisan view:clear
docker exec app php artisan view:cache
docker exec app php artisan storage:link
```

---

## Refactor Discipline

Before modifying any existing file:

1. Read the current file
2. Check `$fillable`, `$casts`, relationships, and helper methods
3. Check middleware that references this model or method
4. Check route names used by this controller
5. Check which views reference this data
6. Merge carefully — never overwrite blindly

Removing a method like `Entitlement::isActive()` silently breaks middleware that calls it. Grep for usages before deleting anything.

---

## Common Pitfalls

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| `@json()` in HTML attribute | Alpine silently fails, dropdowns empty | Use `<script>const __data = @json($data);</script>` |
| Single `}` in Blade `{{ expr }'` | 500 "Unclosed '(' does not match '}'" | Always use double `}}` |
| Missing `$fillable` field | Data silently dropped on save | Audit `$fillable` when adding migration columns |
| Dead controller | Wrong logic executed; security risk | Audit route list; delete dead controllers immediately |
| Missing `starts_at` check | Future-dated entitlements grant immediate access | Check all four entitlement fields in middleware |
| No idempotency guard | Duplicate callbacks create multiple entitlements | Check `entitlement_activated_at` before activating |
| Free-text categorical fields | Data inconsistency unfixable without migration | Use DB reference tables with `Rule::exists()` |
| Custom filter param dropped | Clicking filter loses custom context | Add hidden input to preserve params in AJAX forms |
| `loc.name` when data is string array | No options rendered | Use `loc` directly when locations are plucked strings |

---

## Pre-Ship Checklist

### Code
- [ ] All statuses use model constants — no hardcoded strings
- [ ] No `@json()` inside double-quoted HTML attributes
- [ ] All new model fields in `$fillable` and `$casts`
- [ ] All form fields have `@error` messages
- [ ] Route list audited — no dead or duplicate controllers

### Access and Security
- [ ] Middleware checks type, status, `expires_at`, and `starts_at`
- [ ] Entitlement activation is idempotent (`entitlement_activated_at` guard)
- [ ] No access control in Blade views
- [ ] No auto-approve in employer/user-facing controllers

### UX
- [ ] All categorical fields are dropdowns from DB reference tables
- [ ] Cascading dropdowns use `<script>` JSON approach
- [ ] Locked states have clear messaging and upgrade paths
- [ ] Empty states exist for all list views
- [ ] Mobile layout verified

### Data
- [ ] Reference data seeded
- [ ] Pilot demo data covers all roles and statuses
- [ ] Migrations run cleanly with `--force`
- [ ] Views compile cleanly with `view:cache`

### Deployment
- [ ] APP_URL matches serving URL
- [ ] Payment callback URL is public HTTPS
- [ ] `storage:link` done
- [ ] `optimize:clear` done

---

## The Productization Ladder

Every client solution should be architected toward this progression:

```
Client Solution (custom, bespoke)
    ↓
Reusable Template (same pattern, new branding)
    ↓
Agency Product (sold to multiple clients)
    ↓
White-Label Platform (clients resell it)
    ↓
Full SaaS Platform (self-serve, multi-tenant, recurring revenue)
```

Architecture decisions made at Step 1 determine how fast you reach Step 5.

---

*Likeslocale SaaS Architecture Playbook v2*
*Updated from live production build: Kairox Exchange / TalentBridge ATS*
