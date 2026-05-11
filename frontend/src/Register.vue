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
              <input v-model="form.password" type="password" required minlength="8" class="ml-input" />
            </div>
            <div>
              <label class="ml-label">Confirm password</label>
              <input v-model="form.password_confirmation" type="password" required minlength="8" class="ml-input" />
            </div>
          </div>

          <button type="submit" class="ml-btn-primary w-full" :disabled="auth.loading">
            {{ auth.loading ? 'Creating account…' : 'Create account' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-500">
          Already have an account?
          <router-link to="/login" class="font-semibold text-brand-600 hover:text-brand-700">Log in</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const form = reactive({
  name: '',
  email: '',
  phone_number: '',
  password: '',
  password_confirmation: '',
});

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
