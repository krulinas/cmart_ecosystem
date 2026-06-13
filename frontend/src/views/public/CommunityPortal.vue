<template>
  <div class="min-h-screen bg-gray-50">
    <AppNavbar :variant="auth.isCommunityMember ? 'vendor' : 'public'" />

    <header class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 pt-16 pb-20 px-6 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-10 pointer-events-none"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"
      ></div>
      <div class="max-w-7xl mx-auto relative z-10 text-center text-white">
        <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-3">Carboot@CMart Community</p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Explore Our Community</h1>
        <p class="text-lg text-brand-100 max-w-2xl mx-auto mb-8">
          Discover local vendors, share your experience, and stay connected with Malaysia's favourite carboot marketplace.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
          <router-link
            v-if="!auth.isAuthenticated"
            to="/register"
            class="bg-white text-brand-600 font-black py-3 px-8 rounded-xl shadow-lg hover:-translate-y-0.5 transition"
          >
            Join the Community
          </router-link>
          <router-link
            to="/calendar"
            class="bg-white/10 backdrop-blur border border-white/30 text-white font-bold py-3 px-8 rounded-xl hover:bg-white/20 transition"
          >
            View Event Calendar
          </router-link>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-16 space-y-20 -mt-8">
      <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-brand-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-brand-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Vibrant Community</h2>
          <p class="text-sm text-gray-600">Shoppers, vendors, and locals united at CMart Kompleks Changlun every weekend.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-emerald-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Vendor Marketplace</h2>
          <p class="text-sm text-gray-600">Browse unique finds from approved micro-businesses and weekend traders.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-amber-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Community Reviews</h2>
          <p class="text-sm text-gray-600">Real feedback from visitors shaping a better carboot experience for everyone.</p>
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
        <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4">
          <div>
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Upcoming Events</span>
            <h2 class="text-2xl font-black text-gray-900">Market Activity</h2>
          </div>
          <router-link to="/calendar" class="text-sm font-bold text-brand-600 hover:underline">Full Calendar →</router-link>
        </div>

        <div v-if="loadingEvents" class="text-center py-10 text-gray-500">Loading events…</div>
        <div v-else-if="!upcomingEvents.length" class="text-center py-10 text-gray-500 italic">
          No upcoming events scheduled. Check back soon!
        </div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div
            v-for="event in upcomingEvents.slice(0, 4)"
            :key="event.id"
            class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-brand-200 hover:bg-brand-50/50 transition group"
          >
            <div class="flex items-center gap-4">
              <div class="bg-gray-100 text-gray-700 rounded-lg p-2 text-center min-w-[60px] group-hover:bg-brand-500 group-hover:text-white transition">
                <span class="block text-2xl font-black leading-none">{{ event.day }}</span>
                <span class="block text-[10px] uppercase font-bold">{{ event.month }}</span>
              </div>
              <div>
                <h3 class="font-bold text-gray-900">{{ event.title }}</h3>
                <p class="text-xs text-gray-500">{{ event.time }}</p>
                <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full mt-1 inline-block', event.statusClass]">
                  {{ event.status }}
                </span>
              </div>
            </div>
            <router-link
              :to="bookingLink"
              class="text-sm font-bold bg-brand-100 text-brand-700 px-4 py-2 rounded-lg hover:bg-brand-200 transition shrink-0"
            >
              {{ auth.isApprovedVendor ? 'Book' : 'Learn More' }}
            </router-link>
          </div>
        </div>
      </section>

      <section class="max-w-5xl mx-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
          <div class="relative pt-12 pb-8 px-8 text-center text-white z-10 bg-gradient-to-br from-brand-600 to-brand-400">
            <h2 class="text-3xl font-extrabold mb-3 tracking-tight">Share Your Voice</h2>
            <p class="text-lg opacity-90 font-medium max-w-2xl mx-auto">
              Help us improve the Carboot@CMart experience for shoppers and vendors alike.
            </p>
          </div>
          <div class="relative bg-white z-20 rounded-t-[2.5rem] -mt-6 p-8 border-b border-gray-100">
            <CommunityFeedback @submitted="onFeedbackSubmitted" />
          </div>
          <div class="p-8 md:p-12 bg-gray-50/50">
            <h3 class="text-xl font-bold text-gray-900 mb-6">
              Community Reviews
              <span class="text-brand-600">({{ communityReviews.length }})</span>
            </h3>
            <div v-if="loadingReviews" class="text-center py-8 text-gray-500">Loading reviews…</div>
            <div v-else-if="!communityReviews.length" class="text-center text-gray-500 italic py-8">
              No reviews yet. Be the first to share your experience!
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div
                v-for="review in communityReviews.slice(0, 4)"
                :key="review.id"
                class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100"
              >
                <div class="flex items-center space-x-3 mb-3">
                  <div class="bg-brand-100 text-brand-600 rounded-full h-10 w-10 flex items-center justify-center font-black">
                    {{ (review.user?.name || 'M').charAt(0).toUpperCase() }}
                  </div>
                  <div class="font-bold text-gray-900 text-sm">{{ review.user?.name || 'Community Member' }}</div>
                </div>
                <p v-if="review.comments" class="text-gray-600 text-sm line-clamp-3">"{{ review.comments }}"</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        v-if="!auth.isAuthenticated"
        class="bg-brand-600 rounded-3xl p-10 text-center text-white"
      >
        <h2 class="text-2xl font-black mb-3">Ready to become a vendor?</h2>
        <p class="text-brand-100 mb-6 max-w-lg mx-auto">
          Create your free community account, then apply for a vendor booth at our next carboot event.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
          <router-link to="/register" class="bg-white text-brand-600 font-black py-3 px-8 rounded-xl hover:bg-brand-50 transition">
            Create Account
          </router-link>
          <router-link to="/login" class="border border-white/40 text-white font-bold py-3 px-8 rounded-xl hover:bg-white/10 transition">
            Vendor Login
          </router-link>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import CommunityFeedback from '../../components/CommunityFeedback.vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';

const auth = useAuthStore();
const toast = useToast();

const upcomingEvents = ref([]);
const communityReviews = ref([]);
const loadingEvents = ref(true);
const loadingReviews = ref(true);

const bookingLink = computed(() => {
  if (auth.isApprovedVendor) return '/vendor-booking';
  if (auth.isAuthenticated) return '/dashboard';
  return '/login?redirect=/vendor-booking';
});

const statusClassForEvent = (status) => {
  if (status === 'Available') return 'bg-emerald-100 text-emerald-800';
  if (status === 'Almost Full') return 'bg-amber-100 text-amber-800';
  return 'bg-gray-100 text-gray-700';
};

const fetchEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await api.get('/events');
    const events = Array.isArray(data) ? data : [];
    upcomingEvents.value = events.map((ev) => {
      const start = new Date(ev.starts_at);
      return {
        id: ev.id,
        day: String(start.getDate()),
        month: start.toLocaleString('en-GB', { month: 'short' }),
        title: ev.title,
        time: start.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit' }),
        status: ev.status,
        statusClass: statusClassForEvent(ev.status),
      };
    });
  } catch (error) {
    console.error('Failed to load community events:', error);
  } finally {
    loadingEvents.value = false;
  }
};

const fetchReviews = async () => {
  loadingReviews.value = true;
  try {
    const response = await api.get('/feedbacks');
    communityReviews.value = response.data.data || response.data;
  } catch (error) {
    console.error('Failed to fetch reviews:', error);
  } finally {
    loadingReviews.value = false;
  }
};

const onFeedbackSubmitted = async () => {
  toast.success('Feedback submitted successfully!');
  await fetchReviews();
};

onMounted(async () => {
  await Promise.all([fetchEvents(), fetchReviews()]);
});
</script>
