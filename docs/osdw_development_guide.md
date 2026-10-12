# OSDW Scholarship Management System — Development Guide

**Stack:** Laravel 12 · Livewire 4 · Tailwind CSS 4 · MySQL 8 · XAMPP
**Project name:** `osdw-sms`

---

# 1. Before You Start

## 1.1 Check your PHP version

Laravel 12 requires **PHP 8.2 minimum**.

```bash
php -v
```

| Result | Action |
|---|---|
| PHP 8.2 or higher | Proceed |
| PHP 8.1 or lower | Update XAMPP before continuing |

Laravel 12 receives bug fixes into August 2026 and security fixes into February 2027 — comfortably past this project's timeline. Laravel 13 exists and requires PHP 8.3, but there is no reason to switch mid-project.

## 1.2 Required software

- XAMPP (Apache + MySQL + PHP 8.2+)
- Composer 2.x — `composer -V`
- Node.js 20+ and npm — `node -v`
- Git — `git --version`
- VS Code with: PHP Intelephense, Laravel Blade Snippets, Tailwind CSS IntelliSense, Alpine.js IntelliSense

## 1.3 Enable required PHP extensions

Open `C:\xampp\php\php.ini` and make sure these are uncommented (no leading `;`):

```ini
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

Restart Apache after saving.

---

# 2. Create the Project

Work inside `C:\xampp\htdocs` only if you plan to serve through Apache. Otherwise any folder works, since `php artisan serve` runs its own server.

```bash
cd C:\xampp\htdocs
composer create-project laravel/laravel:^12.0 osdw-sms
cd osdw-sms
```

Verify:

```bash
php artisan --version
```

---

# 3. Install Packages

## 3.1 Livewire 4

```bash
composer require livewire/livewire
```

Confirm the version — Livewire 4 works very differently from the v3 tutorials you will find online:

```bash
php artisan about
```

## 3.2 Tailwind CSS 4

```bash
npm install tailwindcss @tailwindcss/vite
```

Edit `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

Tailwind 4 has **no `tailwind.config.js` by default** — theme tokens are declared in CSS. See Section 8.

## 3.3 Supporting packages

```bash
composer require spatie/laravel-permission
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
composer require --dev laravel/pint
composer require --dev barryvdh/laravel-debugbar
```

| Package | Purpose |
|---|---|
| spatie/laravel-permission | Roles for admin, OSDW head, OSDW staff |
| maatwebsite/excel | SUC-Levelling export |
| laravel-dompdf | Printable acknowledgments |
| pint | Code formatting — keeps four people writing the same style |
| debugbar | Query inspection during development |

---

# 4. Configuration

## 4.1 Database

Create the database in phpMyAdmin: `osdw_sms`, collation `utf8mb4_unicode_ci`.

`.env`:

```ini
APP_NAME="OSDW Scholarship System"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osdw_sms
DB_USERNAME=root
DB_PASSWORD=
```

Set the timezone in `config/app.php` too, or every timestamp in your reports will be off by eight hours.

## 4.2 Mail (Gmail SMTP)

Create a dedicated Gmail account for the system. Enable 2-step verification, then generate an **App Password** — your normal password will not work.

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=osdw.scholarship.system@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="osdw.scholarship.system@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Test it early — before you build any feature that depends on it:

```bash
php artisan tinker
Mail::raw('Test from OSDW system', fn($m) => $m->to('your@email.com')->subject('Test'));
```

## 4.3 File storage

```bash
php artisan storage:link
```

Uploaded documents go to `storage/app/public/documents/{application_id}/`.

---

# 5. Project Conventions

Agree on these on day one. Inconsistent naming across four developers costs more time than any feature.

## 5.1 Naming

| Thing | Convention | Example |
|---|---|---|
| Table | plural snake_case | `offering_form_fields` |
| Model | singular StudyCase | `OfferingFormField` |
| Controller | plural + Controller | `ApplicationController` |
| Livewire component | kebab-case file | `applications/review-panel` |
| Route name | dot notation | `staff.applications.review` |
| Blade view | kebab-case | `public/program-details.blade.php` |
| Form request | verb + model + Request | `StoreApplicationRequest` |
| Enum | singular StudyCase | `ApplicationStatus` |
| CSS class | Tailwind utilities only | no custom class names unless reused 3+ times |

## 5.2 Folder structure

```
app/
├── Enums/                  ApplicationStatus, AllocationMode, ScreeningMode
├── Http/
│   ├── Controllers/
│   │   ├── Public/         Google sign-in (GoogleAuthController)
│   │   ├── Staff/          review, awards, monitoring
│   │   └── Admin/          master data, program config, users
│   ├── Middleware/
│   └── Requests/
├── Livewire/
│   ├── Public/
│   ├── Staff/
│   └── Admin/
├── Models/
├── Policies/
├── Services/               ScreeningService, AwardService, OfferingRequirementService, ReportService,
│                           AccountService, OtpService, StudentNotificationService
└── Exports/                SucLevellingExport

resources/views/
├── components/             reusable Blade components
├── layouts/                public.blade.php, staff.blade.php
├── auth/                login, register, forgot-password
├── student/             apply, complete-profile, applications/, notifications, settings, notification-bell
├── public/
├── staff/
└── admin/
```

**Put business logic in `Services/`, not controllers.** Screening rules and award processing will each be 100+ lines. A controller that calls `ScreeningService::evaluate($application)` is readable; a 300-line controller method is not, and a panelist will notice.

## 5.3 Use enums, not strings

```bash
php artisan make:enum ApplicationStatus
```

```php
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Awarded = 'awarded';
    case Waitlisted = 'waitlisted';
    case Denied = 'denied';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title();
    }
}
```

Typos in status strings are the single most common source of silent bugs in systems like this.

---

# 6. Command Reference

## 6.1 Generating files consistently

Always generate with flags so related files stay together.

```bash
# Model + migration + factory + seeder + resource controller + form requests
php artisan make:model ScholarshipProgram -mfsc --requests

# Model + migration only (for pivot-style tables)
php artisan make:model OfferingCollege -m

# Livewire component
php artisan make:livewire staff.applications.review-panel

# Form request
php artisan make:request StoreApplicationRequest

# Policy
php artisan make:policy ApplicationPolicy --model=Application

# Enum
php artisan make:enum AllocationMode

# Service (no generator — create manually in app/Services)

# Mailable
php artisan make:mail ApplicationSubmitted --markdown=emails.application-submitted

# Job (for queued email)
php artisan make:job SendApplicationEmail

# Observer (for audit logging)
php artisan make:observer ApplicationObserver --model=Application

# Seeder
php artisan make:seeder CollegeSeeder

# Test
php artisan make:test ScreeningServiceTest --pest
```

Flag meanings: `-m` migration · `-f` factory · `-s` seeder · `-c` controller · `--requests` store and update form requests.

## 6.2 Daily workflow

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# After pulling teammate changes
composer install
npm install
php artisan migrate

# Reset the database with fresh seed data
php artisan migrate:fresh --seed

# Clear everything when something behaves strangely
php artisan optimize:clear

# Format code before every commit — non-negotiable
./vendor/bin/pint
```

## 6.3 Inspection

```bash
php artisan route:list --path=staff
php artisan model:show Application
php artisan db:show --counts
php artisan about
```

---

# 7. Git Workflow

```bash
git init
git branch -M main
git remote add origin <your-repo-url>
```

Add to `.gitignore` (already mostly present):

```
/storage/app/public/documents
.env
```

**Branch per feature**, named after the module:

```bash
git checkout -b feature/application-submission
git checkout -b feature/screening-engine
git checkout -b feature/suc-report
```

**Commit message format:**

```
feat: add dynamic requirement upload to application form
fix: correct GWA comparison operator in eligibility check
refactor: move slot-counting logic into AwardService
docs: update data dictionary for offering_form_fields
```

Never commit `.env`. Keep `.env.example` updated whenever you add a variable, or your teammates' setups will break silently.

---

# 8. Design System

The system serves two very different audiences: students applying from mid-range phones on mobile data, and OSDW staff reviewing hundreds of records on a desktop. The design has to serve both without feeling like two products.

## 8.1 Direction

**Concept: the official record, made legible.**

This system's world is documents — certificates of registration, endorsement letters, posted lists. Rather than hiding that, the interface leans into it: clear labeled fields, strong record structure, and typography that reads like a well-designed official form instead of a startup dashboard.

## 8.2 Color tokens

An institutional navy blue anchors the palette. Gold appears **only** at the moment of award — never as decoration — so its appearance always means something.

Declare in `resources/css/app.css`:

```css
@import "tailwindcss";

@theme {
  /* Core */
  --color-ink: #171E29;        /* primary text, blue-tinted near-black */
  --color-slate: #5A6472;      /* secondary text */
  --color-mist: #8E96A3;       /* tertiary text, placeholders */
  --color-paper: #F4F5F7;      /* page background */
  --color-surface: #FFFFFF;    /* cards, panels */
  --color-line: #DFE2E8;       /* hairlines, dividers */

  /* Institutional */
  --color-navy-50:  #EAF0F7;
  --color-navy-100: #CDDCE9;
  --color-navy-600: #1D4E89;  /* primary actions */
  --color-navy-700: #163B68;
  --color-navy-900: #0C2140;

  /* Award — used only for awarded/active states */
  --color-gold-50:  #FBF3DE;
  --color-gold-600: #A67C12;
  --color-gold-900: #5C4207;

  /* Status */
  --color-amber-600: #B45309;   /* pending, warning, deadline near */
  --color-crimson-600: #B42318; /* ineligible, denied, forfeited */
  --color-azure-600: #175CD3;   /* informational, in review */

  /* Type */
  --font-display: "Archivo", sans-serif;
  --font-body: "Public Sans", sans-serif;
  --font-mono: "JetBrains Mono", monospace;

  /* Radius */
  --radius-control: 8px;
  --radius-card: 12px;
}
```

Use them as normal Tailwind utilities: `bg-navy-600`, `text-slate`, `border-line`, `font-display`.

## 8.3 Typography

Three faces, each with a job:

| Face | Role | Why |
|---|---|---|
| **Archivo** | Headings, page titles, section labels | Grotesque with signage character — reads institutional without feeling stiff |
| **Public Sans** | All body text, forms, tables | Designed specifically for government interfaces; exceptional legibility at small sizes on cheap screens |
| **JetBrains Mono** | Student IDs, amounts, GWA | Numbers get read aloud and typed by hand — a monospace face with distinguishable 0/O and 1/l prevents real transcription errors |

That last row is a functional decision, not a stylistic one. Say so if a panelist asks.

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```

**Scale:**

| Element | Size / weight | Notes |
|---|---|---|
| Page title | 30px / Archivo 600 | one per page |
| Section heading | 20px / Archivo 600 | |
| Card title | 16px / Archivo 500 | |
| Body | 15px / Public Sans 400 | line-height 1.65 |
| Label | 13px / Public Sans 500 | letter-spacing 0.02em |
| Caption | 13px / Public Sans 400, slate | |
| Data cell | 14px / Public Sans 400 | numeric columns use mono |

Two weights per face. Sentence case everywhere except the status stamp below.

## 8.4 Status stamps

Status is the most-scanned information in the entire system, so it gets a distinct treatment rather than generic pills: a **left rule plus a tracked uppercase label**, like a stamp on a document.

```html
<span class="inline-flex items-center gap-2 border-l-2 border-navy-600 pl-2
             font-body text-[11px] font-600 uppercase tracking-[0.08em] text-navy-700">
  Shortlisted
</span>
```

| Status group | Rule + text color |
|---|---|
| In progress — submitted, screening, shortlisted | navy-600 |
| Awarded | gold-600 |
| Attention — waitlisted | amber-600 |
| Closed negative — denied | crimson-600 |

Uppercase is used **only here**, which is what makes it read as a stamp rather than shouting.

## 8.5 Layout

**Public pages** — single column, max-width 720px for reading and forms, 1100px for lists. Sticky header with the university mark, program search, and a "Check my application" link. Mobile first: the application form is a vertical stack with one input per row, 16px minimum font on inputs so iOS does not zoom.

**Staff pages** — fixed 260px sidebar, content area max 1400px. The review workspace is a two-pane split: applicant data and score on the left, document viewer on the right, so nobody opens five tabs.

**Spacing scale:** 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64. Nothing in between.

## 8.5.1 Responsive compact rule (added 2026-09-19)

Short name: **`compact-ui`** — invoke it in conversation as "apply compact-ui" or "run a compact-ui pass on \<page\>" during the finalization/polish phase.

Applies at every breakpoint, across the whole system, to pages already built and pages still to come:

- **Text scales down on smaller screens.** Never ship a single fixed text size — pair it with a smaller mobile size, e.g. `text-sm md:text-base` for body copy, `text-lg md:text-xl` (or `text-xl md:text-2xl`) for headings. A size that "looked fine" on a desktop preview still needs the mobile half of the pair.
- **Boxes shrink on smaller screens.** Cards, panels, modals, stat tiles: padding and gap step down on mobile, e.g. `p-4 md:p-6`, `gap-3 md:gap-4`. Use the existing spacing scale above — don't introduce new spacing values to satisfy this.
- **Input fields get tighter, not framework-default.** `x-ui.input` / `x-ui.select` / `x-ui.textarea` padding comes down from whatever generic Tailwind-Forms density they're currently at (roughly `py-2 px-3`) toward something closer to `py-1.5 px-2.5`. The 16px-minimum-font-on-iOS rule in §8.5 still applies — reduce padding, not font-size, below that floor.
- **Box padding and text padding both come down together.** Tightening the input but leaving the card wrapper roomy (or vice versa) still reads as generic/oversized — treat this as one pass per component, not two independent ones.
- This is a density change, not a token change: reuse the existing color tokens (§8.2), type scale (§8.3), and spacing scale above — don't invent new sizes to hit it.

## 8.6 Components to build first

Build these as Blade components before any page, or you will write the same markup fifteen times.

```
components/
├── ui/
│   ├── button.blade.php          primary, secondary, ghost, danger
│   ├── input.blade.php           label, hint, error, required marker
│   ├── select.blade.php
│   ├── textarea.blade.php
│   ├── file-upload.blade.php     drag-drop, preview, size/format validation
│   ├── card.blade.php
│   ├── status-stamp.blade.php
│   ├── data-table.blade.php      sortable, paginated, empty state
│   ├── empty-state.blade.php
│   ├── alert.blade.php           info, success, warning, danger
│   ├── modal.blade.php
│   ├── document-viewer.blade.php one per layout; shows images and PDFs in-page, download for anything else
│   ├── document-link.blade.php   opens a file in the document viewer — never target="_blank"
│   ├── document-preview.blade.php a file shown in place at full height (pdf.js for PDFs)
│   ├── stat-tile.blade.php
│   └── slot-counter.blade.php    used / remaining, per program
└── app/
    ├── application-card.blade.php
    ├── applicant-flag.blade.php  the multi-award warning badge
    ├── requirement-checklist.blade.php
    └── timeline.blade.php        application status history
```

## 8.7 Quality floor

Non-negotiable, and cheap if done from the start:

- Every interactive element has a visible focus ring: `focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2`
- Every form input has a real `<label>`, not a placeholder standing in for one
- Error messages say what to do: "Upload a PDF or JPG under 5 MB" — not "Invalid file"
- Every list has an empty state that offers the next action
- Tables collapse to stacked cards below 768px
- Loading states on every Livewire action: `wire:loading.attr="disabled"`. `<x-ui.button loading="action">` swaps the label for a spinner in place, so the button keeps its width
- Saves never shift the page: a confirmation goes to the toast (`$this->dispatch('toast', message: '…')`, `<x-ui.toast>` in the public layout), not an alert inserted above the content. On staff/admin pages every action result — success or error — opens the notice modal (`$this->dispatch('notice', title: …, message: …, tone: …)`), and every confirmation uses `$store.confirm.ask(…)`; `<x-ui.alert>` is only for a standing condition of the page (e.g. no current term), never an action result. Quiet row actions (verify, physical copy received) update the row in place with no message
- Staff/admin list pages share one card layout, built from `<x-ui.search>`, `<x-ui.filter-select>` (icon select), `<x-ui.segmented>` (switch with counts — `model` for a Livewire filter, `alpine` for a client-side tab), `<x-ui.list-table>` (compact rows that collapse to cards), `<x-ui.icon-button>` for row actions and `<x-ui.list-empty>`. Whole rows open their detail page with `Livewire.navigate()`
- Review pages (application, renewal) are built from `<x-staff.review-header>`, `<x-staff.review-card>`, `<x-staff.review-fact>`, `<x-staff.other-applications>` and `<x-staff.status-timeline>`
- Modals are compact: 15px title, `py-2.5` header, `px-4 py-3.5` body; notice and confirm modals are 340px wide
- Public pages link with `wire:navigate` and component redirects pass `navigate: true`, so moving between pages swaps content without a full reload
- Public search fields use `<x-ui.search>` (icon, clear button, spinner); browse filters live in the query string (`#[Url]`) so Back returns to the same results
- `prefers-reduced-motion` respected on any transition

## 8.8 Writing in the interface

- Buttons name the outcome: "Submit application", not "Submit"
- The same action keeps the same word everywhere — if the button says "Endorse", the toast says "Endorsed"
- Empty states invite: "No applications yet. Applications appear here once students submit."
- Errors explain and instruct, never apologize
- Address the student directly: "Your application is under review", not "The application is under review"

---

# 9. Build Order

Each phase ends with something demonstrable. Do not move on until the checkpoint passes.

| Phase | Build | Checkpoint |
|---|---|---|
| 1 | Migrations, models, relationships, seeders | `migrate:fresh --seed` runs clean; Tinker can traverse every relationship |
| 2 | Auth (one login for every role), roles, layouts, UI components | Log in as each role, see the correct sidebar or student header |
| 3 | Admin: master data + program configuration | Create a scholarship program, open an offering for it, and set that offering's own requirements |
| 4 | Public: browse programs + application form | Submit an application end to end |
| 5 | Student accounts (register with email OTP or Google, profile, forgot password), My applications, notifications + email | Register, apply, see the application under My applications and a bell notification when staff move it |
| 6 | Screening engine | Ineligible applicants correctly flagged with reasons |
| 7 | Staff review + shortlist/reject | Shortlist populated with working slot counters |
| 8 | Award recording | Award directly from the shortlist, within slot limits |
| 9 | Grantees + renewal | Grantees roster (latest award per offering group, search, program → offering filter, disbursement requirements verify/return, Disburse / Disburse all, grantee page); add a renewal offering linked to the current one, open renewals, scholar hands in renewal requirements, staff open each scholar from the Renewals page to a renewal review page, verify and renew |
| 10 | Reports + SUC-Levelling export | Export matches OSDW's file column for column |
| 11 | Audit logs, polish, testing | ISO 25010 evaluation ready |

Phases 1 and 10 are the ones to protect. If time runs short, cut features from phases 8–9 — but a broken export means the system fails at its main purpose.

---

# 10. Troubleshooting

| Problem | Fix |
|---|---|
| `Class not found` after creating a file | `composer dump-autoload` |
| Livewire component not updating | `php artisan optimize:clear`, hard-refresh the browser |
| Tailwind classes not applying | Is `npm run dev` running? Tailwind 4 scans automatically — restart Vite after config changes |
| Mail times out | XAMPP blocks nothing, but antivirus and campus networks often block port 587 — test on another network |
| Upload fails on large files | Raise `upload_max_filesize` and `post_max_size` in `php.ini`, restart Apache |
| Migration fails on foreign keys | Migration order matters — parent tables must migrate first; rename timestamps if needed |
| `SQLSTATE[42000] ... key was too long` | Add `Schema::defaultStringLength(191)` in `AppServiceProvider::boot()` |
