@props(['name', 'label' => null, 'accept' => null, 'hint' => null, 'existingName' => null, 'required' => false, 'preview' => false])

<div
    x-data="{
        name: @js($name),
        fileName: @js($existingName),
        fileSize: null,
        previewUrl: null,
        dragging: false,
        uploading: false,
        progress: 0,
        error: null,
        handleFiles(files) {
            const file = files?.[0];
            if (! file) return;
            this.error = null;
            this.fileName = file.name;
            this.fileSize = file.size;
            if (@js($preview) && file.type.startsWith('image/')) {
                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = URL.createObjectURL(file);
            }
            this.uploading = true;
            this.progress = 0;
            $wire.upload(
                this.name,
                file,
                () => { this.uploading = false; this.progress = 100; },
                () => { this.uploading = false; this.error = 'Upload failed. Try again.'; },
                (event) => { this.progress = event.detail.progress; }
            );
        },
        formatSize(bytes) {
            if (! bytes) return '';
            const kb = bytes / 1024;
            return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB';
        },
    }"
>
    @if ($label)
        <label class="block font-body text-[13px] font-500 tracking-[0.02em] text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-crimson-600">*</span>
            @endif
        </label>
    @endif

    <div
        @click="$refs.input.click()"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)"
        :class="dragging ? 'border-navy-600 bg-navy-50' : 'border-line bg-surface hover:border-navy-600'"
        class="mt-1 flex cursor-pointer items-center gap-3 rounded-control border border-dashed px-4 py-3 transition-colors"
    >
        <input x-ref="input" type="file" @if ($accept) accept="{{ $accept }}" @endif {{ $attributes->merge(['class' => 'sr-only']) }} @change="handleFiles($event.target.files)">

        <img :src="previewUrl" x-show="previewUrl" x-cloak class="size-9 shrink-0 rounded-control object-cover">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700" x-show="! fileName && ! previewUrl">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M12 4 7 9M12 4l5 5" /><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" /></svg>
        </span>
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700" x-show="fileName && ! previewUrl" x-cloak>
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M9 8h6M9 12h6" /></svg>
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate font-body text-[14px] text-ink" x-show="fileName" x-text="fileName" x-cloak></p>
            <p class="font-body text-[14px] text-slate" x-show="! fileName">Drag a file here, or click to browse</p>
            <p class="font-mono text-[11px] text-mist" x-show="fileSize" x-text="formatSize(fileSize)" x-cloak></p>

            <div class="mt-1 h-1 rounded-full bg-paper" x-show="uploading" x-cloak>
                <div class="h-1 rounded-full bg-navy-600 transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <span class="shrink-0 font-body text-[13px] font-500 text-navy-700" x-show="fileName && ! uploading" x-cloak>Change</span>
    </div>

    <template x-if="! error">
        <p class="mt-1 font-mono text-[11px] text-mist">{{ $hint }}</p>
    </template>
    <p class="mt-1 font-body text-[13px] text-crimson-600" x-show="error" x-text="error" x-cloak></p>

    @error($name)
        <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
    @enderror
</div>
