<template>
  <form @submit.prevent="submitFeedback" class="space-y-6">
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
      <label class="block text-gray-700 font-bold mb-2">Your Suggestions / Comments (Optional)</label>
      <textarea
        v-model="comments"
        rows="4"
        class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition resize-none"
        placeholder="e.g., Want more food booths... / Need a bigger parking lot..."
      ></textarea>
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

    <div class="flex items-center bg-gray-50 p-4 rounded-xl border border-gray-100">
      <input
        id="post-anonymous"
        v-model="isAnonymous"
        type="checkbox"
        class="w-5 h-5 text-brand-500 rounded focus:ring-brand-500 border-gray-300 cursor-pointer"
      />
      <label for="post-anonymous" class="ml-3 text-gray-700 font-medium cursor-pointer">
        Post Anonymously (Hide my name)
      </label>
    </div>

    <button
      type="submit"
      :disabled="isSubmitting"
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
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['submitted']);

const serviceRating = ref(0);
const valueRating = ref(0);
const comments = ref('');
const isAnonymous = ref(false);
const mediaFile = ref(null);
const mediaInput = ref(null);
const isSubmitting = ref(false);
const message = ref('');
const isSuccess = ref(false);

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
  comments.value = '';
  isAnonymous.value = false;
  mediaFile.value = null;
  if (mediaInput.value) {
    mediaInput.value.value = '';
  }
};

const submitFeedback = async () => {
  if (serviceRating.value < 1 || valueRating.value < 1) {
    message.value = 'Please rate both Vendor Service and Value & Cleanliness (1–5 stars).';
    isSuccess.value = false;
    return;
  }

  isSubmitting.value = true;
  message.value = '';

  const formData = new FormData();
  formData.append('service_rating', String(serviceRating.value));
  formData.append('value_rating', String(valueRating.value));
  formData.append('is_anonymous', isAnonymous.value ? '1' : '0');
  formData.append('comments', comments.value);
  if (mediaFile.value) {
    formData.append('media', mediaFile.value);
  }

  const headers = { Accept: 'application/json' };
  const token = localStorage.getItem('carboot_cmart_token');
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  try {
    const response = await fetch('/api/feedback/submit', {
      method: 'POST',
      headers,
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (response.ok) {
      isSuccess.value = true;
      message.value = data.message || 'Feedback submitted successfully!';
      resetForm();
      emit('submitted');
    } else {
      isSuccess.value = false;
      const validationMsg = data.errors
        ? Object.values(data.errors).flat().join(' ')
        : null;
      message.value = validationMsg || data.message || 'Could not submit feedback. Please try again.';
    }
  } catch {
    isSuccess.value = false;
    message.value = 'Failed to connect to the server. Is the backend running?';
  } finally {
    isSubmitting.value = false;
  }
};
</script>
