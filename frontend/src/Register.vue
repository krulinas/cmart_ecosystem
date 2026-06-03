<template>
  <div class="min-h-screen bg-gradient-to-br from-brand-50 via-white to-ink-100 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg">
      <router-link to="/" class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6">
        <span class="mr-1">←</span> Back to Carboot@CMart
      </router-link>

      <div class="ml-card">
        <div class="mb-6">
          <span class="ml-badge bg-brand-100 text-brand-700">Changlun Community</span>
          <h1 class="mt-3 text-2xl font-extrabold text-ink-900">Create your community account</h1>
          <p class="mt-1 text-sm text-ink-500">
            Start as a community member. Vendor tools unlock after CMart approves your vendor status.
          </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="ml-label">Full name</label>
            <input v-model="form.name" type="text" required class="ml-input" placeholder="Your name" />
          </div>

          <div>
            <label class="ml-label">Email</label>
            <input v-model="form.email" type="email" required class="ml-input" placeholder="you@example.com" />
          </div>

          <div>
            <label class="ml-label">Phone number</label>
            <input v-model="form.phone_number" type="tel" class="ml-input" placeholder="0123456789" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="ml-label">Password</label>
              <div class="relative w-full">
                <input 
                  v-model="form.password" 
                  :type="showPassword ? 'text' : 'password'" 
                  required 
                  minlength="8" 
                  class="ml-input pr-16" 
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

            <div>
              <label class="ml-label">Confirm password</label>
              <div class="relative w-full">
                <input 
                  v-model="form.password_confirmation" 
                  :type="showPasswordConfirmation ? 'text' : 'password'" 
                  required 
                  minlength="8" 
                  class="ml-input pr-16" 
                />
                <button 
                  type="button" 
                  @click="showPasswordConfirmation = !showPasswordConfirmation" 
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-sm font-semibold text-gray-500 hover:text-brand-600 focus:outline-none"
                >
                  {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
                </button>
              </div>
            </div>
          </div>

          <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading">
            {{ auth.loading ? 'Creating account…' : 'Create account' }}
          </button>
        </form>

        <div class="mt-6 flex items-center justify-between">
          <span class="w-1/5 border-b lg:w-1/4"></span>
          <span class="text-xs text-center text-gray-500 uppercase">OR</span>
          <span class="w-1/5 border-b lg:w-1/4"></span>
        </div>

        <div class="mt-6">
          <button @click.prevent="loginWithGoogle" class="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-200">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
            </svg>
            Sign up with Google
          </button>
        </div>
        <p class="mt-6 text-center text-sm text-ink-500">
          Already have an account?
          <router-link to="/login" class="font-semibold text-brand-600 hover:text-brand-700">Log in</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = reactive({
  name: '',
  email: '',
  phone_number: '',
  password: '',
  password_confirmation: '',
});

const loginWithGoogle = () => {
  window.location.href = 'http://localhost:8000/api/auth/google';
};

const submit = async () => {
  try {
    await auth.register(form);
    toast.success('201 Created: Account registered successfully.');
    router.push(auth.homeForUser());
  } catch (error) {
    const errors = error.response?.data?.errors;
    const firstError = errors ? Object.values(errors)[0]?.[0] : null;
    toast.error(firstError || '400 Bad Request: Registration could not be completed.');
  }
};
</script>