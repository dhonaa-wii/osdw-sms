<?php

use App\Enums\BrandingImageKey;
use App\Models\BrandingImage;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::help', array('title' => 'Help Center'))] class extends Component
{
    /**
     * The guide's contents: chapters, then topics. The sidebar, the search,
     * the PDF contents page and the chapter cards all read from this list,
     * and each topic id matches an <x-help.topic> in resources/views/help.
     */
    public function with(): array
    {
        $chapters = $this->chapters();

        return [
            'chapters' => $chapters,
            // Flat list of topics for the search and the active-section tracking.
            'topics' => collect($chapters)
                ->flatMap(fn (array $chapter) => collect($chapter['topics'])
                    ->map(fn (string $title, string $id) => ['id' => $id, 'chapter' => $chapter['id'], 'title' => $title])
                    ->values())
                ->values()
                ->all(),
            'logoUrl' => BrandingImage::url(BrandingImageKey::Logo),
        ];
    }

    private function chapters(): array
    {
        return [
            ['id' => 'getting-started', 'title' => 'Getting started', 'icon' => 'info', 'view' => 'help.getting-started', 'summary' => 'What the system does, who uses it, and the words it uses.', 'topics' => [
                'gs-about' => 'About the system',
                'gs-roles' => 'Who uses it',
                'gs-signin' => 'Signing in',
                'gs-layout' => 'Finding your way around',
                'gs-statuses' => 'Application statuses',
                'gs-terms' => 'Key terms',
            ]],
            ['id' => 'students', 'title' => 'For students', 'icon' => 'graduation', 'view' => 'help.students', 'summary' => 'Create an account, apply, track and renew your scholarship.', 'topics' => [
                'st-register' => 'Create your account',
                'st-profile' => 'Set up your profile',
                'st-browse' => 'Find a scholarship',
                'st-apply' => 'Apply for a scholarship',
                'st-rules' => 'When an application can’t be submitted',
                'st-track' => 'My applications',
                'st-application' => 'Your application page',
                'st-requirements' => 'Additional requirements and the interview',
                'st-award' => 'When you are awarded',
                'st-renewal' => 'Renewing your scholarship',
                'st-notifications' => 'Notifications',
                'st-results' => 'Published results',
                'st-settings' => 'Settings and passwords',
            ]],
            ['id' => 'staff', 'title' => 'For OSDW staff', 'icon' => 'users', 'view' => 'help.staff', 'summary' => 'Screen, shortlist and award applicants, verify renewals, report.', 'topics' => [
                'sf-setup' => 'Your first log-in',
                'sf-dashboard' => 'Dashboard',
                'sf-applications' => 'Applications list',
                'sf-workspace' => 'Queue and shortlist',
                'sf-review' => 'Reviewing an application',
                'sf-decide' => 'Endorsing or rejecting',
                'sf-shortlist' => 'Shortlist requirements and the interview',
                'sf-award' => 'Awarding a scholarship',
                'sf-renewals' => 'Reviewing renewals',
                'sf-grantees' => 'Grantees, results and disbursement',
                'sf-announcements' => 'Announcements',
                'sf-reports' => 'SUC-Levelling report',
                'sf-account' => 'Notifications and your profile',
            ]],
            ['id' => 'administrators', 'title' => 'For administrators', 'icon' => 'settings', 'view' => 'help.administrators', 'summary' => 'Set up programs and offerings, requirements, accounts and settings.', 'topics' => [
                'ad-overview' => 'Setting up a scholarship, start to finish',
                'ad-reference' => 'Reference data',
                'ad-programs' => 'Scholarship programs',
                'ad-offerings' => 'Opening an offering',
                'ad-requirements' => 'The Requirements page',
                'ad-standard' => 'Standard requirements',
                'ad-renewals' => 'Renewal offerings and opening renewals',
                'ad-delete' => 'Editing, deleting and restoring',
                'ad-users' => 'Staff accounts',
                'ad-maintenance' => 'Audit log, email log and backups',
            ]],
            ['id' => 'faq', 'title' => 'Questions and troubleshooting', 'icon' => 'chat', 'view' => 'help.faq', 'summary' => 'Quick answers to the questions OSDW hears most.', 'topics' => [
                'faq-students' => 'Questions from students',
                'faq-staff' => 'Questions from staff and administrators',
                'faq-contact' => 'Contacting OSDW',
            ]],
        ];
    }
};
?>

<div
    x-data="{
        query: '',
        active: '',
        tocOpen: false,
        topics: @js($topics),
        text: {},
        init() {
            // Search runs over each topic's full text, read once from the page.
            document.querySelectorAll('[data-help-topic]').forEach((el) => {
                this.text[el.id] = el.textContent.toLowerCase().replace(/\s+/g, ' ');
            });

            const observer = new IntersectionObserver((entries) => {
                entries.filter((entry) => entry.isIntersecting).forEach((entry) => { this.active = entry.target.id; });
            }, { rootMargin: '-80px 0px -70% 0px' });
            document.querySelectorAll('[data-help-topic]').forEach((el) => observer.observe(el));

            if (window.location.hash) {
                this.$nextTick(() => document.getElementById(window.location.hash.slice(1))?.scrollIntoView());
            }
        },
        terms() {
            return this.query.toLowerCase().trim().split(/\s+/).filter(Boolean);
        },
        topicVisible(id) {
            const terms = this.terms();
            const title = (this.topics.find((topic) => topic.id === id)?.title ?? '').toLowerCase();
            return terms.every((term) => title.includes(term) || (this.text[id] ?? '').includes(term));
        },
        chapterVisible(id) {
            return this.topics.some((topic) => topic.chapter === id && this.topicVisible(topic.id));
        },
        matchCount() {
            return this.topics.filter((topic) => this.topicVisible(topic.id)).length;
        },
        isActiveChapter(id) {
            return this.topics.some((topic) => topic.chapter === id && topic.id === this.active);
        },
        go(id) {
            this.tocOpen = false;
            document.getElementById(id)?.scrollIntoView();
            history.replaceState(null, '', '#' + id);
            this.active = id;
        },
    }"
>
    {{-- PDF cover and contents: printed only. --}}
    <div data-help-print class="hidden">
        <div class="flex min-h-[250mm] flex-col">
            <div class="flex items-center gap-3 border-b border-line pb-4">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="OSDW logo" class="size-14 rounded-full object-contain">
                @endif
                <div>
                    <p class="font-body text-[12px] text-slate">Office of Student Development and Welfare</p>
                    <p class="font-display text-[15px] font-600 text-navy-700">OSDW Scholarship Management System</p>
                </div>
            </div>
            <div class="my-auto py-16">
                <p class="font-body text-[14px] font-500 text-navy-600">Help Center</p>
                <h1 class="mt-2 font-display text-[44px] font-700 leading-[1.05] text-ink">User guide</h1>
                <p class="mt-4 max-w-[480px] font-body text-[15px] leading-7 text-slate">For students, OSDW staff and system administrators.</p>
                <div class="mt-8 h-1 w-16 rounded-full bg-gold-600"></div>
            </div>
            <p class="font-body text-[12px] text-mist">{{ now()->format('F Y') }} · {{ url('/help') }}</p>
        </div>

        <div style="break-before: page">
            <h2 class="font-display text-[24px] font-600 text-ink">Contents</h2>
            <ol class="mt-6 space-y-5">
                @foreach ($chapters as $chapter)
                    <li data-help-keep>
                        <p class="font-display text-[15px] font-600 text-ink">{{ $loop->iteration }}. {{ $chapter['title'] }}</p>
                        <ol class="mt-1.5 grid grid-cols-2 gap-x-6 gap-y-1 pl-5 font-body text-[12.5px] text-slate">
                            @foreach ($chapter['topics'] as $id => $title)
                                <li>{{ $loop->parent->iteration }}.{{ $loop->iteration }} {{ $title }}</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- Heading, search and chapter cards. --}}
    <section data-help-chrome class="relative overflow-hidden border-b border-line bg-surface">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <svg class="absolute -top-16 left-1/2 h-[360px] w-[1400px] -translate-x-1/2 text-wave/10" viewBox="0 0 1400 360" fill="none" preserveAspectRatio="none">
                <path d="M0,80 C200,30 400,130 700,80 C1000,30 1200,130 1400,80" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,150 C200,100 400,200 700,150 C1000,100 1200,200 1400,150" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,220 C200,170 400,270 700,220 C1000,170 1200,270 1400,220" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,290 C200,240 400,340 700,290 C1000,240 1200,340 1400,290" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </div>

        <div class="relative mx-auto max-w-[1240px] px-4 py-10 sm:px-6 sm:py-14">
            <h1 class="font-display text-[32px] font-700 leading-tight text-ink sm:text-[40px]">How can we help?</h1>

            <div class="mt-5 max-w-[620px]">
                <label for="help-search" class="sr-only">Search the Help Center</label>
                <div class="relative">
                    <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-mist" />
                    <input
                        id="help-search"
                        type="search"
                        x-model.debounce.150ms="query"
                        x-on:keydown.escape="query = ''"
                        placeholder="Search for apply, renewal, disburse, password…"
                        autocomplete="off"
                        class="h-12 w-full rounded-card border border-line bg-surface pl-11 pr-24 font-body text-[15px] text-ink shadow-sm placeholder:text-mist focus:border-navy-600 focus:outline-none focus:ring-2 focus:ring-navy-600/20"
                    >
                    <span x-cloak x-show="query.trim() !== ''" class="absolute right-3 top-1/2 -translate-y-1/2 font-body text-[12px] text-mist">
                        <span class="font-mono" x-text="matchCount()"></span> <span x-text="matchCount() === 1 ? 'topic' : 'topics'"></span>
                    </span>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($chapters as $chapter)
                    <a
                        href="#{{ $chapter['id'] }}"
                        x-on:click.prevent="query = ''; $nextTick(() => go(@js($chapter['id'])))"
                        class="group flex flex-col rounded-card border border-line bg-surface p-4 transition-colors hover:border-navy-600/40 hover:bg-navy-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                    >
                        <span class="flex size-9 items-center justify-center rounded-control bg-navy-50 text-navy-700 transition-colors group-hover:bg-navy-600 group-hover:text-white">
                            <x-ui.icon :name="$chapter['icon']" class="size-[18px]" />
                        </span>
                        <span class="mt-3 font-display text-[15px] font-600 text-ink group-hover:text-navy-700">{{ $chapter['title'] }}</span>
                        <span class="mt-1 font-body text-[13px] leading-5 text-slate">{{ $chapter['summary'] }}</span>
                        <span class="mt-auto pt-3 font-body text-[12px] text-mist"><span class="font-mono">{{ count($chapter['topics']) }}</span> topics</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <div data-help-layout class="mx-auto grid max-w-[1240px] grid-cols-1 gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:gap-12 lg:py-12">
        {{-- Contents: a sticky sidebar from lg up, a collapsible panel below. --}}
        <aside data-help-chrome class="lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto lg:pb-6">
            <button
                type="button"
                x-on:click="tocOpen = ! tocOpen"
                x-bind:aria-expanded="tocOpen.toString()"
                aria-controls="help-contents"
                class="flex w-full items-center justify-between rounded-card border border-line bg-surface px-4 py-3 font-display text-[15px] font-600 text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 lg:hidden"
            >
                Contents
                <x-ui.icon name="chevron-down" class="size-4 text-slate transition-transform" x-bind:class="tocOpen && 'rotate-180'" />
            </button>

            <nav
                id="help-contents"
                aria-label="Help Center contents"
                x-bind:class="tocOpen ? 'block' : 'hidden lg:block'"
                class="mt-2 rounded-card border border-line bg-surface p-3 lg:mt-0 lg:border-0 lg:bg-transparent lg:p-0"
            >
                @foreach ($chapters as $chapter)
                    <div x-show="chapterVisible(@js($chapter['id']))" class="mb-4">
                        <a
                            href="#{{ $chapter['id'] }}"
                            x-on:click.prevent="go(@js($chapter['id']))"
                            class="flex items-center gap-2 rounded-control px-2 py-1.5 font-display text-[14px] font-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                            x-bind:class="isActiveChapter(@js($chapter['id'])) ? 'text-navy-700' : 'text-ink hover:text-navy-700'"
                        >
                            <x-ui.icon :name="$chapter['icon']" class="size-4" />
                            {{ $chapter['title'] }}
                        </a>
                        <ul class="mt-0.5 space-y-px border-l border-line pl-0 lg:ml-[15px]">
                            @foreach ($chapter['topics'] as $id => $title)
                                <li x-show="topicVisible(@js($id))">
                                    <a
                                        href="#{{ $id }}"
                                        x-on:click.prevent="go(@js($id))"
                                        class="-ml-px block border-l-2 py-1 pl-3 pr-2 font-body text-[13px] leading-5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600"
                                        x-bind:class="active === @js($id) ? 'border-navy-600 font-500 text-navy-700' : 'border-transparent text-slate hover:border-line hover:text-ink'"
                                        x-bind:aria-current="active === @js($id) ? 'location' : null"
                                    >{{ $title }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div x-cloak x-show="matchCount() === 0" class="px-2 py-3 font-body text-[13px] text-slate">
                    No topics match.
                    <button type="button" x-on:click="query = ''" class="font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">Clear search</button>
                </div>
            </nav>
        </aside>

        <div class="min-w-0 max-w-[820px] [counter-reset:help-figure]">
            <div x-cloak x-show="matchCount() === 0" data-help-chrome class="rounded-card border border-line bg-surface px-6 py-12 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-navy-50 text-navy-700">
                    <x-ui.icon name="search" class="size-5" />
                </span>
                <p class="mt-3 font-display text-[17px] font-600 text-ink">No topics match “<span x-text="query.trim()"></span>”</p>
                <p class="mt-1 font-body text-[14px] text-slate">Try a shorter word, such as “apply” or “renew”, or browse the chapters.</p>
                <button type="button" x-on:click="query = ''" class="mt-4 inline-flex items-center gap-2 rounded-control border border-line bg-surface px-4 py-2 font-body text-[14px] font-500 text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                    Clear search
                </button>
            </div>

            @foreach ($chapters as $chapter)
                @include($chapter['view'], ['number' => $loop->iteration, 'chapter' => $chapter])
            @endforeach

            <div data-help-chrome class="mt-16 flex flex-col items-start gap-4 rounded-card border border-line bg-surface p-6 sm:flex-row sm:items-center">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700">
                    <x-ui.icon name="chat" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-display text-[16px] font-600 text-ink">Still stuck?</p>
                    <p class="font-body text-[14px] text-slate">Visit or message the OSDW office. Bring your Student ID.</p>
                </div>
                <a href="#faq-contact" x-on:click.prevent="query = ''; $nextTick(() => go('faq-contact'))" class="inline-flex items-center gap-2 rounded-control bg-navy-600 px-4 py-2 font-body text-[14px] font-500 text-white hover:bg-navy-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">
                    Contact OSDW
                </a>
            </div>
        </div>
    </div>
</div>
