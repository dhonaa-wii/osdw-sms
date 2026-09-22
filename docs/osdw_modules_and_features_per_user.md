# OSDW Scholarship Management System — Modules & Features per User

**System scope:** One campus. OSDW handles all review. No SIAS/portal integration. Web-based, Laravel.

**Users:**
1. Student / Applicant (public, no account — reference-authenticated)
2. OSDW Staff
3. System Administrator

---

# 1. STUDENT / APPLICANT

No registration required. Access to their own records is through Student ID + Reference Number, delivered on screen and by email.

## 1.1 Public Browsing (no authentication)

| Feature | Description |
|---|---|
| View open programs | List of scholarships currently accepting applications |
| Program details | Requirements, eligibility, slots available, deadline, amount or discount |
| Filter and search programs | By college eligibility, provider, type, funding source |
| View accepted applicants | Published list per program |
| View announcements | General and per-program |
| Search own name or reference | On any published list |

## 1.2 Application Submission

| Feature | Description |
|---|---|
| Select program | Only from currently open offerings |
| Fixed core fields | Student ID, full name, sex, program, college (auto-derived), year level, email, mobile, address — always collected |
| Conditional core fields | GWA and monthly income are collected only when the offering's eligibility rules actually check them (`field = gwa` / `field = income`) — not every scholarship is academic- or need-based, so these aren't forced on every applicant |
| Dynamic custom fields | Additional fields defined by staff per program — text, number, date, dropdown, radio, checkbox |
| Dynamic document upload | Upload slots generated from the program's requirement list, each with its own label |
| File validation | Format and size checked before submission |
| Eligibility preview | Warns the student if they appear not to qualify before submitting |
| Consent checkbox | Consent to publication of name in official postings |
| Submit and receive reference | Reference number shown on confirmation page and emailed |
| Apply to multiple programs | Permitted; existing applications are shown as a reminder |

## 1.3 Application Tracking (reference-authenticated)

| Feature | Description |
|---|---|
| Login by Student ID + Reference | No password, no account |
| View all own applications | Every program applied to, each with its own status |
| View status timeline | Submitted, screened, shortlisted, result — with dates |
| View screening result | Specific reason if declared ineligible |
| Re-upload documents | When flagged incomplete and the window is still open |
| Documents needed | While shortlisted, the offering's additional requirements with what to do for each — upload it here, or submit the original at the OSDW office |
| Withdraw application | Voluntary cancellation |
| Recover reference number | Via Student ID + last name + birthdate |

## 1.4 Award

| Feature | Description |
|---|---|
| Receive award notice | Email and dashboard notification |
| Accept or decline award | Explicit action with a deadline; declining frees the slot. Acceptance activates the award immediately — requirements are already verified at screening, there is no separate post-award checklist |
| Download acknowledgment | Proof of application or award |

## 1.5 Scholar View

| Feature | Description |
|---|---|
| View grant details | Program, amount or discount rate, term covered, status |
| Submit grades for renewal | GWA and units per term |
| Apply for renewal | Simplified form for existing scholars |
| View award history | All terms received |

---

# 2. OSDW STAFF

Full operational access. Reviews all applicants across all colleges.

## 2.1 Dashboard

- Applications received, screened, shortlisted, awarded — current term
- Programs open and closing soon
- Pending tasks: unreviewed applications, unverified documents
- Slot utilization per program

## 2.2 Applicant Review

| Feature | Description |
|---|---|
| Application queue | Filter by program, college, status, screening result |
| Review workspace | Document viewer beside applicant data |
| Multi-application flag | Badge showing the applicant's other applications and awards — red for active award, amber for pending, gray for history |
| Applicant history | All programs, all terms, all outcomes |
| Document verification | Mark each document verified or rejected with remarks |
| Physical copy tracking | Mark original received for notarized documents |
| Endorse or reject | With mandatory remarks |
| Bulk actions | Bulk verify, bulk notify incomplete applicants |

## 2.3 Screening

| Feature | Description |
|---|---|
| Automated completeness check | All mandatory documents present |
| Automated eligibility check | GWA, year level, college, program, income against program rules |
| Duplicate detection | By Student ID, across programs and terms |
| Per-rule results | Each failed rule recorded with a message |
| Manual override | Pass an applicant who failed a check, with reason logged |
| Screening report | Most common disqualification reasons |

## 2.4 Shortlist

| Feature | Description |
|---|---|
| Shortlist | Applicants staff have endorsed, ordered by submission date — no automated scoring or ranking |
| Additional requirements | Set once per offering, applied to every shortlisted applicant — each item is marked upload or physical copy |
| Requirement tracking | Staff tick off a physical copy at the counter; an upload ticks itself when the applicant's file lands, and staff can only untick it |
| Outstanding-requirement block | Prevents awarding an applicant with an additional requirement still outstanding, from any screen |
| Slot counter | Used and remaining, per program and per college |
| Over-allocation block | Prevents awarding beyond configured slots |
| Export shortlist | For committee deliberation |

## 2.5 Award Processing

| Feature | Description |
|---|---|
| Record awards | Within slot limits |
| Conflict alert | Warns if the applicant holds another active award |
| Send award notices | Email to awardees |
| Track acceptance | Pending, accepted, declined, forfeited — acceptance activates the award immediately |

## 2.6 Scholar Monitoring

| Feature | Description |
|---|---|
| Active scholar roster | Per term, per program, per college |
| Grade encoding | GWA, units, failing grade flag |
| Retention evaluation | Against program retention rules |
| Status management | Active, probation, terminated, graduated — with reason |
| Renewal processing | Review and approve renewal applications |

## 2.7 Announcements and Publishing

| Feature | Description |
|---|---|
| Post announcements | General or per-program |
| Publish accepted applicants | Control what appears on the public page |
| Unpublish or expire | Auto-hide lists after the relevant date |

## 2.8 Reports

| Feature | Description |
|---|---|
| SUC-Levelling summary | Exact column format, one-click Excel export, filtered by term |
| Scholars per college | Counts and amounts |
| Scholars per funding source | Counts and amounts |
| Application funnel | Received, eligible, shortlisted, awarded, accepted |
| Retention report | Continuing versus terminated scholars across terms |
| Multiple-award report | Students holding more than one grant this term |
| Disqualification report | Most frequent failure reasons |
| Custom date range export | Any report, any term |

---

# 3. SYSTEM ADMINISTRATOR

Full operational access, in addition to master data, program configuration, and system settings. Reviews and can act on anything OSDW Staff can, plus the oversight powers in 3.6.

## 3.1 Master Data

- Colleges
- Programs / courses
- Campuses
- Academic terms — open, set current, close
- Providers — name, contact, and funding source (national/local government, national/local non-government, in-house, academic, non-academic)

## 3.2 Program Configuration

| Feature | Description |
|---|---|
| Create scholarship program | Name, provider (funding source is derived from the provider), category, description |
| Open a term offering | Slots, open and close dates, amount or discount rate. Starts pre-filled from the program configuration and can be edited for that term |
| College scope | Open to all colleges, or restricted to selected colleges. Set on the program; each offering inherits it and may override |
| Allocation mode | Open competition, per-college quota, or proportional. Set on the program; each offering inherits it and may override |
| Screening mode | Auto-reject on failure, or flag only |
| Form field builder | Add any field to the application form — text, textarea, number, date, select, checkbox, radio, or file — with label, required flag, display order, and (for file fields) mandatory/physical-copy/format/size limits. A live preview shows the form as the applicant will see it. Available on the program (the template) and on each offering (its own copy) |
| Eligibility rule builder | GWA, year level, college, program, income conditions. Same two levels as the form field builder |
| Carry configuration forward | Automatic — opening an offering copies the program's current configuration into it. Replaces the former "clone offering" action |

## 3.3 User Management

- Create, edit, deactivate users
- Assign roles
- Reset passwords

## 3.4 System Settings

- Application limit per student per term
- Acceptance deadline default
- Email configuration and templates
- Public page visibility settings
- Maximum upload size

## 3.5 Maintenance

- Audit log viewer with filters
- Email delivery log
- Database backup and restore
- Activity monitoring

## 3.6 Oversight

Powers beyond day-to-day Staff review, folded in from the retired OSDW Head role.

| Feature | Description |
|---|---|
| Final award approval | Authority to confirm awards |
| Terminate a scholarship | With documented reason |
| Approve manual eligibility overrides | Staff-requested exceptions |
| Full audit log access | All decisions, all users |
| Executive dashboard | Budget utilization, slot utilization, trends across terms |

---

# 4. CROSS-CUTTING FEATURES

| Feature | Applies to |
|---|---|
| Email notifications | Reference number, screening result, award notice, deadline reminders — all logged |
| Audit trail | Every create, update, approve, and override with user, timestamp, and IP |
| Role-based access control | Enforced at route and view level |
| Data privacy compliance | Consent at collection, tiered visibility, search-engine exclusion on public lists |
| Responsive design | Students will apply and check results on phones |
| File storage | Organized per applicant and requirement, with size and format enforcement |

---

# 5. OUT OF SCOPE

State these explicitly in Chapter 1 under Scope and Delimitation.

- Integration with SIAS or the school portal — student data is self-encoded and staff-verified
- Payment gateway or actual fund disbursement — the system records awards and discount rates only
- Accounting ledger — Finance marks discounts as applied outside the system
- SMS notifications — email only, unless a gateway budget is available
- Multi-campus consolidation — single campus
- Native mobile application — responsive web only
