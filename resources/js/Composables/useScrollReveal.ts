import { nextTick, onMounted, onUnmounted } from 'vue';

export function useScrollReveal(selector = '[data-reveal]') {
    let observer: IntersectionObserver | undefined;
    const animationFrames = new Set<number>();

    const reveal = (element: HTMLElement) => {
        element.classList.remove('reveal-pending');
        element.classList.add('is-revealed');

        element.querySelectorAll<HTMLElement>('[data-count]').forEach((counter) => {
            const target = Number(counter.dataset.count || 0);
            const started = performance.now();
            const animate = (now: number) => {
                const progress = Math.min((now - started) / 900, 1);
                counter.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
                if (progress < 1) {
                    const frame = requestAnimationFrame(animate);
                    animationFrames.add(frame);
                }
            };
            counter.textContent = '0';
            const frame = requestAnimationFrame(animate);
            animationFrames.add(frame);
        });
    };

    onMounted(async () => {
        await nextTick();
        const elements = Array.from(document.querySelectorAll<HTMLElement>(selector));
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
            elements.forEach(reveal);
            return;
        }

        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const element = entry.target as HTMLElement;
                observer?.unobserve(element);
                reveal(element);
            });
        }, { rootMargin: '0px 0px -12% 0px', threshold: 0.01 });

        elements.forEach((element, index) => {
            if (element.getBoundingClientRect().top < window.innerHeight) {
                reveal(element);
                return;
            }
            element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 60}ms`);
            element.classList.add('reveal-pending');
            observer?.observe(element);
        });
    });

    onUnmounted(() => {
        observer?.disconnect();
        animationFrames.forEach(cancelAnimationFrame);
        animationFrames.clear();
    });
}
