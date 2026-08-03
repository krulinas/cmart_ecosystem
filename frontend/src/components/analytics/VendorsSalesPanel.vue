<template>
  <div class="space-y-3" data-testid="vendors-sales-panel">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="min-w-0">
        <AnalyticsDataSourceBadge :sources="sources" filter="csv" />
        <p class="mt-1.5 text-sm text-ink-600">
          Vendor survey insights
          <template v-if="respondentCount != null"> · <strong>n = {{ respondentCount }}</strong></template>
          <template v-if="csvFilename"> · {{ csvFilename }}</template>
        </p>
      </div>

      <div
        v-if="!surveyEmpty"
        class="flex rounded-lg border border-ink-200 bg-white p-0.5 text-xs font-semibold"
        role="group"
        aria-label="Chart metric"
      >
        <button
          type="button"
          class="rounded-md px-2.5 py-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
          :class="metricMode === 'count' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'count'"
        >
          Count
        </button>
        <button
          type="button"
          class="rounded-md px-2.5 py-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
          :class="metricMode === 'percent' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'percent'"
        >
          Percentage
        </button>
      </div>
    </div>

    <p
      v-if="surveyEmpty"
      class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-8 text-center text-sm text-ink-500"
    >
      {{ emptyMessage }}
    </p>

    <template v-else>
      <div class="grid gap-3 lg:grid-cols-2">
        <SurveyDistributionChart
          title="Product categories"
          subtitle="Multi-select · ranked by frequency"
          chart-type="lollipop-h"
          test-id="chart-product-categories"
          :rows="productCategories"
          :order="PRODUCT_CATEGORY_ORDER"
          :denominator="respondentCount"
          :metric-mode="metricMode"
          sort-by-count
          empty-text="No product category selections yet."
        />

        <SurveyDistributionChart
          title="Sales purpose"
          subtitle="Single choice · composition of answered responses"
          chart-type="stacked-h"
          test-id="chart-sales-purpose"
          :rows="salesPurpose"
          :order="SALES_PURPOSE_ORDER"
          :denominator="respondentCount"
          :metric-mode="metricMode"
          :answered-count="salesPurposeAnswered"
          :unanswered-count="salesPurposeUnanswered"
          include-zeros
          empty-text="No sales purpose responses yet."
        />

        <SurveyDistributionChart
          title="Gross sales bands"
          subtitle="Self-reported categorical bands · not exact RM"
          chart-type="column-v"
          test-id="chart-gross-sales"
          :rows="grossSales"
          :order="GROSS_SALES_BAND_ORDER"
          :denominator="respondentCount"
          :metric-mode="metricMode"
          :unanswered-count="grossSalesUnanswered"
          include-zeros
          empty-text="No gross sales band responses yet."
        />

        <SurveyDistributionChart
          title="Used-item sell-through"
          subtitle="Ordered questionnaire bands for used goods"
          chart-type="segmented-h"
          test-id="chart-items-sold"
          :rows="itemsSold"
          :order="ITEMS_SOLD_BAND_ORDER"
          :denominator="respondentCount"
          :metric-mode="metricMode"
          :answered-count="itemsSoldAnswered"
          :unanswered-count="itemsSoldUnanswered"
          include-zeros
          empty-text="No sell-through responses yet."
        />

        <SurveyDistributionChart
          class="lg:col-span-2"
          title="Event information sources"
          subtitle="Multi-select · totals may exceed 100%"
          chart-type="lollipop-h"
          test-id="chart-event-info"
          :rows="eventInfoSources"
          :order="EVENT_INFO_SOURCE_ORDER"
          :denominator="respondentCount"
          :metric-mode="metricMode"
          sort-by-count
          empty-text="No event information source selections yet."
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import AnalyticsDataSourceBadge from './AnalyticsDataSourceBadge.vue';
import SurveyDistributionChart from './SurveyDistributionChart.vue';
import {
  EVENT_INFO_SOURCE_ORDER,
  GROSS_SALES_BAND_ORDER,
  ITEMS_SOLD_BAND_ORDER,
  PRODUCT_CATEGORY_ORDER,
  SALES_PURPOSE_ORDER,
} from '../../utils/surveyChartConfig';

const props = defineProps({
  overview: { type: Object, default: null },
  sources: { type: Array, default: () => [] },
  respondentCount: { type: Number, default: null },
  surveyEmpty: { type: Boolean, default: true },
});

const metricMode = ref('count');

const vendors = computed(() => props.overview?.survey?.sections?.vendors || {});
const economics = computed(() => props.overview?.survey?.sections?.economics || {});
const items = computed(() => props.overview?.survey?.sections?.items || {});
const operations = computed(() => props.overview?.survey?.sections?.operations || {});

const productCategories = computed(() => vendors.value.product_categories || []);
const salesPurpose = computed(() => vendors.value.sales_purpose || []);
const grossSales = computed(() => economics.value.gross_sales_band || []);
const itemsSold = computed(() => items.value.items_sold_band || []);
const eventInfoSources = computed(() => operations.value.event_info_sources || []);

const salesPurposeAnswered = computed(() => {
  if (vendors.value.sales_purpose_answered != null) return Number(vendors.value.sales_purpose_answered);
  return (salesPurpose.value || []).reduce((sum, r) => sum + Number(r.count || 0), 0);
});

const salesPurposeUnanswered = computed(() => {
  if (vendors.value.sales_purpose_unanswered != null) return Number(vendors.value.sales_purpose_unanswered);
  const n = props.respondentCount || 0;
  return Math.max(n - salesPurposeAnswered.value, 0);
});

const grossSalesUnanswered = computed(() => {
  if (economics.value.gross_sales_unanswered != null) return Number(economics.value.gross_sales_unanswered);
  const answered = (grossSales.value || []).reduce((sum, r) => sum + Number(r.count || 0), 0);
  return Math.max((props.respondentCount || 0) - answered, 0);
});

const itemsSoldAnswered = computed(() => {
  if (items.value.items_sold_answered != null) return Number(items.value.items_sold_answered);
  return (itemsSold.value || []).reduce((sum, r) => sum + Number(r.count || 0), 0);
});

const itemsSoldUnanswered = computed(() => {
  if (items.value.items_sold_unanswered != null) return Number(items.value.items_sold_unanswered);
  return Math.max((props.respondentCount || 0) - itemsSoldAnswered.value, 0);
});

const csvFilename = computed(() => {
  const csv = (props.sources || []).find((s) => s.type === 'csv_import' && s.included_in_analytics !== false);
  return csv?.original_filename
    || props.overview?.data_readiness?.latest_import?.original_filename
    || '';
});

const emptyMessage = computed(() =>
  props.overview?.survey?.message || 'No vendor survey imported for this event.',
);
</script>
