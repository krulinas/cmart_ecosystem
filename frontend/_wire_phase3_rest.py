# Wire remaining Phase 3 panels to existing i18n keys. Preserves logic; only UI strings.
from pathlib import Path

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend\src")


def replace_many(path: Path, pairs: list[tuple[str, str]], label: str):
    text = path.read_text(encoding="utf-8")
    missing = []
    for old, new in pairs:
        if old not in text:
            missing.append(old[:80])
        else:
            text = text.replace(old, new)
    path.write_text(text, encoding="utf-8")
    print(f"{label}: {len(pairs) - len(missing)}/{len(pairs)} replacements")
    for m in missing[:8]:
        print(f"  MISS: {m!r}")


# --- StaffFeedbackPanel ---
fb = ROOT / "views/dashboards/staff/StaffFeedbackPanel.vue"
replace_many(
    fb,
    [
        (
            """        <h2 class="text-lg font-extrabold text-ink-900">Community Feedback Moderation</h2>
        <p class="text-sm text-ink-500">Includes hidden reviews. Public portal only shows visible entries.</p>
      </div>
      <button class="ml-btn-ghost shrink-0" @click="load" :disabled="loading">
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>""",
            """        <h2 class="text-lg font-extrabold text-ink-900">{{ t('staff.feedback.title') }}</h2>
        <p class="text-sm text-ink-500">{{ t('staff.feedback.subtitle') }}</p>
      </div>
      <button class="ml-btn-ghost shrink-0" @click="load" :disabled="loading">
        {{ loading ? t('staff.feedback.loading') : t('staff.feedback.refresh') }}
      </button>""",
        ),
        (
            """    <div class="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Feedback filters">""",
            """    <div class="mb-4 flex flex-wrap gap-2" role="tablist" :aria-label="t('staff.feedback.filtersAria')">""",
        ),
        (
            """    <div v-if="loading && !hasLoaded" class="text-center text-ink-500 py-10">Loading feedback…</div>""",
            """    <div v-if="loading && !hasLoaded" class="text-center text-ink-500 py-10">{{ t('staff.feedback.loadingList') }}</div>""",
        ),
        (
            """    <div v-else-if="!items.length" class="text-center text-ink-500 py-10">No feedback records match this filter.</div>""",
            """    <div v-else-if="!items.length" class="text-center text-ink-500 py-10">{{ t('staff.feedback.empty') }}</div>""",
        ),
        (
            """        :aria-label="`View feedback from ${item.user_name || 'community member'}`\"""",
            """        :aria-label="t('staff.feedback.viewAria', { name: item.user_name || t('staff.feedback.communityMember') })\"""",
        ),
        (
            """            <span class="font-bold text-ink-900">{{ item.user_name || 'Community Member' }}</span>""",
            """            <span class="font-bold text-ink-900">{{ item.user_name || t('staff.feedback.communityMember') }}</span>""",
        ),
        (
            """            {{ item.is_hidden ? 'Hidden' : 'Visible' }}""",
            """            {{ item.is_hidden ? t('staff.feedback.hidden') : t('staff.feedback.visible') }}""",
        ),
        (
            """            {{ item.reviewed_at ? 'Reviewed' : 'Unreviewed' }}""",
            """            {{ item.reviewed_at ? t('staff.feedback.reviewed') : t('staff.feedback.unreviewed') }}""",
        ),
        (
            """            {{ imageCount(item) }} photo{{ imageCount(item) === 1 ? '' : 's' }}""",
            """            {{ t('staff.feedback.photoCount', imageCount(item), { n: imageCount(item) }) }}""",
        ),
        (
            """          <span v-if="item.official_reply?.status === 'draft'" class="ml-badge text-[10px] bg-orange-100 text-orange-800">Reply Draft</span>
          <span v-if="item.official_reply?.status === 'published'" class="ml-badge text-[10px] bg-emerald-100 text-emerald-800">Reply Published</span>""",
            """          <span v-if="item.official_reply?.status === 'draft'" class="ml-badge text-[10px] bg-orange-100 text-orange-800">{{ t('staff.feedback.replyDraft') }}</span>
          <span v-if="item.official_reply?.status === 'published'" class="ml-badge text-[10px] bg-emerald-100 text-emerald-800">{{ t('staff.feedback.replyPublished') }}</span>""",
        ),
        (
            """          :aria-label="`View ${imageCount(item)} attached photo${imageCount(item) === 1 ? '' : 's'} from ${item.user_name}`\"""",
            """          :aria-label="t('staff.feedback.viewPhotosAria', imageCount(item), { n: imageCount(item), name: item.user_name || t('staff.feedback.communityMember') })\"""",
        ),
        (
            """            :alt="`Photo attachment from ${item.user_name || 'community member'}`\"""",
            """            :alt="t('staff.feedback.photoAlt', { name: item.user_name || t('staff.feedback.communityMember') })\"""",
        ),
        (
            """            {{ item.is_hidden ? 'Unhide' : 'Hide' }}""",
            """            {{ item.is_hidden ? t('staff.feedback.unhide') : t('staff.feedback.hide') }}""",
        ),
        (
            """            Mark Reviewed""",
            """            {{ t('staff.feedback.markReviewed') }}""",
        ),
        (
            """            Delete
          </button>
        </div>
      </article>""",
            """            {{ t('staff.feedback.delete') }}
          </button>
        </div>
      </article>""",
        ),
        (
            """            <h3 id="delete-feedback-title" class="text-lg font-bold text-ink-900">Delete feedback?</h3>
            <p class="mt-2 text-sm text-ink-600">
              Permanently delete feedback #{{ deleteConfirm.item?.id }}? This cannot be undone.
            </p>
            <div class="mt-6 flex justify-end gap-2">
              <button type="button" class="ml-btn-ghost" @click="cancelDelete">Cancel</button>
              <button type="button" class="ml-btn-danger" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? 'Deleting…' : 'Delete' }}
              </button>""",
            """            <h3 id="delete-feedback-title" class="text-lg font-bold text-ink-900">{{ t('staff.feedback.deleteTitle') }}</h3>
            <p class="mt-2 text-sm text-ink-600">
              {{ t('staff.feedback.deleteBody', { id: deleteConfirm.item?.id }) }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
              <button type="button" class="ml-btn-ghost" @click="cancelDelete">{{ t('staff.feedback.cancel') }}</button>
              <button type="button" class="ml-btn-danger" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? t('staff.feedback.deleting') : t('staff.feedback.delete') }}
              </button>""",
        ),
        (
            """import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import { resolveStorageUrl } from '../../../utils/imageUrl';
import { useManagementAccess } from '../../../composables/useManagementAccess';
import FeedbackDetailModal from '../../../components/management/FeedbackDetailModal.vue';
import ImageLightbox from '../../../components/management/ImageLightbox.vue';

const FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'visible', label: 'Visible' },
  { value: 'hidden', label: 'Hidden' },
  { value: 'unreviewed', label: 'Unreviewed' },
  { value: 'reviewed', label: 'Reviewed' },
  { value: 'with_photo', label: 'With Photo' },
  { value: 'low_rating', label: 'Low Rating' },
];

const toast = useToast();""",
            """import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import { resolveStorageUrl } from '../../../utils/imageUrl';
import { useManagementAccess } from '../../../composables/useManagementAccess';
import FeedbackDetailModal from '../../../components/management/FeedbackDetailModal.vue';
import ImageLightbox from '../../../components/management/ImageLightbox.vue';

const { t } = useI18n();

const FILTERS = computed(() => [
  { value: 'all', label: t('staff.feedback.filterAll') },
  { value: 'visible', label: t('staff.feedback.filterVisible') },
  { value: 'hidden', label: t('staff.feedback.filterHidden') },
  { value: 'unreviewed', label: t('staff.feedback.filterUnreviewed') },
  { value: 'reviewed', label: t('staff.feedback.filterReviewed') },
  { value: 'with_photo', label: t('staff.feedback.filterWithPhoto') },
  { value: 'low_rating', label: t('staff.feedback.filterLowRating') },
]);

const toast = useToast();""",
        ),
        (
            """const lightbox = ref({ open: false, url: null, images: [], startIndex: 0, alt: 'Photo attachment', caption: '' });""",
            """const lightbox = ref({ open: false, url: null, images: [], startIndex: 0, alt: '', caption: '' });""",
        ),
        (
            """    loadError.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load feedback for moderation.';""",
            """    loadError.value = e.forbiddenMessage || e.response?.data?.message || t('staff.feedback.loadError');""",
        ),
        (
            """    alt: `Photo attachment from ${caption || 'community member'}`,
    caption: caption ? `Photo attachment from ${caption}` : '',""",
            """    alt: t('staff.feedback.photoAlt', { name: caption || t('staff.feedback.communityMember') }),
    caption: caption ? t('staff.feedback.photoAttachmentCaption', { name: caption }) : '',""",
        ),
    ],
    "StaffFeedbackPanel template/setup",
)

# Toast strings in StaffFeedbackPanel
fb_text = fb.read_text(encoding="utf-8")
toast_pairs = [
    ("'Unable to remove attachment.'", "t('staff.feedback.unableRemoveAttachment')"),
    ("'Attachment removed.'", "t('staff.feedback.attachmentRemoved')"),
    ("'Unable to update visibility.'", "t('staff.feedback.unableUpdateVisibility')"),
    ("'Review unhidden.'", "t('staff.feedback.reviewUnhidden')"),
    ("'Review hidden from public portal.'", "t('staff.feedback.reviewHidden')"),
    ("'Unable to mark as reviewed.'", "t('staff.feedback.unableMarkReviewed')"),
    ("'Feedback marked as reviewed.'", "t('staff.feedback.markedReviewed')"),
    ("'Unable to save reply draft.'", "t('staff.feedback.unableSaveReply')"),
    ("'Official reply draft saved.'", "t('staff.feedback.replyDraftSaved')"),
    ("'Official reply removed.'", "t('staff.feedback.replyRemoved')"),
    ("'Unable to publish reply.'", "t('staff.feedback.unablePublishReply')"),
    ("'Official reply published.'", "t('staff.feedback.replyPublishedToast')"),
    ("'Unable to delete feedback.'", "t('staff.feedback.unableDelete')"),
    ("'Review deleted.'", "t('staff.feedback.reviewDeleted')"),
]
for old, new in toast_pairs:
    if old in fb_text:
        fb_text = fb_text.replace(old, new)
fb.write_text(fb_text, encoding="utf-8")
print("StaffFeedbackPanel toasts done")

print("OK feedback")
