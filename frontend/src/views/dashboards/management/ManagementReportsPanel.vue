<template>
  <section class="rounded-2xl border border-ink-200 bg-white px-5 py-10 text-center text-sm text-ink-600">
    <h2 class="text-base font-bold text-ink-900">Operational overview retired from CMart Reports</h2>
    <p class="mt-2 max-w-xl mx-auto">
      Live Organizer queue counts are no longer presented as Generated Reports.
      Use the CMart Report Centre for requests and published Post-Event Summaries.
    </p>
  </section>
</template>

<script setup>
/** @deprecated Replaced by CMartReportCentrePanel — kept only as a safe unused fallback. */
const load = async () => {};
defineExpose({ load });
</script>
