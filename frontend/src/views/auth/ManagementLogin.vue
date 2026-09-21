<template>
  <AuthShell
    :title="t('auth.managementLoginTitle')"
    :subtitle="t('auth.managementLoginSubtitle')"
    :back-label="t('auth.backToPublicPortal')"
  >
    <div class="mb-4 flex justify-end">
      <LanguageToggle />
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="ml-label" for="management-login-email">{{ t('auth.workEmail') }}</label>
        <input
          id="management-login-email"
          v-model="form.email"
          type="email"
          required
          autocomplete="username"
          class="ml-input"
          :placeholder="t('auth.workEmailPlaceholder')"
          data-testid="management-login-email"
        />
      </div>

      <div>
        <label class="ml-label" for="management-login-password">{{ t('auth.password') }}</label>
        <div class="relative w-full">
          <input
            id="management-login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="ml-input pr-16"
            :placeholder="t('auth.passwordPlaceholder')"
            data-testid="management-login-password"
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

      <button
        type="submit"
        class="ml-btn-primary w-full"
        :disabled="auth.loading"
        data-testid="management-login-submit"
      >
        {{ auth.loading ? t('auth.signingIn') : t('auth.signIn') }}
      </button>
    </form>
  </AuthShell>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AuthShell from '../../components/auth/AuthShell.vue';
import LanguageToggle from '../../components/LanguageToggle.vue';
import { resolveManagementPostAuthRedirect } from '../../utils/postAuthRedirect';
import { useAuthStore } from '../../stores/auth';

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const showPassword = ref(false);

const form = reactive({
  email: '',
  password: '',
});

const submit = async () => {
  try {
    await auth.login(form);

    if (!auth.isCmartWorker) {
      await auth.logout();
      toast.error(t('auth.managementRestricted'));
      return;
    }

    toast.success(t('auth.signedInSuccess'));
    router.push(resolveManagementPostAuthRedirect(auth, route.query.redirect));
  } catch (error) {
    const message = error.response?.data?.message || t('auth.invalidCredentials');
    toast.error(message);
  }
};
</script>
