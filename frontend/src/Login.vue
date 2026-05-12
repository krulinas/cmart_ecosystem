<template>
  <div class="min-h-screen bg-gradient-to-br from-brand-50 via-white to-ink-100 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <router-link to="/" class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6">
        <span class="mr-1">←</span> Back to Carboot@CMart
      </router-link>

      <div class="ml-card">
        <div class="text-center mb-6">
          <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white font-extrabold text-xl">C</span>
          <h1 class="mt-4 text-2xl font-extrabold text-ink-900">Welcome back</h1>
          <p class="mt-1 text-sm text-ink-500">Log in to your Carboot@CMart workspace.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="ml-label">Email</label>
            <input v-model="form.email" type="email" required class="ml-input" placeholder="admin@cmart.com" />
          </div>

          <div>
            <label class="ml-label">Password</label>
            <div class="relative w-full">
              <input 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                required 
                class="ml-input pr-16" 
                placeholder="password123" 
              />
              <button 
                type="button" 
                @click="showPassword = !showPassword" 
                class="absolute inset-y-0 right-0 px-3 flex items-center text-sm font-semibold text-gray-500 hover:text-brand-600 focus:outline-none"
              >
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </div>

          <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading">
            {{ auth.loading ? 'Logging in…' : 'Log in' }}
          </button>
        </form>

        <div class="mt-6 rounded-xl bg-ink-50 border border-ink-200 p-4 text-xs text-ink-600">
          <div class="font-semibold text-ink-800 mb-1">Demo accounts after seeding</div>
          <div><strong>CMart Admin:</strong> admin@cmart.com / password123</div>
          <div><strong>CMart Staff:</strong> staff@cmart.com / password123</div>
          <div><strong>Approved Vendor:</strong> vendor@cmart.com / password123</div>
        </div>

        <p class="mt-6 text-center text-sm text-ink-500">
          New community member?
          <router-link to="/register" class="font-semibold text-brand-600 hover:text-brand-700">Create an account</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'; // WAJIB ada 'ref' kat sini supaya page tak jadi putih!
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

// Variable untuk kawal butang Show/Hide
const showPassword = ref(false);

const form = reactive({
  email: '',
  password: '',
});

const submit = async () => {
  try {
    await auth.login(form);
    toast.success('200 OK: Authentication successful.');
    router.push(route.query.redirect || auth.homeForUser());
  } catch (error) {
    const message = error.response?.data?.message || '401 Unauthorized: Invalid email or password.';
    toast.error(message);
  }
};
</script>