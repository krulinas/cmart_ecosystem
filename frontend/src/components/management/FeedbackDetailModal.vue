<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open && item"
        class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="feedback-detail-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" aria-hidden="true" @click="close" />

        <div
          class="relative z-10 flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl sm:rounded-2xl border border-ink-200 bg-white shadow-2xl"
          @click.stop
        >
          <header class="flex items-start justify-between gap-3 border-b border-ink-100 px-5 py-4">
            <div class="min-w-0">
              <h3 id="feedback-detail-title" class="text-lg font-extrabold text-ink-900 truncate">
                {{ t('staff.feedbackDetail.title', { id: item.id }) }}
              </h3>
              <p class="text-sm text-ink-500">{{ formatDate(item.created_at) }}</p>
            </div>
            <button
              type="button"
              class="ml-btn-ghost shrink-0 !px-2 !py-1"
              :aria-label="t('staff.feedbackDetail.closeAria')"
              @click="close"
            >
              ✕
            </button>
          </header>

          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <div class="flex flex-wrap gap-2">
              <span class="ml-badge bg-brand-100 text-brand-800">{{ item.user_name || t('staff.feedbackDetail.communityMember') }}</span>
              <span
                v-if="item.participation_type_label || item.role"
                class="ml-badge bg-ink-100 text-ink-700"
              >
                {{ item.participation_type_label || item.role }}
              </span>
              <span
                v-for="background in (item.community_background_labels || [])"
                :key="`detail-bg-${background}`"
                class="ml-badge bg-slate-100 text-slate-700"
              >
                {{ background }}
              </span>
              <span v-if="item.rating" class="ml-badge bg-amber-50 text-amber-800">
                {{ '★'.repeat(item.rating) }}{{ '☆'.repeat(5 - item.rating) }}
              </span>
              <span class="ml-badge" :class="item.is_hidden ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'">
                {{ item.is_hidden ? t('staff.feedbackDetail.hidden') : t('staff.feedbackDetail.visible') }}
              </span>
              <span class="ml-badge" :class="item.reviewed_at ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'">
                {{ item.reviewed_at ? t('staff.feedbackDetail.reviewed') : t('staff.feedbackDetail.unreviewed') }}
              </span>
              <span v-if="feedbackImages.length" class="ml-badge bg-violet-100 text-violet-800">
                {{ t('staff.feedbackDetail.photoCount', { n: feedbackImages.length }) }}
              </span>
              <span
                v-if="item.official_reply?.status === 'draft'"
                class="ml-badge bg-orange-100 text-orange-800"
              >
                {{ t('staff.feedbackDetail.replyDraft') }}
              </span>
              <span
                v-if="item.official_reply?.status === 'published'"
                class="ml-badge bg-emerald-100 text-emerald-800"
              >
                {{ t('staff.feedbackDetail.replyPublished') }}
              </span>
            </div>

            <p v-if="item.reviewed_at && item.reviewed_by_name" class="text-xs text-ink-500">
              {{ t('staff.feedbackDetail.reviewedBy', { name: item.reviewed_by_name, date: formatDate(item.reviewed_at) }) }}
            </p>

            <blockquote class="rounded-xl border border-ink-100 bg-ink-50/60 px-4 py-3 text-sm italic text-ink-700">
              "{{ item.comment || item.comments }}"
            </blockquote>

            <div v-if="feedbackImages.length" class="space-y-2">
              <p class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ t('staff.feedbackDetail.attachments') }}</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div
                  v-for="(image, index) in feedbackImages"
                  :key="image.id || `legacy-${index}`"
                  class="relative overflow-hidden rounded-xl border border-ink-200"
                >
                  <button
                    type="button"
                    class="block w-full overflow-hidden hover:ring-2 hover:ring-brand-500/20 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    @click="$emit('preview-image', feedbackImageUrls, item.user_name, index)"
                  >
                    <img
                      :src="image.image_url"
                      :alt="t('staff.feedbackDetail.photoAlt', { n: index + 1, name: item.user_name || t('staff.feedbackDetail.communityMember') })"
                      class="h-28 w-full object-cover bg-ink-50"
                      loading="lazy"
                    />
                  </button>
                  <button
                    v-if="canDeleteFeedback"
                    type="button"
                    class="absolute top-1 right-1 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
                    @click="$emit('remove-attachment', item, image)"
                  >
                    {{ t('staff.feedbackDetail.remove') }}
                  </button>
                </div>
              </div>
            </div>

            <section class="rounded-xl border border-brand-100 bg-brand-50/40 p-4 space-y-3">
              <h4 class="text-sm font-bold text-ink-900">{{ t('staff.feedbackDetail.officialReply') }}</h4>
              <p v-if="!canPublishOfficialReply" class="text-xs text-ink-500">
                {{ t('staff.feedbackDetail.managerApprovalRequired') }}
              </p>
              <p
                v-if="item.official_reply?.status === 'published' && !canPublishOfficialReply"
                class="text-xs text-amber-700"
              >
                {{ t('staff.feedbackDetail.publishedContactManager') }}
              </p>
              <textarea
                v-model="replyDraft"
                rows="4"
                class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none resize-none"
                :readonly="item.official_reply?.status === 'published' && !canPublishOfficialReply"
                :placeholder="t('staff.feedbackDetail.replyPlaceholder')"
              />
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="ml-btn-ghost text-sm"
                  :disabled="savingReply || (item.official_reply?.status === 'published' && !canPublishOfficialReply)"
                  @click="saveDraft"
                >
                  {{ savingReply ? t('staff.feedbackDetail.saving') : t('staff.feedbackDetail.saveDraft') }}
                </button>
                <button
                  v-if="canPublishOfficialReply"
                  type="button"
                  class="ml-btn-primary text-sm"
                  :disabled="publishingReply || !replyDraft.trim()"
                  @click="publishReply"
                >
                  {{ publishingReply ? t('staff.feedbackDetail.publishing') : t('staff.feedbackDetail.publishReply') }}
                </button>
              </div>
              <p v-if="item.official_reply?.status === 'published' && item.official_reply?.published_at" class="text-xs text-ink-500">
                {{ t('staff.feedbackDetail.publishedBy', { name: item.official_reply.by_name || 'CMart', date: formatDate(item.official_reply.published_at) }) }}
              </p>
            </section>
          </div>

          <footer class="flex flex-wrap gap-2 border-t border-ink-100 px-5 py-4 bg-ink-50/50">
            <button type="button" class="ml-btn-ghost text-sm" @click="$emit('toggle-hidden', item)">
              {{ item.is_hidden ? t('staff.feedbackDetail.unhide') : t('staff.feedbackDetail.hide') }}
            </button>
            <button
              v-if="!item.reviewed_at"
              type="button"
              class="ml-btn-primary text-sm !bg-sky-600 hover:!bg-sky-700 focus:!ring-sky-500"
              @click="$emit('mark-reviewed', item)"
            >
              {{ t('staff.feedbackDetail.markReviewed') }}
            </button>
            <button
              v-if="canDeleteFeedback"
              type="button"
              class="ml-btn-danger text-sm ml-auto"
              @click="$emit('request-delete', item)"
            >
              {{ t('staff.feedbackDetail.delete') }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { resolveStorageUrl } from '../../utils/imageUrl';
import { formatLocaleDateTime } from '../../utils/localeFormat';

const { t } = useI18n();

const props = defineProps({
  open: { type: Boolean, default: false },
  item: { type: Object, default: null },
  canDeleteFeedback: { type: Boolean, default: false },
  canPublishOfficialReply: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:open',
  'close',
  'toggle-hidden',
  'mark-reviewed',
  'request-delete',
  'preview-image',
  'remove-attachment',
  'save-reply-draft',
  'publish-reply',
]);

const replyDraft = ref('');
const savingReply = ref(false);
const publishingReply = ref(false);

const formatDate = (iso) => (iso ? formatLocaleDateTime(iso, { dateStyle: 'medium', timeStyle: 'short' }) : '');
const proofUrl = (item) => resolveStorageUrl(item?.proof_url || item?.media_path || null);
const feedbackImages = computed(() => {
  const item = props.item;
  if (Array.isArray(item?.images) && item.images.length) {
    return item.images
      .map((image) => ({
        ...image,
        image_url: resolveStorageUrl(image.image_url || image.image_path),
      }))
      .filter((image) => image.image_url);
  }

  const url = proofUrl(item);
  return url ? [{ id: null, image_url: url, is_legacy: true }] : [];
});
const feedbackImageUrls = computed(() => feedbackImages.value.map((image) => image.image_url));

const close = () => {
  emit('update:open', false);
  emit('close');
};

watch(
  () => [props.open, props.item],
  () => {
    if (props.open && props.item) {
      replyDraft.value = props.item.official_reply?.text || '';
    }
  },
  { immediate: true },
);

const saveDraft = async () => {
  savingReply.value = true;
  try {
    emit('save-reply-draft', props.item, replyDraft.value.trim());
  } finally {
    savingReply.value = false;
  }
};

const publishReply = async () => {
  publishingReply.value = true;
  try {
    emit('publish-reply', props.item, replyDraft.value.trim());
  } finally {
    publishingReply.value = false;
  }
};
</script>
