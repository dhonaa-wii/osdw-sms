<?php

namespace App\Services;

use App\Enums\BrandingImageKey;
use App\Models\BrandingImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Turns the Help Center page into a downloadable PDF.
 *
 * The page is rendered as usual, made self-contained (built CSS inlined,
 * the logo embedded, scripts dropped), and printed by headless Chrome with
 * the page's own print styles: cover, contents, a new A4 page per chapter.
 * The file is kept until the guide's HTML changes, so only the first
 * download after an edit waits for Chrome.
 */
class HelpCenterPdfService
{
    private const DIRECTORY = 'help-center';

    /** Absolute path of an up-to-date PDF of the guide. */
    public function path(): string
    {
        $html = $this->html();
        $disk = Storage::disk('local');
        $file = self::DIRECTORY.'/'.md5($this->withoutVolatileParts($html)).'.pdf';

        if (! $disk->exists($file)) {
            $disk->deleteDirectory(self::DIRECTORY);
            $disk->makeDirectory(self::DIRECTORY);
            $this->print($html, $disk->path($file));
        }

        return $disk->path($file);
    }

    private function html(): string
    {
        $page = view('layouts.help', [
            'title' => 'OSDW Help Center',
            'slot' => new HtmlString(Livewire::mount('public::help')),
        ])->render();

        // Scripts aren't needed on paper, and the Vite tags would point Chrome at the web server.
        $page = preg_replace('#<script\b[^>]*>.*?</script>#si', '', $page);
        $page = preg_replace('#<link\b[^>]*(?:/build/|@vite|:5173)[^>]*>#i', '', $page);
        $page = str_replace('</head>', '<style>'.Vite::content('resources/css/app.css').'</style></head>', $page);

        $logoUrl = BrandingImage::url(BrandingImageKey::Logo);
        if ($logoUrl) {
            $logo = BrandingImage::findOrFail(BrandingImageKey::Logo->value);
            $page = str_replace(e($logoUrl), 'data:'.$logo->mime_type.';base64,'.base64_encode($logo->data), $page);
        }

        return $page;
    }

    /** Livewire's per-render ids and checksums change every time; they don't change the PDF. */
    private function withoutVolatileParts(string $html): string
    {
        return preg_replace('#\swire:(?:snapshot|id|effects)=(["\']).*?\1#s', '', $html);
    }

    private function print(string $html, string $output): void
    {
        $workDirectory = storage_path('framework/cache/help-center-pdf');
        File::ensureDirectoryExists($workDirectory);
        $source = $workDirectory.'/help.html';
        File::put($source, $html);

        $process = new Process([
            $this->chrome(),
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--no-first-run',
            '--user-data-dir='.$workDirectory.'/profile',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=15000',
            '--print-to-pdf='.$output,
            'file:///'.ltrim(str_replace('\\', '/', $source), '/'),
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! is_file($output) || filesize($output) === 0) {
            throw new RuntimeException('Chrome could not print the Help Center: '.trim($process->getErrorOutput()));
        }
    }

    private function chrome(): string
    {
        $candidates = array_filter([
            config('services.chrome.path'),
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('No Chrome or Edge found. Set CHROME_PATH in .env.');
    }
}
