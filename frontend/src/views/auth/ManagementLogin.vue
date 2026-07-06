<template>
  <AuthShell
    title="CMart Operations Login"
    subtitle="Authorized staff and management access only."
  >
    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="ml-label" for="management-login-email">Work email</label>
        <input
          id="management-login-email"
          v-model="form.email"
          type="email"
          required
          autocomplete="username"
          class="ml-input"
          placeholder="you@cmart.com"
          data-testid="management-login-email"
        />
      </div>

      <div>
        <label class="ml-label" for="management-login-password">Password</label>
        <div class="relative w-full">
          <input
            id="management-login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            class="ml-input pr-16"
            placeholder="Enter your password"
            data-testid="management-login-password"
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

      <button
        type="submit"
        class="ml-btn-primary w-full"
        :disabled="auth.loading"
        data-testid="management-login-submit"
      >
        {{ auth.loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </AuthShell>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AuthShell from '../../components/auth/AuthShell.vue';
import { resolveManagementPostAuthRedirect } from '../../utils/postAuthRedirect';
import { useAuthStore } from '../../stores/auth';

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
      toast.error('This portal is restricted to authorized CMart staff and management.');
      return;
    }

    toast.success('Signed in successfully.');
    router.push(resolveManagementPostAuthRedirect(auth, route.query.redirect));
  } catch (error) {
    const message = error.response?.data?.message || 'Invalid email or password. Please try again.';
    toast.error(message);
  }
};
</script>
