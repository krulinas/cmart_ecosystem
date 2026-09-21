# -*- coding: utf-8 -*-
"""Wire Analytics Hub + survey/data-source/comments components to i18n keys."""
from pathlib import Path

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend\src")


def apply(path: Path, pairs, label):
    text = path.read_text(encoding="utf-8")
    ok = miss = 0
    for old, new in pairs:
        if old in text:
            text = text.replace(old, new)
            ok += 1
        else:
            miss += 1
            print(f"  MISS [{label}]: {old[:100]!r}")
    path.write_text(text, encoding="utf-8")
    print(f"{label}: {ok}/{ok+miss}")
    return text


# ===========================================================================
# OrganizerEventAnalyticsPanel
# ===========================================================================
ap = ROOT / "views/dashboards/organizer/OrganizerEventAnalyticsPanel.vue"
apply(
    ap,
    [
        (
            "import { computed, onMounted, ref, watch } from 'vue';\nimport { useRouter } from 'vue-router';\nimport { useToast } from 'vue-toastification';",
            "import { computed, onMounted, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { useRouter } from 'vue-router';\nimport { useToast } from 'vue-toastification';",
        ),
        (
            "const toast = useToast();\nconst router = useRouter();",
            "const { t } = useI18n();\nconst toast = useToast();\nconst router = useRouter();",
        ),
        (
            """const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'survey-results', label: 'Feedback Summary' },
  { id: 'comments', label: 'Vendor Feedback' },
  { id: 'operations', label: 'Operations' },
  { id: 'data-sources', label: 'Data Sources' },
];""",
            """const tabs = computed(() => [
  { id: 'overview', label: t('organizer.analytics.tabOverview') },
  { id: 'survey-results', label: t('organizer.analytics.tabFeedbackSummary') },
  { id: 'comments', label: t('organizer.analytics.tabVendorFeedback') },
  { id: 'operations', label: t('organizer.analytics.tabOperations') },
  { id: 'data-sources', label: t('organizer.analytics.tabDataSources') },
]);""",
        ),
        (
            "const TAB_IDS = new Set(tabs.map((t) => t.id));",
            "const TAB_IDS = new Set(['overview', 'survey-results', 'comments', 'operations', 'data-sources']);",
        ),
        # header / chrome
        ("Analytics Hub", "{{ t('organizer.analytics.hubEyebrow') }}"),
        (
            "{{ currentEvent?.title || 'Select an event' }}",
            "{{ currentEvent?.title || t('organizer.analytics.selectEventTitle') }}",
        ),
        (
            "<span>Status: <strong class=\"text-ink-800\">{{ currentEvent.status || 'Unknown' }}</strong></span>",
            "<span>{{ t('organizer.analytics.statusLabel') }} <strong class=\"text-ink-800\">{{ currentEvent.status || t('organizer.analytics.unknown') }}</strong></span>",
        ),
        (
            "<span v-if=\"overview?.computed_at\">Updated {{ formatDate(overview.computed_at) }}</span>",
            "<span v-if=\"overview?.computed_at\">{{ t('organizer.analytics.updatedAt', { date: formatDate(overview.computed_at) }) }}</span>",
        ),
        (
            "<span v-if=\"sourceModeLabel\">Source: <strong class=\"text-ink-800\">{{ sourceModeLabel }}</strong></span>",
            "<span v-if=\"sourceModeLabel\">{{ t('organizer.analytics.sourceLabel') }} <strong class=\"text-ink-800\">{{ sourceModeLabel }}</strong></span>",
        ),
        (
            '<span class="mb-1 block text-[11px] font-semibold uppercase text-ink-500">Event</span>',
            '<span class="mb-1 block text-[11px] font-semibold uppercase text-ink-500">{{ t(\'organizer.analytics.event\') }}</span>',
        ),
        (
            '<option value="">Select an event…</option>',
            '<option value="">{{ t(\'organizer.analytics.selectEventOption\') }}</option>',
        ),
        (
            "{{ loadingOverview ? 'Refreshing…' : 'Refresh' }}",
            "{{ loadingOverview ? t('organizer.analytics.refreshing') : t('organizer.analytics.refresh') }}",
        ),
        ("Generate Event Report", "{{ t('organizer.analytics.generateReport') }}"),
        (
            "Select an event to view event-scoped analytics.",
            "{{ t('organizer.analytics.selectEventPrompt') }}",
        ),
        ("Loading analytics…", "{{ t('organizer.analytics.loading') }}"),
        (
            'aria-label="Analytics sections"',
            ":aria-label=\"t('organizer.analytics.sectionsAria')\"",
        ),
        (
            "Survey analytics temporarily unavailable.\n          {{ overview?.survey?.message || 'Operational metrics below remain usable where available.' }}",
            "{{ t('organizer.analytics.surveyUnavailable') }}\n          {{ overview?.survey?.message || t('organizer.analytics.surveyUnavailableFallback') }}",
        ),
        (
            "Small sample: n = {{ overview.survey.respondent_count }}\n          (threshold {{ overview.survey.small_sample_threshold }}). Interpret percentages carefully.",
            "{{ t('organizer.analytics.smallSample', { n: overview.survey.respondent_count, threshold: overview.survey.small_sample_threshold }) }}",
        ),
        ("Add Survey Data", "{{ t('organizer.analytics.addSurveyData') }}"),
        (
            "Optional legacy CSV remains available under Data Sources.",
            "{{ t('organizer.analytics.addSurveyDataHint') }}",
        ),
        ("Event performance", "{{ t('organizer.analytics.eventPerformance') }}"),
        (
            "Selected event only · open sites vs sites sold",
            "{{ t('organizer.analytics.eventPerformanceHint') }}",
        ),
        (">Approved bookings</dt>", ">{{ t('organizer.analytics.approvedBookings') }}</dt>"),
        (">Unique approved vendors</dt>", ">{{ t('organizer.analytics.uniqueApprovedVendors') }}</dt>"),
        (">Open booking sites</dt>", ">{{ t('organizer.analytics.openBookingSites') }}</dt>"),
        (">Sites sold</dt>", ">{{ t('organizer.analytics.sitesSold') }}</dt>"),
        (">Available sites</dt>", ">{{ t('organizer.analytics.availableSites') }}</dt>"),
        (">Site utilisation</dt>", ">{{ t('organizer.analytics.siteUtilisation') }}</dt>"),
        (">Feedback responses</dt>", ">{{ t('organizer.analytics.feedbackResponses') }}</dt>"),
        (">Average rating</dt>", ">{{ t('organizer.analytics.averageRating') }}</dt>"),
        (">Item reservations</dt>", ">{{ t('organizer.analytics.itemReservations') }}</dt>"),
        (
            "Physical sites ({{ eventPerformance?.physical_sites ?? sites?.total ?? '—' }}) are layout capacity, not sites sold.",
            "{{ t('organizer.analytics.physicalSitesNote', { count: eventPerformance?.physical_sites ?? sites?.total ?? '—' }) }}",
        ),
        ("Booking revenue", "{{ t('organizer.analytics.bookingRevenue') }}"),
        (
            "Frozen booking price snapshots for this event",
            "{{ t('organizer.analytics.bookingRevenueHint') }}",
        ),
        (">Expected booking revenue</dt>", ">{{ t('organizer.analytics.expectedBookingRevenue') }}</dt>"),
        (">Invoiced amount</dt>", ">{{ t('organizer.analytics.invoicedAmount') }}</dt>"),
        (">Collected revenue</dt>", ">{{ t('organizer.analytics.collectedRevenue') }}</dt>"),
        (">Outstanding invoice balance</dt>", ">{{ t('organizer.analytics.outstandingInvoiceBalance') }}</dt>"),
        (">Unbilled booking value</dt>", ">{{ t('organizer.analytics.unbilledBookingValue') }}</dt>"),
        (">Collection rate</dt>", ">{{ t('organizer.analytics.collectionRate') }}</dt>"),
        (">Avg / approved vendor</dt>", ">{{ t('organizer.analytics.avgPerApprovedVendor') }}</dt>"),
        (">Avg / site sold</dt>", ">{{ t('organizer.analytics.avgPerSiteSold') }}</dt>"),
        (
            "No invoices have been issued. Collection rate is not available; unbilled booking value shows expected revenue not yet invoiced.",
            "{{ t('organizer.analytics.noInvoicesNote') }}",
        ),
        ("Payments excluded by source mode.", "{{ t('organizer.analytics.paymentsExcluded') }}"),
        ("Booking data unavailable for this event.", "{{ t('organizer.analytics.bookingDataUnavailable') }}"),
        ("No approved bookings for this event.", "{{ t('organizer.analytics.noApprovedBookings') }}"),
        ("Vendor category distribution", "{{ t('organizer.analytics.vendorCategoryDistribution') }}"),
        (
            "Primary metric: unique participating vendors",
            "{{ t('organizer.analytics.vendorCategoryHint') }}",
        ),
        (
            "{{ row.unique_vendors ?? row.count }} vendors",
            "{{ t('organizer.analytics.vendorsCount', { count: row.unique_vendors ?? row.count }) }}",
        ),
        (
            "No category recorded for approved bookings.",
            "{{ t('organizer.analytics.noCategoryRecorded') }}",
        ),
        ("Feedback Summary", "{{ t('organizer.analytics.feedbackSummary') }}"),
        (
            "In-app Feedback for this event · source: {{ inAppFeedback?.source_label || 'In-app Feedback' }}",
            "{{ t('organizer.analytics.feedbackSummaryHint', { source: inAppFeedback?.source_label || t('organizer.analytics.inAppFeedback') }) }}",
        ),
        (">Total responses</dt>", ">{{ t('organizer.analytics.totalResponses') }}</dt>"),
        (">Vendor</dt>", ">{{ t('organizer.analytics.vendor') }}</dt>"),
        (">Non-vendor</dt>", ">{{ t('organizer.analytics.nonVendor') }}</dt>"),
        (">Vendor response rate</dt>", ">{{ t('organizer.analytics.vendorResponseRate') }}</dt>"),
        ("Rating distribution", "{{ t('organizer.analytics.ratingDistribution') }}"),
        ("Participant types", "{{ t('organizer.analytics.participantTypes') }}"),
        ("Non-vendor comments", "{{ t('organizer.analytics.nonVendorComments') }}"),
        ("No non-vendor comments yet.", "{{ t('organizer.analytics.noNonVendorComments') }}"),
        (
            "{{ inAppFeedback?.message || 'No feedback has been submitted for this event yet.' }}",
            "{{ inAppFeedback?.message || t('organizer.analytics.noFeedbackYet') }}",
        ),
        ("Vendor Feedback", "{{ t('organizer.analytics.vendorFeedback') }}"),
        (
            "In-app Feedback · anonymized · this event only",
            "{{ t('organizer.analytics.vendorFeedbackHint') }}",
        ),
    ],
    "AnalyticsPanel-1",
)

# second pass for remaining analytics panel strings
apply(
    ap,
    [
        (
            "Operations are hidden because the current source mode excludes System Data.",
            "{{ t('organizer.analytics.operationsHidden') }}",
        ),
        (
            "{{ overview?.operational?.error || 'Operational snapshot unavailable for this event.' }}",
            "{{ overview?.operational?.error || t('organizer.analytics.operationalUnavailable') }}",
        ),
        ("Total bookings", "{{ t('organizer.analytics.totalBookings') }}"),
        ("Approved\n                </p>", "{{ t('organizer.analytics.approved') }}\n                </p>"),
        ("Sites / slots", "{{ t('organizer.analytics.sitesSlots') }}"),
        (
            "{{ reservations?.available === false ? 'Unavailable' : (reservations?.total ?? 0) }}",
            "{{ reservations?.available === false ? t('organizer.analytics.unavailable') : (reservations?.total ?? 0) }}",
        ),
        (
            'title="Bookings by approval status"',
            ":title=\"t('organizer.analytics.bookingsByApproval')\"",
        ),
        (
            'title="Sites by operational status"',
            ":title=\"t('organizer.analytics.sitesByOperational')\"",
        ),
        (
            'empty-text="No site layout data for this event."',
            ":empty-text=\"t('organizer.analytics.noSiteLayout')\"",
        ),
        (
            "<strong>Unavailable</strong>",
            "<strong>{{ t('organizer.analytics.unavailable') }}</strong>",
        ),
        (
            """case 'system_only': return 'System Data';
    case 'csv_only': return 'Survey CSV Only';
    case 'combined': return 'System + Survey CSV';""",
            """case 'system_only': return t('organizer.analytics.sourceSystemData');
    case 'csv_only': return t('organizer.analytics.sourceCsvOnly');
    case 'combined': return t('organizer.analytics.sourceCombined');""",
        ),
        ("if (!hasInvoices.value) return 'Not available';", "if (!hasInvoices.value) return t('organizer.analytics.notAvailable');"),
        (
            """  return pct
    ? `Top product category: ${top.label} (${pct} of respondents).`
    : `Top product category: ${top.label}.`;""",
            """  return pct
    ? t('organizer.analytics.topProductCategoryPct', { label: top.label, pct })
    : t('organizer.analytics.topProductCategory', { label: top.label });""",
        ),
        ("if (!surveyIncluded.value) return 'Excluded';", "if (!surveyIncluded.value) return t('organizer.analytics.excluded');"),
        ("if (surveyMissing.value) return 'No CSV';", "if (surveyMissing.value) return t('organizer.analytics.noCsv');"),
        ("if (surveyDegraded.value) return 'Unavailable';\n  return 'Unavailable';", "if (surveyDegraded.value) return t('organizer.analytics.unavailable');\n  return t('organizer.analytics.unavailable');"),
        ("if (!systemIncluded.value) return 'Excluded';\n  if (!operationalReady.value) return 'Unavailable';", "if (!systemIncluded.value) return t('organizer.analytics.excluded');\n  if (!operationalReady.value) return t('organizer.analytics.unavailable');"),
        (
            """const overviewKpis = computed(() => [
  {
    id: 'survey_respondents',
    label: 'Survey respondents',
    value: kpiSurveyValue.value,
    note: surveyReady.value
      ? 'View Survey Results'
      : (surveyIncluded.value ? (overview.value?.survey?.message || 'No survey CSV connected') : 'Hidden by source mode'),
    title: 'Open Survey Results',
    clickable: true,
    onClick: () => setActiveTab(surveyReady.value ? 'survey-results' : 'data-sources'),
  },
  {
    id: 'approved_bookings',
    label: 'Approved bookings',
    value: kpiBookingsValue.value,
    note: !systemIncluded.value
      ? 'Excluded by source mode'
      : (Number(approvedCount.value) ? 'Open bookings' : '0 approved bookings'),
    title: 'Open Bookings for this event',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: () => goToBookings({ status: 'Approved' }),
  },
  {
    id: 'expected_revenue',
    label: 'Expected platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.expected)}`),
    note: 'Platform fees',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'collected_revenue',
    label: 'Collected platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.collected)}`),
    note: 'Paid invoices',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },
  {
    id: 'outstanding_revenue',
    label: 'Outstanding platform revenue',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : `RM ${formatMoney(payments.value?.outstanding)}`),
    note: 'Unpaid invoices',
    title: 'Jump to financial performance',
    clickable: systemIncluded.value && operationalReady.value,
    onClick: scrollToFinance,
  },""",
            """const overviewKpis = computed(() => [
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
  },""",
        ),
    ],
    "AnalyticsPanel-2",
)

# third pass - collection rate KPI + dates + toasts + remaining ops
apply(
    ap,
    [
        (
            """    label: 'Collection rate',
    value: !systemIncluded.value
      ? 'Excluded'
      : (!operationalReady.value ? 'Unavailable' : collectionRateLabel.value),""",
            """    label: t('organizer.analytics.collectionRate'),
    value: !systemIncluded.value
      ? t('organizer.analytics.excluded')
      : (!operationalReady.value ? t('organizer.analytics.unavailable') : collectionRateLabel.value),""",
        ),
        ("? 'Appears after invoices'", "? t('organizer.analytics.kpiAppearsAfterInvoices')"),
        ("title: 'Show payment-status breakdown',", "title: t('organizer.analytics.kpiShowPaymentBreakdown'),"),
        ("if (!start && !end) return 'Dates not set';", "if (!start && !end) return t('organizer.analytics.datesNotSet');"),
        (
            "toast.error(e.response?.data?.message || 'Unable to load events.');",
            "toast.error(e.response?.data?.message || t('organizer.analytics.unableLoadEvents'));",
        ),
        (
            "overviewError.value = e.response?.data?.message || 'Unable to load event analytics.';",
            "overviewError.value = e.response?.data?.message || t('organizer.analytics.unableLoadAnalytics');",
        ),
        (
            "No operational records have been created for this event yet.",
            "{{ t('organizer.analytics.noOperationalRecords') }}",
        ),
        (
            'empty-text="0 bookings recorded for this event."',
            ":empty-text=\"t('organizer.analytics.zeroBookings')\"",
        ),
        ("Attendance / check-in:", "{{ t('organizer.analytics.attendanceCheckIn') }}"),
        (
            "Unavailable — event-level check-in totals are not aggregated in this hub yet.",
            "{{ t('organizer.analytics.attendanceUnavailable') }}",
        ),
        ("Vendor respondent", "{{ t('organizer.analytics.vendorRespondent') }}"),
        (
            "No vendor feedback has been submitted for this event yet.",
            "{{ t('organizer.analytics.noVendorFeedback') }}",
        ),
        # kpiPaidFraction if present
        (
            "`${payments.value?.paid_invoice_count ?? 0}/${payments.value?.invoice_count ?? 0} paid`",
            "t('organizer.analytics.kpiPaidFraction', { paid: payments.value?.paid_invoice_count ?? 0, total: payments.value?.invoice_count ?? 0 })",
        ),
    ],
    "AnalyticsPanel-3",
)

print("analytics panel done")
