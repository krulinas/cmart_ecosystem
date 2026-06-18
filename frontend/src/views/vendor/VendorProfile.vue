<template>
  <div class="min-h-screen bg-gradient-to-br from-ink-50 via-brand-50/30 to-ink-50">
    <AppNavbar variant="vendor" />

    <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 space-y-8">
      <header class="rounded-3xl border border-white/60 bg-white/70 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <span class="ml-badge bg-brand-100 text-brand-700">Vendor Profile</span>
            <h1 class="mt-2 text-3xl font-black text-ink-900 tracking-tight">Account Settings</h1>
            <p class="mt-1 text-sm text-ink-500">
              Manage your vendor identity and business details on Carboot@CMart.
            </p>
          </div>
          <button
            type="button"
            class="ml-btn-primary text-sm shrink-0"
            :disabled="loading"
            @click="showEditModal = true"
          >
            Edit Profile
          </button>
        </div>
      </header>

      <div v-if="loading" class="rounded-3xl border border-white/60 bg-white/80 p-8 animate-pulse space-y-4">
        <div class="h-4 w-1/3 rounded bg-ink-100"></div>
        <div class="h-4 w-2/3 rounded bg-ink-100"></div>
        <div class="h-4 w-1/2 rounded bg-ink-100"></div>
      </div>

      <div v-else-if="loadError" class="rounded-3xl border border-amber-200 bg-amber-50/70 p-8 text-center">
        <p class="text-sm text-amber-900 font-semibold">Unable to load your profile.</p>
        <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="loadProfile">Try Again</button>
      </div>

      <template v-else>
        <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
          <h2 class="text-xl font-extrabold text-ink-900 mb-6">Personal Information</h2>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Full Name</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ profile?.name || '—' }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Email</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ profile?.email || '—' }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Phone</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ profile?.phone_number || '—' }}</dd>
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
          <h2 class="text-xl font-extrabold text-ink-900 mb-6">Business Profile</h2>
          <div class="flex items-start gap-4 mb-6">
            <div class="h-16 w-16 rounded-2xl border border-ink-200 bg-ink-50 overflow-hidden flex items-center justify-center shrink-0">
              <img
                v-if="profile?.logo_url"
                :src="profile.logo_url"
                :alt="`${profile.business_name} logo`"
                class="h-full w-full object-cover"
              />
              <span v-else class="text-[10px] font-bold uppercase tracking-wide text-ink-400 text-center px-1">Logo</span>
            </div>
            <div class="min-w-0">
              <p class="text-lg font-bold text-ink-900 truncate">{{ profile?.business_name || '—' }}</p>
              <p v-if="profile?.business_category" class="text-sm text-brand-700 font-semibold mt-0.5">
                {{ profile.business_category }}
              </p>
            </div>
          </div>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Business Phone</dt>
              <dd class="mt-1 text-base font-semibold text-ink-900">{{ profile?.business_phone || '—' }}</dd>
            </div>
            <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 sm:col-span-2">
              <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">Business Description</dt>
              <dd class="mt-1 text-sm text-ink-700 whitespace-pre-line">
                {{ profile?.description || 'No description added yet.' }}
              </dd>
            </div>
          </dl>
        </section>
      </template>

      <section class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 sm:p-8 shadow-xl shadow-brand-900/5">
        <h2 class="text-xl font-extrabold text-ink-900 mb-2">Quick Actions</h2>
        <p class="text-sm text-ink-500 mb-6">Common vendor tasks from your profile.</p>
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
          <router-link to="/dashboard" class="ml-btn-ghost">View Dashboard</router-link>
          <router-link v-if="auth.isApprovedVendor" to="/vendor-booking" class="ml-btn-primary">Book a Space</router-link>
          <router-link v-else to="/dashboard" class="ml-btn-primary">Check Booking Status</router-link>
          <button type="button" class="ml-btn-ghost text-red-600 hover:bg-red-50" @click="logout">Logout</button>
        </div>
      </section>
    </div>

    <VendorProfileEditModal
      v-model="showEditModal"
      :profile="profile"
      :vendor-status="auth.vendorStatus"
      @saved="handleProfileSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import VendorProfileEditModal from '../../components/VendorProfileEditModal.vue';
import { useAuthStore } from '../../stores/auth';
import { useLogout } from '../../composables/useLogout';
import api from '../../services/api';

const auth = useAuthStore();
const { logout } = useLogout();

const profile = ref(null);
const loading = ref(false);
const loadError = ref(false);
const showEditModal = ref(false);

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

const loadProfile = async () => {
  loading.value = true;
  loadError.value = false;
  try {
    const { data } = await api.get('/vendor/profile');
    profile.value = data.profile;
  } catch (error) {
    console.error('Unable to load vendor profile:', error);
    loadError.value = true;
  } finally {
    loading.value = false;
  }
};

const handleProfileSaved = async (data) => {
  profile.value = data.profile;
  if (data.user) {
    auth.persistSession({ token: auth.token, user: data.user });
  } else {
    await auth.fetchMe();
  }
};

onMounted(loadProfile);
</script>
