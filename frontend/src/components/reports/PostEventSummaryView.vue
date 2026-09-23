<template>
  <article class="pes-report" data-testid="post-event-summary-view">
    <!-- 1. Cover -->
    <header class="pes-cover">
      <div class="pes-cover__brand">
        <img
          src="/images/branding/uum-logo.png"
          alt="UUM"
          class="pes-cover__logo"
          @error="uumLogoFailed = true"
          v-show="!uumLogoFailed"
        />
        <img
          src="/images/branding/cmart-logo.png"
          alt="CMart"
          class="pes-cover__logo"
          @error="onCmartLogoError"
          v-show="!cmartLogoFailed"
        />
        <img
          v-show="cmartLogoFailed && !legacyLogoFailed"
          src="/cmart_logo.png"
          alt="CMart"
          class="pes-cover__logo"
          @error="legacyLogoFailed = true"
        />
        <span v-if="uumLogoFailed && cmartLogoFailed && legacyLogoFailed" class="pes-cover__logo-fallback">CMart</span>
      </div>
      <p class="pes-eyebrow">{{ t('reports.summary.eyebrow') }}</p>
      <h1 class="pes-cover__title">{{ eventTitle }}</h1>
      <p class="pes-cover__date">{{ dateRange }}</p>
      <dl class="pes-cover__meta">
        <div><dt>{{ t('reports.summary.venue') }}</dt><dd>{{ venue }}</dd></div>
        <div><dt>{{ t('reports.summary.preparedBy') }}</dt><dd>{{ t('reports.summary.preparedByValue') }}</dd></div>
        <div><dt>{{ t('reports.summary.preparedFor') }}</dt><dd>{{ t('reports.summary.preparedForValue') }}</dd></div>
        <div><dt>{{ t('reports.summary.reportVersion') }}</dt><dd>{{ t('reports.summary.versionN', { n: report.version }) }}</dd></div>
        <div v-if="publishedDisplay"><dt>{{ t('reports.summary.publicationDate') }}</dt><dd>{{ publishedDisplay }}</dd></div>
      </dl>
      <span v-if="coverStatusLabel" class="pes-badge" :class="coverStatusKey === 'provisional' ? 'pes-badge--amber' : 'pes-badge--green'">
        {{ coverStatusLabel }}
      </span>
    </header>

    <!-- 2. Executive Summary -->
    <section class="pes-section">
      <h2>{{ t('reports.summary.sectionExecutive') }}</h2>
      <p v-if="coverStatusKey === 'provisional'" class="pes-warn">
        {{ t('reports.summary.provisionalWarn') }}
      </p>
      <div class="pes-kpi-grid">
        <div v-for="kpi in executiveKpis" :key="kpi.label" class="pes-kpi">
          <div class="pes-kpi__label">{{ kpi.label }}</div>
          <div class="pes-kpi__value">{{ kpi.value }}</div>
        </div>
      </div>
      <p class="pes-summary">{{ executiveNarrative }}</p>
    </section>

    <section class="pes-section" data-testid="programme-introduction">
      <h2>{{ t('reports.summary.sectionIntroduction') }}</h2>
      <div v-if="programmeIntroduction" class="pes-narrative">{{ programmeIntroduction }}</div>
      <p v-else class="pes-muted">{{ t('reports.summary.notProvided') }}</p>
    </section>

    <section class="pes-section" data-testid="programme-details">
      <h2>{{ t('reports.summary.sectionProgrammeDetails') }}</h2>
      <dl class="pes-kv">
        <div v-for="(value, key) in programmeDetailRows" :key="key">
          <dt>{{ value.label }}</dt>
          <dd>{{ value.text }}</dd>
        </div>
      </dl>
    </section>

    <section class="pes-section" data-testid="programme-objectives">
      <h2>{{ t('reports.summary.sectionObjectives') }}</h2>
      <p v-if="objectivesNotApplicable" class="pes-muted">{{ t('reports.summary.objectivesNotApplicable') }}</p>
      <ol v-else-if="programmeObjectives.length">
        <li v-for="(objective, idx) in programmeObjectives" :key="idx">{{ objective }}</li>
      </ol>
      <p v-else class="pes-muted">{{ t('reports.summary.objectivesMissing') }}</p>
    </section>

    <!-- Analytics-aligned visuals from frozen snapshot -->
    <section v-if="hasVisualCharts" class="pes-section" data-testid="report-analytics-visuals">
      <h2>{{ t('reports.summary.sectionAnalyticsVisuals') }}</h2>
      <p class="pes-note">
        {{ t('reports.summary.analyticsVisualsHint') }}
      </p>
      <p class="pes-note" data-testid="benchmark-omitted-note">
        {{ visualSpec.performanceAcrossEventsNote }}
      </p>
      <div class="pes-visual-grid">
        <AnalyticsDoughnutChart
          v-if="visualChart('site_utilisation')"
          :title="visualChart('site_utilisation').title"
          :subtitle="visualChart('site_utilisation').subtitle || ''"
          :rows="visualChart('site_utilisation').rows"
          :center-value="visualChart('site_utilisation').centerValue"
          :center-label="visualChart('site_utilisation').centerLabel || ''"
          :colors="visualChart('site_utilisation').rows.map((r) => r.color)"
          empty-text="—"
          test-id="report-chart-site-utilisation"
        />
        <AnalyticsDoughnutChart
          v-if="visualChart('booking_status')"
          :title="visualChart('booking_status').title"
          :subtitle="visualChart('booking_status').subtitle || ''"
          :rows="visualChart('booking_status').rows"
          :colors="visualChart('booking_status').rows.map((r) => r.color)"
          empty-text="—"
          test-id="report-chart-booking-status"
        />
        <AnalyticsStackedBarChart
          v-if="visualChart('revenue_collection')"
          :title="visualChart('revenue_collection').title"
          :subtitle="visualChart('revenue_collection').subtitle || ''"
          :segments="visualChart('revenue_collection').rows"
          :colors="visualChart('revenue_collection').rows.map((r) => r.color)"
          value-prefix="RM "
          empty-text="—"
          test-id="report-chart-revenue"
        />
        <AnalyticsDoughnutChart
          v-if="categoryChart?.type === 'doughnut'"
          :title="categoryChart.title"
          :subtitle="categoryChart.subtitle || ''"
          :rows="categoryChart.rows"
          :colors="categoryChart.rows.map((r) => r.color)"
          empty-text="—"
          test-id="report-chart-categories-doughnut"
        />
        <AnalyticsRankedBarChart
          v-else-if="categoryChart?.type === 'bar'"
          :title="categoryChart.title"
          :subtitle="categoryChart.subtitle || ''"
          :rows="categoryChart.rows"
          empty-text="—"
          test-id="report-chart-categories-bars"
        />
        <div
          v-else-if="categoryChart?.type === 'compact' && categoryChart.rows.length === 1"
          class="rounded-xl border border-sky-100 bg-white p-3"
          data-testid="report-chart-categories-compact"
        >
          <h4 class="text-sm font-extrabold text-ink-900">{{ categoryChart.title }}</h4>
          <p v-if="categoryChart.subtitle" class="mt-0.5 text-xs text-ink-500">{{ categoryChart.subtitle }}</p>
          <p class="mt-3 text-sm font-semibold text-ink-800">
            <span
              class="mr-1.5 inline-block h-2.5 w-2.5 rounded-sm"
              :style="{ background: categoryChart.rows[0].color }"
            />
            {{ categoryChart.rows[0].label }} · {{ categoryChart.rows[0].count }}
          </p>
        </div>
        <AnalyticsRankedBarChart
          v-if="visualChart('feedback_ratings')"
          :title="visualChart('feedback_ratings').title"
          :subtitle="visualChart('feedback_ratings').subtitle || ''"
          :rows="visualChart('feedback_ratings').rows"
          empty-text="—"
          test-id="report-chart-feedback-ratings"
        />
      </div>
    </section>

    <!-- 3. Event and Participation -->
    <section class="pes-section">
      <h2>{{ t('reports.summary.sectionParticipation') }}</h2>
      <div class="pes-panel">
        <dl class="pes-kv">
          <div><dt>{{ t('reports.summary.event') }}</dt><dd>{{ eventTitle }}</dd></div>
          <div><dt>{{ t('reports.summary.dateTime') }}</dt><dd>{{ dateRange }}</dd></div>
          <div><dt>{{ t('reports.summary.venue') }}</dt><dd>{{ venue }}</dd></div>
        </dl>
      </div>

      <template v-if="pipeline">
        <h3>{{ t('reports.summary.applicationsPipeline') }}</h3>
        <dl class="pes-kv">
          <div><dt>{{ t('reports.summary.applications') }}</dt><dd>{{ displayMetric(pipeline.total_bookings) }}</dd></div>
          <div><dt>{{ t('reports.summary.uniqueApplicants') }}</dt><dd>{{ displayMetric(pipeline.unique_applicants) }}</dd></div>
          <div><dt>{{ t('reports.summary.approvedBookings') }}</dt><dd>{{ displayMetric(firstDefined(pipeline.approved_count, pipeline.by_approval_status?.Approved)) }}</dd></div>
          <div><dt>{{ t('reports.summary.approvedVendors') }}</dt><dd>{{ displayMetric(pipeline.approved_unique_vendors) }}</dd></div>
        </dl>
        <div v-if="statusBars.length" class="pes-bars">
          <div v-for="row in statusBars" :key="row.label" class="pes-bar">
            <div class="pes-bar__label"><span>{{ row.label }}</span><strong>{{ row.count }}</strong></div>
            <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
          </div>
        </div>
        <p class="pes-note">
          Only statuses with recorded applications are shown. Application counts and unique-vendor counts are separate.
          Approved bookings are not verified attendance.
        </p>
      </template>

      <h3>{{ t('reports.summary.verifiedCheckIns') }}</h3>
      <template v-if="attendanceRecorded">
        <dl class="pes-kv">
          <div><dt>{{ t('reports.summary.verifiedCheckIns') }}</dt><dd>{{ attendance.verified_check_in_count }}</dd></div>
        </dl>
        <p class="pes-note">A single check-in timestamp does not prove complete multi-day attendance.</p>
      </template>
      <p v-else class="pes-muted" data-testid="attendance-not-recorded">
        {{ attendance?.message || t('reports.summary.notRecorded') }}
      </p>

      <template v-if="utilisationSection">
        <h3>{{ t('reports.summary.siteDayUtilisation') }}</h3>
        <template v-if="utilisationSection.available">
          <dl class="pes-kv">
            <div><dt>{{ t('reports.summary.availableActiveSiteDays') }}</dt><dd>{{ utilisationSection.available_active_site_days }}</dd></div>
            <div><dt>{{ t('reports.summary.occupiedSiteDays') }}</dt><dd>{{ utilisationSection.occupied_site_days }}</dd></div>
            <div><dt>{{ t('reports.summary.siteDayUtilisation') }}</dt><dd>{{ utilisationSection.utilisation_percent }}%</dd></div>
          </dl>
          <div class="pes-bar">
            <div class="pes-bar__track">
              <div
                class="pes-bar__fill pes-bar__fill--green"
                :style="{ width: `${Math.max(2, Math.min(100, Number(utilisationSection.utilisation_percent) || 0))}%` }"
              ></div>
            </div>
          </div>
          <p class="pes-note">
            Site-day utilisation = occupied active site-days ÷ available active site-days × 100.
            Unavailable sites are excluded. This is not unique physical-booth occupancy.
          </p>
        </template>
        <p v-else class="pes-muted" data-testid="utilisation-unavailable">
          {{ utilisationSection.message || t('reports.summary.notAvailableForEvent') }}
        </p>
      </template>

      <template v-if="categories.length">
        <h3>{{ t('reports.summary.approvedVendorCategories') }}</h3>
        <div class="pes-bars">
          <div v-for="row in categoryBars" :key="row.label" class="pes-bar">
            <div class="pes-bar__label"><span>{{ row.label }}</span><strong>{{ row.count }}</strong></div>
            <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
          </div>
        </div>
      </template>
    </section>

    <!-- 4. Financial Summary -->
    <section v-if="payments" class="pes-section">
      <h2>{{ t('reports.summary.sectionFinancial') }}</h2>
      <p class="pes-note">Site booking revenue from frozen booking price snapshots and invoices. Vendor survey sales are not organizer revenue.</p>
      <dl class="pes-kv">
        <div><dt>{{ t('reports.summary.expectedBookingRevenue') }}</dt><dd>{{ moneyOrMissing(paymentExpected) }}</dd></div>
        <div><dt>{{ t('reports.summary.invoicedAmount') }}</dt><dd>{{ moneyOrMissing(payments.invoiced_amount) }}</dd></div>
        <div><dt>{{ t('reports.summary.collectedRevenue') }}</dt><dd class="pes-pos">{{ moneyOrMissing(paymentCollected) }}</dd></div>
        <div><dt>{{ t('reports.summary.outstandingInvoiceBalance') }}</dt><dd class="pes-amber">{{ hasInvoices ? moneyOrMissing(payments.outstanding_invoice_balance ?? paymentUnpaid) : t('reports.summary.notAvailable') }}</dd></div>
        <div><dt>{{ t('reports.summary.unbilledBookingValue') }}</dt><dd>{{ moneyOrMissing(payments.unbilled_booking_value) }}</dd></div>
        <div><dt>{{ t('reports.summary.collectionRate') }}</dt><dd>{{ collectionRateDisplay }}</dd></div>
        <div v-if="withoutInvoice != null">
          <dt>{{ t('reports.summary.approvedWithoutInvoices') }}</dt><dd>{{ withoutInvoice }}</dd>
        </div>
      </dl>
      <p v-if="paidWithdrawalDisclosure" class="pes-note" data-testid="paid-withdrawal-disclosure">
        {{ paidWithdrawalDisclosure }}
      </p>
      <p v-if="payments.potentially_incomplete" class="pes-warn">
        Financial summary may be incomplete because one or more approved bookings have no invoice.
        Unbilled booking value is not treated as outstanding invoice balance.
      </p>
    </section>

    <section v-if="eventPerformance" class="pes-section" data-testid="event-performance-section">
      <h2>{{ t('reports.summary.sectionEventPerformance') }}</h2>
      <dl class="pes-kv">
        <div><dt>{{ t('reports.summary.uniqueApprovedVendors') }}</dt><dd>{{ eventPerformance.unique_approved_vendors ?? '—' }}</dd></div>
        <div><dt>{{ t('reports.summary.approvedBookings') }}</dt><dd>{{ eventPerformance.approved_bookings ?? '—' }}</dd></div>
        <div><dt>{{ t('reports.summary.openBookingSites') }}</dt><dd>{{ eventPerformance.open_booking_sites ?? '—' }}</dd></div>
        <div><dt>{{ t('reports.summary.sitesSold') }}</dt><dd>{{ eventPerformance.sites_sold ?? '—' }}</dd></div>
        <div><dt>{{ t('reports.summary.availableSites') }}</dt><dd>{{ eventPerformance.available_sites ?? '—' }}</dd></div>
        <div>
          <dt>{{ t('reports.summary.siteUtilisation') }}</dt>
          <dd>{{ eventPerformance.site_utilisation_percent != null ? `${eventPerformance.site_utilisation_percent}%` : '—' }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="feedbackSection" class="pes-section" data-testid="feedback-summary-section">
      <h2>{{ t('reports.summary.sectionFeedback') }}</h2>
      <p class="pes-note">Aggregate In-app Feedback only. Raw comments are excluded from published reports.</p>
      <template v-if="Number(feedbackSection.response_count) > 0">
        <dl class="pes-kv">
          <div><dt>{{ t('reports.summary.totalFeedbackResponses') }}</dt><dd>{{ feedbackSection.response_count }}</dd></div>
          <div><dt>{{ t('reports.summary.vendorResponses') }}</dt><dd>{{ feedbackSection.vendor_response_count ?? 0 }}</dd></div>
          <div><dt>{{ t('reports.summary.nonVendorResponses') }}</dt><dd>{{ feedbackSection.non_vendor_response_count ?? 0 }}</dd></div>
          <div><dt>{{ t('reports.summary.averageOverallRating') }}</dt><dd>{{ feedbackSection.average_rating ?? '—' }}</dd></div>
          <div>
            <dt>{{ t('reports.summary.vendorResponseRate') }}</dt>
            <dd>{{ feedbackSection.vendor_response_rate_percent != null ? `${feedbackSection.vendor_response_rate_percent}%` : '—' }}</dd>
          </div>
        </dl>
      </template>
      <p v-else class="pes-muted">{{ feedbackSection.message || t('reports.summary.notRecorded') }}</p>
    </section>

    <!-- 5. Vendor and Sales Insights -->
    <section v-if="showVendorInsights" class="pes-section" data-testid="survey-section">
      <h2>{{ t('reports.summary.sectionVendorInsights') }}</h2>
      <template v-if="surveyAvailable">
        <p class="pes-note">
          {{ surveySection.base_display || t('reports.summary.surveyBaseFallback', { count: surveySection.respondent_count }) }}.
          Categorical survey aggregates only; exact total vendor revenue is not calculated.
        </p>
        <div v-for="block in surveyBlocks" :key="block.key" class="pes-dist">
          <h3>{{ block.title }}</h3>
          <p v-if="block.message" class="pes-muted">{{ block.message }}</p>
          <template v-else>
            <p class="pes-note">
              {{ block.base }}
              <template v-if="block.denominatorNote"> · {{ block.denominatorNote }}</template>
              <template v-if="block.multiSelect"> · {{ t('reports.summary.multiSelectNote') }}</template>
            </p>
            <div class="pes-bars">
              <div v-for="row in block.rows" :key="`${block.key}-${row.key}`" class="pes-bar">
                <div class="pes-bar__label">
                  <span>{{ row.label }}</span>
                  <strong>{{ row.count }}<template v-if="row.percent != null"> · {{ row.percent }}%</template></strong>
                </div>
                <div class="pes-bar__track"><div class="pes-bar__fill" :style="{ width: row.width }"></div></div>
              </div>
            </div>
          </template>
        </div>
      </template>
      <p v-else-if="categories.length" class="pes-muted">
        Survey responses were not available for this event. Approved vendor categories are shown in Participation.
      </p>
    </section>

    <!-- 6. Environmental and Social -->
    <section v-if="environmentalAvailable" class="pes-section">
      <h2>{{ t('reports.summary.sectionEnvironmental') }}</h2>
      <p class="pes-note">
        <strong>Vendor-reported survey indicators.</strong>
        These indicators are based on vendor responses and are not direct measurements of waste, carbon emissions or total items sold.
      </p>
      <dl class="pes-kv">
        <div>
          <dt>{{ t('reports.summary.vendorsReportingReused') }}</dt>
          <dd>{{ environmentalSection.vendors_reporting_reused_goods ?? 0 }}</dd>
        </div>
        <div><dt>{{ t('reports.summary.plansToDonate') }}</dt><dd>{{ environmentalSection.plans_to_donate ?? 0 }}</dd></div>
        <div><dt>{{ t('reports.summary.plansToRecycle') }}</dt><dd>{{ environmentalSection.plans_to_recycle ?? 0 }}</dd></div>
        <div><dt>{{ t('reports.summary.plansToRelist') }}</dt><dd>{{ environmentalSection.plans_to_relist_or_store ?? 0 }}</dd></div>
        <div><dt>{{ t('reports.summary.plansToDispose') }}</dt><dd>{{ environmentalSection.plans_to_dispose ?? 0 }}</dd></div>
      </dl>
      <template v-if="usedStockBars.length">
        <h3>{{ t('reports.summary.usedStockBands') }}</h3>
        <div class="pes-chips">
          <span v-for="row in usedStockBars" :key="row.label" class="pes-chip">{{ row.label }}: {{ row.count }}</span>
        </div>
      </template>
      <template v-if="supportEffectBars.length">
        <h3>{{ t('reports.summary.supportEffect') }}</h3>
        <div class="pes-chips">
          <span v-for="row in supportEffectBars" :key="row.label" class="pes-chip">{{ row.label }}: {{ row.count }}</span>
        </div>
      </template>
    </section>

    <!-- Analysis blocks -->
    <section v-if="analysisParticipation.length" class="pes-section">
      <h2>{{ t('reports.summary.sectionParticipationOps') }}</h2>
      <p v-for="(sentence, idx) in analysisParticipation" :key="`p-${idx}`">{{ sentence }}</p>
    </section>
    <section v-if="analysisFinance.length" class="pes-section">
      <h2>{{ t('reports.summary.sectionFinancialFindings') }}</h2>
      <p v-for="(sentence, idx) in analysisFinance" :key="`f-${idx}`">{{ sentence }}</p>
    </section>
    <section v-if="analysisFeedback.length" class="pes-section">
      <h2>{{ t('reports.summary.sectionFeedbackFindings') }}</h2>
      <p v-for="(sentence, idx) in analysisFeedback" :key="`fb-${idx}`">{{ sentence }}</p>
    </section>

    <!-- Organizer Assessment -->
    <section class="pes-section">
      <h2>{{ t('reports.summary.sectionOrganizerAssessment') }}</h2>
      <h3>{{ t('reports.summary.organizerObservations') }}</h3>
      <div v-if="report.organizer_observations" class="pes-narrative">{{ report.organizer_observations }}</div>
      <p v-else class="pes-muted">{{ t('reports.summary.notProvided') }}</p>
      <h3>{{ t('reports.summary.recommendations') }}</h3>
      <div v-if="report.organizer_recommendations" class="pes-narrative">{{ report.organizer_recommendations }}</div>
      <p v-else class="pes-muted">{{ t('reports.summary.notProvided') }}</p>
    </section>

    <section class="pes-section" data-testid="programme-conclusion">
      <h2>{{ t('reports.summary.sectionConclusion') }}</h2>
      <div v-if="programmeConclusion" class="pes-narrative">{{ programmeConclusion }}</div>
      <p v-else class="pes-muted">{{ t('reports.summary.notProvided') }}</p>
    </section>

    <!-- Methodology -->
    <section class="pes-section">
      <h2>{{ t('reports.summary.sectionMethodology') }}</h2>
      <dl class="pes-kv pes-kv--method">
        <div><dt>{{ t('reports.summary.reportScope') }}</dt><dd>This report covers one carboot event only.</dd></div>
        <div>
          <dt>{{ t('reports.summary.reportVersion') }}</dt>
          <dd>{{ t('reports.summary.versionN', { n: report.version }) }}<template v-if="coverStatusLabel"> ({{ coverStatusLabel }})</template></dd>
        </div>
        <div v-if="dataCutOff"><dt>{{ t('reports.summary.dataCutOff') }}</dt><dd>{{ dataCutOff }}</dd></div>
        <div>
          <dt>{{ t('reports.summary.applicationsVsUnique') }}</dt>
          <dd>Application counts and unique applicant/vendor counts are separate.</dd>
        </div>
        <div>
          <dt>{{ t('reports.summary.approvedVsAttendance') }}</dt>
          <dd>Approved bookings are not labelled as attendance unless verified check-ins are recorded.</dd>
        </div>
        <div>
          <dt>{{ t('reports.summary.siteDayUtilisation') }}</dt>
          <dd>Occupied active site-days ÷ available active site-days × 100.</dd>
        </div>
        <div v-if="surveyAvailable">
          <dt>{{ t('reports.summary.surveyResponseBase') }}</dt>
          <dd>{{ surveySection.base_display || t('reports.summary.surveyBaseFallback', { count: surveySection.respondent_count }) }}</dd>
        </div>
        <div v-if="surveyAvailable">
          <dt>{{ t('reports.summary.multiSelectQuestions') }}</dt>
          <dd>{{ t('reports.summary.multiSelectNote') }}</dd>
        </div>
        <div>
          <dt>{{ t('reports.summary.financialInclusion') }}</dt>
          <dd>
            Collected booth fees include paid approved invoices and paid withdrawn bookings under the
            non-refundable withdrawal policy. Pending verification and refunds are shown separately when present.
          </dd>
        </div>
        <div>
          <dt>{{ t('reports.summary.missingData') }}</dt>
          <dd>Missing or unavailable metrics are omitted or shown as Not recorded / Not available — never invented as zero.</dd>
        </div>
        <div v-if="dataQualityWarnings.length">
          <dt>{{ t('reports.summary.dataQualityWarnings') }}</dt>
          <dd>{{ dataQualityWarnings.join('; ') }}</dd>
        </div>
        <div>
          <dt>{{ t('reports.summary.provisionalOrFinal') }}</dt>
          <dd>Provisional means the snapshot may still change. Final means the published snapshot for this version is frozen.</dd>
        </div>
      </dl>
    </section>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalyticsDoughnutChart from '../analytics/AnalyticsDoughnutChart.vue';
import AnalyticsRankedBarChart from '../analytics/AnalyticsRankedBarChart.vue';
import AnalyticsStackedBarChart from '../analytics/AnalyticsStackedBarChart.vue';
import {
  collectionRate,
  formatReportMoney,
  reportDistributionTitle,
  reportOptionLabel,
} from '../../utils/postEventReportPresentation.js';
import { buildReportVisualSpec } from '../../utils/reportVisualSpec.js';
import { formatLocaleDateTime } from '../../utils/localeFormat';

const props = defineProps({
  report: { type: Object, required: true },
});

const { t } = useI18n();
const uumLogoFailed = ref(false);
const cmartLogoFailed = ref(false);
const legacyLogoFailed = ref(false);

const onCmartLogoError = () => {
  cmartLogoFailed.value = true;
};

const snapshot = computed(() => props.report?.snapshot || {});
const visualSpec = computed(() =>
  buildReportVisualSpec(snapshot.value, {
    labelFn: (key) => reportOptionLabel(key),
  }),
);
const visualChart = (id) => {
  const chart = visualSpec.value?.charts?.[id];
  if (!chart || chart.empty || !chart.rows?.length) return null;
  return chart;
};
const categoryChart = computed(() => visualChart('vendor_categories'));
const hasVisualCharts = computed(() =>
  ['site_utilisation', 'booking_status', 'revenue_collection', 'vendor_categories', 'feedback_ratings']
    .some((id) => visualChart(id)),
);

const eventTitle = computed(
  () => props.report.event_title_snapshot || snapshot.value?.event?.title || t('reports.summary.eventFallback'),
);
const venue = computed(() => snapshot.value?.event?.venue || snapshot.value?.venue || t('reports.summary.venueFallback'));

const pipeline = computed(() => {
  const section = snapshot.value?.sections?.booking_pipeline;
  if (!section || section.excluded) return null;
  return section;
});
const payments = computed(() => {
  const section = snapshot.value?.sections?.payments;
  if (!section || section.excluded) return null;
  return section;
});
const eventPerformance = computed(() => {
  const section = snapshot.value?.sections?.event_performance;
  if (!section || section.excluded) return null;
  return section;
});
const feedbackSection = computed(() => {
  const section = snapshot.value?.sections?.feedback;
  if (!section || section.excluded) return null;
  return section;
});
const utilisationSection = computed(() => {
  const section = snapshot.value?.sections?.site_day_utilisation;
  if (!section || section.excluded) return null;
  return section;
});
const attendance = computed(() => snapshot.value?.sections?.attendance || null);
const surveySection = computed(() => {
  const section = snapshot.value?.sections?.vendor_survey;
  if (!section || section.excluded) return null;
  return section;
});
const environmentalSection = computed(() => snapshot.value?.sections?.environmental_social || null);

const attendanceRecorded = computed(
  () => Boolean(attendance.value?.recorded) && attendance.value?.verified_check_in_count != null,
);
const surveyAvailable = computed(() => Boolean(surveySection.value?.available));
const environmentalAvailable = computed(() => Boolean(environmentalSection.value?.available));

const coverStatusKey = computed(() => {
  if (props.report?.cover_status) {
    const raw = String(props.report.cover_status);
    if (/provisional/i.test(raw)) return 'provisional';
    if (/final/i.test(raw)) return 'final';
    return raw.toLowerCase();
  }
  if (snapshot.value?.provisional) return 'provisional';
  if (props.report?.status === 'published' || props.report?.status === 'superseded') return 'final';
  return null;
});

const coverStatusLabel = computed(() => {
  if (coverStatusKey.value === 'provisional') return t('reports.summary.provisional');
  if (coverStatusKey.value === 'final') return t('reports.summary.final');
  return props.report?.cover_status || null;
});

const dateRange = computed(() => {
  const display = props.report?.event_date_range_display || snapshot.value?.event?.date_range_display;
  if (display) return display;
  const start = props.report.event_starts_at_snapshot || snapshot.value?.event?.starts_at;
  const end = props.report.event_ends_at_snapshot || snapshot.value?.event?.ends_at;
  if (!start && !end) return t('reports.summary.notRecorded');
  return `${formatEnglishDate(start)} – ${formatEnglishDate(end)}`;
});

const publishedDisplay = computed(
  () => props.report?.published_at_display || formatEnglishDate(props.report?.published_at),
);

const paymentExpected = computed(() => firstDefined(
  payments.value?.expected_booking_revenue,
  payments.value?.expected_booth_fees,
  payments.value?.expected,
));
const paymentCollected = computed(() => firstDefined(
  payments.value?.collected_revenue,
  payments.value?.collected_booth_fees,
  payments.value?.collected,
));
const paymentUnpaid = computed(() => firstDefined(payments.value?.unpaid_approved, payments.value?.outstanding));
const paymentPending = computed(() => payments.value?.pending_verification_approved ?? null);
const paymentRefunded = computed(() => payments.value?.refunded_approved ?? null);
const withoutInvoice = computed(() => payments.value?.approved_bookings_without_invoice ?? null);
const paidWithdrawalDisclosure = computed(() => payments.value?.paid_withdrawals?.disclosure || null);
const hasInvoices = computed(() => Number(payments.value?.invoice_count ?? payments.value?.invoice_count_approved ?? 0) > 0);
const collectionRateDisplay = computed(() => {
  if (payments.value?.collection_rate_percent != null) {
    return `${payments.value.collection_rate_percent}%`;
  }
  if (!hasInvoices.value) return t('reports.summary.notAvailable');
  const legacy = collectionRate(paymentCollected.value, paymentExpected.value);
  return legacy != null ? `${legacy}%` : '—';
});
const collectionRateValue = computed(() => collectionRate(paymentCollected.value, paymentExpected.value));

const categories = computed(() => {
  const dist = snapshot.value?.sections?.vendor_categories?.distribution;
  if (!dist) return [];
  if (Array.isArray(dist)) {
    return dist.map((row) => ({
      label: row.label || row.category || 'Unspecified',
      count: Number(row.count) || 0,
    }));
  }
  return Object.entries(dist).map(([label, count]) => ({ label, count: Number(count) || 0 }));
});

const showVendorInsights = computed(() => surveyAvailable.value || categories.value.length > 0);

const statusBars = computed(() => {
  const p = pipeline.value;
  if (!p) return [];
  const by = p.by_approval_status || {};
  const entries = [
    [t('reports.summary.statusPending'), firstDefined(p.pending_count, sumStatus(by, ['Pending_Organizer', 'Pending_Staff', 'Pending_Boss']))],
    [t('reports.summary.statusNeedsRevision'), firstDefined(p.needs_revision_count, by.Needs_Revision)],
    [t('reports.summary.statusApproved'), firstDefined(p.approved_count, by.Approved)],
    [t('reports.summary.statusRejected'), firstDefined(p.rejected_count, by.Rejected)],
    [t('reports.summary.statusCancelled'), firstDefined(p.cancelled_count, by.Cancelled)],
    [t('reports.summary.statusWithdrawn'), firstDefined(p.withdrawn_count, by.Withdrawn)],
  ]
    .map(([label, count]) => ({ label, count: Number(count) || 0 }))
    .filter((row) => row.count > 0);
  const max = Math.max(...entries.map((row) => row.count), 1);
  return entries.map((row) => ({
    ...row,
    width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
  }));
});

const categoryBars = computed(() => {
  const max = Math.max(...categories.value.map((row) => row.count), 1);
  return categories.value.map((row) => ({
    ...row,
    width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
  }));
});

const surveyBlocks = computed(() => {
  const distributions = surveySection.value?.distributions || {};
  return Object.entries(distributions)
    .filter(([, dist]) => (dist?.rows && dist.rows.length) || dist?.message)
    .map(([key, dist]) => {
      const rows = (dist.rows || []).map((row) => ({
        key: row.key || row.label,
        label: reportOptionLabel(row.label || row.key),
        count: Number(row.count) || 0,
        percent: row.percent ?? null,
      }));
      const max = Math.max(...rows.map((row) => row.count), 1);
      return {
        key,
        title: reportDistributionTitle(key),
        base: dist.base_display || '',
        denominatorNote: dist.denominator_note || null,
        multiSelect: Boolean(dist.multi_select),
        message: !rows.length ? dist.message || null : null,
        rows: rows.map((row) => ({
          ...row,
          width: `${Math.max(4, Math.round((row.count / max) * 100))}%`,
        })),
      };
    });
});

const usedStockBars = computed(() =>
  (environmentalSection.value?.used_stock_sales_bands?.rows || []).map((row) => ({
    label: reportOptionLabel(row.label || row.key),
    count: Number(row.count) || 0,
  })),
);

const supportEffectBars = computed(() =>
  (environmentalSection.value?.supporting_activity_effect?.rows || []).map((row) => ({
    label: reportOptionLabel(row.label || row.key),
    count: Number(row.count) || 0,
  })),
);

const executiveKpis = computed(() => {
  const cards = [];
  if (pipeline.value?.total_bookings != null) {
    cards.push({ label: t('reports.summary.kpiApplications'), value: pipeline.value.total_bookings });
  }
  if (pipeline.value) {
    const approved = firstDefined(pipeline.value.approved_count, pipeline.value.by_approval_status?.Approved);
    if (approved != null) cards.push({ label: t('reports.summary.kpiApprovedBookings'), value: approved });
    if (pipeline.value.approved_unique_vendors != null) {
      cards.push({ label: t('reports.summary.kpiApprovedVendors'), value: pipeline.value.approved_unique_vendors });
    }
  }
  if (attendanceRecorded.value) {
    cards.push({ label: t('reports.summary.kpiVerifiedCheckIns'), value: attendance.value.verified_check_in_count });
  }
  if (utilisationSection.value?.available && utilisationSection.value.utilisation_percent != null) {
    cards.push({ label: t('reports.summary.kpiSiteDayUtilisation'), value: `${utilisationSection.value.utilisation_percent}%` });
  }
  if (paymentCollected.value != null) {
    cards.push({ label: t('reports.summary.kpiCollectedBoothFees'), value: formatReportMoney(paymentCollected.value) });
  }
  if (surveyAvailable.value && surveySection.value.respondent_count != null) {
    cards.push({ label: t('reports.summary.kpiSurveyRespondents'), value: surveySection.value.respondent_count });
  }
  return cards;
});

const executiveNarrative = computed(() => {
  const frozen = snapshot.value?.programme?.executive_summary;
  if (typeof frozen === 'string' && frozen.trim()) return frozen.trim();

  const bits = [];
  if (pipeline.value?.total_bookings != null) {
    const n = Number(pipeline.value.total_bookings);
    bits.push(`${n} application${n === 1 ? '' : 's'} recorded`);
  }
  const approved = pipeline.value
    ? firstDefined(pipeline.value.approved_count, pipeline.value.by_approval_status?.Approved)
    : null;
  if (approved != null) bits.push(`${approved} approved booking${Number(approved) === 1 ? '' : 's'}`);
  if (pipeline.value?.approved_unique_vendors != null) {
    const n = Number(pipeline.value.approved_unique_vendors);
    bits.push(`${n} approved unique vendor${n === 1 ? '' : 's'}`);
  }
  if (attendanceRecorded.value) bits.push(`${attendance.value.verified_check_in_count} verified check-ins`);
  if (utilisationSection.value?.available && utilisationSection.value.utilisation_percent != null) {
    bits.push(`site-day utilisation of ${utilisationSection.value.utilisation_percent}%`);
  }
  if (paymentCollected.value != null) bits.push(`collected booth fees of ${formatReportMoney(paymentCollected.value)}`);
  if (surveyAvailable.value && surveySection.value.respondent_count != null) {
    bits.push(`${surveySection.value.respondent_count} survey responses`);
  }
  if (!bits.length) {
    return 'This report summarises the available snapshot for the selected event. Some operational or survey indicators were not recorded.';
  }
  const joined = bits.length === 1
    ? bits[0]
    : bits.length === 2
      ? `${bits[0]} and ${bits[1]}`
      : `${bits.slice(0, -1).join(', ')}, and ${bits[bits.length - 1]}`;
  return `Based on the frozen event snapshot, this report records ${joined}. Figures reflect recorded system and survey data only and do not imply an overall success judgement.`;
});

const programme = computed(() => snapshot.value?.programme || {});
const programmeIntroduction = computed(
  () => programme.value?.introduction || props.report?.programme_introduction || null,
);
const programmeObjectives = computed(() => {
  const rows = programme.value?.objectives ?? props.report?.programme_objectives ?? [];
  return Array.isArray(rows) ? rows.filter((row) => String(row || '').trim()) : [];
});
const objectivesNotApplicable = computed(
  () => Boolean(programme.value?.objectives_not_applicable || props.report?.objectives_not_applicable),
);
const programmeConclusion = computed(
  () => programme.value?.conclusion || props.report?.conclusion || null,
);
const analysisParticipation = computed(() => programme.value?.analysis?.participation || []);
const analysisFinance = computed(() => programme.value?.analysis?.finance || []);
const analysisFeedback = computed(() => programme.value?.analysis?.feedback || []);
const programmeDetailRows = computed(() => {
  const details = programme.value?.details || {};
  const map = [
    ['event_name', t('reports.summary.eventName')],
    ['date_time', t('reports.summary.dateTime')],
    ['venue', t('reports.summary.venue')],
    ['organizer', t('reports.summary.organizer')],
    ['event_status', t('reports.summary.eventStatus')],
    ['data_cut_off', t('reports.summary.dataCutOff')],
    ['open_booking_sites', t('reports.summary.openBookingSites')],
    ['site_price', t('reports.summary.sitePrice')],
    ['approved_bookings', t('reports.summary.kpiApprovedBookings')],
    ['unique_participating_vendors', t('reports.summary.kpiApprovedVendors')],
  ];
  const rows = [];
  map.forEach(([key, label]) => {
    if (details[key] != null && details[key] !== '') {
      rows.push({ label, text: details[key] });
    }
  });
  if (details.item_reservations_enabled != null) {
    rows.push({
      label: t('reports.summary.itemReservations'),
      text: details.item_reservations_enabled
        ? `${t('reports.summary.enabled')}${details.item_reservation_service_fee ? ` · ${details.item_reservation_service_fee}` : ''}`
        : t('reports.summary.notEnabled'),
    });
  }
  return rows;
});

const dataCutOff = computed(
  () => snapshot.value?.methodology?.data_cut_off || snapshot.value?.generated_at_display || null,
);

const dataQualityWarnings = computed(() => {
  const warnings =
    snapshot.value?.methodology?.data_quality_warnings || snapshot.value?.data_quality_warnings || [];
  return Array.isArray(warnings) ? warnings.filter(Boolean).map(String) : [];
});

function firstDefined(...values) {
  for (const value of values) {
    if (value !== undefined && value !== null) return value;
  }
  return null;
}

function sumStatus(by, keys) {
  if (!by) return null;
  let total = 0;
  let found = false;
  keys.forEach((key) => {
    if (by[key] != null) {
      total += Number(by[key]);
      found = true;
    }
  });
  return found ? total : null;
}

function displayMetric(value) {
  if (value === undefined || value === null) return t('reports.summary.notRecorded');
  return value;
}

function moneyOrMissing(value) {
  const formatted = formatReportMoney(value);
  return formatted ?? t('reports.summary.notAvailableForEvent');
}

function formatEnglishDate(value) {
  if (!value) return '—';
  try {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('reports.summary.notRecorded');
    return formatLocaleDateTime(date, {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
  } catch {
    return t('reports.summary.notRecorded');
  }
}
</script>

<style scoped>
.pes-report {
  --pes-navy: #2D439C;
  --pes-blue: #3970E4;
  --pes-sky: #F5F8FE;
  --pes-ink: #1E293B;
  --pes-muted: #64748B;
  --pes-line: #E8EEF6;
  --pes-green: #2E9D78;
  --pes-amber: #E86F88;
  color: var(--pes-ink);
  font-size: 0.95rem;
  line-height: 1.5;
}

.pes-cover {
  border: 1px solid var(--pes-line);
  background: linear-gradient(180deg, #f8fbff 0%, #ffffff 55%);
  padding: 1.75rem 1.5rem 1.5rem;
  margin-bottom: 1.75rem;
}

.pes-cover__brand {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.pes-cover__logo {
  height: 2.5rem;
  width: auto;
  margin-bottom: 0;
}

.pes-cover__logo-fallback {
  display: inline-block;
  margin-bottom: 0;
  background: var(--pes-blue);
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 0.55rem 0.85rem;
}

.pes-visual-grid {
  display: grid;
  gap: 0.85rem;
  grid-template-columns: 1fr;
}

@media (min-width: 768px) {
  .pes-visual-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.pes-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pes-blue);
}

.pes-cover__title {
  margin: 0.6rem 0 0.35rem;
  font-size: 1.75rem;
  line-height: 1.2;
  color: var(--pes-navy);
  font-weight: 800;
}

.pes-cover__date {
  margin: 0;
  color: var(--pes-muted);
}

.pes-cover__meta {
  display: grid;
  gap: 0.55rem 1.25rem;
  margin: 1.25rem 0 0;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
}

.pes-cover__meta dt {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--pes-muted);
}

.pes-cover__meta dd {
  margin: 0.1rem 0 0;
  font-weight: 600;
}

.pes-badge {
  display: inline-block;
  margin-top: 1rem;
  padding: 0.3rem 0.7rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border: 1px solid transparent;
}

.pes-badge--green {
  background: #ecfdf5;
  color: var(--pes-green);
  border-color: #a7f3d0;
}

.pes-badge--amber {
  background: #fffbeb;
  color: var(--pes-amber);
  border-color: #fcd34d;
}

.pes-section {
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--pes-line);
}

.pes-section h2 {
  margin: 0 0 0.85rem;
  font-size: 0.8rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--pes-navy);
  border-bottom: 2px solid #b3e5fc;
  padding-bottom: 0.35rem;
}

.pes-section h3 {
  margin: 1rem 0 0.5rem;
  font-size: 0.95rem;
  color: var(--pes-blue);
  font-weight: 700;
}

.pes-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
  gap: 0.65rem;
}

.pes-kpi {
  background: var(--pes-sky);
  border: 1px solid #e0f2fe;
  padding: 0.75rem 0.7rem;
}

.pes-kpi__label {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--pes-blue);
}

.pes-kpi__value {
  margin-top: 0.25rem;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--pes-ink);
}

.pes-summary,
.pes-panel,
.pes-narrative {
  background: #f8fafc;
  border: 1px solid var(--pes-line);
  padding: 0.85rem 1rem;
}

.pes-summary {
  margin-top: 0.85rem;
}

.pes-kv {
  display: grid;
  gap: 0.55rem 1rem;
  margin: 0;
}

.pes-kv > div {
  display: grid;
  grid-template-columns: minmax(10rem, 42%) 1fr;
  gap: 0.75rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid #eef2f7;
}

.pes-kv dt {
  color: #475569;
}

.pes-kv dd {
  margin: 0;
  font-weight: 700;
}

.pes-kv--method dd {
  font-weight: 500;
}

.pes-bars {
  margin-top: 0.5rem;
}

.pes-bar {
  margin: 0.45rem 0;
}

.pes-bar__label {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.2rem;
  font-size: 0.9rem;
}

.pes-bar__track {
  height: 0.45rem;
  background: #e2e8f0;
}

.pes-bar__fill {
  height: 100%;
  background: var(--pes-blue);
}

.pes-bar__fill--green {
  background: #059669;
}

.pes-note {
  margin: 0.55rem 0 0;
  font-size: 0.8rem;
  color: var(--pes-muted);
}

.pes-muted {
  color: var(--pes-muted);
}

.pes-warn {
  background: #fffbeb;
  border-left: 3px solid #d97706;
  color: #92400e;
  padding: 0.65rem 0.8rem;
  font-size: 0.85rem;
  margin: 0 0 0.75rem;
}

.pes-pos {
  color: var(--pes-green);
}

.pes-amber {
  color: var(--pes-amber);
}

.pes-narrative {
  white-space: pre-wrap;
  line-height: 1.6;
  margin-top: 0.35rem;
}

.pes-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.35rem;
}

.pes-chip {
  border: 1px solid var(--pes-line);
  background: #fff;
  padding: 0.25rem 0.55rem;
  font-size: 0.8rem;
}

.pes-dist + .pes-dist {
  margin-top: 0.85rem;
}
</style>
