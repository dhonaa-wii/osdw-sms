<x-errors.page
    code="404"
    title="Page not found"
    message="The page you're looking for doesn't exist, or the link is broken."
    icon='<circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /><path d="M9 11h4" />'
>
    <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Return to homepage</a>
    <a href="{{ route('track.lookup') }}" class="inline-flex items-center justify-center gap-2 rounded-control border border-line bg-surface px-4 py-2 font-body text-[14px] font-500 text-ink transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Check your application</a>
</x-errors.page>
