<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open && imageUrl"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="ariaLabel"
      >
        <div
          class="absolute inset-0 bg-black/80 backdrop-blur-sm"
          aria-hidden="true"
          @click="close"
        />

        <div class="relative z-10 flex max-h-[90vh] max-w-[90vw] flex-col items-center justify-center">
          <button
            type="button"
            class="absolute -top-12 right-0 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-700 shadow-lg ring-1 ring-white/20 transition hover:bg-white"
            aria-label="Close image preview"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <img
            :src="imageUrl"
            :alt="altText"
            class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
            @click.stop
          />
          <p v-if="caption" class="mt-3 text-center text-sm text-white/90">{{ caption }}</p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch, onUnmounted, computed } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  imageUrl: { type: String, default: null },
  altText: { type: String, default: 'Image preview' },
  caption: { type: String, default: '' },
});

const emit = defineEmits(['update:open', 'close']);

const ariaLabel = computed(() => `Full size ${props.altText}`);

const close = () => {
  emit('update:open', false);
  emit('close');
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.open) {
    event.stopPropagation();
    close();
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onEscape, true);
    } else {
      document.removeEventListener('keydown', onEscape, true);
    }
  },
);

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape, true);
});
</script>
