<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50">
    <AppNavbar variant="vendor" />

    <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 space-y-8">
      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <span class="ml-badge bg-brand-100 text-brand-700">Vendor Profile</span>
        <h1 class="mt-2 text-3xl font-black text-ink-900 tracking-tight">Account Settings</h1>
        <p class="mt-1 text-sm text-ink-500">
          Manage your vendor identity and business details on Carboot@CMart.
        </p>
      </header>

      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <h2 class="text-xl font-extrabold text-ink-900 mb-6">Personal Information</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Full Name</dt>
            <dd class="mt-1 text-base font-semibold text-ink-900">{{ auth.user?.name || '—' }}</dd>
          </div>
          <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Email</dt>
            <dd class="mt-1 text-base font-semibold text-ink-900">{{ auth.user?.email || '—' }}</dd>
          </div>
          <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Phone</dt>
            <dd class="mt-1 text-base font-semibold text-ink-900">{{ auth.user?.phone_number || '—' }}</dd>
          </div>
          <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
            <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Vendor Status</dt>
            <dd class="mt-1">
              <span :class="vendorStatusClass">{{ vendorStatusLabel }}</span>
            </dd>
          </div>
        </dl>
      </section>

      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <h2 class="text-xl font-extrabold text-ink-900 mb-2">Quick Actions</h2>
        <p class="text-sm text-ink-500 mb-6">Common vendor tasks from your profile.</p>
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
          <router-link to="/dashboard" class="ml-btn-ghost">View Dashboard</router-link>
          <router-link
            v-if="auth.isApprovedVendor"
            to="/vendor-booking"
            class="ml-btn-primary"
          >
            Book a Space
          </router-link>
          <router-link v-else to="/dashboard" class="ml-btn-primary">
            Check Booking Status
          </router-link>
          <button type="button" class="ml-btn-ghost text-red-600 hover:bg-red-50" @click="logout">
            Logout
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import { useAuthStore } from '../../stores/auth';
import { useLogout } from '../../composables/useLogout';

const auth = useAuthStore();
const { logout } = useLogout();

const vendorStatusLabel = computed(() => {
  const status = auth.vendorStatus;
  if (status === 'approved') return 'Approved Vendor';
  if (status === 'pending') return 'Pending Approval';
  if (status === 'rejected') return 'Not Approved';
  return 'Community Member';
});

const vendorStatusClass = computed(() => {
  const status = auth.vendorStatus;
  if (status === 'approved') return 'ml-badge bg-emerald-100 text-emerald-800';
  if (status === 'pending') return 'ml-badge bg-brand-100 text-brand-800';
  if (status === 'rejected') return 'ml-badge bg-rose-100 text-rose-800';
  return 'ml-badge bg-ink-100 text-ink-700';
});
</script>
