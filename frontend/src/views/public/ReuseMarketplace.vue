<template>
  <div class="min-h-screen bg-gray-50" data-testid="marketplace-preview-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <header class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 pt-16 pb-20 px-6 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-10 pointer-events-none"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"
      ></div>
      <div class="max-w-7xl mx-auto relative z-10 text-center text-white">
        <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-3">Carboot@CMart</p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Carboot Preview</h1>
        <p class="text-lg text-brand-100 max-w-3xl mx-auto leading-relaxed">
          Plan your visit to the CMart Carboot. Browse events on the community portal and explore
          pre-loved finds in person at vendor booths on event day.
        </p>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 -mt-8 space-y-6">
      <section
        class="rounded-2xl border border-amber-400 bg-[#FFFBEB] px-4 py-3.5 sm:px-5 sm:py-4 shadow-sm"
        role="note"
        aria-label="Carboot preview policy"
        data-testid="marketplace-preview-notice"
      >
        <div class="flex gap-3 sm:items-start">
          <div class="shrink-0 flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full bg-[#FDE68A] text-[#B45309]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-extrabold text-[#78350F]">Visit in person on event day</p>
            <p class="text-sm text-[#92400E]/95 leading-relaxed">
              Purchases are made at vendor booths during the carboot. There is no online checkout,
              delivery, or postage for reuse items.
            </p>
          </div>
        </div>
      </section>

      <section
        class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 sm:p-12 text-center"
        data-testid="marketplace-preview-policy"
      >
        <div class="mx-auto max-w-2xl space-y-4">
          <h2 class="text-2xl font-extrabold text-gray-900">Specific item previews are not publicly listed yet</h2>
          <p class="text-sm text-gray-600 leading-relaxed">
            To avoid misleading visitors, item availability is confirmed on event day at vendor booths.
            Vendors may prepare listings privately, but public previews are not shown before attendance is confirmed.
          </p>
          <p class="text-sm text-gray-600 leading-relaxed">
            Please visit the CMart Carboot to explore available items in person.
          </p>
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <h2 class="text-lg font-extrabold text-gray-900">Plan your carboot visit</h2>
        <p class="mt-2 text-sm text-gray-600 max-w-2xl">
          Check upcoming event dates, news, and community updates before you head to CMart.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <router-link to="/calendar" class="ml-btn-primary text-sm no-underline">
            View events calendar
          </router-link>
          <router-link to="/community" class="ml-btn-ghost text-sm no-underline">
            Community portal
          </router-link>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
</script>
