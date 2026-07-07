<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm">
      <div class="border-b border-ink-100 px-5 py-4 sm:px-6" :class="theme.heroGradient">
        <h2 class="text-lg font-extrabold text-ink-900">System Tools</h2>
        <p class="mt-1 text-sm text-ink-500 max-w-2xl">
          <template v-if="isStaffView">
            Operational shortcuts for Tier 1 staff. Advanced revenue and audit tools are available to managers in the Insights section.
          </template>
          <template v-else>
            Branch management utilities. Open <strong>Revenue</strong> or <strong>Audit Log</strong> from Insights for advanced reporting.
          </template>
        </p>
      </div>
      <div class="p-5 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <div class="text-xs font-bold uppercase tracking-wider text-ink-400">Operations</div>
            <p class="mt-1 text-sm text-ink-600">Manage bookings, events, news, and community feedback from the sidebar.</p>
          </div>
          <div
            v-if="canSeeManagerSections"
            class="rounded-xl border border-cyan-100 bg-cyan-50/40 p-4"
          >
            <div class="text-xs font-bold uppercase tracking-wider text-cyan-600">Insights</div>
            <p class="mt-1 text-sm text-cyan-900/80">Revenue analytics, word clouds, and audit logs are available in your workspace.</p>
          </div>
          <div
            v-else
            class="rounded-xl border border-cyan-100 bg-cyan-50/40 p-4"
          >
            <div class="text-xs font-bold uppercase tracking-wider text-cyan-600">Tier 1 access</div>
            <p class="mt-1 text-sm text-cyan-900/80">Revenue and audit tools require manager-level access.</p>
          </div>
        </div>
      </div>
    </section>

    <StaffOperationalSnapshot
      v-if="isStaffView"
      ref="snapshotRef"
      class="rounded-2xl border border-ink-100 overflow-hidden shadow-sm"
    />

    <section
      v-else
      class="rounded-2xl border border-ink-100 bg-gray-50 px-6 py-12 text-center shadow-sm"
      data-testid="manager-tools-analytics-placeholder"
    >
      <div class="mx-auto max-w-lg">
        <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-2 block">Management Tools</span>
        <h3 class="text-xl font-extrabold text-gray-900">Analytics modules</h3>
        <p class="mt-3 text-sm text-gray-600 leading-relaxed">
          Management tools and reports will appear here as analytics modules are connected to live data.
          Use <strong>Insights → Revenue</strong> in the sidebar for current branch revenue and payment breakdowns.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import StaffOperationalSnapshot from '../../../components/management/StaffOperationalSnapshot.vue';
import { useManagementAccess } from '../../../composables/useManagementAccess';

const { isStaffView, canSeeManagerSections, workspaceTheme } = useManagementAccess();
const theme = workspaceTheme;

const snapshotRef = ref(null);

const refresh = async () => {
  if (isStaffView.value) {
    await snapshotRef.value?.refresh?.();
  }
};

defineExpose({
  refresh,
});
</script>
