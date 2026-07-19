<template>
  <AuthShell
    wide
    title="Create your Carboot@CMart account"
    subtitle="Join the community to book spaces, save booking details, and manage your carboot activity."
  >
    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="ml-label" for="register-name">Full name</label>
        <input
          id="register-name"
          v-model="form.name"
          type="text"
          required
          autocomplete="name"
          class="ml-input"
          placeholder="Your full name"
          data-testid="register-name"
        />
      </div>

      <div>
        <label class="ml-label" for="register-email">Email</label>
        <input
          id="register-email"
          v-model="form.email"
          type="email"
          required
          autocomplete="email"
          class="ml-input"
          placeholder="you@example.com"
          data-testid="register-email"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="ml-label" for="register-password">Password</label>
          <div class="relative w-full">
            <input
              id="register-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              minlength="8"
              autocomplete="new-password"
              class="ml-input pr-16"
              placeholder="At least 8 characters"
              data-testid="register-password"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-sm font-semibold text-gray-500 hover:text-brand-600 focus:outline-none"
              :aria-pressed="showPassword"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>

        <div>
          <label class="ml-label" for="register-password-confirm">Confirm password</label>
          <div class="relative w-full">
            <input
              id="register-password-confirm"
              v-model="form.password_confirmation"
              :type="showPasswordConfirmation ? 'text' : 'password'"
              required
              minlength="8"
              autocomplete="new-password"
              class="ml-input pr-16"
              placeholder="Repeat password"
              data-testid="register-password-confirm"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-sm font-semibold text-gray-500 hover:text-brand-600 focus:outline-none"
              :aria-pressed="showPasswordConfirmation"
              @click="showPasswordConfirmation = !showPasswordConfirmation"
            >
              {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
            </button>
          </div>
        </div>
      </div>

      <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading" data-testid="register-submit">
        {{ auth.loading ? 'Creating account…' : 'Create account' }}
      </button>
    </form>

    <template v-if="googleEnabled">
      <div class="mt-6 flex items-center justify-between">
        <span class="w-1/5 border-b lg:w-1/4"></span>
        <span class="text-xs text-center text-gray-500 uppercase">or</span>
        <span class="w-1/5 border-b lg:w-1/4"></span>
      </div>

      <div class="mt-6">
        <AuthMethodButton label="Continue with Google" variant="google" @click="continueWithGoogle">
          <template #icon>
            <GoogleIcon />
          </template>
        </AuthMethodButton>
      </div>
    </template>

    <template #footer>
      <p class="text-center text-sm text-ink-500">
        Already have an account?
        <router-link :to="loginLink" class="font-semibold text-brand-600 hover:text-brand-700">Sign in</router-link>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AuthShell from '../../components/auth/AuthShell.vue';
import AuthMethodButton from '../../components/auth/AuthMethodButton.vue';

defineOptions({ name: 'UserRegistration' });
import GoogleIcon from '../../components/auth/GoogleIcon.vue';
import { getGoogleAuthUrl, isGoogleLoginEnabled } from '../../config/auth';
import { resolvePostAuthRedirect, COMMUNITY_REVIEW_INTENT_PATH } from '../../utils/postAuthRedirect';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const reviewIntentPath = COMMUNITY_REVIEW_INTENT_PATH;
const loginLink = computed(() => {
  const redirect = route.query.redirect;
  const path = typeof redirect === 'string' && redirect.startsWith('/') ? redirect : reviewIntentPath;
  return `/login?redirect=${encodeURIComponent(path)}`;
});
const googleEnabled = isGoogleLoginEnabled();
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const continueWithGoogle = () => {
  window.location.href = getGoogleAuthUrl();
};

const submit = async () => {
  try {
    await auth.register(form);
    toast.success('Account created successfully.');
    router.push(resolvePostAuthRedirect(auth, route.query.redirect));
  } catch (error) {
    const errors = error.response?.data?.errors;
    const firstError = errors ? Object.values(errors)[0]?.[0] : null;
    toast.error(firstError || 'Registration could not be completed. Please check your details.');
  }
};
</script>
