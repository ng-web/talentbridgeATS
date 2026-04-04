We are continuing development of the Kairox Exchange Laravel platform.

Context:
- Laravel 12
- Dockerized environment
- Nginx + PHP-FPM
- MySQL
- Tailwind UI
- Role-based dashboards (Admin, Employer, Job Seeker)
- Entitlements system
- WiPay integration
- Domain status constants implemented for Jobs, Applications, Entitlements
- Reusable dashboard components (stat cards, progress cards, status pills)

Current stage:
The app is pilot-ready and we are preparing for deployment, sandbox payment testing, and platform refinements.

Please follow the Likeslocale Master App Architecture Framework v2 for all recommendations.

We want:
- safe refactors
- centralized domain logic
- reusable components
- exact files and commands when changes are needed
- deployment awareness


Project Summary:

App: Kairox Exchange
Type: Job + Work Study Opportunity Platform

Stack:
Laravel 12
Docker
Nginx
MySQL
Tailwind
Alpine
Heroicons

Core Models:
User
Employer
JobSeeker
Job
Application
Payment
Entitlement
Program

Core Workflows:
Employer posts job
Admin approves job
Job seeker applies
Employer updates application status
Payment unlocks entitlements

Key Design Decisions:
- centralized status constants in models
- reusable dashboard components
- entitlement-based access control
- WiPay hosted checkout
- seeded pilot data

Current Status:
Pilot-ready MVP
Preparing for sandbox payment testing and production deployment


Next task:
[describe next task]