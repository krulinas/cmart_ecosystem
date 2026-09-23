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
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
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

            <div class="grid gap-3 lg:grid-cols-2" data-testid="overview-visuals">
              <AnalyticsDoughnutChart
                v-if="siteUtilisationSegments.length"
                :title="t('organizer.analytics.siteUtilisationChart')"
                :subtitle="t('organizer.analytics.siteUtilisationChartHint')"
                :rows="siteUtilisationSegments"
                :center-value="eventPerformance?.site_utilisation_percent != null ? `${eventPerformance.site_utilisation_percent}%` : null"
                :center-label="t('organizer.analytics.utilised')"
                :colors="[palette.primary, palette.neutral]"
                :empty-text="t('organizer.analytics.utilisationChartEmpty')"
                test-id="overview-site-utilisation-doughnut"
              />
              <div
                v-else
                class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
              >
                {{ t('organizer.analytics.utilisationChartEmpty') }}
              </div>

              <AnalyticsDoughnutChart
                v-if="categoryChartType === 'doughnut'"
                :title="t('organizer.analytics.vendorCategorySystemTitle')"
                :subtitle="t('organizer.analytics.vendorCategorySystemHint')"
                :rows="vendorCategoryChartRows"
                :colors="compositionColors"
                :empty-text="t('organizer.analytics.categoryChartEmpty')"
                test-id="overview-vendor-category-doughnut"
              />
              <AnalyticsRankedBarChart
                v-else-if="categoryChartType === 'bar'"
                :title="t('organizer.analytics.vendorCategorySystemTitle')"
                :subtitle="t('organizer.analytics.vendorCategorySystemHint')"
                :rows="vendorCategoryChartRows"
                :color="palette.survey"
                :empty-text="t('organizer.analytics.noCategoryRecorded')"
                test-id="overview-vendor-category-bars"
              />
              <div
                v-else-if="categoryChartType === 'compact' && vendorCategoryChartRows.length === 1"
                class="rounded-xl border border-sky-100 bg-white p-3"
                data-testid="overview-vendor-category-compact"
              >
                <h4 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.vendorCategorySystemTitle') }}</h4>
                <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.vendorCategorySystemHint') }}</p>
                <p class="mt-3 text-sm font-semibold text-ink-800">
                  {{ vendorCategoryChartRows[0].label }} · {{ vendorCategoryChartRows[0].count }}
                </p>
              </div>
              <div
                v-else
                class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-600"
                data-testid="overview-vendor-category-empty"
              >
                {{ t('organizer.analytics.noCategoryRecorded') }}
              </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
              <AnalyticsStackedBarChart
                :title="t('organizer.analytics.revenueCollectionChart')"
                :subtitle="t('organizer.analytics.revenueCollectionHint')"
                :segments="revenueCollectionSegments"
                :colors="[palette.positive, palette.warning]"
                value-prefix="RM "
                :empty-text="t('organizer.analytics.revenueChartEmpty')"
                test-id="overview-revenue-stacked"
              />
              <AnalyticsDoughnutChart
                :title="t('organizer.analytics.bookingStatusChart')"
                :subtitle="t('organizer.analytics.bookingStatusHint')"
                :rows="bookingStatusChartRows"
                :colors="compositionColors"
                :empty-text="t('organizer.analytics.statusChartEmpty')"
                test-id="overview-booking-status-doughnut"
              />
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
                      <dd class="font-bold text-ink-900">{{ displayMoney(payments?.expected_booking_revenue ?? payments?.expected) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.invoicedAmount') }}</dt>
                      <dd class="font-bold text-ink-900">{{ displayMoney(payments?.invoiced_amount) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.collectedRevenue') }}</dt>
                      <dd class="font-bold text-emerald-700">{{ displayMoney(payments?.collected_revenue ?? payments?.collected) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.outstandingInvoiceBalance') }}</dt>
                      <dd class="font-bold text-rose-700">
                        {{ hasInvoices ? displayMoney(payments?.outstanding_invoice_balance ?? payments?.outstanding) : '—' }}
                      </dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.unbilledBookingValue') }}</dt>
                      <dd class="font-bold text-ink-900">{{ displayMoney(payments?.unbilled_booking_value) }}</dd>
                    </div>
                    <div>
                      <dt class="text-ink-500">{{ t('organizer.analytics.collectionRate') }}</dt>
                      <dd class="font-bold text-ink-900">{{ collectionRateLabel }}</dd>
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

              <PerformanceAcrossEventsPanel :event-id="selectedEventId" />
            </div>
          </template>
        </section>

        <!-- Vendor Insights: community feedback + survey CSV + themes (separate denominators) -->
        <section v-else-if="activeTab === 'vendor-insights'" class="space-y-6" data-testid="analytics-vendor-insights">
          <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="community-feedback-section">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.communityFeedbackTitle') }}</h3>
              <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900">
                {{ t('organizer.analytics.sourceBadgeCommunity') }}
              </span>
            </div>
            <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.communityFeedbackHint') }}</p>
            <template v-if="inAppFeedback?.available && Number(inAppFeedback.response_count) > 0">
              <dl class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.totalResponses') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.response_count }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.communityRatingLabel') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.average_rating ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.vendor') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.vendor_response_count }}</dd>
                </div>
                <div>
                  <dt class="text-ink-500">{{ t('organizer.analytics.nonVendor') }}</dt>
                  <dd class="font-bold text-ink-900">{{ inAppFeedback.non_vendor_response_count }}</dd>
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
              <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                  <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.vendorComments') }}</p>
                  <ul v-if="(inAppFeedback.anonymous_vendor_comments || []).length" class="mt-2 space-y-2">
                    <li
                      v-for="(item, idx) in inAppFeedback.anonymous_vendor_comments"
                      :key="`v-${idx}`"
                      class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2 text-sm"
                    >
                      <p class="text-xs font-semibold text-ink-500">
                        {{ t('organizer.analytics.vendorRespondent') }} · {{ item.rating }}★
                        <span v-if="item.submitted_at" class="font-normal"> · {{ formatDate(item.submitted_at) }}</span>
                      </p>
                      <p class="mt-1 whitespace-pre-line text-ink-800">{{ item.comments }}</p>
                    </li>
                  </ul>
                  <p v-else class="mt-2 text-sm text-ink-600">{{ t('organizer.analytics.noVendorFeedback') }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.nonVendorComments') }}</p>
                  <ul v-if="(inAppFeedback.anonymous_non_vendor_comments || []).length" class="mt-2 space-y-2">
                    <li
                      v-for="(item, idx) in inAppFeedback.anonymous_non_vendor_comments"
                      :key="`nv-${idx}`"
                      class="rounded-lg border border-ink-100 bg-ink-50/40 px-3 py-2 text-sm"
                    >
                      <p class="text-xs font-semibold text-ink-500">{{ item.author_label }} · {{ item.rating }}★</p>
                      <p class="mt-1 whitespace-pre-line text-ink-800">{{ item.comments }}</p>
                    </li>
                  </ul>
                  <p v-else class="mt-2 text-sm text-ink-600">{{ t('organizer.analytics.noNonVendorComments') }}</p>
                </div>
              </div>
            </template>
            <p v-else class="mt-3 text-sm text-ink-600">
              {{ inAppFeedback?.message || t('organizer.analytics.noFeedbackYet') }}
            </p>
          </div>

          <div data-testid="vendor-survey-results-section">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.vendorSurveyResultsTitle') }}</h3>
              <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-900">
                {{ t('organizer.analytics.sourceBadgeSurveyCsv') }}
              </span>
            </div>
            <p class="mb-3 text-xs text-ink-500">{{ t('organizer.analytics.vendorSurveyResultsHint') }}</p>
            <SurveyResultsPanel
              :overview="overview"
              :sources="dataSources"
              :respondent-count="respondentCount"
              :survey-empty="surveyEmpty"
              :show-add-csv-cta="showAddSurveyCta || csvOnlyOnboarding"
              @open-data-sources="setActiveTab('data-sources')"
            />
          </div>

          <div data-testid="vendor-comments-themes-section">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.vendorCommentsThemesTitle') }}</h3>
              <span class="inline-flex rounded-full border border-ink-200 bg-ink-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-700">
                {{ t('organizer.analytics.sourceBadgeMixedThemes') }}
              </span>
            </div>
            <p class="mb-3 text-xs text-ink-500">{{ t('organizer.analytics.vendorCommentsThemesHint') }}</p>
            <EventCommentsWordCloud
              :event-id="selectedEventId"
              :system-included="systemIncluded"
              :qualitative="qualitativeComments"
              :respondent-count="respondentCount"
              :feedback-link-ready="feedbackLinkReady"
              :survey-status="overview?.survey?.status || ''"
            />
          </div>
        </section>

        <!-- Operations: detailed operational breakdown (not identical Overview charts) -->
        <section v-else-if="activeTab === 'operations'" class="space-y-3" data-testid="analytics-operations">
          <p class="text-xs text-ink-500">
            <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900">
              {{ t('organizer.analytics.sourceBadgeSystem') }}
            </span>
            <span class="ml-2">{{ t('organizer.analytics.operationsDetailHint') }}</span>
          </p>
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
                <p class="mt-1 text-xl font-extrabold">{{ pipeline?.total_bookings != null ? pipeline.total_bookings : '—' }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.participatingVendorsSystem') }}</p>
                <p class="mt-1 text-xl font-extrabold">{{ resolveUniqueApprovedVendors(eventPerformance) ?? '—' }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.sitesSlots') }}</p>
                <p class="mt-1 text-xl font-extrabold">{{ sites?.total != null ? sites.total : '—' }}</p>
              </article>
              <article class="rounded-xl border border-sky-100 bg-white px-3 py-3">
                <p class="text-[11px] font-semibold uppercase text-ink-500">{{ t('organizer.analytics.itemReservations') }}</p>
                <p class="mt-1 text-xl font-extrabold">
                  {{ reservations?.available === false ? t('organizer.analytics.unavailable') : (reservations?.total != null ? reservations.total : '—') }}
                </p>
              </article>
            </div>

            <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="operations-booking-detail">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.bookingsByApproval') }}</h3>
              <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.operationsBookingDetailHint') }}</p>
              <table v-if="bookingStatusRows.length" class="mt-3 w-full text-left text-sm">
                <thead>
                  <tr class="text-[11px] uppercase tracking-wide text-ink-500">
                    <th class="py-1 font-semibold">{{ t('organizer.analytics.statusCol') }}</th>
                    <th class="py-1 font-semibold">{{ t('organizer.analytics.countCol') }}</th>
                    <th class="py-1 font-semibold">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in bookingStatusRows" :key="row.key" class="border-t border-ink-100">
                    <td class="py-1.5 font-semibold text-ink-800">{{ row.label }}</td>
                    <td class="py-1.5 tabular-nums text-ink-700">{{ row.count }}</td>
                    <td class="py-1.5 tabular-nums text-ink-500">{{ row.percent }}%</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="mt-3 text-sm text-ink-600">{{ t('organizer.analytics.zeroBookings') }}</p>
            </div>

            <div class="rounded-xl border border-sky-100 bg-white p-3" data-testid="operations-site-detail">
              <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.sitesByOperational') }}</h3>
              <p class="mt-0.5 text-xs text-ink-500">{{ t('organizer.analytics.operationsSiteDetailHint') }}</p>
              <table v-if="siteStatusRows.length" class="mt-3 w-full text-left text-sm">
                <thead>
                  <tr class="text-[11px] uppercase tracking-wide text-ink-500">
                    <th class="py-1 font-semibold">{{ t('organizer.analytics.statusCol') }}</th>
                    <th class="py-1 font-semibold">{{ t('organizer.analytics.countCol') }}</th>
                    <th class="py-1 font-semibold">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in siteStatusRows" :key="row.key" class="border-t border-ink-100">
                    <td class="py-1.5 font-semibold text-ink-800">{{ row.label }}</td>
                    <td class="py-1.5 tabular-nums text-ink-700">{{ row.count }}</td>
                    <td class="py-1.5 tabular-nums text-ink-500">{{ row.percent }}%</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="mt-3 text-sm text-ink-600">{{ t('organizer.analytics.noSiteLayout') }}</p>
            </div>

            <div class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-3 text-sm text-ink-600">
              {{ t('organizer.analytics.attendanceCheckIn') }}
              {{ t('organizer.analytics.attendanceUnavailable') }}
            </div>
          </template>
        </section>

        <!-- Data Sources -->
        <section v-else-if="activeTab === 'data-sources'" class="space-y-3" data-testid="analytics-data-sources-tab">
          <AnalyticsDataSourceManager
            :event-id="selectedEventId"
            :event-title="currentEvent?.title || ''"
            :overview="overview"
            @updated="onDataSourceUpdated"
            @view-survey-results="setActiveTab('vendor-insights')"
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
import AnalyticsDataSourceManager from '../../../components/analytics/AnalyticsDataSourceManager.vue';
import AnalyticsDoughnutChart from '../../../components/analytics/AnalyticsDoughnutChart.vue';
import AnalyticsRankedBarChart from '../../../components/analytics/AnalyticsRankedBarChart.vue';
import AnalyticsStackedBarChart from '../../../components/analytics/AnalyticsStackedBarChart.vue';
import EventCommentsWordCloud from '../../../components/analytics/EventCommentsWordCloud.vue';
import PerformanceAcrossEventsPanel from '../../../components/analytics/PerformanceAcrossEventsPanel.vue';
import SurveyResultsPanel from '../../../components/analytics/SurveyResultsPanel.vue';
import {
  displayMoney as formatDisplayMoney,
  operationalStatusLabel as resolveOperationalStatusLabel,
  resolveUniqueApprovedVendors,
} from '../../../utils/analyticsDisplay.js';
import { useEventAnalyticsContext } from '../../../composables/useEventAnalyticsContext';
import { ANALYTICS_HUB_TAB_STORAGE_KEY } from '../../../config/workspaceNav';
import {
  ANALYTICS_PALETTE,
  COMPOSITION_COLORS,
} from '../../../utils/analyticsChartPalette';
import { shouldUseDoughnutForCategories, resolveCategoryChartType } from '../../../utils/chartLifecycle';
import { formatLocaleDateTime, formatLocaleDate, formatLocaleNumber } from '../../../utils/localeFormat';
import {
  getEventAnalyticsOverview,
  listCarbootEventsForAnalytics,
  recomputeEventAnalytics,
} from '../../../services/eventAnalyticsApi';

const { t } = useI18n();
const toast = useToast();
const router = useRouter();
const { selectedEventId, setSelectedEvent, setSelectedEventId } = useEventAnalyticsContext();
const palette = ANALYTICS_PALETTE;
const compositionColors = COMPOSITION_COLORS;

const tabs = computed(() => [
  { id: 'overview', label: t('organizer.analytics.tabOverview') },
  { id: 'vendor-insights', label: t('organizer.analytics.tabVendorInsights') },
  { id: 'operations', label: t('organizer.analytics.tabOperations') },
  { id: 'data-sources', label: t('organizer.analytics.tabDataSources') },
]);

const TAB_IDS = new Set(['overview', 'vendor-insights', 'operations', 'data-sources']);

const LEGACY_TAB_MAP = {
  revenue: 'overview',
  overview: 'overview',
  operations: 'operations',
  'data-quality': 'data-sources',
  'data-sources': 'data-sources',
  'vendor-insights': 'vendor-insights',
  // Former Feedback Summary / Vendor Feedback tabs
  'survey-results': 'vendor-insights',
  comments: 'vendor-insights',
  vendors: 'vendor-insights',
  items: 'vendor-insights',
  experience: 'vendor-insights',
  'feedback-summary': 'vendor-insights',
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

const kpiParticipatingVendorsValue = computed(() => {
  if (!systemIncluded.value) return t('organizer.analytics.excluded');
  if (!operationalReady.value) return t('organizer.analytics.unavailable');
  const unique = resolveUniqueApprovedVendors(eventPerformance.value);
  if (unique == null) return t('organizer.analytics.notAvailable');
  return String(unique);
});

const displayMoney = (value) => formatDisplayMoney(value, formatLocaleNumber);

const overviewKpis = computed(() => [
  {
    id: 'survey_respondents',
    label: t('organizer.analytics.surveyRespondentsCsv'),
    value: kpiSurveyValue.value,
    note: surveyReady.value
      ? t('organizer.analytics.kpiViewSurveyResults')
      : (surveyIncluded.value ? (overview.value?.survey?.message || t('organizer.analytics.kpiNoSurveyCsv')) : t('organizer.analytics.kpiHiddenByMode')),
    title: t('organizer.analytics.kpiOpenSurveyResults'),
    clickable: true,
    onClick: () => setActiveTab(surveyReady.value ? 'vendor-insights' : 'data-sources'),
  },
  {
    id: 'participating_vendors',
    label: t('organizer.analytics.participatingVendorsSystem'),
    value: kpiParticipatingVendorsValue.value,
    note: !systemIncluded.value
      ? t('organizer.analytics.kpiExcludedByMode')
      : t('organizer.analytics.kpiUniqueVendorsNote'),
    title: t('organizer.analytics.kpiOpenApprovedBookingsTitle'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: () => goToBookings({ status: 'Approved' }),
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
  {
    id: 'outstanding_revenue',
    label: t('organizer.analytics.kpiOutstandingRevenue'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value || !hasInvoices.value
        ? t('organizer.analytics.notAvailable')
        : displayMoney(payments.value?.outstanding_invoice_balance ?? payments.value?.outstanding)),
    note: t('organizer.analytics.kpiUnpaidInvoices'),
    title: t('organizer.analytics.kpiJumpFinance'),
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
]);

const operationalStatusLabel = (key) => resolveOperationalStatusLabel(key, t);

const bookingStatusRows = computed(() => {
  const by = pipeline.value?.by_approval_status || {};
  const total = pipeline.value?.total_bookings || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: operationalStatusLabel(key),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? t('organizer.analytics.ofTotal', { count, total, pct: ((count / total) * 100).toFixed(1) }) : `${count}`,
  }));
});

const bookingStatusChartRows = computed(() => bookingStatusRows.value.map((row) => ({
  key: row.key,
  label: row.label,
  count: row.count,
  percent: row.percent,
})));

const siteUtilisationSegments = computed(() => {
  if (!systemIncluded.value || !operationalReady.value) return [];
  const sold = eventPerformance.value?.sites_sold;
  const open = eventPerformance.value?.open_booking_sites
    ?? sites.value?.open_booking_sites
    ?? null;
  if (sold == null || open == null) return [];
  const soldN = Number(sold);
  const openN = Number(open);
  if (Number.isNaN(soldN) || Number.isNaN(openN) || openN <= 0) return [];
  const remaining = Math.max(openN - soldN, 0);
  const utilPct = eventPerformance.value?.site_utilisation_percent;
  return [
    {
      key: 'sold',
      label: t('organizer.analytics.sitesOccupied'),
      count: soldN,
      percent: utilPct != null ? Number(utilPct) : (openN ? Math.round((soldN / openN) * 1000) / 10 : null),
    },
    {
      key: 'remaining',
      label: t('organizer.analytics.sitesRemaining'),
      count: remaining,
      percent: utilPct != null ? Math.round((100 - Number(utilPct)) * 10) / 10 : null,
    },
  ];
});

const vendorCategoryChartRows = computed(() => {
  const dist = vendorCategories.value?.distribution || [];
  return dist
    .map((row) => {
      const count = row.unique_vendors ?? row.count;
      if (count == null || count === '') return null;
      const n = Number(count);
      if (Number.isNaN(n) || n <= 0) return null;
      return {
        key: row.label,
        label: row.label,
        count: n,
        percent: row.vendor_percent != null ? Number(row.vendor_percent) : null,
      };
    })
    .filter(Boolean);
});

const categoryChartType = computed(() =>
  resolveCategoryChartType(vendorCategoryChartRows.value.length),
);

const useCategoryDoughnut = computed(() => categoryChartType.value === 'doughnut');

const revenueCollectionSegments = computed(() => {
  if (!systemIncluded.value || !operationalReady.value || !hasInvoices.value) return [];
  const collected = payments.value?.collected_revenue ?? payments.value?.collected;
  const outstanding = payments.value?.outstanding_invoice_balance ?? payments.value?.outstanding;
  if (collected == null && outstanding == null) return [];
  const segments = [];
  if (collected != null && collected !== '') {
    segments.push({
      key: 'collected',
      label: t('organizer.analytics.collectedSegment'),
      count: Number(collected),
    });
  }
  if (outstanding != null && outstanding !== '') {
    segments.push({
      key: 'outstanding',
      label: t('organizer.analytics.outstandingSegment'),
      count: Number(outstanding),
    });
  }
  return segments.filter((s) => !Number.isNaN(s.count));
});

const siteStatusRows = computed(() => {
  const by = sites.value?.by_operational_status || {};
  const total = sites.value?.total || 0;
  return Object.entries(by).map(([key, count]) => ({
    key,
    label: operationalStatusLabel(key),
    count,
    denominator: total,
    percent: total ? Math.round((count / total) * 1000) / 10 : 0,
    display: total ? t('organizer.analytics.ofTotal', { count, total, pct: ((count / total) * 100).toFixed(1) }) : `${count}`,
  }));
});

const formatDate = (value) => {
  if (!value) return 'Unknown';
  try {
    return formatLocaleDateTime(value, { dateStyle: 'medium', timeStyle: 'short' });
  } catch {
    return value;
  }
};

const formatDateRange = (start, end) => {
  if (!start && !end) return t('organizer.analytics.datesNotSet');
  const fmt = (v) => {
    try {
      return formatLocaleDate(v, { dateStyle: 'medium' });
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
