import { watch } from 'vue';
import { useRoute } from 'vue-router';

const DEFAULT_ROBOTS = 'index,follow';

export function useRouteMeta() {
  const route = useRoute();

  const applyMeta = (to) => {
    const robots = to.meta?.robots || DEFAULT_ROBOTS;
    let robotsTag = document.querySelector('meta[name="robots"]');

    if (!robotsTag) {
      robotsTag = document.createElement('meta');
      robotsTag.setAttribute('name', 'robots');
      document.head.appendChild(robotsTag);
    }

    robotsTag.setAttribute('content', robots);
  };

  watch(
    () => route.meta,
    () => applyMeta(route),
    { immediate: true, deep: true },
  );
}
