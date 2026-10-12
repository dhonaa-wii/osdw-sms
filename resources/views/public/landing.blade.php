<?php

use App\Enums\BrandingImageKey;
use App\Models\Announcement;
use App\Models\BrandingImage;
use App\Models\Campus;
use App\Models\College;
use App\Models\ProgramOffering;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::marketing', array('title' => 'OSDW Scholarship System'))] class extends Component
{
    public function with(): array
    {
        return [
            'logoUrl' => BrandingImage::url(BrandingImageKey::Logo),            'campus' => Campus::first(),
            'openCount' => ProgramOffering::open()->count(),
            'collegeCount' => College::where('is_active', true)->count(),
            'featuredOfferings' => ProgramOffering::open()
                ->whereHas('scholarshipProgram', fn ($query) => $query->where('is_active', true))
                ->with('scholarshipProgram.provider', 'academicTerm')
                ->orderBy('closes_at')
                ->take(3)
                ->get(),
            'nearestDeadline' => ProgramOffering::open()
                ->whereHas('scholarshipProgram', fn ($query) => $query->where('is_active', true))
                ->orderBy('closes_at')
                ->value('closes_at'),
            'announcements' => Announcement::whereNull('program_offering_id')
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->take(3)
                ->get(),
            'publishedOfferings' => ProgramOffering::where('results_published', true)
                ->with('scholarshipProgram.provider')
                ->withCount('awards as awarded_count')
                ->orderByDesc('results_published_at')
                ->take(3)
                ->get(),
        ];
    }
};
?>

<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-line bg-surface">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <svg class="absolute -top-10 left-1/2 h-[420px] w-[1400px] -translate-x-1/2 text-wave/15" viewBox="0 0 1400 420" fill="none" preserveAspectRatio="none">
                <path d="M0,80 C200,30 400,130 700,80 C1000,30 1200,130 1400,80" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,150 C200,100 400,200 700,150 C1000,100 1200,200 1400,150" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,220 C200,170 400,270 700,220 C1000,170 1200,270 1400,220" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,290 C200,240 400,340 700,290 C1000,240 1200,340 1400,290" stroke="currentColor" stroke-width="1.5" />
                <path d="M0,360 C200,310 400,410 700,360 C1000,310 1200,410 1400,360" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </div>

        <div class="relative mx-auto max-w-[1200px] px-6 py-20">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-[1fr_400px]">
                <div class="max-w-[640px]">
                    <p class="font-body text-[13px] font-600 uppercase tracking-[0.08em] text-navy-700">
                        Office of Student Development and Welfare
                        @isset($campus) &middot; {{ $campus->name }} campus @endisset
                    </p>
                    <h1 class="mt-3 font-display text-[42px] font-700 leading-[1.1] text-ink">
                        A Web-Based
                        <span class="relative inline-block">
                            Scholarship
                            <span class="absolute inset-x-0 -bottom-1 h-[3px] rounded-full bg-navy-600/30"></span>
                        </span>
                        Management System
                    </h1>
                    <p class="mt-5 font-body text-[17px] text-slate">
                        Browse every scholarship currently accepting applications, apply with your student account,
                        and follow each application from one page — you're notified when anything changes.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('programs.index') }}" class="inline-flex items-center justify-center rounded-control bg-navy-600 px-5 py-3 font-body text-[15px] font-500 text-white transition hover:bg-navy-700">
                            Browse scholarships
                        </a>
                        <a href="{{ route('results.index') }}" class="inline-flex items-center justify-center rounded-control border border-line bg-surface px-5 py-3 font-body text-[15px] font-500 text-ink transition hover:border-navy-600">
                            View published results
                        </a>
                    </div>
                </div>

                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Office of Student Development and Welfare, Cagayan State University" class="mx-auto w-full max-w-[360px] rounded-full">
                @endif
            </div>

            <div class="mt-14 grid grid-cols-2 gap-6 sm:grid-cols-4" data-reveal-group>
                <div data-reveal-item>
                    <p class="font-mono text-[28px] font-500 text-navy-700" data-count-to="{{ $openCount }}">{{ $openCount }}</p>
                    <p class="font-body text-[13px] text-slate">Open scholarship{{ $openCount === 1 ? '' : 's' }} right now</p>
                </div>
                <div data-reveal-item>
                    <p class="font-mono text-[28px] font-500 text-navy-700" data-count-to="{{ $collegeCount }}">{{ $collegeCount }}</p>
                    <p class="font-body text-[13px] text-slate">Colleges served</p>
                </div>
                <div data-reveal-item>
                    <p class="font-mono text-[28px] font-500 text-navy-700">1</p>
                    <p class="font-body text-[13px] text-slate">Campus{{ isset($campus) ? " ({$campus->name})" : '' }}</p>
                </div>
                <div data-reveal-item>
                    @if ($nearestDeadline)
                        <p class="font-mono text-[28px] font-500 text-navy-700">{{ (int) now()->diffInDays($nearestDeadline) }}d</p>
                        <p class="font-body text-[13px] text-slate">Until the nearest deadline</p>
                    @else
                        <p class="font-mono text-[28px] font-500 text-mist">&mdash;</p>
                        <p class="font-body text-[13px] text-slate">No deadlines open right now</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-[1200px] px-6 py-16">
        <h2 class="font-display text-[26px] font-600 text-ink">How it works</h2>
        <div class="relative mt-10" data-reveal-group>
            <div class="pointer-events-none absolute inset-x-0 top-[22px] hidden h-px bg-line lg:block" aria-hidden="true"></div>
            <div class="relative grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['step' => '01', 'title' => 'Browse', 'body' => 'See every scholarship currently open, its requirements, slots, and deadline.'],
                    ['step' => '02', 'title' => 'Register and apply', 'body' => 'Confirm your email, fill in your profile once, then apply and upload the documents each offering asks for.'],
                    ['step' => '03', 'title' => 'Track your status', 'body' => 'See every application on My applications. The bell tells you when your status changes or something is due.'],
                ] as $item)
                    <div data-reveal-item class="group bg-paper pr-4 lg:bg-transparent">
                        <div class="flex size-11 items-center justify-center rounded-full border border-line bg-paper font-mono text-[14px] font-500 text-navy-700 transition group-hover:border-navy-600 group-hover:bg-navy-600 group-hover:text-white">
                            {{ $item['step'] }}
                        </div>
                        <p class="mt-4 font-display text-[17px] font-600 text-ink">{{ $item['title'] }}</p>
                        <p class="mt-1 font-body text-[14px] text-slate">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured open scholarships --}}
    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-[1200px] px-6 py-16">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-[26px] font-600 text-ink">Open right now</h2>
                <a href="{{ route('programs.index') }}" class="font-body text-[14px] font-500 text-navy-700 hover:underline">View all &rarr;</a>
            </div>

            @if ($featuredOfferings->isEmpty())
                <p class="mt-6 font-body text-[15px] text-slate">Nothing is open for applications right now. Check back soon.</p>
            @else
                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($featuredOfferings as $offering)
                        @php $closingSoon = $offering->closes_at <= now()->addDays(7); @endphp
                        <a href="{{ route('programs.show', $offering) }}" data-reveal-item class="group block rounded-card border border-line bg-paper p-5 transition hover:-translate-y-0.5 hover:border-navy-600 hover:shadow-md">
                            <p class="font-body text-[12px] text-slate">{{ $offering->category->label() }}</p>
                            <p class="mt-0.5 font-display text-[17px] font-600 text-ink">{{ $offering->scholarshipProgram->name }}</p>
                            <p class="mt-1 font-body text-[13px] text-slate">{{ $offering->scholarshipProgram->provider->name }} &middot; {{ $offering->displayName() }}</p>

                            @if ($closingSoon)
                                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1">
                                    <span class="border-l-2 border-amber-600 pl-2 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-amber-600">Closing soon</span>
                                </div>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 border-t border-line pt-3 font-body text-[13px] text-slate">
                                <span>Slots: <span class="font-mono text-ink">{{ $offering->total_slots }}</span></span>
                                <span>Deadline: <span class="font-mono text-ink">{{ $offering->closes_at->format('M j, Y') }}</span></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Published results --}}
    @if ($publishedOfferings->isNotEmpty())
        <section class="border-t border-line">
            <div class="mx-auto max-w-[1200px] px-6 py-16">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-[26px] font-600 text-ink">Published results</h2>
                    <a href="{{ route('results.index') }}" class="font-body text-[14px] font-500 text-navy-700 hover:underline">View all &rarr;</a>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($publishedOfferings as $offering)
                        <div data-reveal-item class="rounded-card border border-line bg-surface p-5">
                            <span class="flex size-9 items-center justify-center rounded-full bg-gold-50 text-gold-600">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" /></svg>
                            </span>
                            <p class="mt-3 font-display text-[17px] font-600 text-ink">{{ $offering->scholarshipProgram->name }}</p>
                            <p class="mt-1 font-body text-[13px] text-slate">{{ $offering->scholarshipProgram->provider->name }} &middot; {{ $offering->displayName() }}</p>
                            <p class="mt-3 font-body text-[13px] text-slate">{{ $offering->awarded_count }} awarded</p>
                            <a href="{{ route('programs.results', $offering) }}" class="mt-4 inline-flex items-center justify-center rounded-control border border-line bg-surface px-4 py-2 font-body text-[14px] font-500 text-ink transition hover:border-navy-600">View</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Announcements --}}
    @if ($announcements->isNotEmpty())
        <section id="announcements" class="mx-auto max-w-[1200px] scroll-mt-20 px-6 py-16">
            <h2 class="font-display text-[26px] font-600 text-ink">Announcements</h2>
            <div class="mt-8 max-w-[640px]" data-reveal-group>
                @foreach ($announcements as $announcement)
                    <div data-reveal-item class="relative border-l-2 border-line py-1 pb-8 pl-6 last:border-transparent last:pb-0">
                        <span class="absolute -left-[5px] top-2 size-2 rounded-full bg-navy-600"></span>
                        <p class="font-mono text-[12px] text-mist">{{ $announcement->published_at?->format('M j, Y') }}</p>
                        <p class="mt-1 font-display text-[16px] font-600 text-ink">{{ $announcement->title }}</p>
                        <p class="mt-1 font-body text-[14px] text-slate">{{ $announcement->body }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Good to know --}}
    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-[1200px] px-6 py-16">
            <h2 class="font-display text-[26px] font-600 text-ink">Good to know</h2>
            <div class="mt-8 grid grid-cols-1 divide-y divide-line sm:grid-cols-2 sm:gap-x-10 sm:gap-y-8 sm:divide-y-0" data-reveal-group>
                @foreach ([
                    [
                        'title' => 'One account for everything',
                        'body' => 'Your profile fills in every application, and your requirements, interview and results all show up in one place.',
                        'icon' => '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 7.5-2"/><circle cx="12" cy="15.5" r="1.25"/></svg>',
                    ],
                    [
                        'title' => 'Apply to more than one',
                        'body' => "You're welcome to apply to multiple scholarships. Each one is tracked separately.",
                        'icon' => '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 4 8l8 4 8-4-8-4Z"/><path d="M4 12l8 4 8-4"/><path d="M4 16l8 4 8-4"/></svg>',
                    ],
                    [
                        'title' => 'Requirements vary per scholarship',
                        'body' => 'Each offering lists exactly which documents it needs before you start &mdash; check the details page first.',
                        'icon' => '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M9 8h6M9 12h6"/><path d="m9 16 1.5 1.5L14 14"/></svg>',
                    ],
                    [
                        'title' => 'Reviewed by OSDW staff',
                        'body' => "Every application is screened and reviewed by a person. Automated checks only flag issues &mdash; they don't decide outcomes.",
                        'icon' => '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="8" r="3.5"/><path d="M4 20c0-3.3 2.7-6 6-6 1.3 0 2.5.4 3.5 1.1"/><path d="m15 17 2 2 4-4"/></svg>',
                    ],
                ] as $item)
                    <div data-reveal-item class="flex gap-4 py-6 first:pt-0 sm:py-0">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700">
                            {!! $item['icon'] !!}
                        </span>
                        <div>
                            <p class="font-display text-[16px] font-600 text-ink">{{ $item['title'] }}</p>
                            <p class="mt-1 font-body text-[14px] text-slate">{!! $item['body'] !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
