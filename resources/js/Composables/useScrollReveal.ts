import { onMounted, onUnmounted } from 'vue';

export function useScrollReveal(selector = '[data-reveal]') {
    let revert: (() => void) | undefined;

    onMounted(async () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const [{ gsap }, { ScrollTrigger }] = await Promise.all([
            import('gsap'),
            import('gsap/ScrollTrigger'),
        ]);
        gsap.registerPlugin(ScrollTrigger);
        const context = gsap.context(() => {
            gsap.utils.toArray<HTMLElement>(selector).forEach((element, index) => {
                gsap.from(element, {
                    opacity: 0,
                    y: 28,
                    duration: 0.75,
                    delay: Math.min(index % 4, 3) * 0.06,
                    ease: 'power3.out',
                    scrollTrigger: { trigger: element, start: 'top 88%', once: true },
                });
            });

            gsap.utils.toArray<HTMLElement>('[data-parallax]').forEach((element) => {
                gsap.to(element, {
                    yPercent: -4,
                    ease: 'none',
                    scrollTrigger: { trigger: element, start: 'top bottom', end: 'bottom top', scrub: 1.2 },
                });
            });

            gsap.utils.toArray<HTMLElement>('[data-count]').forEach((element) => {
                const target = Number(element.dataset.count || 0);
                const counter = { value: 0 };
                gsap.to(counter, {
                    value: target,
                    duration: 1.4,
                    ease: 'power2.out',
                    scrollTrigger: { trigger: element, start: 'top 90%', once: true },
                    onUpdate: () => { element.textContent = String(Math.round(counter.value)); },
                });
            });
        });
        revert = () => context.revert();
    });

    onUnmounted(() => revert?.());
}
