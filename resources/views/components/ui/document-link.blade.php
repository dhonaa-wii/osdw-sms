@props(['url', 'name', 'mime' => null, 'title' => null])

<a
    href="{{ $url }}"
    x-data
    @click.prevent="$dispatch('open-document', {{ \Illuminate\Support\Js::from(['url' => $url, 'name' => $name, 'mime' => $mime, 'title' => $title]) }})"
    {{ $attributes }}
>{{ $slot }}</a>
