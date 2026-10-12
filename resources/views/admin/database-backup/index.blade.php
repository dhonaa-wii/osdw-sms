<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::staff', array('title' => 'Database Backup'))] class extends Component
{
    public function with(): array
    {
        return [
            'connection' => config('database.default'),
        ];
    }
};
?>

<div>
    <x-ui.back-link :href="route('admin.settings')" label="Application settings" />

    <h1 class="mt-3 font-display text-[30px] font-600 text-ink">Database backup</h1>

    @if ($connection !== 'mysql')
        <x-ui.alert type="warning" class="mt-6">
            The active connection is "{{ $connection }}", not MySQL. Backup export requires the MySQL connection configured for this deployment.
        </x-ui.alert>
    @else
        <div class="mt-6 rounded-card border border-line bg-surface p-5">
            <p class="font-body text-[14px] text-ink">This runs <code class="font-mono text-[13px]">mysqldump</code> against the live database and streams the result to you — nothing is written to the server.</p>
            <p class="mt-2 font-body text-[13px] text-slate">Restoring from a backup is intentionally not built into this page. Import the .sql file with a database tool (phpMyAdmin, the mysql CLI) after review — restoring blind, from the browser, risks overwriting current data by mistake.</p>
            <a href="{{ route('admin.database-backup.download') }}" class="mt-4 inline-flex items-center justify-center rounded-control bg-navy-600 px-4 py-2 font-body text-[14px] font-500 text-white hover:bg-navy-700">Download backup (.sql)</a>
        </div>
    @endif
</div>
