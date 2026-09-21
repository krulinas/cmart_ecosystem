<template>
  <div class="space-y-4" data-testid="organizer-event-analytics-hub">
    <header class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <p class="text-[11px] font-bold uppercase tracking-wider text-brand-700">{{ t('organizer.analytics.hubEyebrow') }}</p>
          <h2 class="mt-0.5 truncate text-xl font-extrabold text-ink-900">
            {{ currentEvent?.title || t('organizer.analytics.selectEventTitle') }}
          </h2>
          <p v-if="currentEvent" class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-600">
            <span>{{ t('organizer.analytics.statusLabel') }} <strong class="text-ink-800">{{ currentEvent.status || t('organizer.analytics.unknown') }}</strong></span>
            <span>{{ formatDateRange(currentEvent.starts_at, currentEvent.ends_at) }}</span>
            <span v-if="overview?.computed_at">{{ t('organizer.analytics.updatedAt', { date: formatDate(overview.computed_at) }) }}</span>
            <span v-if="sourceModeLabel">{{ t('organizer.analytics.sourceLabel') }} <strong class="text-ink-800">{{ sourceModeLabel }}</strong></span>
          </p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
          <label class="block min-w-[14rem] flex-1 sm:flex-none">
            <span class="mb-1 block text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.event') }}</span>
            <select v-model="selectedEventId" class="ml-input w-full text-sm" :disabled="loadingEvents">
              <option value="">{{ t('organizer.analytics.selectEventOption') }}</option>
              <option v-for="event in events" :key="event.id" :value="String(event.id)">
                {{ event.title }}
              </option>
            </select>
          </label>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="!selectedEventId || loadingOverview"
            @click="refreshAll"
          >
            {{ loadingOverview ? t('organizer.analytics.refreshing') : t('organizer.analytics.refresh') }}
          </button>
          <button
            type="button"
            class="ml-btn-primary text-sm"
            :disabled="!selectedEventId"
            @click="goToReportCentre"
          >
            {{ t('organizer.analytics.generateReport') }}
          </button>
        </div>
      </div>
    </header>

    <p
      v-if="!selectedEventId"
      class="rounded-2xl border border-dashed border-sky-200 bg-sky-50/40 px-4 py-8 text-center text-sm text-ink-600"
    >
      {{ t('organizer.analytics.selectEventPrompt') }}
    </p>

    <template v-else>
      <div
        v-if="loadingOverview && !overview"
        class="rounded-2xl border border-ink-100 bg-white px-4 py-8 text-center text-sm text-ink-500"
      >
        {{ t('organizer.analytics.loading') }}
      </div>

      <template v-else>
        <p v-if="overviewError" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
          {{ overviewError }}
        </p>

        <nav
          class="flex gap-1 overflow-x-auto rounded-xl border border-sky-100 bg-white p-1 shadow-sm"
          :aria-label="t('organizer.analytics.sectionsAria')"
        >
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
            :class="activeTab === tab.id
              ? 'bg-brand-600 text-white shadow-sm'
              : 'text-ink-600 hover:bg-sky-50 hover:text-brand-800'"
            @click="setActiveTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </nav>

        <p
          v-if="surveyDegraded"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
          {{ t('organizer.analytics.surveyUnavailable') }}
          {{ overview?.survey?.message || t('organizer.analytics.surveyUnavailableFallback') }}
        </p>

        <p
          v-if="overview?.survey?.small_sample"
          class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
        >
          {{ t('organizer.analytics.smallSample', { n: overview.survey.respondent_count, threshold: overview.survey.small_sample_threshold }) }}
        </p>

        <!-- Overview -->
        <section v-if="activeTab === 'overview'" class="space-y-3" data-testid="analytics-overview">
          <AnalyticsDataSourceBadge :sources="dataSources" compact />

          <div
            v-if="showAddSurveyCta"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-brand-200 bg-brand-50/40 px-4 py-3"
          >
            <div>
              <p class="text-sm font-semibold text-ink-900">{{ t('organizer.analytics.addSurveyData') }}</p>
              <p class="text-xs text-ink-500">{{ t('organizer.analytics.addSurveyDataHint') }}</p>
            </div>
            <button type="button" class="ml-btn-primary text-sm" @click="setActiveTab('data-sources')">
              {{ t('organizer.analytics.addSurveyData') }}
            </button>
          </div>

          <div v-if="loadingOverview && !overview" class="rounded-xl border border-ink-100 bg-white px-3 py-6 text-center text-sm text-ink-500">
            {{ t('organizer.analytics.loading') }}
          </div>

          <template v-else>
            <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="event-performance">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.eventPerformance') }}</h3>
              <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.eventPerformanceHint') }}</p>
              <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3 lg:grid-cols-5">
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.approvedBookings') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.approved_bookings ?? approvedCount ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.uniqueApprovedVendors') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.unique_approved_vendors ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.openBookingSites') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.open_booking_sites ?? sites?.open_booking_sites ?? sites?.active_count ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.sitesSold') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.sites_sold ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.availableSites') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.available_sites ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.siteUtilisation') }}</dt>
                  <dd class="font-bold text-ink-900">
                    {{ eventPerformance?.site_utilisation_percent != null ? `${eventPerformance.site_utilisation_percent}%` : '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.feedbackResponses') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.feedback_response_count ?? inAppFeedback?.response_count ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.averageRating') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance?.average_overall_rating ?? inAppFeedback?.average_rating ?? '—' }}</dd>
                </div>
                <div v-if="eventPerformance?.item_reservations_total != null">
                  <dt class="text-ink-500">{{ t('organizer.analytics.itemReservations') }}</dt>
                  <dd class="font-bold text-ink-900">{{ eventPerformance.item_reservations_total }}</dd>
                </div>
              </dl>
              <p class="mt-2 text-[11px] text-ink-500">
                {{ t('organizer.analytics.physicalSitesNote', { count: eventPerformance?.physical_sites ?? sites?.total ?? '—' }) }}
              </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
              <button
                v-for="card in overviewKpis"
                :key="card.id"
                type="button"
                class="rounded-xl border border-sky-100 bg-white px-3 py-3 text-left shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                :class="card.clickable
                  ? 'cursor-pointer hover:border-brand-300 hover:bg-sky-50/60'
                  : 'cursor-default'"
                :disabled="!card.clickable"
                :title="card.title"
                @click="card.clickable && card.onClick()"
              >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">{{ card.label }}</p>
                <p class="mt-1 text-xl font-extrabold text-ink-900">{{ card.value }}</p>
                <p v-if="card.note" class="mt-0.5 text-xs text-ink-500">{{ card.note }}</p>
              </button>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
              <div
                ref="financeSectionRef"
                class="rounded-xl border border-sky-100 bg-white p-3"
                data-testid="overview-finance"
              >
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.bookingRevenue') }}</h3>
                    <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.bookingRevenueHint') }}</p>
                  </div>
                </div>

                <template v-if="systemIncluded && operationalReady">
                  <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.expectedBookingRevenue') }}</dt>
                      <dd class="font-bold text-ink-900">RM {{ formatMoney(payments?.expected_booking_revenue ?? payments?.expected) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.invoicedAmount') }}</dt>
                      <dd class="font-bold text-ink-900">RM {{ formatMoney(payments?.invoiced_amount) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.collectedRevenue') }}</dt>
                      <dd class="font-bold text-emerald-700">RM {{ formatMoney(payments?.collected_revenue ?? payments?.collected) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.outstandingInvoiceBalance') }}</dt>
                      <dd class="font-bold text-rose-700">
                        {{ hasInvoices ? `RM ${formatMoney(payments?.outstanding_invoice_balance ?? payments?.outstanding)}` : '—' }}
                      </dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.unbilledBookingValue') }}</dt>
                      <dd class="font-bold text-ink-900">RM {{ formatMoney(payments?.unbilled_booking_value) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.collectionRate') }}</dt>
                      <dd class="font-bold text-ink-900">{{ collectionRateLabel }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.avgPerApprovedVendor') }}</dt>
                      <dd class="font-bold text-ink-900">
                        {{ payments?.average_revenue_per_approved_vendor != null ? `RM ${formatMoney(payments.average_revenue_per_approved_vendor)}` : '—' }}
                      </dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.avgPerSiteSold') }}</dt>
                      <dd class="font-bold text-ink-900">
                        {{ payments?.average_revenue_per_site_sold != null ? `RM ${formatMoney(payments.average_revenue_per_site_sold)}` : '—' }}
                      </dd>
                    </div>
                  </dl>
                  <p v-if="!hasInvoices" class="mt-2 text-xs text-ink-500">
                    {{ t('organizer.analytics.noInvoicesNote') }}
                  </p>
                </template>
                <div v-else class="mt-3 space-y-2 text-sm text-ink-600">
                  <p v-if="!systemIncluded">{{ t('organizer.analytics.paymentsExcluded') }}</p>
                  <p v-else-if="!operationalReady">{{ t('organizer.analytics.bookingDataUnavailable') }}</p>
                  <p v-else-if="!Number(approvedCount)">{{ t('organizer.analytics.noApprovedBookings') }}</p>
                </div>
              </div>

              <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="vendor-category-distribution">
                <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.vendorCategoryDistribution') }}</h3>
                <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.vendorCategoryHint') }}</p>
                <ul v-if="(vendorCategories?.distribution || []).length" class="mt-3 space-y-2 text-sm">
                  <li
                    v-for="row in vendorCategories.distribution"
                    :key="row.label"
                    class="flex items-center justify-between gap-2 rounded-lg border border-ink-100 px-2 py-1.5"
                  >
                    <span class="font-semibold text-ink-800">{{ row.label }}</span>
                    <span class="text-xs text-ink-500">
                      {{ t('organizer.analytics.vendorsCount', { count: row.unique_vendors ?? row.count }) }}
                      <template v-if="row.vendor_percent != null"> · {{ row.vendor_percent }}%</template>
                    </span>
                  </li>
                </ul>
                <p v-else class="mt-3 text-sm text-ink-600">{{ t('organizer.analytics.noCategoryRecorded') }}</p>
              </div>
            </div>
          </template>
        </section>

        <!-- {{ t('organizer.analytics.feedbackSummary') }} (reuses survey-results tab id) -->
        <section v-else-if="activeTab === 'survey-results'" class="space-y-3">
          <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="feedback-summary">
            <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.feedbackSummary') }}</h3>
            <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.feedbackSummaryHint', { source: inAppFeedback?.source_label || t('organizer.analytics.inAppFeedback') }) }}</p>
            <template v-if="inAppFeedback?.available && Number(inAppFeedback.response_count) > 0">
              <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.totalResponses') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.response_count }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.vendor') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.vendor_response_count }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.nonVendor') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.non_vendor_response_count }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.averageRating') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.average_rating ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.vendorResponseRate') }}</dt>
                  <dd class="font-bold text-ink-900">
                    {{ inAppFeedback.vendor_response_rate_percent != null ? `${inAppFeedback.vendor_response_rate_percent}%` : '—' }}
                  </dd>
                </div>
              </dl>
              <div class="mt-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.ratingDistribution') }}</p>
                <ul class="mt-1 flex flex-wrap gap-2 text-xs">
                  <li
                    v-for="star in [5, 4, 3, 2, 1]"
                    :key="star"
                    class="rounded-md border border-ink-100 px-2 py-1"
                  >
                    {{ star }}★ · {{ inAppFeedback.rating_distribution?.[star] ?? 0 }}
                  </li>
                </ul>
              </div>
              <div v-if="(inAppFeedback.participation_distribution || []).length" class="mt-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.participantTypes') }}</p>
                <ul class="mt-1 space-y-1 text-sm text-ink-700">
                  <li v-for="row in inAppFeedback.participation_distribution" :key="row.type">
                    {{ row.label || row.type }} · {{ row.count }}
                  </li>
                </ul>
              </div>
              <div class="mt-4 border-t border-ink-100 pt-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.nonVendorComments') }}</p>
                <ul v-if="(inAppFeedback.anonymous_non_vendor_comments || []).length" class="mt-2 space-y-2">
                  <li
                    v-for="(item, idx) in inAppFeedback.anonymous_non_vendor_comments"
                    :key="`nv-${idx}`"
                    class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2 text-sm"
                  >
                    <p class="text-xs font-semibold text-ink-500">{{ item.author_label }} · {{ item.rating }}★</p>
                    <p class="mt-1 text-ink-800 whitespace-pre-line">{{ item.comments }}</p>
                  </li>
                </ul>
                <p v-else class="mt-2 text-sm text-ink-600">{{ t('organizer.analytics.noNonVendorComments') }}</p>
              </div>
            </template>
            <p v-else class="mt-3 text-sm text-ink-600">
              {{ inAppFeedback?.message || t('organizer.analytics.noFeedbackYet') }}
            </p>
          </div>

          <SurveyResultsPanel
            :overview="overview"
            :sources="dataSources"
            :respondent-count="respondentCount"
            :survey-empty="surveyEmpty"
            :show-add-csv-cta="showAddSurveyCta || csvOnlyOnboarding"
            @open-data-sources="setActiveTab('data-sources')"
          />
        </section>

        <!-- {{ t('organizer.analytics.vendorFeedback') }} -->
        <section v-else-if="activeTab === 'comments'" class="space-y-3">
          <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="vendor-feedback-list">
            <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.vendorFeedback') }}</h3>
            <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.vendorFeedbackHint') }}</p>
            <ul v-if="(inAppFeedback?.anonymous_vendor_comments || []).length" class="mt-3 space-y-2">
              <li
                v-for="(item, idx) in inAppFeedback.anonymous_vendor_comments"
                :key="`v-${idx}`"
                class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2 text-sm"
              >
                <p class="text-xs font-semibold text-ink-500">
                  {{ t('organizer.analytics.vendorRespondent') }} · {{ item.rating }}★
                  <span v-if="item.submitted_at" class="font-normal"> · {{ formatDate(item.submitted_at) }}</span>
                </p>
                <p class="mt-1 text-ink-800 whitespace-pre-line">{{ item.comments }}</p>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-ink-600">
              {{ t('organizer.analytics.noVendorFeedback') }}
            </p>
          </div>
          <AnalyticsDataSourceBadge :sources="dataSources" filter="csv" />
          <EventCommentsWordCloud
            :event-id="selectedEventId"
            :qualitative="qualitativeComments"
            :respondent-count="respondentCount"
            :feedback-link-ready="feedbackLinkReady"
            :survey-status="overview?.survey?.status || ''"
          />
        </section>

        <!-- Operations -->
        <section v-else-if="activeTab === 'operations'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" filter="system" />
          <div
            v-if="!systemIncluded"
            class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
          >
            {{ t('organizer.analytics.operationsHidden') }}
          </div>
          <div
            v-else-if="!operationalReady"
            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
          >
            {{ overview?.operational?.error || t('organizer.analytics.operationalUnavailable') }}
          </div>
          <template v-else>
            <div
              v-if="!hasOperationalRecords"
              class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
            >
              {{ t('organizer.analytics.noOperationalRecords') }}
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.totalBookings') }}</p>
                <p class="mt-1 text-xl font-extrabold">{{ pipeline?.total_bookings ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.approved') }}</p>
                <p class="mt-1 text-xl font-extrabold">{{ approvedCount ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.sitesSlots') }}</p>
                <p class="mt-1 text-xl font-extrabold">{{ sites?.total ?? 0 }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.itemReservations') }}</p>
                <p class="mt-1 text-xl font-extrabold">
                  {{ reservations?.available === false ? t('organizer.analytics.unavailable') : (reservations?.total ?? 0) }}
                </p>
              </article>
            </div>
            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsBarList
                :title="t('organizer.analytics.bookingsByApproval')"
                :rows="bookingStatusRows"
                :denominator="pipeline?.total_bookings || null"
                :empty-text="t('organizer.analytics.zeroBookings')"
              />
              <AnalyticsBarList
                :title="t('organizer.analytics.sitesByOperational')"
                :rows="siteStatusRows"
                :denominator="sites?.total || null"
                :empty-text="t('organizer.analytics.noSiteLayout')"
              />
            </div>
            <div class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-3 text-sm text-ink-600">
              {{ t('organizer.analytics.attendanceCheckIn') }}
              {{ t('organizer.analytics.attendanceUnavailable') }}
            </div>
          </template>
        </section>

        <!-- Data Sources -->
        <section v-else-if="activeTab === 'data-sources'" class="space-y-3">
          <AnalyticsDataSourceBadge :sources="dataSources" />
          <AnalyticsDataSourceManager
            :event-id="selectedEventId"
            :event-title="currentEvent?.title || ''"
            :overview="overview"
            @updated="onDataSourceUpdated"
            @view-survey-results="setActiveTab('survey-results')"
          />
        </section>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AnalyticsBarList from '../../../components/analytics/AnalyticsBarList.vue';
import AnalyticsDataSourceBadge from '../../../components/analytics/AnalyticsDataSourceBadge.vue';
import AnalyticsDataSourceManager from '../../../components/analytics/AnalyticsDataSourceManager.vue';
import EventCommentsWordCloud from '../../../components/analytics/EventCommentsWordCloud.vue';
import SurveyResultsPanel from '../../../components/analytics/SurveyResultsPanel.vue';
import { useEventAnalyticsContext } from '../../../composables/useEventAnalyticsContext';
import { ANALYTICS_HUB_TAB_STORAGE_KEY } from '../../../config/workspaceNav';
import {
  getEventAnalyticsOverview,
  listCarbootEventsForAnalytics,
  recomputeEventAnalytics,
} from '../../../services/eventAnalyticsApi';

const { t } = useI18n();
const toast = useToast();
const router = useRouter();
const { selectedEventId, setSelectedEvent, setSelectedEventId } = useEventAnalyticsContext();

const tabs = computed(() => [
  { id: 'overview', label: t('organizer.analytics.tabOverview') },
  { id: 'survey-results', label: t('organizer.analytics.tabFeedbackSummary') },
  { id: 'comments', label: t('organizer.analytics.tabVendorFeedback') },
  { id: 'operations', label: t('organizer.analytics.tabOperations') },
  { id: 'data-sources', label: t('organizer.analytics.tabDataSources') },
]);

const TAB_IDS = new Set(['overview', 'survey-results', 'comments', 'operations', 'data-sources']);

const LEGACY_TAB_MAP = {
  revenue: 'overview',
  vendors: 'survey-results',
  items: 'survey-results',
  experience: 'survey-results',
  'data-quality': 'data-sources',
  'data-sources': 'data-sources',
  comments: 'comments',
  'survey-results': 'survey-results',
  operations: 'operations',
  overview: 'overview',
};

const events = ref([]);
const overview = ref(null);
const loadingEvents = ref(false);
const loadingOverview = ref(false);
const overviewError = ref('');
const activeTab = ref(readStoredTab());
const financeMetric = ref('amount');
const financeSectionRef = ref(null);

function normalizeTab(tab) {
  if (!tab) return 'overview';
  const mapped = LEGACY_TAB_MAP[tab] || tab;
  return TAB_IDS.has(mapped) ? mapped : 'overview';
}

function readStoredTab() {
  try {
    return normalizeTab(sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY));
  } catch {
    return 'overview';
  }
}

const setActiveTab = (tabId) => {
  const next = normalizeTab(tabId);
  activeTab.value = next;
  try {
    sessionStorage.setItem(ANALYTICS_HUB_TAB_STORAGE_KEY, next);
  } catch {
    /* ignore */
  }
};

const currentEvent = computed(() =>
  events.value.find((e) => String(e.id) === String(selectedEventId.value)) || null,
);

const sourceMode = computed(() => overview.value?.analytics_source_mode || 'system_only');

const sourceModeLabel = computed(() => {
  switch (sourceMode.value) {
    case 'system_only': return t('organizer.analytics.sourceSystemData');
    case 'csv_only': return t('organizer.analytics.sourceCsvOnly');
    case 'combined': return t('organizer.analytics.sourceCombined');
    default: return sourceMode.value;
  }
});

const systemIncluded = computed(() =>
  sourceMode.value === 'system_only' || sourceMode.value === 'combined',
);

const surveyIncluded = computed(() =>
  sourceMode.value === 'csv_only' || sourceMode.value === 'combined',
);

const surveyDegraded = computed(() => overview.value?.survey?.degraded === true
  || overview.value?.survey?.status === 'degraded');

const surveyReady = computed(() => overview.value?.survey?.status === 'ready');
const surveyExcluded = computed(() => overview.value?.survey?.status === 'excluded');
const surveyMissing = computed(() =>
  ['missing_source', 'empty'].includes(overview.value?.survey?.status)
  || (surveyIncluded.value && !surveyReady.value && !surveyDegraded.value && !surveyExcluded.value),
);

const surveyEmpty = computed(() => !surveyReady.value);

const respondentCount = computed(() => {
  if (!surveyReady.value) return null;
  return overview.value?.survey?.respondent_count ?? null;
});

const showAddSurveyCta = computed(() =>
  surveyIncluded.value
  && !surveyReady.value
  && !surveyExcluded.value
  && sourceMode.value !== 'csv_only',
);

const csvOnlyOnboarding = computed(() =>
  sourceMode.value === 'csv_only' && !surveyReady.value,
);

const operationalReady = computed(() => overview.value?.operational?.available === true);
const payments = computed(() => overview.value?.operational?.sections?.payments || null);
const pipeline = computed(() => overview.value?.operational?.sections?.booking_pipeline || null);
const sites = computed(() => overview.value?.operational?.sections?.event_sites || null);
const eventPerformance = computed(() => overview.value?.operational?.sections?.event_performance || null);
const vendorCategories = computed(() => overview.value?.operational?.sections?.vendor_categories || null);
const inAppFeedback = computed(() => overview.value?.operational?.sections?.feedback || null);
const reservations = computed(() => overview.value?.operational?.sections?.item_reservations || null);
const approvedCount = computed(() => pipeline.value?.approved_count ?? null);

const hasOperationalRecords = computed(() =>
  Number(pipeline.value?.total_bookings || 0) > 0
  || Number(sites.value?.total || 0) > 0
  || Number(reservations.value?.total || 0) > 0,
);

const feedbackLinkReady = computed(() =>
  Boolean(overview.value?.data_readiness?.checks?.community_feedback_event_link?.ready),
);

const qualitativeComments = computed(() =>
  overview.value?.survey?.sections?.experience?.qualitative_comments || null,
);

const dataSources = computed(() => overview.value?.data_sources || []);

const collectionRateLabel = computed(() => {
  if (payments.value?.collection_rate_percent != null) {
    return `${payments.value.collection_rate_percent}%`;
  }
  if (!hasInvoices.value) return t('organizer.analytics.notAvailable');
  return '—';
});

const hasInvoices = computed(() => Number(
  payments.value?.invoice_count
  ?? payments.value?.invoice_count_approved
  ?? 0,
) > 0);

const surveyTopInsight = computed(() => {
  if (!surveyReady.value) return null;
  const cats = overview.value?.survey?.sections?.vendors?.product_categories;
  const rows = Array.isArray(cats) ? cats : [];
  if (!rows.length) return null;
  const top = [...rows].sort((a, b) => Number(b.count || 0) - Number(a.count || 0))[0];
  if (!top?.label) return null;
  const pct = top.percent != null ? `${top.percent}%` : null;
  return pct
    ? t('organizer.analytics.topProductCategoryPct', { label: top.label, pct })
    : t('organizer.analytics.topProductCategory', { label: top.label });
});

const scrollToFinance = () => {
  financeSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
  financeMetric.value = 'amount';
};

const showPaymentBreakdown = () => {
  financeSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
  financeMetric.value = 'count';
};

const goToBookings = ({ status } = {}) => {
  if (!selectedEventId.value) return;
  try {
    sessionStorage.setItem('cmart.bookings.preselectEventId', String(selectedEventId.value));
    if (status) {
      sessionStorage.setItem('cmart.bookings.preselectStatus', status);
    } else {
      sessionStorage.removeItem('cmart.bookings.preselectStatus');
    }
  } catch {
    /* ignore */
  }
  router.push({ path: '/admin', hash: '#bookings' });
};

const kpiSurveyValue = computed(() => {
  if (!surveyIncluded.value) return t('organizer.analytics.excluded');
  if (surveyReady.value) return String(respondentCount.value ?? 0);
  if (surveyMissing.value) return t('organizer.analytics.noCsv');
  if (surveyDegraded.value) return t('organizer.analytics.unavailable');
  return t('organizer.analytics.unavailable');
});

const kpiBookingsValue = computed(() => {
  if (!systemIncluded.value) return t('organizer.analytics.excluded');
  if (!operationalReady.value) return t('organizer.analytics.unavailable');
  return String(approvedCount.value ?? 0);
});

const overviewKpis = computed(() => [
  {
    id: 'survey_respondents',
    label: t('organizer.analytics.kpiSurveyRespondents'),
    value: kpiSurveyValue.value,
    note: surveyReady.value
      ? t('organizer.analytics.kpiViewSurveyResults')
      : (surveyIncluded.value ? (overview.value?.survey?.message || t('organizer.analytics.kpiNoSurveyCsv')) : t('organizer.analytics.kpiHiddenByMode')),
    title: t('organizer.analytics.kpiOpenSurveyResults'),
    clickable: true,
    onClick: () => setActiveTab(surveyReady.value ? 'survey-results' : 'data-sources'),
  },
  {
    id: 'approved_bookings',
    label: t('organizer.analytics.approvedBookings'),
    value: kpiBookingsValue.value,
    note: !systemIncluded.value
      ? t('organizer.analytics.kpiExcludedByMode')
      : (Number(approvedCount.value) ? t('organizer.analytics.kpiOpenBookings') : t('organizer.analytics.kpiZeroApproved')),
    title: t('organizer.analytics.kpiOpenBookingsTitle'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: () => goToBookings({ status: 'Approved' }),
  },
  {
    id: 'expected_revenue',
    label: t('organizer.analytics.kpiExpectedRevenue'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value ? t('organizer.analytics.unavailable') : `RM ${formatMoney(payments.value?.expected)}`),
    note: t('organizer.analytics.kpiPlatformFees'),
    title: t('organizer.analytics.kpiJumpFinance'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collected_revenue',
    label: t('organizer.analytics.kpiCollectedRevenue'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value ? t('organizer.analytics.unavailable') : `RM ${formatMoney(payments.value?.collected)}`),
    note: t('organizer.analytics.kpiPaidInvoices'),
    title: t('organizer.analytics.kpiJumpFinance'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'outstanding_revenue',
    label: t('organizer.analytics.kpiOutstandingRevenue'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value ? t('organizer.analytics.unavailable') : `RM ${formatMoney(payments.value?.outstanding)}`),
    note: t('organizer.analytics.kpiUnpaidInvoices'),
    title: t('organizer.analytics.kpiJumpFinance'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collection_rate',
    label: t('organizer.analytics.collectionRate'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value ? t('organizer.analytics.unavailable') : collectionRateLabel.value),
    note: hasInvoices.value
      ? t('organizer.analytics.kpiPaidFraction', { paid: payments.value?.paid_count ?? 0, total: payments.value?.invoice_count ?? 0 })
      : t('organizer.analytics.kpiAppearsAfterInvoices'),
    title: t('organizer.analytics.kpiShowPaymentBreakdown'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: showPaymentBreakdown,
  },
]);

const bookingStatusRows = computed(() => {
  const by = pipeline.value?.by_approval_status || {};
  const total = pipeline.value?.total_bookings || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: key.replace(/_/g, ' '),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? t('organizer.analytics.ofTotal', { count, total, pct: ((count / total) * 100).toFixed(1) }) : `${count}`,
  }));
});

const siteStatusRows = computed(() => {
  const by = sites.value?.by_operational_status || {};
  const total = sites.value?.total || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: key.replace(/_/g, ' '),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? t('organizer.analytics.ofTotal', { count, total, pct: ((count / total) * 100).toFixed(1) }) : `${count}`,
  }));
});

const formatMoney = (value) => {
  if (value == null || value === '') return '0.00';
  const n = Number(value);
  if (Number.isNaN(n)) return '0.00';
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (value) => {
  if (!value) return 'Unknown';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const formatDateRange = (start, end) => {
  if (!start && !end) return t('organizer.analytics.datesNotSet');
  const fmt = (v) => {
    try {
      return new Date(v).toLocaleDateString();
    } catch {
      return v;
    }
  };
  if (start && end) return `${fmt(start)} – ${fmt(end)}`;
  return fmt(start || end);
};

const unwrapEvents = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
};

const loadEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await listCarbootEventsForAnalytics();
    events.value = unwrapEvents(data);
    if (selectedEventId.value) {
      const match = events.value.find((e) => String(e.id) === String(selectedEventId.value));
      if (match) setSelectedEvent(match);
    }
  } catch (e) {
    toast.error(e.response?.data?.message || t('organizer.analytics.unableLoadEvents'));
  } finally {
    loadingEvents.value = false;
  }
};

const loadOverview = async (recompute = false) => {
  if (!selectedEventId.value) {
    overview.value = null;
    return;
  }
  loadingOverview.value = true;
  overviewError.value = '';
  try {
    const { data } = recompute
      ? await recomputeEventAnalytics(selectedEventId.value)
      : await getEventAnalyticsOverview(selectedEventId.value);
    overview.value = data;
    if (currentEvent.value) setSelectedEvent(currentEvent.value);
  } catch (e) {
    overview.value = null;
    overviewError.value = e.response?.data?.message || t('organizer.analytics.unableLoadAnalytics');
  } finally {
    loadingOverview.value = false;
  }
};

const refreshAll = () => loadOverview(true);

const onDataSourceUpdated = (nextOverview) => {
  if (nextOverview) {
    overview.value = nextOverview;
    return;
  }
  loadOverview(true);
};

const goToReportCentre = () => {
  if (!selectedEventId.value) return;
  try {
    sessionStorage.setItem('cmart.reportCentre.preselectEventId', String(selectedEventId.value));
  } catch {
    /* ignore */
  }
  router.push({ path: '/admin', hash: '#report-centre' });
};

watch(selectedEventId, (id) => {
  setSelectedEventId(id);
  loadOverview(false);
});

onMounted(async () => {
  try {
    const redirectedTab = sessionStorage.getItem(ANALYTICS_HUB_TAB_STORAGE_KEY);
    if (redirectedTab) activeTab.value = normalizeTab(redirectedTab);
  } catch {
    /* ignore */
  }
  await loadEvents();
  if (selectedEventId.value) await loadOverview(false);
});

defineExpose({
  refresh: refreshAll,
  setActiveTab,
});
</script>
