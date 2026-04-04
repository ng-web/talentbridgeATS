# Pilot QA Checklist

## Access and Roles
- [ ] Admin can log in
- [ ] Employer can log in
- [ ] Job seeker can log in
- [ ] Role redirects work correctly

## Seeker Access
- [ ] Seeker without entitlement is redirected to locked page
- [ ] Seeker with entitlement can browse jobs
- [ ] Seeker can upload resume
- [ ] Seeker can upload cover letter
- [ ] Seeker cannot apply twice
- [ ] Seeker cannot apply without resume
- [ ] Seeker sees submitted applications

## Employer Access
- [ ] Employer without entitlement is redirected to locked page
- [ ] Employer with entitlement can create jobs
- [ ] Employer can edit jobs
- [ ] Employer can upload logo
- [ ] Employer can view applicants
- [ ] Employer can update applicant status

## Admin Operations
- [ ] Admin can approve jobs
- [ ] Admin can archive jobs
- [ ] Admin can set jobs pending
- [ ] Admin can create entitlements
- [ ] Admin can revoke entitlements
- [ ] Admin can record payments
- [ ] Paid payments activate entitlements

## Notifications
- [ ] Seeker gets application submitted email
- [ ] Employer gets new applicant email
- [ ] Employer gets job approved email
- [ ] User gets access activated email

## Payments
- [ ] Manual payment saves correctly
- [ ] Manual payment creates entitlement
- [ ] WiPay seeker payment route opens
- [ ] WiPay employer payment route opens
- [ ] WiPay callback updates payment
- [ ] WiPay callback activates entitlement on verified success

## UI / UX
- [ ] Seeker listing hover feels correct
- [ ] Employer jobs list matches design language
- [ ] Admin jobs list matches design language
- [ ] Mobile sidebar works
- [ ] Buttons are visually consistent