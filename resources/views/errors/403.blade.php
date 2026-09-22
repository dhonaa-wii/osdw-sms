<x-errors.page
    code="403"
    title="You don't have access"
    message="You don't have permission to view this page."
    icon='<rect x="4" y="10" width="16" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />'
>
    @auth
        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Go to your dashboard</a>
    @else
        <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Return to homepage</a>
    @endauth
</x-errors.page>
