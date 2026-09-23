<template>
  <AuthShell
    :title="t('auth.publicLoginTitle')"
    :subtitle="t('auth.publicLoginSubtitle')"
    :back-label="t('auth.backToPublicPortal')"
  >
    <div class="mb-4 flex justify-end">
      <LanguageToggle />
    </div>

    <div v-if="showMethodChooser" class="space-y-3">
      <AuthMethodButton
        :label="t('auth.continueWithGoogle')"
        variant="google"
        test-id="auth-continue-google"
        @click="continueWithGoogle"
      >
        <template #icon>
          <GoogleIcon />
        </template>
      </AuthMethodButton>

      <AuthMethodButton
        :label="t('auth.continueWithEmail')"
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
        {{ t('auth.backToSignInOptions') }}
      </button>

      <div>
        <label class="ml-label" for="login-email">{{ t('auth.email') }}</label>
        <input
          id="login-email"
          v-model="form.email"
          type="email"
          required
          autocomplete="username"
          class="ml-input"
          :placeholder="t('auth.emailPlaceholder')"
          data-testid="login-email"
        />
      </div>

      <div>
        <label class="ml-label" for="login-password">{{ t('auth.password') }}</label>
        <div class="relative w-full">
          <input
            id="login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="ml-input pr-16"
            :placeholder="t('auth.passwordPlaceholder')"
            data-testid="login-password"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-0 px-3 flex items-center text-sm font-semibold text-gray-500 hover:text-brand-600 focus:outline-none"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? t('common.hide') : t('common.show') }}
          </button>
        </div>
      </div>

      <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading" data-testid="login-submit">
        {{ auth.loading ? t('auth.signingIn') : t('auth.signIn') }}
      </button>
    </form>

    <template #footer>
      <p class="text-center text-sm text-ink-500">
        {{ t('auth.newToCmart') }}
        <router-link to="/register" class="font-semibold text-brand-600 hover:text-brand-700">
          {{ t('auth.createAccount') }}
        </router-link>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AuthShell from '../../components/auth/AuthShell.vue';
import AuthMethodButton from '../../components/auth/AuthMethodButton.vue';
import GoogleIcon from '../../components/auth/GoogleIcon.vue';
import LanguageToggle from '../../components/LanguageToggle.vue';
import { getGoogleAuthUrl, isGoogleLoginEnabled } from '../../config/auth';
import { resolvePostAuthRedirect } from '../../utils/postAuthRedirect';
import { useAuthStore } from '../../stores/auth';

const { t } = useI18n();
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
    toast.success(t('auth.signedInSuccess'));
    router.push(resolvePostAuthRedirect(auth, route.query.redirect));
  } catch (error) {
    const message = error.response?.data?.message || t('auth.invalidCredentials');
    toast.error(message);
  }
};
</script>
