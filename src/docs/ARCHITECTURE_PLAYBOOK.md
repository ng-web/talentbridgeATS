Reusable Architecture for Platforms, Portals, and SaaS Systems

Author: Likeslocale
Purpose: Standardize the design and development of scalable SaaS platforms.

This document defines the architecture patterns, development rules, and reusable modules used when building digital platforms under the Likeslocale ecosystem.

These standards ensure that every platform is:

scalable

modular

maintainable

pilot-ready

reusable across projects

Core Philosophy

Likeslocale builds solutions, not just websites.

Platforms should always be designed with these goals:

reusable architecture

modular components

centralized domain logic

scalable workflows

operational clarity

pilot readiness

SaaS product potential

Every system should be evaluated as if it could become:

a reusable internal framework

a white-label product

a future SaaS platform

Standard SaaS Platform Layers

Every platform should follow this layered architecture.

Public Layer
Authentication Layer
Role Portal Layer
Business Domain Layer
Integration Layer
Infrastructure Layer
Public Layer

Purpose:
Public marketing or entry layer.

Typical features:

landing pages

platform overview

pricing

login / registration

onboarding entry

Key rules:

lightweight

branded

clear call-to-action

SEO-friendly when needed

Common components:

hero sections

CTA blocks

pricing tables

feature highlights

authentication entry points

Authentication Layer

Purpose:
Secure access control.

Standard approach:

Laravel authentication

role-based access

session-based auth

optional social login

Roles should be managed through:

Spatie Laravel Permission

Example roles:

admin
employer
job_seeker
vendor
manager
client
operator
Role Portal Layer

After authentication, users should enter a role-specific portal.

Each role should have its own dashboard.

Example:

/admin/dashboard
/employer/dashboard
/jobseeker/dashboard

Rules:

dashboards must be role-specific

minimal cross-role confusion

simple navigation

Business Domain Layer

This layer represents the actual system logic.

Examples:

job marketplace

vendor system

applicant tracking

bookings

listings

workflows

Domain models should be clearly defined and centralized.

Example domain entities:

User
Employer
JobSeeker
Job
Application
Payment
Entitlement
Program
Vendor
Listing
Booking
Integration Layer

External services integrate here.

Examples:

payment gateways

email systems

AI services

third-party APIs

Integrations should be implemented through:

Service classes

Example:

App\Services\WiPayHostedCheckoutService

Never place API logic directly in controllers.

Infrastructure Layer

Infrastructure concerns include:

Docker containers

web servers

queue workers

storage

environment configuration

Preferred infrastructure:

Docker
Nginx
MySQL
Redis (optional)

Deployment targets may include:

Vultr

DigitalOcean

AWS

Domain Status Registry Pattern

One of the most important architecture rules.

Statuses must never be hardcoded in views.

Statuses must always live in models or enums.

Example:

class Application
{
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PLACED = 'placed';
    public const STATUS_REJECTED = 'rejected';
}

Views should reference:

Application::STATUSES

Controllers should validate against:

Rule::in(Application::STATUSES)

Seeders should use:

Application::STATUS_APPLIED

This prevents status drift.

Reusable Dashboard System

All Likeslocale platforms should share a reusable dashboard component system.

Core components:

Stat Cards

Used for metrics.

Examples:

Jobs Published

Applications Received

Profile Completion

Active Listings

Structure:

title
metric value
icon
optional trend
Progress Cards

Used for onboarding or profile completion.

Example:

Profile Completion
85%

Often displayed as:

progress bar

circular progress

checklist

Status Pills

Small visual indicators.

Used for:

application status

job status

payment status

entitlement status

Example:

Approved
Interview
Pending Review
Info Cards

Used for:

platform tips

instructions

announcements

Often appear on dashboards.

List Interaction Pattern

Lists should follow a consistent pattern.

Example:

jobs list
applications list
users list
vendors list

Rules:

entire row clickable

status visible

primary action clear

secondary actions available

Access Control Pattern

Access should not be based solely on roles.

Use entitlements for gated features.

Example:

job_seeker_access
employer_posting_access
vendor_access
premium_access

Access logic:

User -> Entitlements -> isActive()

Middleware checks entitlement validity.

Payment Architecture

Payment systems should follow this structure.

Payment Record
↓
External Gateway
↓
Callback Verification
↓
Entitlement Activation

Payment records store:

order_id
gateway
external_reference
amount
status
raw_payload

Entitlements unlock platform features.

Seed Data Pattern

Every platform should include pilot demo data.

Seeder should create:

demo users

demo records

records in multiple states

realistic workflow scenarios

Example accounts:

admin@test.com
employer@test.com
user@test.com

Purpose:

platform demos

testing

QA

Pilot Readiness Requirements

A system is considered pilot-ready when:

users can complete full workflows

demo data exists

dashboards show meaningful data

mobile layout works

errors handled gracefully

access control works

payments testable

Deployment Readiness

Deployment considerations:

APP_URL
ASSET_URL
HTTPS enforcement
storage linking
queue workers
build assets

Common issues to check:

mixed content errors

asset URL mismatches

payment callback URLs

environment config caching

Refactor Discipline

When refactoring code:

Never blindly replace models.

Always verify:

relationships

helper methods

accessors

middleware dependencies

Example mistake avoided:

Removing:

Entitlement::isActive()

which may break middleware.

Command Support Standard

When providing implementation guidance, always include:

File changes

Exact file paths.

Commands to run

Examples:

php artisan optimize:clear
php artisan view:clear
php artisan migrate
docker compose restart app
Environment Strategy

Platforms must support:

Local development
Tunnel testing
Production deployment

Example:

localhost
ngrok
live domain

Environment variables should control behavior.

Example:

FORCE_HTTPS=true
Future Platform Enhancements

Common future modules:

Messaging systems

Employer ↔ applicant chat.

AI assistance

Matching or recommendation engines.

Analytics dashboards

Admin platform insights.

Multi-tenant SaaS

Convert system to tenant-based architecture.

Likeslocale Design Principle

Every platform should aim to be:

simple for users

powerful for operators

scalable for business

reusable for the agency

Platforms should evolve from:

Client Solution
↓
Reusable Template
↓
Agency Product
↓
Full SaaS Platform
Using This Playbook

When starting a new project:

Use the following context:

Use the Likeslocale SaaS Architecture Playbook.

Design and build the platform using the reusable patterns defined in this document.

Combine with:

PROJECT_CONTEXT.md

for project-specific information.

What This Gives You

By maintaining this playbook, Likeslocale now has:

A standard SaaS architecture framework

Reusable across:

job platforms

vendor systems

marketplaces

operational portals

knowledge platforms

AI-assisted systems