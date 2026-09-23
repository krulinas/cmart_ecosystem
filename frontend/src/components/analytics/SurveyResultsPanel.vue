<template>
  <div class="space-y-6" data-testid="survey-results-panel">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="min-w-0">
        <AnalyticsDataSourceBadge :sources="sources" filter="csv" />
        <p class="mt-1.5 text-sm text-ink-600">
          {{ t('organizer.analytics.survey.quantitativeLead') }}
          <template v-if="respondentCount != null"> · <strong>n = {{ respondentCount }}</strong></template>
        </p>
      </div>

      <div
        v-if="!surveyEmpty"
        class="flex rounded-lg border border-ink-200 bg-white p-0.5 text-xs font-semibold"
        role="group"
        :aria-label="t('organizer.analytics.survey.chartMetricAria')"
      >
        <button
          type="button"
          class="rounded-md px-2.5 py-1 transition"
          :class="metricMode === 'count' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'count'"
        >
          {{ t('organizer.analytics.survey.count') }}
        </button>
        <button
          type="button"
          class="rounded-md px-2.5 py-1 transition"
          :class="metricMode === 'percent' ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50'"
          @click="metricMode = 'percent'"
        >
          {{ t('organizer.analytics.survey.percentage') }}
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
        {{ t('organizer.analytics.survey.uploadSurveyCsv') }}
      </button>
    </div>

    <template v-else>
      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.survey.sectionVendorProfile') }}</h3>
          <p class="text-xs text-ink-500">{{ t('organizer.analytics.survey.sectionVendorProfileHint') }}</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.productCategories')"
            :subtitle="t('organizer.analytics.survey.productCategoriesSub')"
            chart-type="lollipop-h"
            test-id="chart-product-categories"
            :rows="productCategories"
            :order="PRODUCT_CATEGORY_ORDER"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.productCategoriesEmpty')"
          />
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.salesPurpose')"
            :subtitle="t('organizer.analytics.survey.salesPurposeSub')"
            chart-type="stacked-h"
            test-id="chart-sales-purpose"
            :rows="salesPurpose"
            :order="SALES_PURPOSE_ORDER"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            :answered-count="salesPurposeAnswered"
            :unanswered-count="salesPurposeUnanswered"
            include-zeros
            :empty-text="t('organizer.analytics.survey.salesPurposeEmpty')"
          />
          <SurveyDistributionChart
            class="lg:col-span-2"
            :title="t('organizer.analytics.survey.eventInfoSources')"
            :subtitle="t('organizer.analytics.survey.eventInfoSourcesSub')"
            chart-type="lollipop-h"
            test-id="chart-event-info"
            :rows="eventInfoSources"
            :order="EVENT_INFO_SOURCE_ORDER"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.eventInfoSourcesEmpty')"
          />
        </div>
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.survey.sectionSalesOutcomes') }}</h3>
          <p class="text-xs text-ink-500">{{ t('organizer.analytics.survey.sectionSalesOutcomesHint') }}</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.grossSalesBands')"
            :subtitle="t('organizer.analytics.survey.grossSalesBandsSub')"
            chart-type="column-v"
            test-id="chart-gross-sales"
            :rows="grossSales"
            :order="GROSS_SALES_BAND_ORDER"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            :unanswered-count="grossSalesUnanswered"
            include-zeros
            :empty-text="t('organizer.analytics.survey.grossSalesBandsEmpty')"
          />
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.usedItemSellThrough')"
            :subtitle="t('organizer.analytics.survey.usedItemSellThroughSub')"
            chart-type="column-v"
            test-id="chart-items-sold"
            :rows="itemsSold"
            :order="ITEMS_SOLD_BAND_ORDER"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            :answered-count="itemsSoldAnswered"
            :unanswered-count="itemsSoldUnanswered"
            include-zeros
            :empty-text="t('organizer.analytics.survey.usedItemSellThroughEmpty')"
          />
        </div>
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.survey.sectionItemsReuse') }}</h3>
          <p class="text-xs text-ink-500">{{ t('organizer.analytics.survey.sectionItemsReuseHint') }}</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.itemConditions')"
            :subtitle="t('organizer.analytics.survey.itemConditionsSub')"
            chart-type="lollipop-h"
            test-id="chart-item-conditions"
            :rows="itemConditions"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.itemConditionsEmpty')"
          />
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.unsoldActions')"
            :subtitle="t('organizer.analytics.survey.unsoldActionsSub')"
            chart-type="lollipop-h"
            test-id="chart-unsold-actions"
            :rows="unsoldActions"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.unsoldActionsEmpty')"
          />
        </div>
        <div
          v-if="circularity"
          class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
        >
          <p class="font-bold text-ink-900">{{ t('organizer.analytics.survey.circularityProxies') }}</p>
          <p class="mt-1">
            {{ t('organizer.analytics.survey.positiveReuseActions') }}
            <strong>{{ circularity.positive_action_display }}</strong>
          </p>
          <p>
            {{ t('organizer.analytics.survey.discarded') }}
            <strong>{{ circularity.discard_action_display }}</strong>
          </p>
          <p class="mt-1 text-xs text-ink-500">{{ circularity.note }}</p>
        </div>
      </section>

      <section class="space-y-3">
        <header>
          <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.survey.sectionExperience') }}</h3>
          <p class="text-xs text-ink-500">{{ t('organizer.analytics.survey.sectionExperienceHint') }}</p>
        </header>
        <div class="grid gap-3 lg:grid-cols-2">
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.experienceRating')"
            :subtitle="t('organizer.analytics.survey.experienceRatingSourceSub')"
            chart-type="column-v"
            test-id="chart-experience-rating"
            :rows="experienceRating"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            :empty-text="t('organizer.analytics.survey.experienceRatingEmpty')"
          />
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.improvementPriorities')"
            :subtitle="t('organizer.analytics.survey.improvementPrioritiesSub')"
            chart-type="lollipop-h"
            test-id="chart-improvements"
            :rows="improvementAreas"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.improvementPrioritiesEmpty')"
          />
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.supportingAttracted')"
            :subtitle="t('organizer.analytics.survey.supportingAttractedSub')"
            chart-type="stacked-h"
            test-id="chart-supporting-attracted"
            :rows="supportingAttracted"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            :empty-text="t('organizer.analytics.survey.supportingAttractedEmpty')"
          />
          <AnalyticsDoughnutChart
            v-if="difficultyRows.length"
            :title="t('organizer.analytics.survey.vendorDifficulties')"
            :subtitle="t('organizer.analytics.survey.yesNo', { yes: hasDifficulty?.yes_display || '—', no: hasDifficulty?.no_display || '—' })"
            :rows="difficultyRows"
            :colors="[palette.warning, palette.positive]"
            test-id="chart-has-difficulty-doughnut"
          />
          <div
            v-else-if="hasDifficulty"
            class="rounded-xl border border-sky-100 bg-white p-3 text-sm text-ink-700"
          >
            <p class="font-bold text-ink-900">{{ t('organizer.analytics.survey.vendorDifficulties') }}</p>
            <p class="mt-1">
              {{ t('organizer.analytics.survey.yesNo', { yes: hasDifficulty.yes_display, no: hasDifficulty.no_display }) }}
            </p>
          </div>
          <SurveyDistributionChart
            :title="t('organizer.analytics.survey.supportingImpacts')"
            :subtitle="t('organizer.analytics.survey.supportingImpactsSub')"
            chart-type="lollipop-h"
            test-id="chart-supporting-impacts"
            :rows="supportingImpacts"
            :denominator="respondentCount"
            :metric-mode="metricMode"
            sort-by-count
            :empty-text="t('organizer.analytics.survey.supportingImpactsEmpty')"
          />
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalyticsDataSourceBadge from './AnalyticsDataSourceBadge.vue';
import AnalyticsDoughnutChart from './AnalyticsDoughnutChart.vue';
import SurveyDistributionChart from './SurveyDistributionChart.vue';
import { ANALYTICS_PALETTE } from '../../utils/analyticsChartPalette';
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

const { t } = useI18n();
const metricMode = ref('count');
const palette = ANALYTICS_PALETTE;

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

const difficultyRows = computed(() => {
  const block = hasDifficulty.value;
  if (!block) return [];
  const yes = block.yes ?? block.yes_count;
  const no = block.no ?? block.no_count;
  const rows = [];
  if (yes != null && yes !== '') {
    rows.push({ key: 'yes', label: 'Yes', count: Number(yes) });
  }
  if (no != null && no !== '') {
    rows.push({ key: 'no', label: 'No', count: Number(no) });
  }
  return rows.filter((r) => !Number.isNaN(r.count));
});

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
  props.overview?.survey?.message || t('organizer.analytics.survey.noCsvConnected'),
);
</script>
