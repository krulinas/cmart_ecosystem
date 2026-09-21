# -*- coding: utf-8 -*-
"""Wire analytics child components to i18n keys."""
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


# ---------------------------------------------------------------------------
# AnalyticsDataSourceManager
# ---------------------------------------------------------------------------
dsm = ROOT / "components/analytics/AnalyticsDataSourceManager.vue"
apply(
    dsm,
    [
        (
            "import { computed, ref, watch } from 'vue';\nimport { useToast } from 'vue-toastification';",
            "import { computed, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { useToast } from 'vue-toastification';",
        ),
        ("const toast = useToast();", "const { t } = useI18n();\nconst toast = useToast();"),
        ("Analytics source", "{{ t('organizer.analytics.dataSources.analyticsSource') }}"),
        (
            "Choose what Analytics Hub and new event reports include for this event.",
            "{{ t('organizer.analytics.dataSources.analyticsSourceHint') }}",
        ),
        (
            'aria-label="Analytics source mode"',
            ":aria-label=\"t('organizer.analytics.dataSources.sourceModeAria')\"",
        ),
        ("Current System Data", "{{ t('organizer.analytics.dataSources.currentSystemData') }}"),
        (
            "Bookings, payments, event spaces and reservations recorded in the system.",
            "{{ t('organizer.analytics.dataSources.currentSystemDataHint') }}",
        ),
        (
            "{{ systemIncluded ? 'Available' : 'Excluded by current mode' }}",
            "{{ systemIncluded ? t('organizer.analytics.dataSources.available') : t('organizer.analytics.dataSources.excludedByMode') }}",
        ),
        ("Current Survey CSV", "{{ t('organizer.analytics.dataSources.currentSurveyCsv') }}"),
        (">Filename</dt>", ">{{ t('organizer.analytics.dataSources.filename') }}</dt>"),
        (
            "{{ currentCsv.original_filename || 'Unknown file' }}",
            "{{ currentCsv.original_filename || t('organizer.analytics.dataSources.unknownFile') }}",
        ),
        (">Respondents</dt>", ">{{ t('organizer.analytics.dataSources.respondents') }}</dt>"),
        (">Imported</dt>", ">{{ t('organizer.analytics.dataSources.imported') }}</dt>"),
        (">Survey template</dt>", ">{{ t('organizer.analytics.dataSources.surveyTemplate') }}</dt>"),
        (
            "Vendor post-event · {{ currentCsv.schema_version || 'v1' }}",
            "{{ t('organizer.analytics.dataSources.vendorPostEvent', { version: currentCsv.schema_version || 'v1' }) }}",
        ),
        ("Replace CSV\n          </button>", "{{ t('organizer.analytics.dataSources.replaceCsv') }}\n          </button>"),
        ("Delete CSV Data\n          </button>", "{{ t('organizer.analytics.dataSources.deleteCsvData') }}\n          </button>"),
        (
            "No CSV data is connected to this event.",
            "{{ t('organizer.analytics.dataSources.noCsvConnected') }}",
        ),
        (
            "Supported file: CSV · Expected template: Vendor post-event survey",
            "{{ t('organizer.analytics.dataSources.supportedFile') }}",
        ),
        (
            "Choose a file to validate first. Nothing is imported until you confirm.",
            "{{ t('organizer.analytics.dataSources.chooseFileHint') }}",
        ),
        ("Choose CSV\n        </button>", "{{ t('organizer.analytics.dataSources.chooseCsv') }}\n        </button>"),
        ("View Survey Results\n        </button>", "{{ t('organizer.analytics.dataSources.viewSurveyResults') }}\n        </button>"),
        ("Remain in Data Sources\n        </button>", "{{ t('organizer.analytics.dataSources.remainInDataSources') }}\n        </button>"),
        ("Cancel\n          </button>", "{{ t('organizer.analytics.dataSources.cancel') }}\n          </button>"),
        (
            "{{ busy ? 'Working…' : (pending.confirmLabel || 'Confirm') }}",
            "{{ busy ? t('organizer.analytics.dataSources.working') : (pending.confirmLabel || t('organizer.analytics.dataSources.confirm')) }}",
        ),
        (
            """const modeOptions = [
  {
    value: 'system_only',
    label: 'System Data',
    hint: 'Bookings, payments, spaces and reservations only.',
  },
  {
    value: 'combined',
    label: 'System + Survey CSV',
    hint: 'Combine operational records with the active survey CSV.',
  },
  {
    value: 'csv_only',
    label: 'Survey CSV Only',
    hint: 'Show survey results only. System Data stays stored but hidden.',
  },
];""",
            """const modeOptions = computed(() => [
  {
    value: 'system_only',
    label: t('organizer.analytics.dataSources.modeSystem'),
    hint: t('organizer.analytics.dataSources.modeSystemHint'),
  },
  {
    value: 'combined',
    label: t('organizer.analytics.dataSources.modeCombined'),
    hint: t('organizer.analytics.dataSources.modeCombinedHint'),
  },
  {
    value: 'csv_only',
    label: t('organizer.analytics.dataSources.modeCsvOnly'),
    hint: t('organizer.analytics.dataSources.modeCsvOnlyHint'),
  },
]);""",
        ),
        ("if (!value) return 'Not available';", "if (!value) return t('organizer.analytics.dataSources.notAvailable');"),
        ("toast.success('Analytics source updated.');", "toast.success(t('organizer.analytics.dataSources.sourceUpdated'));"),
        (
            "error.value = e.response?.data?.message || 'Unable to update analytics source.';",
            "error.value = e.response?.data?.message || t('organizer.analytics.dataSources.unableUpdateSource');",
        ),
        (
            """    confirmLabel: 'Delete CSV Data',
    title: 'Delete CSV Data?',""",
            """    confirmLabel: t('organizer.analytics.dataSources.deleteCsvData'),
    title: t('organizer.analytics.dataSources.deleteTitle'),""",
        ),
        (
            "error.value = e.response?.data?.message || 'Unable to delete CSV survey data.';",
            "error.value = e.response?.data?.message || t('organizer.analytics.dataSources.unableDelete');",
        ),
        (
            """      confirmLabel: 'Replace CSV',
      title: 'Replace CSV?',
      body: 'This will permanently replace the current CSV and its responses. This cannot be undone.',""",
            """      confirmLabel: t('organizer.analytics.dataSources.replaceCsv'),
      title: t('organizer.analytics.dataSources.replaceTitle'),
      body: t('organizer.analytics.dataSources.replaceBody'),""",
        ),
        (
            "info.value = replaceExisting ? 'Survey CSV replaced.' : 'Survey CSV imported.';",
            "info.value = replaceExisting ? t('organizer.analytics.dataSources.csvReplaced') : t('organizer.analytics.dataSources.csvImported');",
        ),
        (
            "info.value = 'This CSV is already the active survey for this event. No duplicate responses were added.';",
            "info.value = t('organizer.analytics.dataSources.duplicateCsv');",
        ),
        (
            """        confirmLabel: 'Replace CSV',
        title: 'Replace CSV?',
        body: 'This will permanently replace the current CSV and its responses. This cannot be undone.',""",
            """        confirmLabel: t('organizer.analytics.dataSources.replaceCsv'),
        title: t('organizer.analytics.dataSources.replaceTitle'),
        body: t('organizer.analytics.dataSources.replaceBody'),""",
        ),
        (
            "error.value = payload.message || 'Survey import failed. The previous dataset was left unchanged.';",
            "error.value = payload.message || t('organizer.analytics.dataSources.importFailed');",
        ),
    ],
    "DataSourceManager",
)

# Fix delete body construction - read and patch
dst = dsm.read_text(encoding="utf-8")
# Find confirmDelete body array construction
old_body = None
import re
m = re.search(
    r"body:\s*\[\s*'Event:.*?\],",
    dst,
    re.S,
)
if m:
    dst = dst[: m.start()] + "body: t('organizer.analytics.dataSources.deleteBody', {\n      event: props.eventTitle || t('organizer.analytics.dataSources.thisEvent'),\n      filename: currentCsv.value?.original_filename || t('organizer.analytics.dataSources.theCurrentCsv'),\n      n: currentCsv.value?.respondent_count ?? currentCsv.value?.valid_row_count ?? 0,\n    })," + dst[m.end() :]
    dsm.write_text(dst, encoding="utf-8")
    print("DataSourceManager deleteBody patched")
else:
    print("  WARN: delete body pattern not found; checking manually")
    # try alternate
    if "This permanently deletes the CSV-imported" in dst:
        print("  still has English delete body fragments")


# ---------------------------------------------------------------------------
# SurveyResultsPanel
# ---------------------------------------------------------------------------
srp = ROOT / "components/analytics/SurveyResultsPanel.vue"
apply(
    srp,
    [
        (
            "import { computed, ref } from 'vue';\nimport AnalyticsDataSourceBadge from './AnalyticsDataSourceBadge.vue';",
            "import { computed, ref } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport AnalyticsDataSourceBadge from './AnalyticsDataSourceBadge.vue';",
        ),
        (
            "defineEmits(['open-data-sources']);\n\nconst metricMode = ref('count');",
            "defineEmits(['open-data-sources']);\n\nconst { t } = useI18n();\nconst metricMode = ref('count');",
        ),
        (
            "Quantitative vendor survey results (Q1–Q13)",
            "{{ t('organizer.analytics.survey.quantitativeLead') }}",
        ),
        (
            'aria-label="Chart metric"',
            ":aria-label=\"t('organizer.analytics.survey.chartMetricAria')\"",
        ),
        ("Count\n        </button>", "{{ t('organizer.analytics.survey.count') }}\n        </button>"),
        ("Percentage\n        </button>", "{{ t('organizer.analytics.survey.percentage') }}\n        </button>"),
        ("Upload Survey CSV\n      </button>", "{{ t('organizer.analytics.survey.uploadSurveyCsv') }}\n      </button>"),
        ("Vendor and selling profile", "{{ t('organizer.analytics.survey.sectionVendorProfile') }}"),
        (
            "Product categories, sales purpose, and information sources",
            "{{ t('organizer.analytics.survey.sectionVendorProfileHint') }}",
        ),
        ('title="Product categories"', ":title=\"t('organizer.analytics.survey.productCategories')\""),
        ('subtitle="Multi-select · ranked by frequency"', ":subtitle=\"t('organizer.analytics.survey.productCategoriesSub')\""),
        ('empty-text="No product category selections yet."', ":empty-text=\"t('organizer.analytics.survey.productCategoriesEmpty')\""),
        ('title="Sales purpose"', ":title=\"t('organizer.analytics.survey.salesPurpose')\""),
        ('subtitle="Single choice · composition of answered responses"', ":subtitle=\"t('organizer.analytics.survey.salesPurposeSub')\""),
        ('empty-text="No sales purpose responses yet."', ":empty-text=\"t('organizer.analytics.survey.salesPurposeEmpty')\""),
        ('title="Event information sources"', ":title=\"t('organizer.analytics.survey.eventInfoSources')\""),
        ('subtitle="Multi-select · totals may exceed 100%"', ":subtitle=\"t('organizer.analytics.survey.eventInfoSourcesSub')\""),
        ('empty-text="No event information source selections yet."', ":empty-text=\"t('organizer.analytics.survey.eventInfoSourcesEmpty')\""),
        ("Sales outcomes", "{{ t('organizer.analytics.survey.sectionSalesOutcomes') }}"),
        (
            "Self-reported bands only — not exact RM totals",
            "{{ t('organizer.analytics.survey.sectionSalesOutcomesHint') }}",
        ),
        ('title="Gross sales bands"', ":title=\"t('organizer.analytics.survey.grossSalesBands')\""),
        ('subtitle="Self-reported categorical bands · not exact RM"', ":subtitle=\"t('organizer.analytics.survey.grossSalesBandsSub')\""),
        ('empty-text="No gross sales band responses yet."', ":empty-text=\"t('organizer.analytics.survey.grossSalesBandsEmpty')\""),
        ('title="Used-item sell-through"', ":title=\"t('organizer.analytics.survey.usedItemSellThrough')\""),
        ('subtitle="Ordered questionnaire bands for used goods"', ":subtitle=\"t('organizer.analytics.survey.usedItemSellThroughSub')\""),
        ('empty-text="No sell-through responses yet."', ":empty-text=\"t('organizer.analytics.survey.usedItemSellThroughEmpty')\""),
        ("Items and reuse", "{{ t('organizer.analytics.survey.sectionItemsReuse') }}"),
        (
            "Item conditions, unsold actions, and reuse proxies",
            "{{ t('organizer.analytics.survey.sectionItemsReuseHint') }}",
        ),
        ('title="Item conditions"', ":title=\"t('organizer.analytics.survey.itemConditions')\""),
        # productCategoriesSub already used for multi-select ranked - reuse same key for item conditions sub
        ('empty-text="No item condition selections yet."', ":empty-text=\"t('organizer.analytics.survey.itemConditionsEmpty')\""),
        ('title="Unsold-item actions"', ":title=\"t('organizer.analytics.survey.unsoldActions')\""),
        ('subtitle="Multi-select · reuse and discard proxies"', ":subtitle=\"t('organizer.analytics.survey.unsoldActionsSub')\""),
        ('empty-text="No unsold-item action selections yet."', ":empty-text=\"t('organizer.analytics.survey.unsoldActionsEmpty')\""),
        ("Reuse / circularity proxies", "{{ t('organizer.analytics.survey.circularityProxies') }}"),
        ("Positive reuse actions:", "{{ t('organizer.analytics.survey.positiveReuseActions') }}"),
        ("Discarded:", "{{ t('organizer.analytics.survey.discarded') }}"),
        ("Experience and improvements", "{{ t('organizer.analytics.survey.sectionExperience') }}"),
        (
            "Ratings, difficulties, and improvement priorities",
            "{{ t('organizer.analytics.survey.sectionExperienceHint') }}",
        ),
        ('title="Experience rating"', ":title=\"t('organizer.analytics.survey.experienceRating')\""),
        ('subtitle="Ordered rating responses"', ":subtitle=\"t('organizer.analytics.survey.experienceRatingSub')\""),
        ('empty-text="No experience rating responses yet."', ":empty-text=\"t('organizer.analytics.survey.experienceRatingEmpty')\""),
        ('title="Improvement priorities"', ":title=\"t('organizer.analytics.survey.improvementPriorities')\""),
        ('empty-text="No improvement priority selections yet."', ":empty-text=\"t('organizer.analytics.survey.improvementPrioritiesEmpty')\""),
        ('title="Supporting activity attracted visitors"', ":title=\"t('organizer.analytics.survey.supportingAttracted')\""),
        ('subtitle="Single choice"', ":subtitle=\"t('organizer.analytics.survey.supportingAttractedSub')\""),
        ('empty-text="No supporting-activity responses yet."', ":empty-text=\"t('organizer.analytics.survey.supportingAttractedEmpty')\""),
        ('title="Supporting activity impacts"', ":title=\"t('organizer.analytics.survey.supportingImpacts')\""),
        ('empty-text="No supporting-activity impact selections yet."', ":empty-text=\"t('organizer.analytics.survey.supportingImpactsEmpty')\""),
        (
            "Vendor difficulties (registration / info)",
            "{{ t('organizer.analytics.survey.vendorDifficulties') }}",
        ),
        (
            """            Yes: <strong>{{ hasDifficulty.yes_display }}</strong>
            · No: <strong>{{ hasDifficulty.no_display }}</strong>""",
            "{{ t('organizer.analytics.survey.yesNo', { yes: hasDifficulty.yes_display, no: hasDifficulty.no_display }) }}",
        ),
        (
            "props.overview?.survey?.message || 'No CSV data is connected to this event.',",
            "props.overview?.survey?.message || t('organizer.analytics.survey.noCsvConnected'),",
        ),
    ],
    "SurveyResultsPanel",
)

# Fix remaining Multi-select · ranked by frequency for item conditions / improvement / supporting impacts
srt = srp.read_text(encoding="utf-8")
# After first productCategoriesSub replace, remaining static subtitles for itemConditions etc.
for old, new in [
    (
        'subtitle="Multi-select · ranked by frequency"',
        ":subtitle=\"t('organizer.analytics.survey.itemConditionsSub')\"",
    ),
]:
    # only first remaining occurrence → item conditions; then improvement; then supporting
    pass

# Count remaining static multi-select ranked
count = srt.count('subtitle="Multi-select · ranked by frequency"')
keys = [
    "organizer.analytics.survey.itemConditionsSub",
    "organizer.analytics.survey.improvementPrioritiesSub",
    "organizer.analytics.survey.supportingImpactsSub",
]
for key in keys:
    old = 'subtitle="Multi-select · ranked by frequency"'
    if old in srt:
        srt = srt.replace(old, f':subtitle="t(\'{key}\')"', 1)
        print(f"  patched subtitle -> {key}")
    else:
        print(f"  no more static multi-select for {key}")
srp.write_text(srt, encoding="utf-8")

print("survey panel done")


# ---------------------------------------------------------------------------
# EventCommentsWordCloud
# ---------------------------------------------------------------------------
ec = ROOT / "components/analytics/EventCommentsWordCloud.vue"
apply(
    ec,
    [
        (
            "import { computed, ref, watch } from 'vue';\nimport { getEventWordcloud } from '../../services/eventAnalyticsApi';",
            "import { computed, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { getEventWordcloud } from '../../services/eventAnalyticsApi';\n\nconst { t } = useI18n();",
        ),
        ("Vendor Comments", "{{ t('organizer.analytics.comments.title') }}"),
        (
            "What vendors wrote, grouped by question · no sentiment scoring",
            "{{ t('organizer.analytics.comments.lead') }}",
        ),
        (
            """          Substantive comments:
          <strong class="text-ink-900">{{ qualitative.substantive_count ?? 0 }}</strong>""",
            """          {{ t('organizer.analytics.comments.substantiveComments') }}
          <strong class="text-ink-900">{{ qualitative.substantive_count ?? 0 }}</strong>""",
        ),
        (
            """          Actionable suggestions:
          <strong class="text-ink-900">{{ qualitative.actionable_suggestion_count ?? 0 }}</strong>""",
            """          {{ t('organizer.analytics.comments.actionableSuggestions') }}
          <strong class="text-ink-900">{{ qualitative.actionable_suggestion_count ?? 0 }}</strong>""",
        ),
        ("Vendor Survey CSV", "{{ t('organizer.analytics.comments.vendorSurveyCsv') }}"),
        (
            "No substantive responses in this group.",
            "{{ t('organizer.analytics.comments.noSubstantiveInGroup') }}",
        ),
        ("Community Feedback", "{{ t('organizer.analytics.comments.communityFeedback') }}"),
        (
            "Event-scoped reviews only · hidden feedback excluded",
            "{{ t('organizer.analytics.comments.communityFeedbackHint') }}",
        ),
        ("Loading…", "{{ t('organizer.analytics.comments.loading') }}"),
        (
            "Community feedback is not yet linked to individual events. Vendor survey comments above still apply.",
            "{{ t('organizer.analytics.comments.feedbackNotLinked') }}",
        ),
        (
            "Vendor Product Descriptions",
            "{{ t('organizer.analytics.comments.productDescriptions') }}",
        ),
        (
            "Approved booking product details for this event",
            "{{ t('organizer.analytics.comments.productDescriptionsHint') }}",
        ),
        (
            """            productTerms.length
              ? 'More written responses are needed for a meaningful word cloud.'
              : 'No approved vendor product descriptions for this event yet.'""",
            """            productTerms.length
              ? t('organizer.analytics.comments.needMoreResponses')
              : t('organizer.analytics.comments.noProductDescriptions')""",
        ),
        (
            """const commentGroups = computed(() => [
  {
    key: 'operational_difficulties',
    label: 'Operational difficulties',
    items: groupsMap.value.operational_difficulties || [],
  },
  {
    key: 'improvement_suggestions',
    label: 'Improvement suggestions',
    items: groupsMap.value.improvement_suggestions || [],
  },
  {
    key: 'general_comments',
    label: 'General comments',
    items: groupsMap.value.general_comments || [],
  },
  {
    key: 'supporting_activity_impacts',
    label: 'Supporting-activity impacts',
    items: groupsMap.value.supporting_activity_impacts || [],
  },
  {
    key: 'other_responses',
    label: 'Other responses',
    items: groupsMap.value.other_responses || [],
  },
]);""",
            """const commentGroups = computed(() => [
  {
    key: 'operational_difficulties',
    label: t('organizer.analytics.comments.operationalDifficulties'),
    items: groupsMap.value.operational_difficulties || [],
  },
  {
    key: 'improvement_suggestions',
    label: t('organizer.analytics.comments.improvementSuggestions'),
    items: groupsMap.value.improvement_suggestions || [],
  },
  {
    key: 'general_comments',
    label: t('organizer.analytics.comments.generalComments'),
    items: groupsMap.value.general_comments || [],
  },
  {
    key: 'supporting_activity_impacts',
    label: t('organizer.analytics.comments.supportingActivityImpacts'),
    items: groupsMap.value.supporting_activity_impacts || [],
  },
  {
    key: 'other_responses',
    label: t('organizer.analytics.comments.otherResponses'),
    items: groupsMap.value.other_responses || [],
  },
]);""",
        ),
        (
            "return 'Survey comments are hidden because the current source mode excludes Survey CSV.';",
            "return t('organizer.analytics.comments.hiddenByMode');",
        ),
        (
            "return 'No CSV data is connected to this event.';",
            "return t('organizer.analytics.comments.noCsvConnected');",
        ),
        (
            "return 'No substantive vendor survey comments for this event.';",
            "return t('organizer.analytics.comments.noSubstantiveComments');",
        ),
        (
            "return 'Event-linked community feedback is not available yet.';",
            "return t('organizer.analytics.comments.eventLinkedUnavailable');",
        ),
        (
            "return 'More written responses are needed for a meaningful word cloud.';",
            "return t('organizer.analytics.comments.needMoreResponses');",
        ),
        (
            "return 'No event-linked community feedback text for this event.';",
            "return t('organizer.analytics.comments.noFeedbackText');",
        ),
        (
            "feedbackUnavailableReason.value = 'Community feedback is not linked to events in this environment.';",
            "feedbackUnavailableReason.value = t('organizer.analytics.comments.feedbackNotLinkedEnv');",
        ),
        (
            "feedbackError.value = msg || 'Unable to load event-scoped feedback themes.';",
            "feedbackError.value = msg || t('organizer.analytics.comments.unableLoadFeedback');",
        ),
        (
            "|| 'Unable to load vendor product themes for this event.';",
            "|| t('organizer.analytics.comments.unableLoadProducts');",
        ),
    ],
    "EventCommentsWordCloud",
)


# ---------------------------------------------------------------------------
# AnalyticsDataSourceBadge
# ---------------------------------------------------------------------------
badge = ROOT / "components/analytics/AnalyticsDataSourceBadge.vue"
apply(
    badge,
    [
        (
            "import { computed } from 'vue';",
            "import { computed } from 'vue';\nimport { useI18n } from 'vue-i18n';\n\nconst { t } = useI18n();",
        ),
        (
            'title="No analytics sources available for this event"',
            ":title=\"t('organizer.analytics.badge.noDataTitle')\"",
        ),
        ("No Data", "{{ t('organizer.analytics.badge.noData') }}"),
        (
            'title="Overview combines System Data and CSV survey responses"',
            ":title=\"t('organizer.analytics.badge.mixedTitle')\"",
        ),
        ("Mixed Sources", "{{ t('organizer.analytics.badge.mixedSources') }}"),
        (
            "CSV: {{ source.original_filename || 'survey file' }}",
            "{{ t('organizer.analytics.badge.csvPrefix', { filename: source.original_filename || t('organizer.analytics.badge.surveyFile') }) }}",
        ),
        (
            """        <template v-else>
          System Data
        </template>""",
            """        <template v-else>
          {{ t('organizer.analytics.badge.systemData') }}
        </template>""",
        ),
        ("(excluded)", "{{ t('organizer.analytics.badge.excluded') }}"),
        (
            "source.original_filename || 'CSV import',",
            "source.original_filename || t('organizer.analytics.badge.csvImport'),",
        ),
        (
            "source.batch_id != null ? `batch #${source.batch_id}` : null,",
            "source.batch_id != null ? t('organizer.analytics.badge.batch', { id: source.batch_id }) : null,",
        ),
        (
            "source.imported_at ? `imported ${formatDate(source.imported_at)}` : null,",
            "source.imported_at ? t('organizer.analytics.badge.imported', { date: formatDate(source.imported_at) }) : null,",
        ),
        ("'System Database',", "t('organizer.analytics.badge.systemDatabase'),"),
        (
            "source.updated_at ? `updated ${formatDate(source.updated_at)}` : null,",
            "source.updated_at ? t('organizer.analytics.badge.updated', { date: formatDate(source.updated_at) }) : null,",
        ),
    ],
    "DataSourceBadge",
)

# Chart defaults
sdc = ROOT / "components/analytics/SurveyDistributionChart.vue"
apply(
    sdc,
    [
        (
            "emptyText: { type: String, default: 'No responses for this question yet.' },",
            "emptyText: { type: String, default: '' },",
        ),
    ],
    "SurveyDistributionChart",
)

abl = ROOT / "components/analytics/AnalyticsBarList.vue"
apply(
    abl,
    [
        (
            "emptyText: { type: String, default: 'No data for this distribution.' },",
            "emptyText: { type: String, default: '' },",
        ),
    ],
    "AnalyticsBarList",
)

print("all child analytics components done")
