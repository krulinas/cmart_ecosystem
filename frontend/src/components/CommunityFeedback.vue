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
      <h3 class="text-2xl font-extrabold text-gray-800 mb-2">Members Only</h3>
      <p class="text-gray-600 max-w-md mx-auto mb-8 leading-relaxed">
        Share your experience with the Carboot@CMart community. Log in or join as a member to submit
        ratings and detailed feedback.
      </p>
      <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
        <router-link
          :to="loginPath"
          class="w-full sm:w-auto bg-brand-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-brand-600 transition transform hover:-translate-y-0.5"
        >
          Leave a Review
        </router-link>
        <router-link
          :to="registerPath"
          class="w-full sm:w-auto bg-white text-brand-600 font-bold py-3 px-8 rounded-xl border-2 border-brand-500 hover:bg-brand-50 transition"
        >
          Join Community
        </router-link>
      </div>
      <p class="text-xs text-ink-500 mt-6">Anyone can read reviews below — writing requires a free member account.</p>
    </div>

    <!-- Members: feedback form -->
    <form v-else-if="auth.isAuthenticated" @submit.prevent="submitFeedback" class="space-y-6">
      <div>
        <label class="block text-gray-700 font-bold mb-3">Overall Rating</label>
        <div class="flex justify-center sm:justify-start gap-2">
          <button
            v-for="star in 5"
            :key="'rating-' + star"
            type="button"
            @click="overallRating = star"
            class="text-4xl focus:outline-none transition-transform hover:scale-110"
            :class="star <= overallRating ? 'text-brand-500' : 'text-gray-300 hover:text-brand-200'"
            :aria-label="`Overall rating ${star} star`"
          >
            ★
          </button>
        </div>
      </div>

      <div>
        <label for="participation-type" class="block text-gray-700 font-bold mb-2">
          How did you participate in Carboot@CMart?
        </label>
        <select
          id="participation-type"
          v-model="participationType"
          required
          class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition cursor-pointer"
        >
          <option value="" disabled>Select your participation type</option>
          <option
            v-for="option in PARTICIPATION_TYPE_OPTIONS"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>

      <fieldset>
        <legend class="block text-gray-700 font-bold mb-1">
          Tell us about your background (optional)
        </legend>
        <p class="text-sm text-gray-500 mb-3">Select all that apply.</p>
        <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/60 p-4">
          <label
            v-for="option in COMMUNITY_BACKGROUND_OPTIONS"
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
        <label class="block text-gray-700 font-bold mb-2">Your Feedback (5–100 words)</label>
        <textarea
          v-model="comments"
          rows="6"
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition resize-none"
          placeholder="Share your experience at Carboot@CMart — what went well, what could improve, and any suggestions for the community..."
        ></textarea>
        <p
          class="mt-2 text-sm font-medium transition-colors"
          :class="wordCountClass"
        >
          <span class="tabular-nums">{{ wordCount }}</span> / {{ MAX_WORDS }} words
          <span v-if="wordCount > 0 && wordCount < MIN_WORDS" class="text-amber-600">
            — at least {{ MIN_WORDS - wordCount }} more word{{ MIN_WORDS - wordCount === 1 ? '' : 's' }} needed
          </span>
          <span v-else-if="wordCount > MAX_WORDS" class="text-rose-600 font-bold">
            — {{ wordCount - MAX_WORDS }} word{{ wordCount - MAX_WORDS === 1 ? '' : 's' }} over the limit
          </span>
        </p>
      </div>

      <div>
        <label class="block text-gray-700 font-bold mb-2">Photo Proof (Optional)</label>
        <input
          ref="mediaInput"
          type="file"
          accept="image/jpeg,image/png,image/jpg"
          class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer"
          @change="handleFileUpload"
        />
        <p class="text-xs text-ink-500 mt-1">One photo only. Max 5MB. JPEG or PNG.</p>
      </div>

      <button
        type="submit"
        :disabled="!canSubmit"
        class="w-full bg-brand-500 text-white font-bold py-4 px-6 rounded-xl shadow-lg hover:bg-brand-600 transition transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0"
      >
        {{ isSubmitting ? 'Submitting...' : 'Submit Feedback' }}
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
import { ref, computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { loginPathWithRedirect, registerPathWithRedirect, COMMUNITY_REVIEW_INTENT_PATH } from '../utils/postAuthRedirect';
import {
  PARTICIPATION_TYPE_OPTIONS,
  COMMUNITY_BACKGROUND_OPTIONS,
  normalizeCommunityBackgrounds,
} from '../utils/feedbackClassification';
import api from '../services/api';

const emit = defineEmits(['submitted']);

defineProps({
  hideGuestGate: { type: Boolean, default: false },
});

const auth = useAuthStore();

const reviewIntentPath = COMMUNITY_REVIEW_INTENT_PATH;
const loginPath = loginPathWithRedirect(reviewIntentPath);
const registerPath = registerPathWithRedirect(reviewIntentPath);

const MIN_WORDS = 5;
const MAX_WORDS = 100;

const overallRating = ref(0);
const participationType = ref('');
const communityBackgrounds = ref([]);
const comments = ref('');
const mediaFile = ref(null);
const mediaInput = ref(null);
const isSubmitting = ref(false);
const message = ref('');
const isSuccess = ref(false);

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
  return overallRating.value >= 1
    && participationType.value !== ''
    && wordCount.value >= MIN_WORDS
    && wordCount.value <= MAX_WORDS
    && !isSubmitting.value;
});

const onCommunityBackgroundChange = (changedValue) => {
  communityBackgrounds.value = normalizeCommunityBackgrounds(
    communityBackgrounds.value,
    changedValue,
  );
};

const handleFileUpload = (event) => {
  const file = event.target.files?.[0];
  if (!file) {
    mediaFile.value = null;
    return;
  }

  if (!file.type.startsWith('image/')) {
    message.value = 'Only JPEG or PNG images are allowed.';
    isSuccess.value = false;
    event.target.value = '';
    mediaFile.value = null;
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    message.value = 'File is too large. Maximum size is 5MB.';
    isSuccess.value = false;
    event.target.value = '';
    mediaFile.value = null;
    return;
  }

  mediaFile.value = file;
  message.value = '';
};

const resetForm = () => {
  overallRating.value = 0;
  participationType.value = '';
  communityBackgrounds.value = [];
  comments.value = '';
  mediaFile.value = null;
  if (mediaInput.value) {
    mediaInput.value.value = '';
  }
};

const submitFeedback = async () => {
  if (!canSubmit.value) {
    return;
  }

  isSubmitting.value = true;
  message.value = '';

  const backgrounds = normalizeCommunityBackgrounds(communityBackgrounds.value);

  const formData = new FormData();
  formData.append('rating', String(overallRating.value));
  formData.append('participation_type', participationType.value);
  backgrounds.forEach((value, index) => {
    formData.append(`community_backgrounds[${index}]`, value);
  });
  formData.append('comments', comments.value);
  if (mediaFile.value) {
    formData.append('media', mediaFile.value);
  }

  try {
    const response = await api.post('/feedback/submit', formData);

    isSuccess.value = true;
    message.value = response.data.message || 'Feedback submitted successfully!';
    resetForm();
    emit('submitted');
  } catch (error) {
    isSuccess.value = false;

    if (error.response?.data) {
      const data = error.response.data;
      const validationMsg = data.errors
        ? Object.values(data.errors).flat().join(' ')
        : null;
      message.value = validationMsg || data.message || 'Could not submit feedback. Please try again.';
    } else {
      message.value = 'Failed to connect to the server. Is the backend running?';
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>
