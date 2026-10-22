@props(['name'])

{{-- Outline icons on a 24px grid, drawn with currentColor so they follow the surrounding text colour. --}}
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('form')
            <path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M14 3v5h5" /><path d="M9 13h6M9 17h4" />
            @break
        @case('clipboard-check')
            <path d="M9 4H7a1 1 0 0 0-1 1v15a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-2" /><rect x="9" y="2.5" width="6" height="3.5" rx="1" /><path d="m9.5 14 2 2 3.5-4" />
            @break
        @case('calendar')
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 10h17M8 3v4M16 3v4" />
            @break
        @case('upload')
            <path d="M12 15V4M12 4 7.5 8.5M12 4l4.5 4.5" /><path d="M4 15v4a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-4" />
            @break
        @case('printer')
            <path d="M7 9V3.5h10V9" /><path d="M7 17H5a1.5 1.5 0 0 1-1.5-1.5v-5A1.5 1.5 0 0 1 5 9h14a1.5 1.5 0 0 1 1.5 1.5v5A1.5 1.5 0 0 1 19 17h-2" /><path d="M7 14h10v6.5H7Z" />
            @break
        @case('download')
            <path d="M12 4v11M12 15l-4.5-4.5M12 15l4.5-4.5" /><path d="M4 15v4a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-4" />
            @break
        @case('building')
            <path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16" /><path d="M15 9h4a1 1 0 0 1 1 1v11" /><path d="M3 21h18" /><path d="M8 8h3M8 12h3M8 16h3" />
            @break
        @case('layers')
            <path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 13 9 5 9-5" />
            @break
        @case('shortlist')
            <path d="M9 6h11M9 12h11M9 18h11" /><path d="m3.5 6 1 1 2-2M3.5 12l1 1 2-2M3.5 18l1 1 2-2" />
            @break
        @case('award')
            <circle cx="12" cy="9" r="5.5" /><path d="m8.5 13.5-1.5 7.5 5-2.5 5 2.5-1.5-7.5" />
            @break
        @case('arrow-up')
            <path d="M12 19V5M6 11l6-6 6 6" />
            @break
        @case('arrow-down')
            <path d="M12 5v14M6 13l6 6 6-6" />
            @break
        @case('pencil')
            <path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z" /><path d="m13.5 6.5 4 4" />
            @break
        @case('trash')
            <path d="M4 7h16M10 11v6M14 11v6" /><path d="M5.5 7l1 12.5a1.5 1.5 0 0 0 1.5 1.5h8a1.5 1.5 0 0 0 1.5-1.5l1-12.5" /><path d="M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7" />
            @break
        @case('restore')
            <path d="M4 4v5h5" /><path d="M4.5 9A8 8 0 1 1 4 13" />
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('dots-vertical')
            <circle cx="12" cy="5.5" r="1" fill="currentColor" /><circle cx="12" cy="12" r="1" fill="currentColor" /><circle cx="12" cy="18.5" r="1" fill="currentColor" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" /><path d="m8 12.5 2.5 2.5L16 9.5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('alert-circle')
            <circle cx="12" cy="12" r="9" /><path d="M12 7.5v5M12 16.5h.01" />
            @break
        @case('map-pin')
            <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" />
            @break
        @case('info')
            <circle cx="12" cy="12" r="9" /><path d="M12 11v5.5M12 7.5h.01" />
            @break
        @case('expand')
            <path d="M14.5 4H20v5.5M9.5 20H4v-5.5M20 4l-6.5 6.5M4 20l6.5-6.5" />
            @break
        @case('eye')
            <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" /><circle cx="12" cy="12" r="3" />
            @break
        @case('chat')
            <path d="M5 18.5V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8.5L5 18.5Z" /><path d="M9 9h6M9 12.5h4" />
            @break
        @case('type')
            <path d="M5 7V5h14v2M12 5v14M9 19h6" />
            @break
        @case('paragraph')
            <path d="M4 6h16M4 10h16M4 14h16M4 18h10" />
            @break
        @case('hash')
            <path d="M9 4 7 20M17 4l-2 16M4.5 9h16M3.5 15h16" />
            @break
        @case('mail')
            <rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4 7 8 6 8-6" />
            @break
        @case('phone')
            <rect x="7" y="3" width="10" height="18" rx="2" /><path d="M11 17.5h2" />
            @break
        @case('link')
            <path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1" /><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" />
            @break
        @case('list')
            <path d="M9 6h11M9 12h11M9 18h11" /><circle cx="4.5" cy="6" r="1" /><circle cx="4.5" cy="12" r="1" /><circle cx="4.5" cy="18" r="1" />
            @break
        @case('check-square')
            <rect x="4" y="4" width="16" height="16" rx="2" /><path d="m8.5 12 2.5 2.5 4.5-5" />
            @break
        @case('image')
            <rect x="3.5" y="4.5" width="17" height="15" rx="2" /><circle cx="9" cy="10" r="1.75" /><path d="m20.5 16-5-5-8.5 8.5" />
            @break
        @case('sliders')
            <path d="M4 7h10M18 7h2M4 17h4M12 17h8" /><circle cx="16" cy="7" r="2" /><circle cx="10" cy="17" r="2" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" /><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0" />
            @break
        @case('lock')
            <rect x="5" y="10.5" width="14" height="10" rx="2" /><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.4-4.4" />
            @break
        @case('chart-bar')
            <path d="M4 20h16" /><path d="M7 16v-5M12 16V6M17 16v-8" />
            @break
        @case('megaphone')
            <path d="M4 10v4a1 1 0 0 0 1 1h2l6 4V5L7 9H5a1 1 0 0 0-1 1Z" /><path d="M17 9a4 4 0 0 1 0 6" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2 .8 3.5 2.8 3.5 5.5" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('log-out')
            <path d="M15 4h3a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 18 20h-3" /><path d="M10 16.5 5.5 12 10 7.5M5.5 12H15" />
            @break
        @case('power')
            <path d="M12 3.5v8" /><path d="M7 6.5a7.5 7.5 0 1 0 10 0" />
            @break
        @case('check')
            <path d="M5 13l4 4L19 7" />
            @break
        @case('graduation')
            <path d="m12 4 10 5-10 5L2 9l10-5Z" /><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5" />
            @break
        @case('help')
            <circle cx="12" cy="12" r="9" /><path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.4c-.6.3-1 .8-1 1.5v.6" /><path d="M12 16.8h.01" />
            @break
        @case('bell')
            <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z" /><path d="M10 20.5a2.2 2.2 0 0 0 4 0" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />
            @break
    @endswitch
</svg>
