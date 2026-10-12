# OSDW Scholarship Management System — Database Schema

**Stack:** Laravel 11 / MySQL 8
**Conventions:** snake_case plural table names, `id` bigint auto-increment PK, `created_at` / `updated_at` on all tables, soft deletes (`deleted_at`) where noted.

---

## 1. Master Data

These tables replace the free-text columns that currently cause typos and inconsistent casing in the SUC-Levelling spreadsheet.

### `colleges`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | varchar(20) unique | CTE, CFAS, CBEA, CICS, CCJE, CIT, GS |
| name | varchar(150) | College of Teacher Education, etc. |
| is_active | boolean | default true |

### `programs`
Academic programs / courses. Seeded from OSDW's official program list (2nd sheet of their file).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| college_id | bigint FK → colleges | |
| code | varchar(30) | BSED, BSF, BSIT |
| name | varchar(200) | Full name as it must appear in reports |
| degree_level | enum | undergraduate, graduate |
| is_active | boolean | |

### `campuses`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(100) | Aparri |
| is_active | boolean | |

### `academic_terms`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| school_year | varchar(9) | 2025-2026 |
| semester | enum | first, second, summer |
| start_date | date | |
| end_date | date | |
| is_current | boolean | only one true at a time |

### `providers`
Scholarship sponsors. A provider's funding source is a fixed attribute of who
it is — DOST-SEI is always National Government, an LGU is always Local
Government — so it's stored on the provider, not picked independently per
program.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(200) | DOST-SEI, ACEF-GIAHEP, CSUA Alumni Association, Open Heart Foundation |
| funding_source | enum | `App\Enums\FundingSource` — national_government, national_non_government, local_government, local_non_government, in_house, academic, non_academic |
| contact_person | varchar(150) nullable | |
| contact_email | varchar(150) nullable | |
| is_active | boolean | |

---

## 2. Program Configuration

The key design decision: **a scholarship program is identity only; each offering configures its own requirements independently.**

A provider offers scholarship programs. A scholarship program runs in offerings — one per term, each named by the admin ("Batch 1", "First semester intake"). Opening an offering (`OfferingService::openOffering()`) creates it with no form fields, eligibility rules or requirements of its own — staff configure all of it directly on the offering, starting from a blank set each time.

The program holds only the grant's identity (name, provider, description). Everything else — category, form fields, eligibility rules, requirements, which offering it renews, college scope, the eligible colleges, allocation mode, slots, dates, amount — is set on the offering itself.

**Standard requirements.** The admin also keeps one standard application form and one set of standard disbursement requirements (`standard_form_fields`, `standard_requirements`), edited on the Standard requirements page (`admin.scholarship-programs.standard-requirements`, a button on Scholarship programs). There are no standard shortlist requirements. On an offering that takes new applicants, the Requirements page chooses where its application form and disbursement requirements come from (`program_offerings.requirements_source`): **Set manually** (the default) or **Standard requirements**. Choosing Standard (`StandardRequirementService::useStandard()`, allowed only before the offering's first new application) copies the standard form fields into `offering_form_fields` and the standard requirements into `offering_additional_requirements` as `award` stage rows, blank forms included, replacing the offering's own. Those two sections are then read-only on the offering; its required GWA, shortlist requirements and interview stay editable. Every later save on the Standard requirements page re-copies into each Standard offering that still has no new applications (`syncOfferings()`); once one comes in, the offering's copy is frozen. Switching back to Set manually keeps the copied rows, now editable.

Editing one offering's configuration never reaches a sibling offering: an applicant's stored answer always points at the exact offering field they filled in, so historical records cannot be rewritten by a later edit on another offering.

### `scholarship_programs`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(200) | |
| provider_id | bigint FK → providers | funding source is derived from the provider, not stored here |
| description | text nullable | |
| is_active | boolean | |
| deleted_at | timestamp nullable | soft delete — see **Deleting programs and offerings** below |

### `program_offerings`
One run of a program in a given term. Registration status is derived from `opens_at`, `closes_at` and `max_applications` — there is no stored status column.

A **renewal offering** has `renews_offering_id` set: it continues one earlier offering of the same program and takes only that offering's awardees as renewals. By default it has no slots (`total_slots` 0, no cap, all colleges, open allocation), no application form, and is never in `ProgramOffering::open()`. With `accepts_new_applicants` on, it also takes new applicants the way a first offering does — `total_slots` (new applicants only; renewals take none), cap, college scope, allocation, application form, shortlist/award requirements and interview all apply, it is in `ProgramOffering::open()` during its window, and `ProgramOffering::takesNewApplicants()` is true. The offering form then suggests the slots as `RenewalService::graduatingCount()` of the offering it renews: scholars in their last term there, i.e. year level 4 or above in a second-semester term (`RenewalService::isLastTerm()`). Graduating scholars can still renew. Its `category` is copied from the offering it renews. Its `opens_at` / `closes_at` are the renewal submission window ("Submission starts" / "Submission due"): `OfferingRequirementService::submitUpload()` refuses a renewal-requirement upload outside it (`ProgramOffering::acceptsRenewalSubmissions()`). Admin creates it with "Add renewal offering" on an offering's page, which links it to the latest offering of that group. An offering and every renewal offering that continues it (directly or down the chain) form a group: the offerings list shows one row per group, and the group's page shows each member as a tab (`RenewalService::chainOf()`).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| scholarship_program_id | bigint FK | |
| renews_offering_id | bigint FK → program_offerings nullable | set on a renewal offering: the offering whose awardees it renews. Restrict on delete |
| accepts_new_applicants | boolean default false | renewal offering only: also takes new applicants into `total_slots` |
| requirements_source | enum default manual | manual, standard (`RequirementSource`) — where the application form and disbursement requirements come from; only meaningful while the offering takes new applicants |
| academic_term_id | bigint FK | |
| name | varchar(150) nullable | the admin's label for this run — "Batch 1"; falls back to the term when absent |
| category | enum | academic, non_academic — set per offering, maps to the report's Type column |
| campus_id | bigint FK | |
| opens_at | datetime | |
| closes_at | datetime | |
| total_slots | int | |
| max_applications | int nullable | null = no cap; reaching it closes registration early |
| college_scope | enum | all, restricted — set per offering |
| allocation_mode | enum | open, quota, proportional — set per offering |
| amount | decimal(10,2) | fund amount per scholar, required |
| results_published | boolean | default false |
| publish_instructions | text nullable | admin-written note shown to awardees once published |
| results_published_at | datetime nullable | set once, on first publish; unpublishing does not clear it |
| has_interview | boolean | default false — set on the offering's Requirements page (Interview section) |
| interview_at | datetime nullable | one schedule for the whole shortlist; required when `has_interview` is on |
| interview_venue | varchar(255) nullable | required when `has_interview` is on |
| interview_instructions | text nullable | shown with the schedule on a shortlisted applicant's application page |
| shortlist_requirements_opened_at | datetime nullable | set when staff open the shortlist requirements from the Shortlist tab — allowed once shortlisted + awarded new applicants reach `total_slots`. Until then shortlisted applicants do not see them |
| award_requirements_opened_at | datetime nullable | set when staff open the disbursement requirements from the Grantees page — allowed once every slot is awarded. Until then awardees do not see them |
| registration_opened_notified_at | datetime nullable | when every student was notified that this offering opened for registration; set once, by whichever caller of `StudentNotificationService::syncRegistrationNotices()` claims it first |
| registration_closed_notified_at | datetime nullable | when every student was notified that it stopped taking applications (deadline passed or application cap reached); only after the opened notice |
| deleted_at | timestamp nullable | soft delete — see **Deleting programs and offerings** below |

Opening either stage is one-way; applicants who reach that stage later see its requirements straight away. The interview notice appears on the application page of every applicant whose status is `shortlisted`, as soon as `has_interview` is on and `interview_at` is set. There is no per-applicant slot and no publish step. Turning `has_interview` off clears the three interview columns.

**Deleting programs and offerings.** Both tables use soft deletes (`DeletionService`). Only configuration can be deleted: an offering only while it holds no applications or renewals and no renewal offering continues it; a program only while none of its offerings holds applications, and its offerings are deleted with it, stamped with the program's `deleted_at` so restoring the program brings exactly those back. Deleted rows drop out of every query and route. The administrator restores them from Settings → Deleted items (`admin.settings.deleted-items`); an offering can't be restored while the offering it renews is still deleted. After `DeletionService::RETENTION_DAYS` (60) days `osdw:purge-deleted` (daily, and on each load of Deleted items) removes them for good: the offering's announcements and blank-form files go with it, and its form fields, rules, colleges and requirements cascade.

### `offering_colleges`
Only populated when `college_scope = restricted` or `allocation_mode = quota`. The colleges are ticked on the offering form, and under quota allocation each one carries its slot number for the term.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | |
| college_id | bigint FK | |
| slots | int nullable | null when scope is restriction-only |

Unique index on (`program_offering_id`, `college_id`).

### `offering_form_fields`
The application form renders entirely from data: loop over an offering's fields in `sort_order` and emit one input per row. `application_documents` and `application_field_responses` point here, so a field with answers cannot be deleted or retyped.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | |
| field_type | enum | text, textarea, number, date, select, checkbox, radio, file |
| label | varchar(150) | Certificate of Registration |
| description | text nullable | helper text shown under the field |
| options | text nullable | one choice per line — only used when field_type is select or radio |
| is_required | boolean | default true |
| requires_physical_copy | boolean | default false — only meaningful when field_type = file |
| accepted_formats | varchar(100) nullable | pdf,jpg,png — only used when field_type = file |
| max_size_mb | int nullable | only used when field_type = file |
| sort_order | int | |

### `offering_additional_requirements`
Items an offering asks for beyond its application form, configured on the offering's Requirements page (Additional requirements section; on a renewal offering, the page's only section). Each is tied to a stage:

- **shortlist** — shortlisted applicants must comply before they can be awarded. `AwardService::create()` refuses to award while any is outstanding.
- **award** — asked of awardees after the award, as what's needed before the award is released. They never block the award, but every one must be complied before staff can record the award as disbursed (`awards.disbursed_at`); progress shows on the Grantees roster, where staff verify or return them, and on the review page. Like renewal uploads, each is submitted once and re-uploaded only after staff return it.
- **renewal** — only on a renewal offering: what a renewing scholar hands in (e.g. certificate of grades, certificate of registration). `AwardService::create()` refuses to renew while any is outstanding. Staff verify them on the renewal review page, opened from the Renewals page.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | cascade on delete |
| stage | enum | shortlist, award, renewal |
| label | varchar(150) | Certificate of Good Moral Character |
| instructions | text nullable | shown to the applicant on their application page |
| submission_mode | enum | upload, physical, both |
| accepted_formats | varchar(100) nullable | pdf,jpg,png — only when submission_mode needs an upload |
| max_size_mb | int nullable | only when submission_mode needs an upload |
| template_path | varchar(255) nullable | optional blank form (PDF/Word, max 10 MB) admin uploads for applicants to download, fill in and hand back; local disk, deleted with the requirement |
| template_name | varchar(255) nullable | original file name of the blank form |
| sort_order | int | ordered within a stage |

### `standard_form_fields`
The standard application form — same columns as `offering_form_fields` (shared `App\Models\Base\FormField`) without `program_offering_id`. Never answered directly; copied into offerings that use the standard requirements.

### `standard_requirements`
The standard disbursement requirements — the columns of `offering_additional_requirements` without `program_offering_id` and `stage` (always copied as `award`). Blank forms live under `standard-requirements/templates` on the local disk and are copied into each offering's own `offerings/{id}/templates` folder.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| label | varchar(150) | |
| instructions | text nullable | |
| submission_mode | enum | upload, physical, both |
| accepted_formats | varchar(100) nullable | only when submission_mode needs an upload |
| max_size_mb | int nullable | only when submission_mode needs an upload |
| template_path | varchar(255) nullable | optional blank form |
| template_name | varchar(255) nullable | |
| sort_order | int | |

### `offering_eligibility_rules`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | |
| field | enum | gwa, year_level, college, program, income |
| operator | enum | lte, gte, eq, in |
| value | varchar(255) | numeric or comma-separated IDs |
| message | varchar(255) | shown to applicant on failure. An `income` rule (`lte`) is the offering's family income cap, set on the offering form; its message never names the amount |

---
---

## 3. Applicants & Applications

### `applicants`
The global record keyed on student ID, owned by one student account. Created once when the student sets up their profile after registering; the student edits the personal details in Settings. Applications read the identity from here — the apply form no longer collects it.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → users nullable unique | the student account that owns this record; null only for records made before accounts existed |
| student_id | varchar(30) unique | the cross-program identity key; set at profile setup, never editable |
| last_name | varchar(100) | |
| first_name | varchar(100) | |
| middle_name | varchar(100) nullable | |
| suffix | varchar(20) nullable | |
| sex | enum | male, female |
| birthdate | date nullable | |
| email | varchar(150) | copied from the account's email |
| mobile | varchar(20) nullable | |
| address | varchar(255) nullable | |

### `applications`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| applicant_id | bigint FK | |
| program_offering_id | bigint FK | |
| kind | enum | new, renewal — default new |
| renewed_from_award_id | bigint FK → awards nullable | renewals only: the award in the previous offering this continues |
| program_id | bigint FK | snapshot — student may shift courses |
| college_id | bigint FK | denormalized for report + quota checks |
| year_level | tinyint nullable | snapshot from the application form; a renewal starts null and the scholar picks it (dropdown, 1–6) once on their application page each term — it cannot be changed after submitting — `AwardService::create()` refuses to renew until it is set |
| gwa | decimal(5,2) nullable | percentage scale, e.g. 90.00; new applications only |
| monthly_income | decimal(10,2) nullable | family monthly income — required on every new application; null on renewals |
| consent_publish | boolean | default false |
| status | enum | draft, submitted, screening, shortlisted, awarded, waitlisted, denied |
| submitted_at | datetime nullable | for a renewal, when it was opened |
| screened_at | datetime nullable | |

Unique index on (`applicant_id`, `program_offering_id`) — one application per student per offering. A scholar with a renewal in an offering therefore cannot also apply to it as new.

**Renewals.** A renewal is an application of kind `renewal` in a renewal offering, created by the "Open renewals" command on that offering's page (`RenewalService::openRenewals()`) for every scholar awarded in the offering it renews. It skips the form, screening, shortlist and interview, and is listed on the offering's Renewals page (`staff.renewals.index`), not the queue/shortlist workspace, and reviewed on its own renewal review page (`staff.renewals.review`). Its statuses read differently (`Application::statusLabel()`):

| status | renewal label | meaning |
|---|---|---|
| screening | Under review | opened; the scholar enters their year level and hands in the renewal requirements on their application page, and staff verify them (the Renewals page tab reads "Renewal review") |
| awarded | Renewed | staff renewed it — an `awards` row, taking no slot |
| denied | Denied | no longer qualifies, graduated, or left — staff give the reason |

Renewals are excluded from applicant counts, the `max_applications` cap, the Queue tab and the application funnel report.

### `application_documents`
Uploads against an offering's requirement fields.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | |
| offering_form_field_id | bigint FK | must reference a row where field_type = file |
| file_path | varchar(255) | |
| original_name | varchar(255) | |
| mime_type | varchar(100) | |
| file_size | int | bytes |
| status | enum | pending, verified, rejected |
| physical_received | boolean | default false |
| verified_by | bigint FK → users nullable | |
| verified_at | datetime nullable | |
| remarks | text nullable | |

### `application_field_responses`
The applicant's answer to a non-file `offering_form_fields` row (file answers live in `application_documents` instead). Added when the public application form was built — `offering_form_fields` covers any input type, but only file uploads had a storage table until this one.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | |
| offering_form_field_id | bigint FK | must reference a row where field_type != file |
| value | text nullable | raw answer; checkbox stored as "1"/"0" |

Unique index on (`application_id`, `offering_form_field_id`).

### `application_screenings`
One row per rule evaluated — this is what lets you tell the applicant exactly which check failed.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | |
| offering_eligibility_rule_id | bigint FK nullable | null for completeness checks |
| check_type | enum | completeness, eligibility, duplicate |
| passed | boolean | |
| message | varchar(255) | |

### `application_additional_requirements`
One applicant's submission against one `offering_additional_requirements` row. A row appears the first time the applicant uploads or staff record a physical copy; no row means nothing handed in yet.

A submission is **complied** when every part its requirement's `submission_mode` asks for is in: the upload verified (upload, both) and the physical copy received (physical, both).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | cascade on delete |
| offering_additional_requirement_id | bigint FK | cascade on delete; the file is deleted with it |
| file_path | varchar(255) nullable | the applicant's upload, from their application page |
| original_name | varchar(255) nullable | |
| mime_type | varchar(100) nullable | |
| file_size | int nullable | bytes |
| submitted_at | datetime nullable | last upload |
| status | enum | pending, verified, rejected — review of the upload; a new upload resets it to pending |
| remarks | text nullable | why staff returned the upload; shown to the applicant |
| verified_by | bigint FK → users nullable | |
| verified_at | datetime nullable | |
| physical_received_at | datetime nullable | null = not yet received |
| physical_received_by | bigint FK → users nullable | |

Unique index on (`application_id`, `offering_additional_requirement_id`).

### `application_status_logs`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | |
| from_status | varchar(30) nullable | |
| to_status | varchar(30) | |
| changed_by | bigint FK → users nullable | null for system transitions |
| reason | text nullable | required for overrides |
| created_at | timestamp | |

---

## 4. Award & Monitoring

The system only screens and records — it does not process award distribution or acceptance. Awarding is a single, final action staff take from an offering's Shortlist tab (`AwardService::create()`), gated by slot limits. Publishing results to the public site also happens from that Shortlist tab, once every new-applicant slot is filled. There is no acceptance step and no forfeiture.

Renewal is the one per-term check: a scholar is renewed term by term through a renewal application in a renewal offering that continues the offering they were awarded in (see `program_offerings` and `applications` above). Renewing also goes through `AwardService::create()`, which takes no slot and, instead of the shortlist-requirement gate, requires every renewal requirement to be complied. Each renewal is its own `awards` row in the new term, so the SUC-Levelling report counts renewed scholars in the term they were renewed for. The Grantees page (`staff.grantees.index`) lists recorded awards across all programs and offerings, one row per grantee per offering group at their latest award (`RenewalService::latestAwardIdsPerChain()`), searchable by name or student ID and filterable by college and by program → offering group (tabs open on the latest offering in the group); renewed awards carry a "Renewed" tag. Each row opens the disbursement requirements to verify or return, and a read-only grantee page (`staff.grantees.show`, built from `RenewalService::historyOf()`).

### `awards`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK unique | |
| applicant_id | bigint FK | denormalized for history queries |
| program_offering_id | bigint FK | |
| academic_term_id | bigint FK | |
| amount | decimal(10,2) | copied from the offering's fund amount at award time |
| awarded_by | bigint FK → users | |
| awarded_at | datetime | |
| disbursed_at | datetime nullable | set when staff record the award as disbursed (`AwardService::disburse()` / `disburseAll()`) — only once every disbursement requirement is complied (renewals need none); one-way. The application stays `awarded` and its status reads "Disbursed" |
| disbursed_by | bigint FK → users nullable | |
| is_override | boolean | reserved for a future rank-based override flow — unused while awarding has no ranking |
| override_reason | text nullable | required when is_override |

---

## 5. System

### `users`
Laravel default plus:

| Column | Type | Notes |
|---|---|---|
| role | enum | admin, osdw_staff, student |
| is_active | boolean | |
| google_id | varchar(255) nullable unique | set when the account signs in with Google |
| password | varchar(255) nullable | null for a student who signed up with Google and has not set one |
| password_changed_at | datetime nullable | set when the account holder chooses their own password; null on a staff account still on the administrator-generated temporary password (first log-in sends them to account setup) |
| email_verified_at | datetime nullable | set when the registration OTP is verified, or by Google |

A student account owns one `applicants` row (`applicants.user_id`). Staff and administrators have none. Everyone logs in from the same page. Staff accounts are created only by the administrator; an OSDW Staff account must set its own password and (when Google sign-in is configured) link a Google account before it can open any staff page.

### `email_otps`
One-time 6-digit codes mailed to prove an email address is the student's — before an account is created on it, and before a forgotten password is reset. Only the hash is stored.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| email | varchar(150) index | lowercased |
| purpose | enum | register, password_reset (`OtpPurpose`) |
| code_hash | varchar(255) | bcrypt of the code |
| attempts | tinyint | wrong tries; the code stops working at 5 |
| expires_at | datetime | 10 minutes after sending |
| consumed_at | datetime nullable | set when the right code is entered |

A new code can be asked for 60 seconds after the last. Every unused, unexpired code for the email is accepted (a student may read an earlier email); wrong tries count against the newest, and a match uses them all up. The code leads the email subject, so each email shows on its own in the inbox.

### `notifications`
Laravel's database-notification table — the student and staff notification bells. Each row's `data` holds `kind` (`NotificationKind`), `title`, `body` and `url` (a site path, no host). See `StudentNotificationService` and `StaffNotificationService` for who gets which.

| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| type | varchar(255) | `App\Notifications\StudentNotification` or `App\Notifications\StaffNotification` |
| notifiable_type / notifiable_id | morph | the student's or staff member's `users` row |
| data | text (JSON) | kind, title, body, url |
| read_at | timestamp nullable | set when opened or "Mark all as read" |

### `announcements`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK nullable | null = general announcement |
| title | varchar(200) | |
| body | text | |
| is_published | boolean | |
| published_at | datetime nullable | |
| created_by | bigint FK → users | |

### `audit_logs`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK nullable | |
| action | varchar(50) | created, updated, deleted, approved, overridden |
| auditable_type | varchar(100) | model class |
| auditable_id | bigint | |
| old_values | json nullable | |
| new_values | json nullable | |
| ip_address | varchar(45) | |
| created_at | timestamp | |

### `email_logs`
Proof of delivery for your testing chapter and for student disputes.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK nullable | |
| recipient | varchar(150) | |
| subject | varchar(200) | |
| type | varchar(50) | screening_result, award_notice, renewal_notice, reminder |
| status | enum | queued, sent, failed |
| error_message | text nullable | |
| sent_at | datetime nullable | |

### `settings`
| Column | Type | Notes |
|---|---|---|
| key | varchar(100) PK | |
| value | text | |
| description | varchar(255) nullable | |

### `branding_images`
The OSDW images shown in the interface, stored in the database and served by `branding.show` (`/branding/{key}`). Seeded by `BrandingSeeder` from `database/seeders/branding/`.

| Column | Type | Notes |
|---|---|---|
| key | varchar(50) PK | `BrandingImageKey`: `logo` (landing hero, staff/admin sidebar), `footer_banner` (landing footer) |
| file_name | varchar(255) | original file name |
| mime_type | varchar(100) | sent as the response Content-Type |
| data | longblob | the image bytes; the database backup uses `mysqldump --hex-blob` so they survive the dump |

---

## 6. Notes on Key Design Decisions

**Why `program_offerings` is separate from `scholarship_programs`**
Slots, deadlines, and rules change every semester. Separating them means the DOST-SEI grant is one record that gets reopened each term, rather than a duplicated row per year.

**Why the configuration tables exist twice instead of once**
An offering could have pointed at the program's rows instead of copying them. It does not, for two reasons. First, records: `application_documents` references the field the applicant actually filled in, so if that row were shared, renaming a field next term would silently rewrite what past applicants appear to have answered. Second, control: OSDW adjusts requirements term to term, and a shared row would force every offering to change together. Copying costs four extra tables and buys an audit trail that cannot be falsified by an edit.

The two sides must stay column-for-column identical, since the copy reads one and writes the other. That is enforced by the shared abstract bases in `App\Models\Base\`, not by convention.

**Why standard requirements are copied into an offering, not linked**
The same reason as above: submitted answers and files point at the offering's own field and requirement rows, so they must not change under an applicant. The copy keeps following the standard requirements only while the offering has no applications; after that it is frozen.

**Why `college_id` is stored on `applications`**
It is derivable from `program_id` → `programs.college_id`, but it is denormalized here because quota checks and the mandatory College report column query it constantly. It is also a historical snapshot — if a student shifts courses later, the application record still reflects the college they competed under.

**Why `application_screenings` is a table and not a JSON column**
Individual rows let you tell the applicant exactly which rule failed, and let OSDW report on the most common disqualification reasons. That reporting capability is a defensible feature.

**Why `awards` carries `is_override` and `override_reason`**
Reserved for a future rank-based override flow: once GWA/income ranking is built, out-of-rank approvals will need to leave a permanent, reportable trace here, paired with `application_status_logs` for the full decision history. Unused for now — awarding currently has no ranking to be "out of."

---

## 7. Suggested Seed Data

- `colleges` — from the College column in the SUC-Levelling file
- `programs` — from the official program list (2nd sheet)
- `providers` — DOST-SEI, ACEF-GIAHEP, CSUA Alumni Association, Open Heart Foundation, each tagged with its `funding_source`
- `campuses` — Aparri
- `academic_terms` — 1st Sem 2025-2026 as current
- `users` — one admin and one staff account (`DemoDataSeeder` also gives every demo applicant a student account: their email, password `password`)

---

## 8. SUC-Levelling Report Query Mapping

| Report column | Source |
|---|---|
| Sequence No. | row number in result set |
| Last / First / Middle Name | `applicants` |
| Sex | `applicants.sex` |
| Course | `programs.name` |
| Year | `applications.year_level` |
| College | `colleges.name` |
| Name of Scholarship | `scholarship_programs.name` |
| Type | `program_offerings.category` |
| Funding | `providers.funding_source` (via the award's scholarship program's provider) |
| Campus | `campuses.name` |
| Amount | `awards.amount` |

Filter: the Grantees page filters (`ReportService::granteeQuery()`) — one offering's awards when an offering is chosen, otherwise each grantee's latest award per offering group; then program, college, and name or student ID search.
