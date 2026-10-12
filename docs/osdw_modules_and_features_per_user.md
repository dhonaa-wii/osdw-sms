# OSDW Scholarship Management System — Modules & Features per User

**System scope:** One campus. OSDW handles all review. No SIAS/portal integration. Web-based, Laravel.

**Users:**
1. Student / Applicant (student account, tied to their email)
2. OSDW Staff
3. System Administrator

Everyone signs in from the same `/login` page and lands on their own home: students on My applications, staff and administrators on the dashboard.

---

# 1. STUDENT / APPLICANT

Browsing is public. Applying, tracking and everything after need a student account. A student only ever sees their own records — there is no search over other applicants.

## 1.0 Account

| Feature | Description |
|---|---|
| Register | Email first; a 6-digit code is mailed to it (expires in 10 minutes, 5 wrong tries, a new code after 60 s) and must be entered before the account exists. Then a password, separate from the email's own password (8+ characters) |
| Continue with Google | Signs in or signs up with Google — Google has already verified the email, so no code is needed. An existing account with the same email is linked, not duplicated. Shown only when a Google client ID is configured |
| Set up profile | Right after the account is made: Student ID, name, sex, birthdate, mobile, address. The Student ID is unique across the system and cannot be changed afterwards; a Student ID already registered to another account is refused with a "contact the OSDW office" message. Until it is done every student page sends them here |
| Log in | Email and password, or Google. "Keep me signed in". A student sent to log in from an Apply button returns to that Apply page afterwards |
| Forgot password | Email, then the 6-digit code mailed to it, then a new password. The page never says whether an account uses the email |
| Settings | Edit name, sex, birthdate, mobile and address (Student ID and email are read-only); change the password, or set one for an account made with Google |

## 1.1 Public Browsing (no authentication)

| Feature | Description |
|---|---|
| View programs | Only offerings open for registration right now — inside their application window and under any application cap. Closed and upcoming offerings are not listed and their detail pages return "not found". Signed-in students browse the same list |
| Program details | Requirements, eligibility, slots available, opening date, deadline (no award amount — public pages never show it). "Apply now" sends a guest to log in or create an account first; a student who already applied sees "View your application" and its status |
| Filter and search programs | By provider and type |
| View published results | Published list per offering: the awardees who consented to publication, then "N more awardees chose not to have their names published" — counted, never named |
| View announcements | General announcements on the landing page; an offering's announcements on its detail page and on its applicants' application pages |

## 1.2 Application Submission (student account)

| Feature | Description |
|---|---|
| Select program | Only from currently open offerings |
| Identity from the profile | Student ID, name, sex, birthdate, email, mobile and address come from the student's profile and are shown read-only, with a link to Settings to correct them |
| Core fields | College, year level and family monthly income, always collected |
| Conditional core fields and caps | GWA is asked only on an academic offering. An offering with a family income cap refuses an income above it at submission; the cap is never shown to students (not on the scholarship page, the form, or the error message) |
| Dynamic custom fields | Additional fields defined by staff per program — text, number, date, dropdown, radio, checkbox |
| Dynamic document upload | Upload slots generated from the program's requirement list, each with its own label |
| File validation | Format and size checked before submission |
| Eligibility preview | Warns the student if they appear not to qualify before submitting |
| Required GWA (academic offerings) | An academic offering's application cannot be submitted with a GWA below the offering's required GWA — the GWA field shows the rule's message and the form stops before review and again on submit (`EligibilityService::unmetGwaRule()`, also enforced in `ApplicationService::submit()`). No application row is created |
| Consent checkbox | Consent to publication of name in official postings |
| Submit application | On submit, the student is taken straight to that application's page |
| Apply to multiple programs | Permitted; their existing applications are shown as a reminder. Opening Apply on an offering they already applied to goes to that application instead |
| One national scholarship | A grantee of a national scholarship (National Government / National Non-Government provider) can't apply to another national one — the scholarship page shows why instead of Apply now — but can apply to local ones. Two national applications may run at once; once one is awarded, the other's application page says it can't be awarded |

## 1.3 My Applications (student account)

| Feature | Description |
|---|---|
| My applications | Every application and renewal of the student, newest first, filtered by a status group: All, In progress (submitted, under review, shortlisted, waitlisted), Awarded (awarded or renewed), Not approved (denied) — each with a count |
| Application page | Opens one application, same design as before: status headline, award, screening notes, additional requirements, interview, status timeline, and the offering's announcements |
| View what was submitted | The submitted form (college, year level, GWA, income, custom answers, publication consent) and documents, read-only |
| View screening result | Specific reason if declared ineligible |
| Additional requirements | Each requirement for the applicant's current stage (shortlist while shortlisted, award release once awarded) with its instructions and state: to submit, under review, returned (with staff remarks), complied. A requirement with a blank form (e.g. a contract) has a download link so the applicant can fill it in first. Upload requirements take the file right there; physical-copy requirements tell the applicant to bring it to the OSDW office |
| Interview notice | While shortlisted, if the offering requires an interview: date, time, venue and instructions, as set on the offering's Requirements page |
| Renewal | When admin opens renewals, the scholar's renewal appears in My applications (they are also notified and emailed). The page lists the offering's renewal requirements with their instructions and state: upload ones take the file right there, physical-copy ones tell the scholar to bring it to the OSDW office, and a blank form can be downloaded. The scholar also picks their year level this term from a dropdown and submits it once — it is view-only afterwards. It reads "Hand in your requirements to renew your scholarship" until the year level is in and everything is complied, then "Your renewal is under review", then renewed or not approved. Each renewal upload is submitted once — after that the scholar can only view the file. A returned upload shows the staff remarks and can be uploaded again |

## 1.4 Notifications (student account)

A bell in the header with an unread count, a dropdown of the latest notifications, "Mark all as read", and a Notifications page with the full list. Clicking a notification marks it read and opens where it points. The bell refreshes every minute.

| Notification | Who gets it | Opens |
|---|---|---|
| Status changed | The applicant, whenever staff move their application (shortlisted, awarded/renewed, waitlisted, not approved with the reason, back under review) or open their renewal. Their own submission is not notified | Their application |
| Disbursed | The grantee, when staff record their award as disbursed (one by one or with "Disburse all") | Their application |
| Requirements to complete | Shortlisted applicants when staff open the shortlist requirements; awardees when staff open the disbursement requirements (only if the stage has requirements) | Their application |
| Interview scheduled / moved | Shortlisted applicants, when the interview date or venue is set or changed. Someone shortlisted later sees the interview in their "shortlisted" notification | Their application |
| Announcement | An offering's announcement: everyone who applied to it, whatever their status. A general announcement: every student (and it shows on the landing page) | Their application / the landing page's announcements |
| Results are out | Everyone who applied to the offering, the first time its results are published | The offering's published results |
| Now open | Every student, once, when an offering opens for registration | The offering's details |
| Registration closed | Every student, once, when an open offering stops taking applications — at its deadline or as soon as it reaches its application cap | Their application if they applied, otherwise the program list |

Emails are unchanged and sent alongside: award/renewal notices, renewal opened, and incomplete-application reminders, each linking to the student's application page (they log in first).

## 1.5 Award

| Feature | Description |
|---|---|
| Receive award notice | Email and bell notification |
| View award | Read-only — program and date awarded (the amount is not shown on the application page). No accept/decline action; a recorded award is final |
| View publish instructions | Once OSDW publishes the offering's results, any note staff wrote for awardees appears on the applicant's own application page |
| Download acknowledgment | Proof of application or award |

---

# 2. OSDW STAFF

Full operational access. Reviews all applicants across all colleges.

## 2.0 Account

| Feature | Details |
|---|---|
| First log-in | An account made by the administrator opens a setup page before anything else: set your own password (different from the temporary one), then connect your Google account (only when Google sign-in is configured). Every other staff page sends you back there until both are done |
| Profile | From the account menu: edit your name, change your password (current password required), connect or switch your Google account. The email is changed only by the administrator. Administrators have the same page |
| Log in | Email and password, or Google once connected |

## 2.0a Notifications

A bell in the top bar with an unread count, a dropdown of the latest notifications, "Mark all as read", and a Notifications page with the full list — the same design as the student bell. Clicking a notification marks it read and opens where it points. The bell refreshes every minute. Every active staff and administrator account gets each one.

| Notification | When | Opens |
|---|---|---|
| New scholarship program | The administrator adds a scholarship program | Administrator: the program's offerings · Staff: Applications |
| Now open | An offering that takes new applicants opens for registration | The offering's workspace |
| Registration closed | That offering reaches its deadline or application cap, with the number of applications to review | The offering's workspace |
| Interview scheduled / moved | The offering's interview date or venue is set or changed | The offering's workspace |

## 2.1 Dashboard

- Search scholarship programs (by program or provider name) and providers; a program opens its review pages (admin: its offerings page), a provider opens its page for an administrator
- Applications received, screened, shortlisted, awarded — current term
- Daily activity chart (received vs. screened), last 7 / 14 / 30 days
- Application status breakdown and slots filled — current term
- Programs open and closing soon, with slot utilization per offering
- Pending tasks: unreviewed applications, unverified documents, offerings at their application cap — plus shortcuts to Applications, Grantees, Reports and Announcements
- Recent awards
- Administrator additionally sees the executive cards: budget utilization per provider, slots offered vs. awarded across the latest terms, and record counts

## 2.2 Applicant Review

| Feature | Description |
|---|---|
| Applications list | One row per group — the first offering, with a count of the renewal offerings that continue it and the total applications across them. Review opens the latest offering of the group; the review pages carry a tab for each offering in the group (newest four shown, "View all" reveals older ones) |
| Application queue | Filter by program, college, status, screening result |
| Review workspace | Document viewer beside applicant data |
| Multi-application flag | Badge showing the applicant's other applications and awards — red for awarded, amber for still pending, gray for history |
| Scholarships held | Card on the application review, renewal review and grantee pages listing the scholarships the student holds in other programs with their funding source; queue and shortlist rows flag "Grantee elsewhere" or "National grantee". A national grantee's application to another national scholarship can't be endorsed or awarded |
| Inline documents | One Documents card grouped as Application documents, Additional requirements and Disbursement requirements (Renewal requirements on a renewal). Every uploaded document and requirement is shown in place at full height on the review pages, one after another, with Verify / Reject beside its name and a button to open it full screen |
| Applicant history | All programs, all terms, all outcomes |
| Document verification | Mark each document verified or rejected with remarks |
| Physical copy tracking | Mark original received for notarized documents |
| Endorse or reject | With optional remarks, shown to the applicant in their status history |
| Bulk actions | Bulk verify, bulk notify incomplete applicants |

## 2.3 Screening

| Feature | Description |
|---|---|
| Automated completeness check | All mandatory documents present |
| Automated eligibility check | GWA, year level, college, program, income against program rules. Failed rules are recorded for staff; an academic offering's required GWA and an offering's family income cap are enforced earlier, at submission |
| Duplicate detection | By Student ID, across programs and terms |
| Per-rule results | Each failed rule recorded with a message |
| Manual override | Pass an applicant who failed a check, with reason logged |

## 2.4 Shortlist

| Feature | Description |
|---|---|
| Shortlist | Applicants staff have endorsed, ordered by submission date — no automated scoring or ranking |
| Open shortlist requirements | While the offering is taking applications, applicants are asked for the application form only. "Open shortlist requirements" on the Shortlist tab shows the shortlist requirements to every shortlisted applicant on their application page. Enabled once the shortlist (plus anyone already awarded) holds at least as many applicants as there are slots — the shortlist may hold more. Awarding waits until they are opened and complied |
| Interview | "Schedule interview" (administrator) opens the Interview section of the offering's Requirements page. Once scheduled, the date, venue and instructions show on the Shortlist tab and on every shortlisted applicant's application page |
| Requirement progress | When the offering has shortlist requirements, each row shows how many are complied (e.g. 1/3) and links to the review page |
| Verify additional requirements | On the review page: view and verify or return each upload (with remarks), tick physical copies received |
| Slot counter | Used and remaining, per program and per college |
| Over-allocation block | Prevents awarding beyond configured slots |
| Export shortlist | For committee deliberation |

## 2.5 Award Recording

| Feature | Description |
|---|---|
| Award directly from the shortlist | Within slot limits, and only once every shortlist requirement is complied. Recording is immediate and final — no accept/decline step, no distribution processing |
| Conflict alert | Warns if the applicant holds another award |
| Send award notices | Email to awardees |
| Publish results | On the Grantees page, filtered to an offering: once every slot is awarded, publish the roster to the public site with a written note for awardees; edit the note or unpublish at any time. The public Published results page lists one row per published offering (scholarship · offering, provider, awardee count, date); each opens that offering's page — the note first, then its awardees in a compact table (#, name, college) with a name search |

## 2.5a Renewal Verification

| Feature | Description |
|---|---|
| Renewals page | One page per renewal offering (`staff.renewals.index`), separate from the queue/shortlist workspace — the Applications list opens it for a renewal offering. Lists every renewing scholar with student ID, college, the year level they entered this term (flagged "Last term" at 4th year in a second-semester term), how many renewal requirements are complied and how many uploads wait for review, and their status; tabs for renewal review, renewed, denied or all, and search by name or student ID. When the offering takes new applicants, a "New applications" tab comes first and opens its queue/shortlist workspace |
| Renewal review page | "Review" on a scholar's row opens their renewal on its own page (`staff.renewals.review`), the same way the queue and shortlist open an application's review page: scholar details and year level, other scholarship applications, each renewal requirement to verify or return (uploads) and mark received (physical copies), the decision, and the history |
| Renew scholarship | Records the award and emails the scholar. Takes no slot — every scholar still enrolled renews. Blocked until every renewal requirement is complied and the scholar has entered their year level |
| Deny renewal | With a required reason — graduated, has a failing grade, or no longer enrolled. Denying is how a renewal chain ends |

## 2.6 Grantees

| Feature | Description |
|---|---|
| Grantee roster | One row per grantee per offering group, at their latest award — a scholar renewed term after term is listed once, at the newest renewal offering. Columns: applicant, sex, college, year level, scholarship (program and offering name, e.g. "ACEF-GIAHEP Grant-in-Aid · 2nd sem"), type, funding source, campus, amount, disbursement requirements (a "Disbursed" stamp once disbursed), and View |
| Search | By name or student ID |
| Program and offering filters | "All programs" lists every scholarship program; picking one lists its offerings, one entry per offering group (the first offering and its renewals together). Picking an offering group shows its offerings as tabs, opening on the latest one, and lists the grantees awarded in the chosen offering. Also filterable by college |
| Publish and open disbursement requirements | Shown once the page is filtered to an offering that takes new applicants: "Publish results" (2.5) and "Open disbursement requirements", which shows the disbursement requirements to every awardee on their application page. Both are enabled only once every slot is awarded. "Disburse all" records every grantee of the offering as disbursed; it is enabled only once every slot is awarded and every grantee has every disbursement requirement complied (uploads verified, physical copies received) |
| Disbursement requirements | Per grantee, how many of the offering's disbursement (award-stage) requirements are complied and how many uploads wait for review. Clicking it opens the submitted requirements: staff and admin view each upload, verify it or return it with a reason, and mark physical copies received. A disbursement upload is submitted once; returning it lets the grantee upload a corrected file from their application page |
| View | Opens the grantee's page (`staff.grantees.show`), view only: the student information the system collected, then for the original application and each renewal in the group, the application form (answers and documents), shortlist and disbursement requirements (or renewal requirements), and the award |
| Renewed tag | Awards recorded through a renewal are marked "Renewed" |

The system screens and records. Its only per-term check is renewal (2.5a): each term, the scholar hands in the renewal requirements and staff verify them once. It does not process distribution of the award — that happens outside the system. Disbursement (award-stage) requirements only record whether the grantee handed in what the release needs.

## 2.7 Announcements and Publishing

| Feature | Description |
|---|---|
| Post announcements | General or per-program; saved as a draft or published. The list searches by title and filters All / Published / Drafts |
| Publish accepted applicants | From an offering's Shortlist tab, once its slots are full — see 2.5. Names (consenting applicants only, with a count of awardees who chose not to be listed) and the staff-written instructions appear on a dedicated public page for that offering, linked from the landing page and from the Published results list of offerings |
| Unpublish or expire | Auto-hide lists after the relevant date |

## 2.8 Reports

| Feature | Description |
|---|---|
| SUC-Levelling summary | The only report on the page. Exact column format, one-click Excel export and a print view of the rows currently shown |
| Filters | The same as the Grantees page (2.6) and the same rows: search by name or student ID, program → offering group (tabs for the group's offerings, opening on the latest), and college. With no offering chosen, each grantee's latest award per offering group |

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
| Create scholarship program | Name, provider (funding source is derived from the provider), description |
| Standard requirements | A button on Scholarship programs opens one page with two sections: the standard application form (same field builder) and the standard disbursement requirements (same requirement editor, after-awarding stage only). No shortlist requirements. Changes are copied into every offering that uses them and has no applications yet |
| Requirements source | On an offering's Requirements page (when it takes new applicants): "Standard requirements" or "Set manually" (default). Choosing Standard asks for confirmation, then replaces the offering's application form and disbursement requirements with copies of the standard ones and shows them read-only, with a link to edit the standard requirements; the required GWA, shortlist requirements and interview stay editable. Only possible before the first application. "Set manually" keeps the copied items and makes them editable |
| Open a term offering | Type of scholarship, slots, open and close dates, fund amount, maximum applications and maximum family monthly income (both blank for no cap; the income cap is stored as an `income` / `lte` eligibility rule and only applies to offerings that take new applicants) |
| Offerings list | One row per group — the first offering, with a count of the renewal offerings that continue it. Opening it shows the group's page with a tab for the first offering and each renewal offering |
| Add renewal offering | On a group's page: opens the offering form already linked to the latest offering of the group, starting with its amount. It keeps the type of scholarship of the offering it renews (no type field). Instead of an application window it sets a submission window — "Submission starts" and "Submission due" (stored in `opens_at` / `closes_at`) — for scholars to upload renewal requirements; uploads are refused outside it. By default a renewal offering takes only that offering's awardees, so it has no slots, application form, college scope or allocation, and is not open for applications. Ticking "Open to new applicants" adds new-applicant slots, cap, college scope and allocation, and opens it for applications within the same window; the slots start at the suggested number — scholars of the renewed offering in their last term (4th year, second semester). Graduating scholars still renew |
| Requirements page | One page per offering with three sections: Application form, Additional requirements, Interview. Form fields are listed as cards with a type icon and summary (type, required, formats, physical copy) and expand to edit in place; system fields and the required GWA sit beside them. A renewal offering's page has only its renewal requirements (plus the three usual sections when it takes new applicants): a summary of the submission window, the offering it renews and the requirement count, the requirement list, and a preview of what renewing scholars see |
| Edit or delete an offering | A menu (⋮) on each offering's tab — on the offering page and on the staff review pages — with "Edit offering" (opens the offering form) and "Delete offering"; the offerings list has a Delete action per row too. Delete only works while the offering holds no renewals or applications and no renewal offering continues it (`DeletionService::deleteOffering()`); afterwards the page moves to the offering it renewed, or to the program's offerings. Administrator only |
| Delete scholarship program | A Delete action on each row of Scholarship programs. Only while none of its offerings holds applications; its offerings are deleted with it (`DeletionService::deleteProgram()`) |
| Renewal requirements | On a renewal offering: what scholars hand in to renew — label, instructions, upload / physical copy / both, optional blank form. Every one must be complied before staff can renew |
| Open renewals | On a renewal offering's page: lists the scholars awarded in the offering it renews who have no renewal yet, and opens a renewal for all of them in one action, emailing each. Safe to run again — scholars who already have one are skipped |
| Additional requirements | Pick the stage first — while shortlisted (must be complied before awarding) or after awarding (for the award release) — then the label, instructions, and how it is submitted: upload, physical copy, or both (with accepted formats and max size for uploads). Optionally attach a blank form (PDF or Word) that applicants download from their application page, fill in and hand back. Reorder, edit, or remove per stage. Configured per offering |
| Interview | Toggle whether shortlisted applicants must attend an interview; when on, set one date and time, a venue, and instructions. A preview shows what applicants see on their application page |
| College scope | Open to all colleges, or restricted to selected colleges. Set on the program; each offering inherits it and may override |
| Allocation mode | Open competition, per-college quota, or proportional. Set on the program; each offering inherits it and may override |
| Screening mode | Auto-reject on failure, or flag only |
| Form field builder | Add any field to the application form — text, textarea, number, date, select, checkbox, radio, or file — with label, required flag, display order, and (for file fields) mandatory/physical-copy/format/size limits. A live preview shows the form as the applicant will see it. Configured per offering — a new offering starts blank |
| Eligibility rule builder | GWA, year level, college, program, income conditions. Configured per offering, same as the form field builder |

## 3.3 User Management

- Add OSDW Staff accounts (name and email). The system generates a temporary password, shown to the administrator once to hand over
- Edit name and email, deactivate / activate
- Reset password: generates a new temporary password, shown once; the staff member must change it at their next log-in
- Accounts that have not finished first-login setup are marked "Setup pending"

## 3.4 System Settings

- Deleted items: deleted scholarship programs and offerings, each with the days left and a Restore action. Restoring a program brings back the offerings deleted with it. Anything deleted more than 60 days ago is removed for good
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
| Email notifications | Screening result, award notice, deadline reminders — all logged |
| Audit trail | Every create, update, approve, and override with user, timestamp, and IP |
| Role-based access control | Enforced at route and view level |
| Data privacy compliance | Consent at collection, tiered visibility, search-engine exclusion on public lists |
| Responsive design | Students will apply and check results on phones |
| File storage | Organized per applicant and requirement, with size and format enforcement |

---

# 5. OUT OF SCOPE

State these explicitly in Chapter 1 under Scope and Delimitation.

- Integration with SIAS or the school portal — student data is self-encoded and staff-verified
- Payment gateway or actual fund disbursement — the system records award amounts and when an award was disbursed only
- Accounting ledger — Finance marks disbursements as applied outside the system
- SMS notifications — email only, unless a gateway budget is available
- Multi-campus consolidation — single campus
- Native mobile application — responsive web only
