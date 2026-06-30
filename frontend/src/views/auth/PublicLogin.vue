<template>
  <AuthShell
    title="Welcome to Carboot@CMart"
    subtitle="Sign in or create an account to book vendor spaces, manage bookings, view receipts, and follow upcoming carboot events."
  >
    <div v-if="showMethodChooser" class="space-y-3">
      <AuthMethodButton
        label="Continue with Google"
        variant="google"
        test-id="auth-continue-google"
        @click="continueWithGoogle"
      >
        <template #icon>
          <GoogleIcon />
        </template>
      </AuthMethodButton>

      <AuthMethodButton
        label="Continue with email"
        test-id="auth-continue-email"
        @click="step = 'email'"
      />
    </div>

    <form v-else @submit.prevent="submit" class="space-y-4">
      <button
        v-if="googleEnabled"
        type="button"
        class="text-sm font-semibold text-brand-600 hover:text-brand-700 transition-colors"
        data-testid="auth-back-to-options"
        @click="step = 'chooser'"
      >
        ← Back to all sign-in options
      </button>

      <div>
        <label class="ml-label" for="login-email">Email</label>
        <input
          id="login-email"
          v-model="form.email"
          type="email"
          required
          autocomplete="username"
          class="ml-input"
          placeholder="you@example.com"
          data-testid="login-email"
        />
      </div>

      <div>
        <label class="ml-label" for="login-password">Password</label>
        <div class="relative w-full">
          <input
            id="login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="ml-input pr-16"
            placeholder="Enter your password"
            data-testid="login-password"
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

      <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading" data-testid="login-submit">
        {{ auth.loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>

    <template #footer>
      <p class="text-center text-sm text-ink-500">
        New to Carboot@CMart?
        <router-link to="/register" class="font-semibold text-brand-600 hover:text-brand-700">
          Create an account
        </router-link>
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
import GoogleIcon from '../../components/auth/GoogleIcon.vue';
import { getGoogleAuthUrl, isGoogleLoginEnabled } from '../../config/auth';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const googleEnabled = isGoogleLoginEnabled();
const step = ref(googleEnabled ? 'chooser' : 'email');
const showPassword = ref(false);

const showMethodChooser = computed(() => googleEnabled && step.value === 'chooser');

const form = reactive({
  email: '',
  password: '',
});

const continueWithGoogle = () => {
  window.location.href = getGoogleAuthUrl();
};

const submit = async () => {
  try {
    await auth.login(form);
    toast.success('Signed in successfully.');
    router.push(route.query.redirect || auth.homeForUser());
  } catch (error) {
    const message = error.response?.data?.message || 'Invalid email or password. Please try again.';
    toast.error(message);
  }
};
</script>
