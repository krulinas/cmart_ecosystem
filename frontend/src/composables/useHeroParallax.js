import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Subtle parallax offset for hero foreground vs background on scroll.
 */
export function useHeroParallax() {
  const parallaxOffset = ref(0);
  let ticking = false;

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const scrollY = window.scrollY;
      parallaxOffset.value = Math.min(scrollY * 0.35, 120);
      ticking = false;
    });
  };

  onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  });

  onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
  });

  const contentStyle = () => ({
    transform: `translate3d(0, ${parallaxOffset.value * 0.15}px, 0)`,
  });

  const videoStyle = () => ({
    transform: `translate3d(0, ${parallaxOffset.value * 0.45}px, 0) scale(1.05)`,
  });

  return { parallaxOffset, contentStyle, videoStyle };
}
