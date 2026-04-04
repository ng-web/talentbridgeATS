# Likeslocale Core Components Inventory

This document tracks reusable UI, workflow, and backend patterns being developed inside this project so they can later be extracted into a Likeslocale Core starter system.

---

## Layouts

### Portal Shell
- File: `resources/views/components/layouts/portal.blade.php`
- Purpose: Shared authenticated portal shell with sidebar, responsive header, and mobile menu
- Reusable in:
  - job boards
  - ATS systems
  - vendor portals
  - admin dashboards
  - member portals
- Status: active

---

## Reusable Dashboard Patterns

### Dashboard Stat Cards
- Files:
  - `resources/views/jobseeker/dashboard.blade.php`
  - `resources/views/employer/dashboard.blade.php`
  - `resources/views/admin/dashboard.blade.php`
- Purpose: Summary metrics for role-specific dashboards
- Extraction target: `resources/views/components/likeslocale/stat-card.blade.php`
- Status: candidate

### Quick Actions Block
- Files:
  - seeker dashboard
  - employer dashboard
  - admin dashboard
- Purpose: Primary CTA section for common tasks
- Extraction target: `resources/views/components/likeslocale/quick-actions.blade.php`
- Status: candidate

---

## Reusable Form Patterns

### Portal Form Page
- Files:
  - `resources/views/employer/jobs/create.blade.php`
  - `resources/views/employer/jobs/edit.blade.php`
  - `resources/views/jobseeker/profile/edit.blade.php`
  - `resources/views/employer/company-edit.blade.php`
- Purpose: Rounded white card form layout inside portal shell
- Extraction target: `resources/views/components/likeslocale/form-page.blade.php`
- Status: candidate

---

## Reusable Status Patterns

### Status Pill
- Files:
  - admin jobs moderation
  - seeker applications
  - employer jobs
- Purpose: visually consistent job/application statuses
- Extraction target: `resources/views/components/likeslocale/status-pill.blade.php`
- Status: candidate

### Flash Banner
- File: `resources/views/components/layouts/portal.blade.php`
- Purpose: success/error feedback after actions
- Extraction target: `resources/views/components/likeslocale/flash-banner.blade.php`
- Status: active candidate

---

## Reusable Document Patterns

### Resume / Cover Letter Upload Panels
- File: `resources/views/jobseeker/profile/edit.blade.php`
- Purpose: reusable upload cards for profile documents
- Extraction target: `resources/views/components/likeslocale/upload-panel.blade.php`
- Status: candidate

### Employer Document Review
- File: `resources/views/employer/applicants/index.blade.php`
- Purpose: reusable document preview links for reviewing applicants
- Extraction target: `resources/views/components/likeslocale/document-links.blade.php`
- Status: candidate

---

## Reusable Backend Patterns

### Profile Completeness Calculator
- Files:
  - `app/Http/Controllers/JobSeeker/ProfileController.php`
  - `app/Http/Controllers/JobSeeker/DocumentController.php`
- Purpose: reusable completeness scoring logic for portal profiles
- Extraction target: `app/Support/Likeslocale/ProfileCompletenessService.php`
- Status: candidate

### Role-Based Dashboard Redirect
- File: `app/Http/Controllers/DashboardController.php`
- Purpose: route users into the correct portal area after login
- Extraction target: core auth starter
- Status: active

### Admin Moderation Workflow
- Files:
  - `app/Http/Controllers/Admin/JobController.php`
  - `resources/views/admin/jobs/index.blade.php`
- Purpose: reusable moderation flow for approval-based systems
- Reusable in:
  - job boards
  - vendor onboarding
  - marketplace listings
  - service provider directories
- Status: active candidate

---

## Future Extraction Plan

### Phase 1
Keep reusable pieces inside this project while patterns settle.

### Phase 2
Create a dedicated starter repo:
- `likeslocale-laravel-core`

### Phase 3
Optionally extract stable backend utilities into internal Composer packages.

---

## Newly Added Core Components

### Likeslocale Button Component
- File: `resources/views/components/likeslocale/button.blade.php`
- Purpose: shared primary, secondary, outline, and soft button pattern
- Reusable in:
  - dashboards
  - forms
  - listings
  - moderation panels
- Status: active

### Likeslocale Status Pill Component
- File: `resources/views/components/likeslocale/status-pill.blade.php`
- Purpose: shared branded and status pill presentation
- Reusable in:
  - jobs
  - applications
  - moderation
  - pipelines
- Status: active

### Employer Logo Upload Pattern
- Files:
  - `app/Http/Controllers/Employer/LogoController.php`
  - `resources/views/employer/company-edit.blade.php`
- Purpose: reusable company/provider logo upload system
- Reusable in:
  - job boards
  - vendor directories
  - service marketplaces
- Status: active candidate