<template>
  <div class="min-h-screen bg-gradient-to-br from-brand-50 via-white to-cyan-50/40 flex items-center justify-center px-4 py-12">
    <div class="w-full" :class="wide ? 'max-w-lg' : 'max-w-md'">
      <router-link
        v-if="showBackLink"
        :to="backTo"
        class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6 transition-colors"
      >
        <span class="mr-1" aria-hidden="true">←</span>
        {{ backLabel }}
      </router-link>

      <div class="ml-card shadow-lg shadow-brand-500/5 border border-white/80">
        <div class="text-center mb-6">
          <span
            class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 text-white font-extrabold text-xl shadow-md shadow-brand-500/30"
            aria-hidden="true"
          >
            C
          </span>
          <h1 class="mt-4 text-2xl font-extrabold text-ink-900 tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="mt-2 text-sm text-ink-500 leading-relaxed max-w-sm mx-auto">{{ subtitle }}</p>
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-6 pt-2 border-t border-ink-100">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  backTo: { type: String, default: '/' },
  backLabel: { type: String, default: 'Back to public portal' },
  showBackLink: { type: Boolean, default: true },
  wide: { type: Boolean, default: false },
});
</script>
