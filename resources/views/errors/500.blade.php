<x-errors.page
    code="500"
    title="Something went wrong"
    message="An unexpected error occurred. It has been logged — try again in a moment."
    icon='<path d="M12 3 2 20h20L12 3Z" /><path d="M12 9v4" /><path d="M12 16h.01" />'
>
    <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-600 px-4 py-2 font-body text-[14px] font-500 text-white transition-colors hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest-600 focus-visible:ring-offset-2">Return to homepage</a>
</x-errors.page>
