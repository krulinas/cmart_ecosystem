<template>
  <div
    class="inline-flex items-center rounded-lg border border-ink-200 bg-white/90 p-0.5 shadow-sm"
    role="group"
    :aria-label="t('language.selectorLabel')"
    data-testid="language-toggle"
  >
    <button
      v-for="option in options"
      :key="option.code"
      type="button"
      class="rounded-md px-2.5 py-1.5 text-xs font-bold transition"
      :class="locale === option.code
        ? 'bg-brand-600 text-white shadow-sm'
        : 'text-ink-500 hover:bg-ink-50 hover:text-ink-800'"
      :aria-pressed="locale === option.code"
      :aria-label="t(option.labelKey)"
      :data-testid="`language-${option.code}`"
      @click="setAppLocale(option.code)"
    >
      {{ option.shortLabel }}
    </button>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { setAppLocale } from '../i18n';

const { locale, t } = useI18n({ useScope: 'global' });

const options = [
  { code: 'ms', shortLabel: 'BM', labelKey: 'language.malay' },
  { code: 'en', shortLabel: 'EN', labelKey: 'language.english' },
];
</script>
