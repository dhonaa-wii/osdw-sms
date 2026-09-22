<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Application Settings'))] class extends Component
{
    //
};
?>

<div>
    <h1 class="font-display text-[30px] font-600 text-ink">Application settings</h1>

    <p class="px-0 pb-3 pt-8 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Reference data</p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['route' => 'admin.campus', 'label' => 'Campus', 'description' => 'Single-campus name shown on the SUC-Levelling report.', 'icon' => '<path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" />'],
            ['route' => 'admin.colleges', 'label' => 'Colleges', 'description' => 'Colleges used to classify programs and applications.', 'icon' => '<path d="M4 21V8l8-5 8 5v13" /><path d="M10 21v-6h4v6" />'],
            ['route' => 'admin.programs', 'label' => 'Programs', 'description' => 'Academic programs students apply under.', 'icon' => '<path d="m22 9-10-5L2 9l10 5 10-5Z" /><path d="M6 11v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4" />'],
            ['route' => 'admin.providers', 'label' => 'Providers', 'description' => 'Scholarship sponsors and their funding source.', 'icon' => '<rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M3 12h18" />'],
            ['route' => 'admin.scholarship-programs', 'label' => 'Scholarship programs', 'description' => 'Configure programs, offerings, and requirements.', 'icon' => '<circle cx="12" cy="8" r="5" /><path d="m8.5 12.5-1.5 7L12 17l5 2.5-1.5-7" />', 'gold' => true],
            ['route' => 'admin.academic-terms', 'label' => 'Academic terms', 'description' => 'Open, set current, or close a term.', 'icon' => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" />'],
        ] as $item)
            <a href="{{ route($item['route']) }}" class="group flex items-start gap-3 rounded-card border border-line bg-surface p-5 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $item['gold'] ?? false ? 'bg-gold-50 text-gold-600' : 'bg-forest-50 text-forest-700' }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                </span>
                <div>
                    <p class="font-display text-[16px] font-600 text-ink">{{ $item['label'] }}</p>
                    <p class="mt-1 font-body text-[14px] text-slate">{{ $item['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <p class="px-0 pb-3 pt-8 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">System</p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['route' => 'audit-log.index', 'label' => 'Audit log', 'description' => 'Every create, update, and delete on scholarship decisions, with user, timestamp, and IP.', 'icon' => '<path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M9 8h6M9 12h6M9 16h4" />'],
            ['route' => 'admin.users', 'label' => 'Users', 'description' => 'Create, edit, deactivate users. Assign roles, reset passwords.', 'icon' => '<circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6" /><path d="M16 8.5a3 3 0 1 0 0-6" /><path d="M17.5 14.3c2.3.6 4 2.7 4 5.7" />'],
            ['route' => 'admin.email-log', 'label' => 'Email log', 'description' => 'Every reference, screening, and award notice the system has sent.', 'icon' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" />'],
            ['route' => 'admin.settings.system', 'label' => 'System settings', 'description' => 'Application limits, deadline defaults, upload size, public page visibility.', 'icon' => '<circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" />'],
            ['route' => 'admin.database-backup', 'label' => 'Database backup', 'description' => 'Download a full SQL backup of the database.', 'icon' => '<ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" /><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />'],
        ] as $item)
            <a href="{{ route($item['route']) }}" class="group flex items-start gap-3 rounded-card border border-line bg-surface p-5 transition hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-md">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                </span>
                <div>
                    <p class="font-display text-[16px] font-600 text-ink">{{ $item['label'] }}</p>
                    <p class="mt-1 font-body text-[14px] text-slate">{{ $item['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</div>
