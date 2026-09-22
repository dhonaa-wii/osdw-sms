# OSDW Scholarship Management System

A web-based scholarship application and management system with departmental classification, built for the Office of Student Development and Welfare (OSDW) of a Philippine state university. This is an undergraduate capstone project.

## Reference documents

Read these before implementing anything. They are the source of truth — if this file and a doc disagree, ask me rather than guessing.

- `docs/osdw_scholarship_system_schema.md` — database schema
- `docs/osdw_modules_and_features_per_user.md` — features per user role
- `docs/osdw_development_guide.md` — conventions, design system, build order

## Stack

- Laravel 12 (PHP 8.2+)
- Livewire 4 — **not** Livewire 3. Use Single-File Components, `Route::livewire()`, and `#[Validate]` attributes. Do not follow v3 patterns with separate class and view files.
- Tailwind CSS 4 — theme tokens live in `resources/css/app.css` under `@theme`. There is no `tailwind.config.js`.
- MySQL 8 via XAMPP
- Alpine.js for small client-side interactions only

## Domain context

**Users:** Students/applicants (no accounts — reference-number authenticated), OSDW Staff, System Administrator. There is no OSDW Head role and no panelist role — the Administrator absorbs everything Staff can do plus final award approval and full audit-log/executive-dashboard access.

**Key constraints:**
- No integration with the school's student information system. Students self-encode their data; staff verify against uploaded documents.
- Single campus.
- OSDW staff review all applicants — there are no per-college coordinators.
- Awards are recorded, not disbursed. No payment processing.

**Critical business rules:**
1. `student_id` is the global identity key. The same student across programs and terms is one `applicants` record.
2. Students may apply to multiple programs. Do not block this — flag it. When reviewing, staff must see the applicant's other applications and awards.
3. The system screens for eligibility and completeness. It does **not** score, rank, or decide. Staff manually shortlist or reject each application, then award directly from the shortlist within slot limits. (Ranking by GWA and family income is a planned future feature, not built yet — don't reintroduce scoring or rank-based ordering without asking.)
4. There is a single requirement stage — every document/field is checked during screening, before an application can be shortlisted. There is no post-award requirements checklist: a student who accepts an award is already compliant, so acceptance activates the award immediately. Don't reintroduce a post-award stage or a separate compliance-confirmation step without asking. The one exception is **additional requirements**: staff set a list once on an offering, from the Shortlist tab, that applies to every shortlisted applicant on it. Each item is either an upload (received only when the applicant's file lands — staff can untick but never tick one by hand) or a physical copy (staff tick it off at the counter). An applicant with anything outstanding cannot be awarded; that gate lives in `AwardService::create()` so no screen can skip it. They live on the offering only — never on the program template or a sibling offering.
5. A provider offers scholarship programs; a scholarship program runs in **offerings**, one per term, each named by the admin ("Batch 1"). The program holds the requirements *template* — form fields, eligibility rules, retention rules. Opening an offering **copies** that template into the offering's own rows (`OfferingService::openOffering()`).
6. An offering's copy is independent and editable. Editing an offering never affects the template or any sibling offering, and a later template change never reaches an offering that already exists. Everything that can change from term to term — name, slots, dates, amount, discount rate, college scope, eligible colleges, allocation mode — lives on the offering only and is never copied from the program. Never hardcode one policy.
7. The SUC-Levelling report is the system's headline output. Its column format is fixed by the agency — see section 8 of the schema doc.

## Conventions

| Thing | Convention |
|---|---|
| Table | plural snake_case — `offering_form_fields` |
| Model | singular StudlyCase — `OfferingFormField` |
| Controller | `ApplicationController`, grouped in `Public/`, `Staff/`, `Admin/` |
| Livewire component | kebab-case — `staff.applications.review-panel` |
| Route name | dot notation — `staff.applications.review` |
| Form request | `StoreApplicationRequest` |
| Enum | singular StudlyCase — `ApplicationStatus` |

**Business logic goes in `app/Services/`**, not controllers. `ScreeningService`, `AwardService`, `ReportService`. Controllers stay thin.

**Use PHP enums for all status and mode fields.** Never compare raw strings.

**Run `./vendor/bin/pint` before finishing any task.**

## Design system

Tokens are defined in `resources/css/app.css`. Use them as Tailwind utilities — never hardcode hex values.

- Text: `ink`, `slate`, `mist` · Surfaces: `paper`, `surface` · Lines: `line`
- Primary: `forest-600` / `forest-700` / `forest-900`
- Award states only: `gold-600`. Never decorative.
- Status: `amber-600` pending · `crimson-600` negative · `azure-600` informational

**Type:** Archivo for headings, Public Sans for body and forms, JetBrains Mono for reference numbers, student IDs, GWA, and amounts. The monospace choice is functional — these values get read aloud and typed by hand.

**Status stamps**, not pills: a left border rule plus a small tracked uppercase label. Uppercase appears nowhere else in the interface.

**Quality floor, always:** visible focus rings, real `<label>` on every input, error messages that say what to do, empty states that offer the next action, tables that collapse to cards under 768px, `wire:loading` states on every Livewire action.

**Interface copy:** buttons name the outcome ("Submit application", not "Submit"). Same word for the same action everywhere. Address students directly — "Your application is under review".

**No narration copy.** Never explain what changed, what used to be there, or how a field's value is computed — no "no longer set here", "now shown automatically", "this used to be manual", etc. That's a commit message, not interface copy; it belongs in this file or a PR description, not on the page. A hint on an input says what to type or what happens next in one short clause ("Leave blank for no cap") — it does not narrate the feature's history or restate the obvious. If a hint would just repeat the label or explain internal logic, cut it.

**No page subtitles.** A heading names the page; do not add a descriptive sentence under it restating or explaining what the page is for. If a heading needs disambiguating context (which offering, which term, which program), fold that identifier directly into the heading text instead of a separate line below it. This does not apply to error messages, empty-state next-action text, or validation hints on inputs — those stay, per the quality floor above.

## What not to do

- Do not use Livewire 3 syntax
- Do not create a `tailwind.config.js`
- Do not hardcode colors, statuses, or scholarship-specific rules
- Do not put business logic in controllers or Blade views
- Do not use `localStorage` or `sessionStorage`
- Do not add features that are not in `docs/osdw_modules_and_features_per_user.md`
- Do not invent domain policy. Slot rules and retention thresholds come from the agency — ask me.
- Do not build the payment, SMS, or SIAS integration modules. They are explicitly out of scope.
- Do not write narration copy in the UI ("no longer set here", "now automatic", "this used to be..."). See Interface copy rules above.

## Working style

- Propose a plan before implementing anything non-trivial
- Work in the phase order from the development guide; stop at phase boundaries for review
- After generating code, explain the non-obvious parts — I have to defend this to a panel
- Prefer clear, readable implementations over clever ones
- Flag it when something in the docs is ambiguous rather than picking silently
