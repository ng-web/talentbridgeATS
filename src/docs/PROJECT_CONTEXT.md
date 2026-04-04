Kairox Exchange Platform
Overview

Kairox Exchange is a role-based opportunity platform connecting job seekers, employers, and administrators through a structured workflow for work, study, and travel opportunities.

The system supports:

opportunity publishing

applicant management

entitlement-based access

payment integration

administrative moderation

role-based dashboards

The platform is designed as a pilot-ready MVP with architecture that supports future expansion into a scalable SaaS platform.

This system is being developed by Likeslocale, a digital solutions agency focused on building reusable SaaS platforms and operational systems.

Technology Stack
Backend

Laravel 12

PHP 8.4+

MySQL

Infrastructure

Docker

Nginx

PHP-FPM

Frontend

TailwindCSS

Alpine.js

Heroicons

Authentication

Laravel Auth

Spatie Laravel Permission (roles)

Payment Gateway

WiPay Hosted Checkout

Email

Laravel Mail

Core System Architecture
User Roles
Admin

Platform administrators responsible for moderation and management.

Capabilities:

approve opportunities

manage users

manage entitlements

manage payments

view platform metrics

moderate applications

Employer

Organizations posting opportunities and managing applicants.

Capabilities:

create opportunities

edit opportunities

review applicants

update applicant status

manage company profile

Job Seeker

Applicants seeking opportunities.

Capabilities:

create profile

browse opportunities

apply for opportunities

track application status

Core Domain Models
User

Represents authenticated users.

Relationships:

hasOne Employer

hasOne JobSeeker

hasMany Entitlements

hasMany Payments

Employer

Represents companies or organizations posting opportunities.

Relationships:

belongsTo User

hasMany Jobs

JobSeeker

Represents applicants.

Relationships:

belongsTo User

hasMany Applications

Job

Represents opportunities on the platform.

Attributes:

employer_id

program_id

title

slug

description

listing_type

category

employment_type

location

country

status

is_approved

remote_flag

Relationships:

belongsTo Employer

belongsTo Program

hasMany Applications

Application

Represents an application submitted by a job seeker.

Attributes:

job_id

job_seeker_id

status

applied_at

submitted_resume_path

submitted_cover_letter_path

Relationships:

belongsTo Job

belongsTo JobSeeker

Entitlement

Controls platform access and permissions tied to payments.

Attributes:

user_id

type

status

starts_at

expires_at

source

notes

Relationships:

belongsTo User

Includes helper method:

isActive()

Used by middleware to determine access permissions.

Payment

Represents a financial transaction.

Attributes:

user_id

gateway

entitlement_type

order_id

external_ref

currency

amount

status

raw_payload

paid_at

Payments activate entitlements.

Program

Represents work/travel programs associated with jobs.

Domain Status Systems

Statuses are centralized in model constants to avoid duplication across controllers, views, and seeders.

Job Status
draft
pending_review
published
archived
Application Status
applied
reviewing
interview
approved
placed
rejected
Entitlement Status
active
inactive
expired
revoked
Workflow Overview
Employer Job Flow
Employer creates job
↓
Admin reviews job
↓
Job published
↓
Job seekers apply
↓
Employer reviews applicants
Application Pipeline
Applied
↓
Reviewing
↓
Interview
↓
Approved
↓
Placed

Rejected applications exit the pipeline.

Payment / Access Flow
User selects pricing
↓
Redirect to WiPay hosted checkout
↓
Payment processed
↓
Callback to platform
↓
Payment recorded
↓
Entitlement activated
↓
User access unlocked
Access Control

Platform access is controlled through Entitlements.

Examples:

job_seeker_access
employer_posting_access

Middleware checks:

User -> Entitlements -> isActive()

to determine access.

Dashboard Architecture

Dashboards use reusable UI components.

Examples:

Stat Cards

Reusable dashboard metrics.

Examples:

Published Opportunities

Applications Submitted

Applicants Received

Profile Completion

Progress Cards

Used for:

profile completion

onboarding progress

Status Pills

Reusable status indicators.

Used for:

application status

job status

entitlement status

UI Design Principles

The platform prioritizes:

clarity

role-based simplicity

responsive design

strong visual hierarchy

clickable list rows

clear call-to-action buttons

minimal cognitive load

Design elements include:

consistent iconography

dashboard cards

progress indicators

pipeline status indicators

Pilot Demo Data

The project includes a PilotDemoSeeder that generates:

Users

Admin

admin@kairox.test
password

Employer with access

employer.active@kairox.test
password

Employer without access

employer.locked@kairox.test
password

Job seeker with access

seeker.active@kairox.test
password

Job seeker without access

seeker.locked@kairox.test
password
Seeder Purpose

Seed data provides:

testable dashboards

jobs in multiple states

applications in multiple states

active and inactive entitlements

realistic demo records

Development Environment
Local Environment
Docker
Nginx
MySQL
Laravel

App URL

http://localhost:8080
Public Testing

Public testing uses:

ngrok

Used for:

external tester access

payment gateway callbacks

Payment Integration

Payment gateway:

WiPay Hosted Checkout

Sandbox testing supported.

Flow:

Create payment record
↓
Generate hosted checkout URL
↓
Redirect user to WiPay
↓
User completes payment
↓
WiPay redirects to response URL
↓
Payment verified
↓
Entitlement activated
Deployment Targets

Future hosting options under consideration:

Vultr

DigitalOcean

AWS

Deployment expected to support:

containerized infrastructure

Nginx

queue workers

SSL

Development Rules
Domain logic

Never hardcode domain states in views.

Statuses must always come from model constants.

Refactoring

Do not replace models without verifying existing relationships and helper methods.

Merge improvements carefully.

Controllers

Controllers must validate against centralized constants.

Views

Views must not contain duplicated domain logic.

Seeders

Seeders must reference model constants.

Pilot Readiness Criteria

The system is considered pilot-ready when:

all user roles can complete workflows

seeded data allows realistic testing

payment flow can be tested in sandbox

dashboards display meaningful metrics

UI is responsive and branded

errors are handled gracefully

Future Expansion

Potential future enhancements include:

Applicant pipeline UI

Kanban-style pipeline for employers.

Messaging system

Employer ↔ applicant communication.

Reporting

Admin platform analytics.

Notification system

Email and in-platform alerts.

AI assistance

Applicant matching and recommendations.

SaaS productization

Convert platform to multi-tenant SaaS.

Likeslocale Architectural Philosophy

This platform follows the Likeslocale Modular SaaS Architecture Model, emphasizing:

reusable modules

centralized domain rules

scalable workflows

pilot-first development

real-world usability

Future platforms should reuse these architectural patterns whenever possible.

How to Use This File in Future AI Chats

When starting a new development conversation, provide this file as context:

Example:

Use the following project context:

[paste PROJECT_CONTEXT.md]

Help continue development of the Kairox Exchange platform.