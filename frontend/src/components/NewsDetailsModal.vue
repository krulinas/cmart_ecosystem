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
        v-if="modelValue && post"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc="close"
      >
        <div
          class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]"
          aria-hidden="true"
          @click="close"
        />

        <Transition
          appear
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="modelValue"
            ref="panelRef"
            class="relative z-10 w-full max-w-5xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
            tabindex="-1"
            @click.stop
          >
            <button
              type="button"
              class="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-gray-500 shadow-md ring-1 ring-gray-200 transition hover:bg-gray-50 hover:text-gray-800"
              aria-label="Close news details"
              @click="close"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:gap-6 p-5 sm:p-6 lg:p-8">
              <div class="mb-4 lg:mb-0">
                <MediaImageGallery
                  :images="post.images || []"
                  :alt-text="`${post.title} news banner`"
                  placeholder-text="No news image"
                />
              </div>

              <div class="flex flex-col min-w-0" :class="post.bannerUrl ? '' : 'lg:col-span-2'">
                <p class="text-xs font-bold uppercase tracking-wider text-brand-600 mb-2">News &amp; Updates</p>
                <h2 :id="titleId" class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight pr-10">
                  {{ post.title }}
                </h2>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                  <span class="text-xs font-bold uppercase tracking-wider text-brand-600 bg-brand-50 px-2.5 py-1 rounded-full">
                    {{ post.category }}
                  </span>
                  <span v-if="showStatus && post.statusLabel" :class="['text-xs font-bold px-2.5 py-1 rounded-full', post.statusClass]">
                    {{ post.statusLabel }}
                  </span>
                </div>

                <dl class="mt-5 space-y-3 text-sm text-gray-700">
                  <div v-if="post.publishedDateLabel" class="flex gap-3">
                    <dt class="w-24 shrink-0 font-semibold text-gray-500">Published</dt>
                    <dd>{{ post.publishedDateLabel }}</dd>
                  </div>
                </dl>

                <p v-if="post.excerpt && post.body" class="mt-5 text-sm sm:text-base font-medium text-gray-800 leading-relaxed">
                  {{ post.excerpt }}
                </p>

                <div class="mt-4 text-sm sm:text-base text-gray-600 leading-relaxed whitespace-pre-line">
                  {{ fullContent }}
                </div>

                <div class="mt-8 pt-5 border-t border-gray-100">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-full border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    @click="close"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onUnmounted, nextTick } from 'vue';
import MediaImageGallery from './MediaImageGallery.vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  post: { type: Object, default: null },
  showStatus: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const panelRef = ref(null);

const titleId = computed(() => (props.post?.id ? `news-modal-title-${props.post.id}` : 'news-modal-title'));

const fullContent = computed(() => {
  if (!props.post) return '';
  if (props.post.body?.trim()) return props.post.body;
  return props.post.excerpt || '';
});

const close = () => {
  emit('update:modelValue', false);
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.modelValue) {
    close();
  }
};

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onEscape);
      await nextTick();
      panelRef.value?.focus();
    } else {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onEscape);
    }
  },
);

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onEscape);
});
</script>
