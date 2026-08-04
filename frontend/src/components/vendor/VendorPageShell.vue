<template>
  <div
    class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/40 to-white"
    :data-testid="testId"
  >
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <div class="max-w-page mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <header
        v-if="title"
        class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3"
      >
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-black text-ink-900 tracking-tight">
            {{ title }}
          </h1>
          <p v-if="subtitle" class="mt-1 text-sm text-ink-500">
            {{ subtitle }}
          </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2 shrink-0">
          <slot name="actions" />
        </div>
      </header>

      <slot />
    </div>
  </div>
</template>

<script setup>
import AppNavbar from '../navigation/AppNavbar.vue';
import { useAuthStore } from '../../stores/auth';

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  testId: { type: String, default: 'vendor-workspace-page' },
});

const auth = useAuthStore();
</script>
