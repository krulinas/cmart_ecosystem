<template>
  <VendorPageShell test-id="vendor-insights-page-root">
    <VendorAnalyticsDashboard
      :analytics="vendorAnalytics"
      :loading="loadingInsights"
      :load-error="insightsError"
      @retry="fetchVendorInsights"
      @edit-profile="router.push('/profile')"
      @manage-reuse="router.push('/vendor/manage/items')"
      @close="router.push('/dashboard')"
    />
  </VendorPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import VendorPageShell from '../../components/vendor/VendorPageShell.vue';
import VendorAnalyticsDashboard from '../../components/VendorAnalyticsDashboard.vue';
import api from '../../services/api';

const router = useRouter();

const DEFAULT_ANALYTICS = {
  summary: {
    total_bookings: 0,
    upcoming_bookings: 0,
    completed_bookings: 0,
    cancelled_bookings: 0,
    rejected_bookings: 0,
    total_receipts: 0,
    total_paid_amount: 0,
    active_reuse_listings: 0,
    inactive_reuse_listings: 0,
    total_reuse_listings: 0,
    profile_completion_percent: 0,
    profile_missing_fields: [],
  },
  booth: {
    items_reused: 0,
    estimated_sales: 0,
    booth_status: 'No Active Booking',
    current_event: null,
    booth_number: null,
  },
  trends: { monthly_bookings: [], monthly_payments: [] },
  distributions: { booking_status: {}, reuse_listing_status: { active: 0, inactive: 0 } },
  recent_activity: [],
  latest: { booking: null, receipt: null, reuse_item: null },
};

const vendorAnalytics = ref(structuredClone(DEFAULT_ANALYTICS));
const loadingInsights = ref(false);
const insightsError = ref(false);

const fetchVendorInsights = async () => {
  loadingInsights.value = true;
  insightsError.value = false;
  try {
    const { data } = await api.get('/vendor/analytics/me');
    vendorAnalytics.value = {
      ...structuredClone(DEFAULT_ANALYTICS),
      ...data,
      summary: { ...DEFAULT_ANALYTICS.summary, ...data.summary },
      booth: { ...DEFAULT_ANALYTICS.booth, ...data.booth },
      trends: { ...DEFAULT_ANALYTICS.trends, ...data.trends },
      distributions: { ...DEFAULT_ANALYTICS.distributions, ...data.distributions },
      latest: { ...DEFAULT_ANALYTICS.latest, ...data.latest },
      recent_activity: Array.isArray(data.recent_activity) ? data.recent_activity : [],
    };
  } catch (e) {
    console.error('Unable to retrieve vendor analytics from the API.', e);
    insightsError.value = true;
    vendorAnalytics.value = structuredClone(DEFAULT_ANALYTICS);
  } finally {
    loadingInsights.value = false;
  }
};

onMounted(fetchVendorInsights);
</script>
