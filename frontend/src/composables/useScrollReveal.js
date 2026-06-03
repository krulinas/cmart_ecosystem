import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Lightweight scroll-reveal using Intersection Observer (no external animation libs).
 */
export function useScrollReveal(options = {}) {
  const targetRef = ref(null);
  const isVisible = ref(false);

  const threshold = options.threshold ?? 0.12;
  const rootMargin = options.rootMargin ?? '0px 0px -6% 0px';
  const once = options.once !== false;

  let observer = null;

  onMounted(() => {
    const element = targetRef.value;
    if (!element) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      isVisible.value = true;
      return;
    }

    observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          isVisible.value = true;
          if (once && observer) {
            observer.disconnect();
            observer = null;
          }
        } else if (!once) {
          isVisible.value = false;
        }
      },
      { threshold, rootMargin },
    );

    observer.observe(element);
  });

  onUnmounted(() => {
    observer?.disconnect();
  });

  const revealClass = (variant = 'fade') => {
    const base = 'transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:opacity-100 motion-reduce:translate-y-0';
    if (isVisible.value) {
      return `${base} opacity-100 translate-y-0`;
    }
    if (variant === 'slide') {
      return `${base} opacity-0 translate-y-10`;
    }
    return `${base} opacity-0 translate-y-4`;
  };

  return { targetRef, isVisible, revealClass };
}
