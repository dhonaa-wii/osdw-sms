<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Application Settings'))] class extends Component
{
    //
};
?>

@php
    $sections = [
        ['title' => 'Reference data', 'icon' => 'layers', 'items' => [
            ['route' => 'admin.campus', 'label' => 'Campus', 'description' => 'Campus name printed on reports', 'icon' => 'map-pin'],
            ['route' => 'admin.colleges', 'label' => 'Colleges', 'description' => 'Colleges that classify programs and applications', 'icon' => 'building'],
            ['route' => 'admin.programs', 'label' => 'Programs', 'description' => 'Academic programs students enroll in', 'icon' => 'graduation'],
            ['route' => 'admin.providers', 'label' => 'Providers', 'description' => 'Scholarship sponsors and their funding source', 'icon' => 'users'],
            ['route' => 'admin.scholarship-programs', 'label' => 'Scholarship programs', 'description' => 'Programs, offerings and requirements', 'icon' => 'award'],
            ['route' => 'admin.academic-terms', 'label' => 'Academic terms', 'description' => 'Open, set current or close a term', 'icon' => 'calendar'],
        ]],
        ['title' => 'System', 'icon' => 'sliders', 'items' => [
            ['route' => 'admin.users', 'label' => 'Users', 'description' => 'Staff accounts, passwords and access', 'icon' => 'user'],
            ['route' => 'audit-log.index', 'label' => 'Audit log', 'description' => 'Who changed what on scholarship decisions', 'icon' => 'clipboard-check'],
            ['route' => 'admin.email-log', 'label' => 'Email log', 'description' => 'Notices the system has sent', 'icon' => 'mail'],
            ['route' => 'admin.settings.system', 'label' => 'System settings', 'description' => 'Upload size and public page visibility', 'icon' => 'sliders'],
            ['route' => 'admin.settings.deleted-items', 'label' => 'Deleted items', 'description' => 'Restore deleted programs and offerings', 'icon' => 'trash'],
            ['route' => 'admin.database-backup', 'label' => 'Database backup', 'description' => 'Download a full SQL backup', 'icon' => 'download'],
        ]],
    ];
@endphp

<div>
    <h1 class="font-display text-[26px] font-600 text-ink">Application settings</h1>

    <div class="mt-5 grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
        @foreach ($sections as $section)
            <x-staff.review-card :icon="$section['icon']" :title="$section['title']">
                <ul class="divide-y divide-line">
                    @foreach ($section['items'] as $item)
                        <li wire:key="setting-{{ $item['route'] }}">
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-paper/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-600"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-control border border-line bg-paper text-slate transition-colors group-hover:border-navy-600/30 group-hover:bg-navy-50 group-hover:text-navy-700">
                                    <x-ui.icon :name="$item['icon']" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-body text-[14px] font-500 text-ink group-hover:text-navy-700">{{ $item['label'] }}</span>
                                    <span class="block truncate font-body text-[12px] text-mist">{{ $item['description'] }}</span>
                                </span>
                                <x-ui.icon name="chevron-right" class="size-4 text-mist transition-transform group-hover:translate-x-0.5 group-hover:text-navy-700" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-staff.review-card>
        @endforeach
    </div>
</div>
