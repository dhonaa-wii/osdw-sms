<x-errors.page
    code="419"
    title="Your session expired"
    message="Your form session timed out for security. Go back and submit it again."
    icon='<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" />'
>
    <a href="javascript:history.back()" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Go back</a>
    <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-control border border-line bg-surface px-4 py-2 font-body text-[14px] font-500 text-ink transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Return to homepage</a>
</x-errors.page>
