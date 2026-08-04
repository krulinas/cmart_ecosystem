<template>
  <div class="space-y-6" data-testid="survey-results-panel">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="min-w-0">
        <AnalyticsDataSourceBadge :sources="sources" filter="csv" />
        <p class="mt-1.5 text-sm text-ink-600">
          Quantitative vendor survey results (Q1–Q13)
          <template v-if="respondentCount != null"> · <strong>n = {{ respondentCount }}</strong></template>
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
          class="rounded-md px-2.5 py-1 transition"
          :class="metricMode === 'count' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'count'"
        >
          Count
        </button>
        <button
          type="button"
          class="rounded-md px-2.5 py-1 transition"
          :class="metricMode === 'percent' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'percent'"
        >
          Percentage
        </button>
      </div>
    </div>

    <div
      v-if="surveyEmpty"
      class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-8 text-center text-sm text-ink-500"
    >
      <p>{{ emptyMessage }}</p>
      <button
        v-if="showAddCsvCta"
        type="button"
        class="ml-btn-primary mt-4 text-sm"
        @click="$emit('open-data-sources')"
      >
        Upload Survey CSV
      </button>
    </div>

    <template v-else>
      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">Vendor and selling profile</h3>
          <p class="text-xs text-ink-500">Product categories, sales purpose, and information sources</p>
        </header>
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
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">Sales outcomes</h3>
          <p class="text-xs text-ink-500">Self-reported bands only — not exact RM totals</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
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
        </div>
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">Items and reuse</h3>
          <p class="text-xs text-ink-500">Item conditions, unsold actions, and reuse proxies</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            title="Item conditions"
            subtitle="Multi-select · ranked by frequency"
            chart-type="lollipop-h"
            test-id="chart-item-conditions"
            :rows="itemConditions"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            empty-text="No item condition selections yet."
          />
          <SurveyDistributionChart
            title="Unsold-item actions"
            subtitle="Multi-select · reuse and discard proxies"
            chart-type="lollipop-h"
            test-id="chart-unsold-actions"
            :rows="unsoldActions"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            empty-text="No unsold-item action selections yet."
          />
        </div>
        <div
          v-if="circularity"
          class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
        >
          <p class="font-bold text-ink-900">Reuse / circularity proxies</p>
          <p class="mt-1">
            Positive reuse actions:
            <strong>{{ circularity.positive_action_display }}</strong>
          </p>
          <p>
            Discarded:
            <strong>{{ circularity.discard_action_display }}</strong>
          </p>
          <p class="mt-1 text-xs text-ink-500">{{ circularity.note }}</p>
        </div>
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">Experience and improvements</h3>
          <p class="text-xs text-ink-500">Ratings, difficulties, and improvement priorities</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            title="Experience rating"
            subtitle="Ordered rating responses"
            chart-type="column-v"
            test-id="chart-experience-rating"
            :rows="experienceRating"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            empty-text="No experience rating responses yet."
          />
          <SurveyDistributionChart
            title="Improvement priorities"
            subtitle="Multi-select · ranked by frequency"
            chart-type="lollipop-h"
            test-id="chart-improvements"
            :rows="improvementAreas"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            empty-text="No improvement priority selections yet."
          />
          <SurveyDistributionChart
            title="Supporting activity attracted visitors"
            subtitle="Single choice"
            chart-type="stacked-h"
            test-id="chart-supporting-attracted"
            :rows="supportingAttracted"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            empty-text="No supporting-activity responses yet."
          />
          <SurveyDistributionChart
            title="Supporting activity impacts"
            subtitle="Multi-select · ranked by frequency"
            chart-type="lollipop-h"
            test-id="chart-supporting-impacts"
            :rows="supportingImpacts"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            empty-text="No supporting-activity impact selections yet."
          />
        </div>
        <div
          v-if="hasDifficulty"
          class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
        >
          <p class="font-bold text-ink-900">Vendor difficulties (registration / info)</p>
          <p class="mt-1">
            Yes: <strong>{{ hasDifficulty.yes_display }}</strong>
            · No: <strong>{{ hasDifficulty.no_display }}</strong>
          </p>
        </div>
      </section>
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
  showAddCsvCta: { type: Boolean, default: false },
});

defineEmits(['open-data-sources']);

const metricMode = ref('count');

const vendors = computed(() => props.overview?.survey?.sections?.vendors || {});
const economics = computed(() => props.overview?.survey?.sections?.economics || {});
const items = computed(() => props.overview?.survey?.sections?.items || {});
const experience = computed(() => props.overview?.survey?.sections?.experience || {});
const operations = computed(() => props.overview?.survey?.sections?.operations || {});

const productCategories = computed(() => vendors.value.product_categories || []);
const salesPurpose = computed(() => vendors.value.sales_purpose || []);
const grossSales = computed(() => economics.value.gross_sales_band || []);
const itemsSold = computed(() => items.value.items_sold_band || []);
const eventInfoSources = computed(() => operations.value.event_info_sources || []);
const itemConditions = computed(() => vendors.value.item_conditions || []);
const unsoldActions = computed(() => items.value.unsold_item_actions || []);
const circularity = computed(() => items.value.circularity_proxies || null);
const experienceRating = computed(() => experience.value.experience_rating || []);
const improvementAreas = computed(() => operations.value.improvement_areas || []);
const supportingAttracted = computed(() => experience.value.supporting_activity_attracted_visitors || []);
const supportingImpacts = computed(() => experience.value.supporting_activity_impacts || []);
const hasDifficulty = computed(() => operations.value.has_difficulty || null);

const salesPurposeAnswered = computed(() => {
  if (vendors.value.sales_purpose_answered != null) return Number(vendors.value.sales_purpose_answered);
  return (salesPurpose.value || []).reduce((sum, r) => sum + Number(r.count || 0), 0);
});

const salesPurposeUnanswered = computed(() => {
  if (vendors.value.sales_purpose_unanswered != null) return Number(vendors.value.sales_purpose_unanswered);
  return Math.max((props.respondentCount || 0) - salesPurposeAnswered.value, 0);
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

const emptyMessage = computed(() =>
  props.overview?.survey?.message || 'No CSV data is connected to this event.',
);
</script>
