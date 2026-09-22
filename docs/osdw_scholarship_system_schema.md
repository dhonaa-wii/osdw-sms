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

The key design decision: **a scholarship program holds the configuration template; each offering holds a copy of it.**

A provider offers scholarship programs. A scholarship program runs in offerings — one per term, each named by the admin ("Batch 1", "First semester intake"). Opening an offering copies the program's form fields, eligibility rules and retention rules into the offering's own rows (`OfferingService::openOffering()`). Staff therefore start from a filled-in set of requirements rather than a blank one, and can adjust it for that term.

The program is a requirements template only. Anything that can change from term to term — college scope, the eligible colleges, allocation mode, slots, dates, amount, discount rate — is set on the offering itself and never copied.

The copy runs once, at creation. After that the two sides are independent:

- editing an offering never changes the template or any sibling offering
- editing the template never reaches an offering that already exists
- an applicant's stored answer always points at the exact offering field they filled in, so historical records cannot be rewritten by a later edit

Each configuration table therefore exists twice, column-for-column identical, differing only in its owner. The shared shape lives in `App\Models\Base\{FormField, EligibilityRule, RetentionRule}` so the pair cannot drift apart.

| Program template | Offering working copy |
|---|---|
| `program_form_fields` | `offering_form_fields` |
| `program_eligibility_rules` | `offering_eligibility_rules` |
| `program_retention_rules` | `offering_retention_rules` |

### `scholarship_programs`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(200) | |
| provider_id | bigint FK → providers | funding source is derived from the provider, not stored here |
| category | enum | academic, non_academic — maps to the report's Type column |
| description | text nullable | |
| is_active | boolean | |

### `program_form_fields`
Merges the modules doc's "Requirement builder" and "Dynamic field builder" into one table — a document upload is just `field_type = file`. Same columns as `offering_form_fields` below, owned by `scholarship_program_id`.

### `program_eligibility_rules`
Same columns as `offering_eligibility_rules` below, owned by `scholarship_program_id`.

### `program_retention_rules`
Same columns as `offering_retention_rules` below, owned by `scholarship_program_id`.

### `program_offerings`
One run of a program in a given term. Registration status is derived from `opens_at`, `closes_at` and `max_applications` — there is no stored status column.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| scholarship_program_id | bigint FK | |
| academic_term_id | bigint FK | |
| name | varchar(150) nullable | the admin's label for this run — "Batch 1"; falls back to the term when absent |
| campus_id | bigint FK | |
| opens_at | datetime | |
| closes_at | datetime | |
| total_slots | int | |
| max_applications | int nullable | null = no cap; reaching it closes registration early |
| college_scope | enum | all, restricted — set per offering |
| allocation_mode | enum | open, quota, proportional — set per offering |
| amount | decimal(10,2) nullable | cash grant |
| discount_rate | decimal(5,2) nullable | tuition discount % |
| results_published | boolean | default false |

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

### `offering_eligibility_rules`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | |
| field | enum | gwa, year_level, college, program, income |
| operator | enum | lte, gte, eq, in |
| value | varchar(255) | numeric or comma-separated IDs |
| message | varchar(255) | shown to applicant on failure |

### `offering_retention_rules`
Optional. Checked against a continuing scholar's `scholar_terms` submission during renewal evaluation — not against the original application. An offering with no rows here falls back to manual staff judgment; nothing is forced on every program.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | |
| field | enum | gwa, units_enrolled, failing_grade |
| operator | enum | lte, gte, eq |
| value | varchar(255) | numeric, e.g. 0 for zero failing grades allowed |
| message | varchar(255) | shown to staff when a scholar's term fails this check |

### `offering_additional_requirements`
Items staff ask the shortlisted applicants of one offering for on top of its form fields — a hard copy the provider wants, an affidavit the agency asked for late. Set **once per offering** from the Shortlist tab and applied to everyone on that shortlist; each applicant is then ticked off individually as their copy arrives (see `application_additional_requirements`).

Not copied from the program template and never reaching a sibling offering: these are a mid-cycle ask, not part of the application form.

Two rules hold regardless of which screen you are on, both enforced in the service layer:

- **An upload item is received only when the applicant's file lands.** Staff cannot tick one by hand — that would mean an award with nothing behind it. They can untick one, which rejects the upload and deletes the file. A physical-copy item is ticked by staff at the counter.
- **An applicant with anything outstanding cannot be awarded.** `AwardService::create()` refuses, so the shortlist, the review page and any future bulk action are all covered.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| program_offering_id | bigint FK | cascade on delete |
| label | varchar(255) | what staff typed |
| submission_mode | enum | upload, physical_copy — how the applicant hands it in |
| created_by | bigint FK → users nullable | |
| created_at / updated_at | timestamp | |

---
---

## 3. Applicants & Applications

### `applicants`
The global record keyed on student ID. Upserted on every submission — no separate registration step.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| student_id | varchar(30) unique | the cross-program identity key |
| last_name | varchar(100) | |
| first_name | varchar(100) | |
| middle_name | varchar(100) nullable | |
| suffix | varchar(20) nullable | |
| sex | enum | male, female |
| birthdate | date nullable | |
| email | varchar(150) | reference number delivery |
| mobile | varchar(20) nullable | |
| address | varchar(255) nullable | |

### `applications`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| reference_no | varchar(30) unique | SCH-2026-A7K9M2 |
| access_token | varchar(64) | random, for passwordless lookup |
| applicant_id | bigint FK | |
| program_offering_id | bigint FK | |
| program_id | bigint FK | snapshot — student may shift courses |
| college_id | bigint FK | denormalized for report + quota checks |
| year_level | tinyint | snapshot |
| gwa | decimal(5,2) nullable | percentage scale, e.g. 90.00 |
| monthly_income | decimal(10,2) nullable | |
| consent_publish | boolean | default false |
| status | enum | draft, submitted, screening, ineligible, shortlisted, approved, waitlisted, denied, accepted, forfeited, withdrawn |
| submitted_at | datetime nullable | |
| screened_at | datetime nullable | |

Unique index on (`applicant_id`, `program_offering_id`) — one application per student per offering.

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
One applicant's receipt for one of the offering's additional requirements (see `offering_additional_requirements` in section 2). The row exists **only once the item has been handed in** — no row means the applicant still owes it. That keeps "outstanding" a pure derivation, with no backfill when a requirement is added or an applicant reaches the shortlist.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK | cascade on delete |
| offering_additional_requirement_id | bigint FK | cascade on delete |
| received_at | datetime | |
| file_path | varchar(255) nullable | set only when the applicant uploaded it |
| original_name | varchar(255) nullable | |
| mime_type | varchar(100) nullable | |
| file_size | int nullable | |
| received_by | bigint FK → users nullable | null when the applicant uploaded it themselves |
| created_at / updated_at | timestamp | |

Unique on (`application_id`, `offering_additional_requirement_id`).

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

### `awards`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| application_id | bigint FK unique | |
| applicant_id | bigint FK | denormalized for history queries |
| program_offering_id | bigint FK | |
| academic_term_id | bigint FK | |
| amount | decimal(10,2) nullable | |
| discount_rate | decimal(5,2) nullable | |
| awarded_by | bigint FK → users | |
| awarded_at | datetime | |
| is_override | boolean | reserved for a future rank-based override flow — unused while awarding has no ranking |
| override_reason | text nullable | required when is_override |
| acceptance_status | enum | pending, accepted, declined, forfeited |
| accepted_at | datetime nullable | |
| acceptance_deadline | datetime | |
| status | enum | reserved, active, terminated, completed |

### `scholar_terms`
Per-semester monitoring. This is the table that gives OSDW retention statistics they cannot compute today.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| award_id | bigint FK | |
| applicant_id | bigint FK | |
| academic_term_id | bigint FK | |
| gwa | decimal(5,2) nullable | percentage scale, e.g. 90.00 |
| units_enrolled | int nullable | |
| has_failing_grade | boolean | default false |
| status | enum | active, probation, terminated, graduated |
| remarks | text nullable | |
| evaluated_by | bigint FK → users nullable | |

Unique index on (`award_id`, `academic_term_id`).

---

## 5. System

### `users`
Laravel default plus:

| Column | Type | Notes |
|---|---|---|
| role | enum | admin, osdw_staff |
| is_active | boolean | |

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
| type | varchar(50) | reference, screening_result, award_notice, reminder |
| status | enum | queued, sent, failed |
| error_message | text nullable | |
| sent_at | datetime nullable | |

### `settings`
| Column | Type | Notes |
|---|---|---|
| key | varchar(100) PK | |
| value | text | |
| description | varchar(255) nullable | |

---

## 6. Notes on Key Design Decisions

**Why `program_offerings` is separate from `scholarship_programs`**
Slots, deadlines, and rules change every semester. Separating them means the DOST-SEI grant is one record that gets reopened each term, rather than a duplicated row per year. It also makes the SUC-Levelling report a simple filter on `academic_term_id`.

**Why the configuration tables exist twice instead of once**
An offering could have pointed at the program's rows instead of copying them. It does not, for two reasons. First, records: `application_documents` references the field the applicant actually filled in, so if that row were shared, renaming a field next term would silently rewrite what past applicants appear to have answered. Second, control: OSDW adjusts requirements term to term, and a shared row would force every offering to change together. Copying costs four extra tables and buys an audit trail that cannot be falsified by an edit.

The two sides must stay column-for-column identical, since the copy reads one and writes the other. That is enforced by the shared abstract bases in `App\Models\Base\`, not by convention.

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
- `users` — one admin account

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
| Type | `scholarship_programs.category` |
| Funding | `providers.funding_source` (via the award's scholarship program's provider) |
| Campus | `campuses.name` |
| Amount | `awards.amount` |

Filter: `awards.status = active` AND `awards.academic_term_id = :term`.
