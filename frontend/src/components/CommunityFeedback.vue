<template>
  <div>
    <!-- Guests: login / register CTA -->
    <div
      v-if="!auth.isAuthenticated && !hideGuestGate"
      class="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-gray-50 p-8 sm:p-10 text-center shadow-sm"
    >
      <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 text-brand-600">
        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
          />
        </svg>
      </div>
      <h3 class="text-2xl font-extrabold text-gray-800 mb-2">{{ t('community.feedback.membersOnly') }}</h3>
      <p class="text-gray-600 max-w-md mx-auto mb-8 leading-relaxed">
        {{ t('community.feedback.guestBody') }}
      </p>
      <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
        <router-link
          :to="loginPath"
          class="w-full sm:w-auto bg-brand-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-brand-600 transition transform hover:-translate-y-0.5"
        >
          {{ t('community.feedback.leaveReview') }}
        </router-link>
        <router-link
          :to="registerPath"
          class="w-full sm:w-auto bg-white text-brand-600 font-bold py-3 px-8 rounded-xl border-2 border-brand-500 hover:bg-brand-50 transition"
        >
          {{ t('community.feedback.joinCommunity') }}
        </router-link>
      </div>
      <p class="text-xs text-ink-500 mt-6">{{ t('community.feedback.guestFootnote') }}</p>
    </div>

    <!-- Members: feedback form -->
    <form v-else-if="auth.isAuthenticated" @submit.prevent="submitFeedback" class="space-y-6">
      <div>
        <label class="block text-gray-700 font-bold mb-3">{{ t('community.feedback.overallRating') }}</label>
        <div class="flex justify-center sm:justify-start gap-2">
          <button
            v-for="star in 5"
            :key="'rating-' + star"
            type="button"
            @click="overallRating = star"
            class="text-4xl focus:outline-none transition-transform hover:scale-110"
            :class="star <= overallRating ? 'text-brand-500' : 'text-gray-300 hover:text-brand-200'"
            :aria-label="t('community.feedback.overallRatingAria', { n: star })"
          >
            ★
          </button>
        </div>
      </div>

      <div>
        <label for="participation-type" class="block text-gray-700 font-bold mb-2">
          {{ t('community.feedback.participationLabel') }}
        </label>
        <select
          id="participation-type"
          v-model="participationType"
          required
          class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition cursor-pointer"
        >
          <option value="" disabled>{{ t('community.feedback.selectParticipation') }}</option>
          <option
            v-for="option in participationOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>

      <!-- Vendor event selection -->
      <div v-if="isVendorParticipation" class="space-y-2">
        <label for="vendor-event" class="block text-gray-700 font-bold mb-2">
          {{ t('community.feedback.vendorEventLabel') }}
        </label>
        <p
          v-if="!vendorEligible"
          class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
          data-testid="vendor-feedback-ineligible"
        >
          {{ vendorIneligibleMessage }}
        </p>
        <template v-else>
          <select
            id="vendor-event"
            v-model="selectedEventId"
            required
            class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition cursor-pointer"
            :disabled="vendorEvents.length === 1 && Boolean(preselectedEventId)"
          >
            <option value="" disabled>{{ t('community.feedback.selectEvent') }}</option>
            <option
              v-for="event in vendorEvents"
              :key="event.id"
              :value="String(event.id)"
            >
              {{ event.title }} · {{ event.date_label }}
            </option>
          </select>
          <p v-if="selectedEventDisplay" class="text-sm text-ink-600">
            {{ t('community.feedback.reviewingPrefix') }} <span class="font-semibold text-ink-900">{{ selectedEventDisplay }}</span>
          </p>
        </template>
      </div>

      <!-- Non-vendor event selection -->
      <div v-else-if="participationType" class="space-y-2">
        <label for="feedback-event" class="block text-gray-700 font-bold mb-2">
          {{ t('community.feedback.nonVendorEventLabel') }}
        </label>
        <div
          v-if="lockedEvent"
          class="rounded-lg border border-brand-100 bg-brand-50/50 px-3 py-2 text-sm text-ink-800"
        >
          <span class="font-semibold">{{ lockedEvent.title }}</span>
          <span class="text-ink-500"> · {{ lockedEvent.date_label }}</span>
        </div>
        <select
          v-else
          id="feedback-event"
          v-model="selectedEventId"
          required
          class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition cursor-pointer"
        >
          <option value="" disabled>{{ t('community.feedback.selectEvent') }}</option>
          <option
            v-for="event in visibleEvents"
            :key="event.id"
            :value="String(event.id)"
          >
            {{ event.title }} · {{ event.date_label }}
          </option>
        </select>
      </div>

      <fieldset>
        <legend class="block text-gray-700 font-bold mb-1">
          {{ t('community.feedback.backgroundLegend') }}
        </legend>
        <p class="text-sm text-gray-500 mb-3">{{ t('community.feedback.selectAll') }}</p>
        <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/60 p-4">
          <label
            v-for="option in backgroundOptions"
            :key="option.value"
            class="flex items-start gap-3 cursor-pointer rounded-lg px-2 py-1.5 hover:bg-white transition"
          >
            <input
              v-model="communityBackgrounds"
              type="checkbox"
              class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
              :value="option.value"
              @change="onCommunityBackgroundChange(option.value)"
            />
            <span class="text-sm font-semibold text-gray-800">{{ option.label }}</span>
          </label>
        </div>
      </fieldset>

      <div>
        <label class="block text-gray-700 font-bold mb-2">{{ t('community.feedback.yourFeedback') }}</label>
        <textarea
          v-model="comments"
          rows="6"
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition resize-none"
          :placeholder="t('community.feedback.placeholder')"
        ></textarea>
        <p
          class="mt-2 text-sm font-medium transition-colors"
          :class="wordCountClass"
        >
          <span class="tabular-nums">{{ wordCount }}</span> / {{ MAX_WORDS }} {{ t('community.feedback.wordsSuffix') }}
          <span v-if="wordCount > 0 && wordCount < MIN_WORDS" class="text-amber-600">
            — {{ t('community.feedback.moreWordsHint') }}
          </span>
          <span v-else-if="wordCount > MAX_WORDS" class="text-rose-600 font-bold">
            — {{ t('community.feedback.overWordsHint') }}
          </span>
        </p>
      </div>

      <div>
        <label class="block text-gray-700 font-bold mb-2">{{ t('community.feedback.photoProof') }}</label>
        <input
          ref="mediaInput"
          type="file"
          accept="image/jpeg,image/png,image/jpg,image/webp"
          multiple
          class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer"
          @change="handleFileUpload"
        />
        <p class="text-xs text-ink-500 mt-1">{{ t('community.feedback.photoHint') }}</p>
        <div v-if="mediaPreviews.length" class="mt-3 grid grid-cols-3 gap-3">
          <div
            v-for="preview in mediaPreviews"
            :key="preview.key"
            class="relative overflow-hidden rounded-lg border border-gray-200"
          >
            <img :src="preview.url" :alt="preview.name || t('community.feedback.imagePreviewAlt')" class="h-24 w-full object-cover" />
            <button
              type="button"
              class="absolute top-1 right-1 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-rose-600 shadow"
              @click="removeMediaPreview(preview.key)"
            >
              {{ t('community.feedback.remove') }}
            </button>
          </div>
        </div>
      </div>

      <button
        type="submit"
        :disabled="!canSubmit"
        class="w-full bg-brand-500 text-white font-bold py-4 px-6 rounded-xl shadow-lg hover:bg-brand-600 transition transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0"
      >
        {{ isSubmitting ? t('community.feedback.submitting') : t('community.feedback.submit') }}
      </button>

      <p
        v-if="message"
        class="p-4 rounded-xl text-center font-medium"
        :class="isSuccess ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
      >
        {{ message }}
      </p>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { loginPathWithRedirect, registerPathWithRedirect, COMMUNITY_REVIEW_INTENT_PATH } from '../utils/postAuthRedirect';
import {
  PARTICIPATION_TYPE_OPTIONS,
  COMMUNITY_BACKGROUND_OPTIONS,
  normalizeCommunityBackgrounds,
} from '../utils/feedbackClassification';
import api from '../services/api';

const emit = defineEmits(['submitted']);
const { t } = useI18n();

const props = defineProps({
  hideGuestGate: { type: Boolean, default: false },
  /** Preselect when the page is opened from a specific event. */
  eventId: { type: [Number, String], default: null },
});

const auth = useAuthStore();

const reviewIntentPath = COMMUNITY_REVIEW_INTENT_PATH;
const loginPath = loginPathWithRedirect(reviewIntentPath);
const registerPath = registerPathWithRedirect(reviewIntentPath);

const PARTICIPATION_LABEL_KEYS = {
  visitor_shopper: 'community.feedback.participation.visitorShopper',
  vendor: 'community.feedback.participation.vendor',
  organizer_event_crew: 'community.feedback.participation.organizerCrew',
  other: 'community.feedback.participation.other',
};

const BACKGROUND_LABEL_KEYS = {
  uum_student: 'community.feedback.background.uumStudent',
  uum_staff: 'community.feedback.background.uumStaff',
  other_institution: 'community.feedback.background.otherInstitution',
  changlun_resident: 'community.feedback.background.changlunResident',
  outside_changlun: 'community.feedback.background.outsideChanglun',
  prefer_not_to_say: 'community.feedback.background.preferNot',
};

const participationOptions = computed(() =>
  PARTICIPATION_TYPE_OPTIONS.map((option) => ({
    value: option.value,
    label: t(PARTICIPATION_LABEL_KEYS[option.value] || option.label),
  })),
);

const backgroundOptions = computed(() =>
  COMMUNITY_BACKGROUND_OPTIONS.map((option) => ({
    value: option.value,
    label: t(BACKGROUND_LABEL_KEYS[option.value] || option.label),
  })),
);

const MIN_WORDS = 5;
const MAX_WORDS = 100;
const MAX_IMAGES = 3;
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
const overallRating = ref(0);
const participationType = ref('');
const communityBackgrounds = ref([]);
const comments = ref('');
const mediaFiles = ref([]);
const mediaPreviews = ref([]);
const mediaInput = ref(null);
const isSubmitting = ref(false);
const message = ref('');
const isSuccess = ref(false);
const selectedEventId = ref('');
const visibleEvents = ref([]);
const vendorEvents = ref([]);
const vendorEligible = ref(false);
const vendorIneligibleMessage = ref('');
const optionsLoaded = ref(false);

const preselectedEventId = computed(() => (
  props.eventId != null && String(props.eventId).trim() !== ''
    ? String(props.eventId)
    : ''
));

const isVendorParticipation = computed(() => participationType.value === 'vendor');

const lockedEvent = computed(() => {
  if (!preselectedEventId.value || isVendorParticipation.value) return null;
  return visibleEvents.value.find((event) => String(event.id) === preselectedEventId.value) || null;
});

const selectedEventDisplay = computed(() => {
  const pool = isVendorParticipation.value ? vendorEvents.value : visibleEvents.value;
  const match = pool.find((event) => String(event.id) === String(selectedEventId.value));
  if (!match) return '';
  return `${match.title} · ${match.date_label}`;
});

const countWords = (text) => {
  const trimmed = text.trim();
  if (!trimmed) return 0;
  return trimmed.split(/\s+/).filter(Boolean).length;
};

const wordCount = computed(() => countWords(comments.value));

const wordCountClass = computed(() => {
  if (wordCount.value > MAX_WORDS) return 'text-rose-600';
  if (wordCount.value > 0 && wordCount.value < MIN_WORDS) return 'text-amber-600';
  return 'text-gray-500';
});

const canSubmit = computed(() => {
  if (overallRating.value < 1 || !participationType.value) return false;
  if (wordCount.value < MIN_WORDS || wordCount.value > MAX_WORDS) return false;
  if (isSubmitting.value) return false;
  if (isVendorParticipation.value) {
    if (!vendorEligible.value) return false;
    if (!selectedEventId.value) return false;
  } else if (participationType.value) {
    if (!selectedEventId.value && !lockedEvent.value) return false;
  }
  return true;
});

const onCommunityBackgroundChange = (changedValue) => {
  communityBackgrounds.value = normalizeCommunityBackgrounds(
    communityBackgrounds.value,
    changedValue,
  );
};

const revokeMediaPreviews = () => {
  mediaPreviews.value.forEach((preview) => {
    if (preview.url?.startsWith('blob:')) {
      URL.revokeObjectURL(preview.url);
    }
  });
};

const handleFileUpload = (event) => {
  const files = Array.from(event.target.files || []);
  if (mediaInput.value) {
    mediaInput.value.value = '';
  }
  if (!files.length) return;

  if (mediaFiles.value.length + files.length > MAX_IMAGES) {
    message.value = t('community.feedback.toastMaxImages');
    isSuccess.value = false;
    return;
  }

  for (const file of files) {
    const typeOk = ALLOWED_IMAGE_TYPES.includes(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name);
    if (!typeOk) {
      message.value = t('community.feedback.toastImageType');
      isSuccess.value = false;
      return;
    }
    if (file.size > MAX_IMAGE_BYTES) {
      message.value = t('community.feedback.toastImageSize');
      isSuccess.value = false;
      return;
    }
  }

  files.forEach((file) => {
    mediaFiles.value.push(file);
    mediaPreviews.value.push({
      key: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
      url: URL.createObjectURL(file),
      name: file.name,
    });
  });
  message.value = '';
};

const removeMediaPreview = (key) => {
  const index = mediaPreviews.value.findIndex((preview) => preview.key === key);
  if (index === -1) return;
  const preview = mediaPreviews.value[index];
  if (preview.url?.startsWith('blob:')) {
    URL.revokeObjectURL(preview.url);
  }
  mediaPreviews.value.splice(index, 1);
  mediaFiles.value.splice(index, 1);
};

const resetForm = () => {
  overallRating.value = 0;
  participationType.value = '';
  communityBackgrounds.value = [];
  comments.value = '';
  if (!preselectedEventId.value) {
    selectedEventId.value = '';
  }
  revokeMediaPreviews();
  mediaFiles.value = [];
  mediaPreviews.value = [];
  if (mediaInput.value) {
    mediaInput.value.value = '';
  }
};

const applyEventDefaults = () => {
  if (isVendorParticipation.value) {
    if (vendorEvents.value.length === 1) {
      selectedEventId.value = String(vendorEvents.value[0].id);
      return;
    }
    if (
      preselectedEventId.value
      && vendorEvents.value.some((event) => String(event.id) === preselectedEventId.value)
    ) {
      selectedEventId.value = preselectedEventId.value;
      return;
    }
    selectedEventId.value = '';
    return;
  }

  if (preselectedEventId.value) {
    selectedEventId.value = preselectedEventId.value;
    return;
  }
  if (visibleEvents.value.length === 1) {
    selectedEventId.value = String(visibleEvents.value[0].id);
  }
};

const loadOptions = async () => {
  if (!auth.isAuthenticated) return;
  try {
    const { data } = await api.get('/feedback/options');
    visibleEvents.value = Array.isArray(data.visible_events) ? data.visible_events : [];
    vendorEvents.value = Array.isArray(data.vendor_eligible_events) ? data.vendor_eligible_events : [];
    vendorEligible.value = Boolean(data.vendor_eligible);
    vendorIneligibleMessage.value = data.vendor_ineligible_message || t('community.feedback.vendorIneligibleDefault');
    optionsLoaded.value = true;
    applyEventDefaults();
  } catch {
    visibleEvents.value = [];
    vendorEvents.value = [];
    vendorEligible.value = false;
    optionsLoaded.value = true;
  }
};

watch(participationType, () => {
  applyEventDefaults();
});

watch(() => props.eventId, () => {
  applyEventDefaults();
});

const submitFeedback = async () => {
  if (!canSubmit.value) {
    return;
  }

  const eventId = lockedEvent.value
    ? String(lockedEvent.value.id)
    : String(selectedEventId.value || '');

  if (!eventId) {
    message.value = t('community.feedback.toastSelectEvent');
    isSuccess.value = false;
    return;
  }

  if (isVendorParticipation.value && !vendorEligible.value) {
    message.value = vendorIneligibleMessage.value;
    isSuccess.value = false;
    return;
  }

  isSubmitting.value = true;
  message.value = '';

  const backgrounds = normalizeCommunityBackgrounds(communityBackgrounds.value);

  const formData = new FormData();
  formData.append('rating', String(overallRating.value));
  formData.append('participation_type', participationType.value);
  formData.append('carboot_event_id', eventId);
  backgrounds.forEach((value, index) => {
    formData.append(`community_backgrounds[${index}]`, value);
  });
  formData.append('comments', comments.value);
  mediaFiles.value.forEach((file) => {
    formData.append('images[]', file);
  });

  try {
    const response = await api.post('/feedback/submit', formData);

    isSuccess.value = true;
    message.value = response.data.message || t('community.feedbackSubmittedToast');
    resetForm();
    emit('submitted');
  } catch (error) {
    isSuccess.value = false;

    if (error.response?.data) {
      const data = error.response.data;
      const validationMsg = data.errors
        ? Object.values(data.errors).flat().join(' ')
        : null;
      message.value = validationMsg || data.message || t('community.feedback.toastUnableSubmit');
    } else {
      message.value = t('community.feedback.toastUnableSubmit');
    }
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  if (auth.isAuthenticated) {
    loadOptions();
  }
});

watch(() => auth.isAuthenticated, (authed) => {
  if (authed) loadOptions();
});

onUnmounted(revokeMediaPreviews);
</script>
