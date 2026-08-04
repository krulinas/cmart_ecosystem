<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="visible && event"
        class="pointer-events-none fixed z-[90] w-72 max-w-[calc(100vw-1.5rem)]"
        :style="positionStyle"
        role="tooltip"
        :aria-hidden="!visible"
      >
        <div class="overflow-hidden rounded-xl border border-sky-100 bg-white shadow-xl ring-1 ring-black/5">
          <div v-if="event.posterUrl" class="h-28 w-full bg-sky-50">
            <img
              :src="event.posterUrl"
              :alt="`${event.title} poster preview`"
              class="h-full w-full object-cover"
            />
          </div>
          <div v-else class="flex h-20 items-center justify-center bg-gradient-to-br from-sky-50 to-white">
            <svg class="h-8 w-8 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>

          <div class="space-y-2 p-3">
            <div class="flex items-start justify-between gap-2">
              <h4 class="text-sm font-bold leading-snug text-gray-900 line-clamp-2">{{ event.title }}</h4>
              <span
                v-if="event.status"
                :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide', event.statusClass]"
              >
                {{ event.status }}
              </span>
            </div>

            <p v-if="event.dateLabel" class="text-xs text-gray-600">{{ event.dateLabel }}</p>
            <p v-if="event.dateNumeric" class="text-[11px] text-gray-500">{{ event.dateNumeric }}</p>
            <p v-if="event.time" class="text-xs font-semibold text-brand-700">{{ event.time }}</p>
            <p v-if="descriptionSnippet" class="text-xs leading-relaxed text-gray-500 line-clamp-2">
              {{ descriptionSnippet }}
            </p>
            <p class="text-[10px] font-medium uppercase tracking-wide text-sky-500">Click to view details</p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  visible: { type: Boolean, default: false },
  event: { type: Object, default: null },
  anchorRect: { type: Object, default: null },
});

const descriptionSnippet = computed(() => {
  const text = props.event?.description;
  if (!text) return '';
  return text;
});

const positionStyle = computed(() => {
  const rect = props.anchorRect;
  if (!rect) {
    return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' };
  }

  const cardWidth = 288;
  const cardHeight = 260;
  const gap = 8;
  const viewportW = window.innerWidth;
  const viewportH = window.innerHeight;

  let left = rect.left + rect.width / 2 - cardWidth / 2;
  let top = rect.bottom + gap;

  if (left + cardWidth > viewportW - 12) left = viewportW - cardWidth - 12;
  if (left < 12) left = 12;

  if (top + cardHeight > viewportH - 12) {
    top = rect.top - cardHeight - gap;
  }
  if (top < 12) top = 12;

  return { top: `${top}px`, left: `${left}px` };
});
</script>
