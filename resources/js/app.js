import './bootstrap';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function animateCountUp(el) {
    const target = Number(el.dataset.countTo);
    if (!Number.isFinite(target)) return;

    if (prefersReducedMotion) {
        el.textContent = target;
        return;
    }

    const duration = 900;
    const start = performance.now();

    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(eased * target);
        if (progress < 1) requestAnimationFrame(tick);
    }

    requestAnimationFrame(tick);
}

// Runs on the first load and again after every wire:navigate page swap.
function initReveal() {
    const revealTargets = [...document.querySelectorAll('[data-reveal], [data-reveal-group]')]
        .filter((el) => !el.dataset.revealBound);
    if (!revealTargets.length) return;
    revealTargets.forEach((el) => { el.dataset.revealBound = '1'; });

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        revealTargets.forEach((el) => el.classList.add('is-visible'));
        document.querySelectorAll('[data-count-to]').forEach(animateCountUp);
        return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            entry.target.querySelectorAll('[data-count-to]').forEach(animateCountUp);
            if (entry.target.hasAttribute('data-count-to')) animateCountUp(entry.target);

            obs.unobserve(entry.target);
        });
    }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

    revealTargets.forEach((el) => observer.observe(el));
}

document.addEventListener('DOMContentLoaded', initReveal);
document.addEventListener('livewire:navigated', initReveal);

// Inline document previews on the staff review pages. pdf.js draws each page
// onto a canvas, so PDFs read the same on phones (whose browsers can't show a
// PDF inside a page) as on desktops. Loaded on first use to keep app.js small.
let pdfjs = null;

async function loadPdfjs() {
    if (!pdfjs) {
        const [lib, worker] = await Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?worker'),
        ]);
        // Bundled by Vite as a .js worker, so any web server serves it as JavaScript.
        lib.GlobalWorkerOptions.workerPort = new worker.default();
        pdfjs = lib;
    }

    return pdfjs;
}

window.renderPdfPreview = async (container, url) => {
    const lib = await loadPdfjs();
    const pdf = await lib.getDocument({ url, withCredentials: true }).promise;
    const width = Math.max(container.clientWidth, 280);
    const ratio = Math.min(window.devicePixelRatio || 1, 2);

    container.replaceChildren();

    for (let number = 1; number <= pdf.numPages; number++) {
        const page = await pdf.getPage(number);
        const viewport = page.getViewport({ scale: (width / page.getViewport({ scale: 1 }).width) * ratio });
        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.className = 'block h-auto w-full rounded-control border border-line bg-white shadow-sm shadow-ink/5';
        canvas.setAttribute('aria-label', `Page ${number} of ${pdf.numPages}`);

        if (pdf.numPages > 1) {
            const label = document.createElement('p');
            label.className = 'mb-1 font-mono text-[11px] text-mist';
            label.textContent = `Page ${number} of ${pdf.numPages}`;
            container.append(label);
        }

        container.append(canvas);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    }

    return pdf.numPages;
};
