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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-20 -mt-8">
      <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-brand-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-brand-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Vibrant Community</h2>
          <p class="text-base text-gray-600 leading-relaxed">Shoppers, vendors, and locals united at CMart Kompleks Changlun every weekend.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-emerald-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Vendor Marketplace</h2>
          <p class="text-base text-gray-600 leading-relaxed">Browse unique finds from approved micro-businesses and weekend traders.</p>
          <router-link to="/marketplace" class="inline-block mt-4 text-base font-bold text-brand-600 hover:underline">
            Browse Reuse Marketplace →
          </router-link>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <div class="bg-amber-50 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          </div>
          <h2 class="text-lg font-black text-gray-900 mb-2">Community Reviews</h2>
          <p class="text-base text-gray-600 leading-relaxed">Real feedback from visitors shaping a better carboot experience for everyone.</p>
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
          <article
            v-for="event in upcomingEvents.slice(0, 4)"
            :key="event.id"
            tabindex="0"
            role="button"
            :aria-label="`View details for ${event.title}`"
            class="rounded-xl border border-gray-100 overflow-hidden hover:border-brand-200 hover:shadow-md hover:ring-2 hover:ring-brand-500/10 transition group cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 bg-white"
            @click="openEventDetails(event)"
            @keydown.enter.prevent="openEventDetails(event)"
            @keydown.space.prevent="openEventDetails(event)"
          >
            <img
              v-if="event.posterUrl"
              :src="event.posterUrl"
              :alt="`${event.title} poster preview`"
              class="w-full h-[140px] object-cover object-top border-b border-gray-100 pointer-events-none"
            />
            <div class="flex items-center justify-between p-4 gap-3">
              <div class="flex items-center gap-4 min-w-0 pointer-events-none">
                <div class="bg-gray-100 text-gray-700 rounded-lg p-2 text-center min-w-[60px] group-hover:bg-brand-500 group-hover:text-white transition">
                  <span class="block text-2xl font-black leading-none">{{ event.day }}</span>
                  <span class="block text-[10px] uppercase font-bold">{{ event.month }}</span>
                </div>
                <div class="min-w-0">
                  <h3 class="font-bold text-gray-900 truncate">{{ event.title }}</h3>
                  <p class="text-xs text-gray-500">{{ event.time }}</p>
                  <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full mt-1 inline-block', event.statusClass]">
                    {{ event.status }}
                  </span>
                </div>
              </div>
              <router-link
                :to="vendorBookingLink(event.id, auth)"
                class="text-sm font-bold bg-brand-100 text-brand-700 px-4 py-2 rounded-lg hover:bg-brand-200 transition shrink-0"
                @click.stop
              >
                {{ auth.isApprovedVendor ? 'Book' : 'Learn More' }}
              </router-link>
            </div>
          </article>
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
          <div ref="reviewsSection" class="p-8 md:p-12 bg-gray-50/50">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-6">
              <div>
                <h3 class="text-xl font-bold text-gray-900">
                  Community Reviews
                  <span class="text-brand-600">({{ reviewMeta.total }})</span>
                </h3>
                <p v-if="reviewMeta.total > 0" class="text-sm text-gray-500 mt-1">
                  Showing {{ reviewMeta.from }}–{{ reviewMeta.to }} of {{ reviewMeta.total }}
                </p>
              </div>
            </div>

            <div v-if="loadingReviews" class="text-center py-8 text-gray-500">Loading reviews…</div>
            <div v-else-if="!communityReviews.length" class="text-center text-gray-500 italic py-8">
              No reviews yet. Be the first to share your experience!
            </div>
            <div v-else>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <article
                  v-for="review in communityReviews"
                  :key="review.id"
                  class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col"
                >
                  <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="bg-brand-100 text-brand-600 rounded-full h-10 w-10 shrink-0 flex items-center justify-center font-black">
                        {{ reviewInitial(review) }}
                      </div>
                      <div class="min-w-0">
                        <div class="font-bold text-gray-900 text-base truncate">{{ reviewUserName(review) }}</div>
                        <span
                          v-if="reviewRole(review)"
                          class="text-xs font-semibold text-brand-700 bg-brand-50 px-2 py-0.5 rounded-full"
                        >
                          {{ reviewRole(review) }}
                        </span>
                      </div>
                    </div>
                    <div
                      v-if="reviewRating(review)"
                      class="shrink-0 text-sm text-brand-500"
                      :aria-label="`${reviewRating(review)} out of 5 stars`"
                    >
                      <span v-for="star in 5" :key="`${review.id}-star-${star}`">
                        {{ star <= reviewRating(review) ? '★' : '☆' }}
                      </span>
                    </div>
                  </div>

                  <p v-if="reviewComment(review)" class="text-gray-600 text-base leading-relaxed line-clamp-4 flex-1">
                    "{{ reviewComment(review) }}"
                  </p>

                  <div
                    v-if="reviewOfficialReply(review)"
                    class="mt-3 rounded-xl border border-brand-100 bg-brand-50/60 px-3 py-2.5"
                  >
                    <p class="text-xs font-bold uppercase tracking-wide text-brand-700 mb-1">CMart Official Reply</p>
                    <p class="text-base text-gray-700 leading-relaxed">{{ reviewOfficialReply(review) }}</p>
                  </div>

                  <button
                    v-if="reviewProofUrl(review)"
                    type="button"
                    class="mt-3 self-start rounded-lg overflow-hidden border border-gray-200 hover:border-brand-300 hover:ring-2 hover:ring-brand-500/20 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    @click="openProofLightbox(reviewProofUrl(review), reviewUserName(review))"
                  >
                    <img
                      :src="reviewProofUrl(review)"
                      :alt="`Photo proof from ${reviewUserName(review)}`"
                      class="h-16 w-16 object-cover"
                      loading="lazy"
                    />
                  </button>
                </article>
              </div>

              <nav
                v-if="reviewMeta.last_page > 1"
                class="mt-8 flex flex-wrap items-center justify-center gap-2"
                aria-label="Review pagination"
              >
                <button
                  type="button"
                  class="px-4 py-2 rounded-lg text-sm font-bold border transition disabled:opacity-40 disabled:cursor-not-allowed"
                  :class="reviewMeta.current_page <= 1 ? 'border-gray-200 text-gray-400' : 'border-brand-200 text-brand-700 hover:bg-brand-50'"
                  :disabled="reviewMeta.current_page <= 1 || loadingReviews"
                  @click="goToReviewPage(reviewMeta.current_page - 1)"
                >
                  Previous
                </button>
                <span class="text-sm text-gray-600 px-2">
                  Page {{ reviewMeta.current_page }} of {{ reviewMeta.last_page }}
                </span>
                <button
                  type="button"
                  class="px-4 py-2 rounded-lg text-sm font-bold border transition disabled:opacity-40 disabled:cursor-not-allowed"
                  :class="reviewMeta.current_page >= reviewMeta.last_page ? 'border-gray-200 text-gray-400' : 'border-brand-200 text-brand-700 hover:bg-brand-50'"
                  :disabled="reviewMeta.current_page >= reviewMeta.last_page || loadingReviews"
                  @click="goToReviewPage(reviewMeta.current_page + 1)"
                >
                  Next
                </button>
              </nav>
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

    <EventDetailsModal
      v-model="showEventModal"
      :event="selectedEvent"
      :booking-link="vendorBookingLink(selectedEvent?.id, auth)"
      :booking-label="auth.isApprovedVendor ? 'Book Space' : 'Learn More'"
    />

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="proofLightbox.open"
          class="fixed inset-0 z-[100] flex items-center justify-center p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Photo proof preview"
          @keydown.esc="closeProofLightbox"
        >
          <div
            class="absolute inset-0 bg-black/70 backdrop-blur-sm"
            aria-hidden="true"
            @click="closeProofLightbox"
          />
          <div class="relative z-10 max-w-3xl w-full">
            <button
              type="button"
              class="absolute -top-12 right-0 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-md hover:bg-white transition"
              aria-label="Close photo preview"
              @click="closeProofLightbox"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <img
              :src="proofLightbox.url"
              :alt="`Photo proof from ${proofLightbox.caption}`"
              class="w-full max-h-[80vh] object-contain rounded-xl bg-white shadow-2xl"
            />
            <p v-if="proofLightbox.caption" class="mt-3 text-center text-sm text-white/90">
              Photo proof from {{ proofLightbox.caption }}
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import CommunityFeedback from '../../components/CommunityFeedback.vue';
import EventDetailsModal from '../../components/EventDetailsModal.vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';
import { DEFAULT_EVENT_LOCATION, mapApiEventToCard } from '../../utils/eventDisplay';
import { vendorBookingLink } from '../../utils/vendorBooking';
import { resolveStorageUrl } from '../../utils/imageUrl';

const auth = useAuthStore();
const toast = useToast();

const upcomingEvents = ref([]);
const communityReviews = ref([]);
const reviewMeta = ref({
  current_page: 1,
  per_page: 6,
  total: 0,
  last_page: 1,
  from: 0,
  to: 0,
});
const selectedEvent = ref(null);
const showEventModal = ref(false);
const loadingEvents = ref(true);
const loadingReviews = ref(true);
const reviewsSection = ref(null);
const proofLightbox = ref({ open: false, url: null, caption: '' });

const openEventDetails = (event) => {
  selectedEvent.value = event;
  showEventModal.value = true;
};

const fetchEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await api.get('/events');
    const events = Array.isArray(data) ? data : [];
    upcomingEvents.value = events.map((ev) => mapApiEventToCard(ev, DEFAULT_EVENT_LOCATION));
  } catch (error) {
    console.error('Failed to load community events:', error);
  } finally {
    loadingEvents.value = false;
  }
};

const fetchReviews = async (page = 1) => {
  loadingReviews.value = true;
  try {
    const response = await api.get('/feedbacks', { params: { page } });
    const payload = response.data;

    communityReviews.value = Array.isArray(payload?.data) ? payload.data : [];
    reviewMeta.value = {
      current_page: payload?.current_page ?? 1,
      per_page: payload?.per_page ?? 6,
      total: payload?.total ?? communityReviews.value.length,
      last_page: payload?.last_page ?? 1,
      from: payload?.from ?? (communityReviews.value.length ? 1 : 0),
      to: payload?.to ?? communityReviews.value.length,
    };
  } catch (error) {
    console.error('Failed to fetch reviews:', error);
  } finally {
    loadingReviews.value = false;
  }
};

const goToReviewPage = async (page) => {
  if (page < 1 || page > reviewMeta.value.last_page) return;
  await fetchReviews(page);
  reviewsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const reviewUserName = (review) => review.user_name || review.user?.name || 'Community Member';
const reviewRole = (review) => review.role || review.reviewer_role || null;
const reviewComment = (review) => review.comment || review.comments || '';
const reviewRating = (review) => review.rating || null;
const reviewProofUrl = (review) => resolveStorageUrl(review.proof_url || review.media_path || null);
const reviewOfficialReply = (review) => review.official_reply?.text || null;
const reviewInitial = (review) => reviewUserName(review).charAt(0).toUpperCase();

const openProofLightbox = (url, caption) => {
  proofLightbox.value = { open: true, url, caption };
};

const closeProofLightbox = () => {
  proofLightbox.value = { open: false, url: null, caption: '' };
};

const onFeedbackSubmitted = async () => {
  toast.success('Feedback submitted successfully!');
  await fetchReviews(1);
};

onMounted(async () => {
  await Promise.all([fetchEvents(), fetchReviews()]);
});
</script>
