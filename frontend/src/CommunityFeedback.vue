<template>
  <div>
    <!-- Guests: login / register CTA -->
    <div
      v-if="!auth.isAuthenticated"
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
          to="/login"
          class="w-full sm:w-auto bg-brand-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-brand-600 transition transform hover:-translate-y-0.5"
        >
          Log In
        </router-link>
        <router-link
          to="/register"
          class="w-full sm:w-auto bg-white text-brand-600 font-bold py-3 px-8 rounded-xl border-2 border-brand-500 hover:bg-brand-50 transition"
        >
          Join Community
        </router-link>
      </div>
      <p class="text-xs text-ink-500 mt-6">Anyone can read reviews below — writing requires a free member account.</p>
    </div>

    <!-- Members: feedback form -->
    <form v-else @submit.prevent="submitFeedback" class="space-y-6">
      <div>
        <label class="block text-gray-700 font-bold mb-3">Vendor Service</label>
        <div class="flex justify-center sm:justify-start gap-2">
          <button
            v-for="star in 5"
            :key="'service-' + star"
            type="button"
            @click="serviceRating = star"
            class="text-4xl focus:outline-none transition-transform hover:scale-110"
            :class="star <= serviceRating ? 'text-brand-500' : 'text-gray-300 hover:text-brand-200'"
            :aria-label="`Vendor service ${star} star`"
          >
            ★
          </button>
        </div>
      </div>

      <div>
        <label class="block text-gray-700 font-bold mb-3">Value &amp; Cleanliness</label>
        <div class="flex justify-center sm:justify-start gap-2">
          <button
            v-for="star in 5"
            :key="'value-' + star"
            type="button"
            @click="valueRating = star"
            class="text-4xl focus:outline-none transition-transform hover:scale-110"
            :class="star <= valueRating ? 'text-brand-500' : 'text-gray-300 hover:text-brand-200'"
            :aria-label="`Value and cleanliness ${star} star`"
          >
            ★
          </button>
        </div>
      </div>

      <div>
        <label for="reviewer-role" class="block text-gray-700 font-bold mb-2">I am a...</label>
        <select
          id="reviewer-role"
          v-model="reviewerRole"
          class="w-full border border-gray-300 rounded-lg p-3 bg-white text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition cursor-pointer"
        >
          <option value="" disabled>Select your role</option>
          <option v-for="role in REVIEWER_ROLES" :key="role" :value="role">
            {{ role }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-gray-700 font-bold mb-2">Your Feedback (minimum 50 words)</label>
        <textarea
          v-model="comments"
          rows="6"
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition resize-none"
          placeholder="Share your detailed experience at Carboot@CMart — what went well, what could improve, and any suggestions for the community..."
        ></textarea>
        <p
  class="mt-2 text-sm font-medium transition-colors"
  :class="wordCount > 50 ? 'text-rose-600' : 'text-gray-500'"
>
  <span class="tabular-nums">{{ wordCount }}</span> / 50 words max
  <span v-if="wordCount > 50" class="font-bold">
    — You are {{ wordCount - 50 }} words over the limit!
  </span>
</p>
      </div>

      <div>
        <label class="block text-gray-700 font-bold mb-2">Photo/Video Proof (Optional)</label>
        <input
          ref="mediaInput"
          type="file"
          accept="image/jpeg,image/png,image/jpg,video/mp4"
          class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer"
          @change="handleFileUpload"
        />
        <p class="text-xs text-ink-500 mt-1">Max 5MB. JPEG, PNG, or MP4 only.</p>
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
import { useAuthStore } from './stores/auth';
import api from './services/api';

const emit = defineEmits(['submitted']);

const auth = useAuthStore();

const REVIEWER_ROLES = ['Shopper', 'Vendor', 'UUM Student', 'Local Resident'];

const serviceRating = ref(0);
const valueRating = ref(0);
const reviewerRole = ref('');
const comments = ref('');
const mediaFile = ref(null);
const mediaInput = ref(null);
const isSubmitting = ref(false);
const message = ref('');
const isSuccess = ref(false);

/** Mirrors PHP str_word_count(trim($value)) for typical text input. */
const countWords = (text) => {
  const trimmed = text.trim();
  if (!trimmed) return 0;
  return trimmed.split(/\s+/).filter(Boolean).length;
};

const wordCount = computed(() => countWords(comments.value));

const canSubmit = computed(() => {
  return serviceRating.value >= 1 && 
         valueRating.value >= 1 && 
         reviewerRole.value !== '' &&
         wordCount.value > 0 && 
         wordCount.value <= 50 && // Must be LESS THAN OR EQUAL to 50
         !isSubmitting.value;
});

const handleFileUpload = (event) => {
  const file = event.target.files?.[0];
  if (!file) {
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
  serviceRating.value = 0;
  valueRating.value = 0;
  reviewerRole.value = '';
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

  const formData = new FormData();
  formData.append('service_rating', String(serviceRating.value));
  formData.append('value_rating', String(valueRating.value));
  formData.append('reviewer_role', reviewerRole.value);
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
