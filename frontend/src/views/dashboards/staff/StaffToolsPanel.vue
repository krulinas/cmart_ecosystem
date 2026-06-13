<template>
  <div class="space-y-6">
    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900">Workspace Tools</h2>
      <p class="text-sm text-ink-500 mt-2 max-w-xl">
        Revenue analytics, profitability calculations, and advanced reporting are available to CMart Admin (Boss) only.
        Use the <strong>Revenue</strong> section in the sidebar if you have Boss access.
      </p>
      <p class="text-xs text-ink-400 mt-4">
        Staff can manage bookings, feedback, events, and news from the navigation menu.
      </p>
    </section>

    <ImpactDashboard
      :reused-items="impactMetrics.reusedItems"
      :economic-value-rm="impactMetrics.economicValueRm"
      :active-vendors="impactMetrics.activeVendors"
      :active-students="impactMetrics.activeStudents"
      class="rounded-2xl border border-ink-100 overflow-hidden"
    />
  </div>
</template>

<script setup>
import ImpactDashboard from '../../../components/ImpactDashboard.vue';

const impactMetrics = {
  reusedItems: 12450,
  economicValueRm: 892000,
  activeVendors: 156,
  activeStudents: 89,
};

defineExpose({
  refresh: async () => {},
});
</script>
