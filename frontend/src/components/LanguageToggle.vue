<template>
  <div
    class="inline-flex items-center rounded-lg border border-ink-200 bg-white p-0.5 shadow-sm"
    role="group"
    :aria-label="t('common.languageToggleAria')"
    :data-testid="rootTestId"
  >
    <button
      type="button"
      class="min-w-[2.25rem] rounded-md px-2 py-1 text-xs font-bold tracking-wide transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1"
      :class="isMs
        ? 'bg-brand-500 text-white shadow-sm'
        : 'text-ink-500 hover:text-brand-700 hover:bg-brand-50'"
      :aria-pressed="isMs"
      :data-testid="`${rootTestId}-ms`"
      @click="select('ms')"
    >
      {{ t('common.bm') }}
    </button>
    <button
      type="button"
      class="min-w-[2.25rem] rounded-md px-2 py-1 text-xs font-bold tracking-wide transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1"
      :class="isEn
        ? 'bg-brand-500 text-white shadow-sm'
        : 'text-ink-500 hover:text-brand-700 hover:bg-brand-50'"
      :aria-pressed="isEn"
      :data-testid="`${rootTestId}-en`"
      @click="select('en')"
    >
      {{ t('common.en') }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { setAppLocale } from '../i18n';

const props = defineProps({
  /** Visual placement for tests / a11y — does not create a separate locale store. */
  placement: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'desktop', 'mobile'].includes(v),
  },
});

const { t, locale } = useI18n();

const rootTestId = computed(() => {
  if (props.placement === 'desktop') return 'locale-toggle-desktop';
  if (props.placement === 'mobile') return 'locale-toggle-mobile';
  return 'locale-toggle';
});

const isMs = computed(() => locale.value === 'ms');
const isEn = computed(() => locale.value === 'en');

const select = (next) => {
  if (locale.value === next) return;
  setAppLocale(next);
};
</script>
