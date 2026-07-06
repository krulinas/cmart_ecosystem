<template>
  <div class="min-h-screen bg-gray-50">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

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

      <section class="max-w-6xl mx-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
          <div class="relative pt-12 pb-8 px-8 text-center text-white z-10 bg-gradient-to-br from-brand-600 to-brand-400">
            <h2 class="text-3xl font-extrabold mb-3 tracking-tight">Share Your Voice</h2>
            <p class="text-lg opacity-90 font-medium max-w-2xl mx-auto">
              Help us improve the Carboot@CMart experience for shoppers and vendors alike.
            </p>
          </div>
          <div id="share-feedback" class="relative bg-white z-20 rounded-t-[2.5rem] -mt-6 p-8 border-b border-gray-100">
            <CommunityFeedback @submitted="onFeedbackSubmitted" />
          </div>
          <div ref="reviewsSection" class="p-8 md:p-12 bg-gray-50/50">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-gray-900">Community Reviews</h3>
              <p v-if="resultCountLabel" class="text-sm text-gray-500 mt-1">{{ resultCountLabel }}</p>
            </div>

            <div
              v-if="reviewSummary.total_reviews > 0"
              class="mb-6 rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm"
            >
              <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                <div class="shrink-0 text-center sm:text-left sm:min-w-[120px]">
                  <p class="text-xs font-bold uppercase tracking-wider text-brand-600 mb-1">Community rating</p>
                  <p class="text-4xl font-black text-gray-900 tabular-nums">
                    {{ reviewSummary.average_rating.toFixed(1) }}
                    <span class="text-lg font-bold text-gray-400">/ 5</span>
                  </p>
                  <p class="text-sm text-gray-500 mt-1">{{ reviewSummary.total_reviews }} reviews</p>
                  <p class="text-xs text-gray-400 mt-1">Based on visible public reviews.</p>
                </div>
                <div class="flex-1 space-y-2">
                  <div
                    v-for="star in [5, 4, 3, 2, 1]"
                    :key="`dist-${star}`"
                    class="flex items-center gap-3 text-sm"
                  >
                    <span class="w-12 shrink-0 font-semibold text-gray-600">{{ star }} ★</span>
                    <div class="h-2.5 flex-1 rounded-full bg-gray-100 overflow-hidden">
                      <div
                        class="h-full rounded-full bg-brand-500 transition-all duration-300"
                        :style="{ width: distributionPercent(star) }"
                      />
                    </div>
                    <span class="w-8 shrink-0 text-right text-gray-500 tabular-nums">
                      {{ reviewSummary.distribution[String(star)] ?? 0 }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-8 space-y-3">
              <label class="sr-only" for="review-search">Search reviews</label>
              <input
                id="review-search"
                v-model="reviewSearch"
                type="search"
                placeholder="Search reviews…"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-base text-gray-900 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                @input="debouncedFetchReviews"
              />

              <div class="flex flex-wrap items-center gap-3">
                <label class="sr-only" for="review-sort">Sort reviews</label>
                <select
                  id="review-sort"
                  v-model="reviewSort"
                  class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                  @change="fetchReviews(1)"
                >
                  <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>

                <label class="sr-only" for="review-rating">Filter by rating</label>
                <select
                  id="review-rating"
                  v-model="reviewRatingFilter"
                  class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                  @change="fetchReviews(1)"
                >
                  <option v-for="option in RATING_FILTERS" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>

                <label class="sr-only" for="review-type">Filter by reviewer type</label>
                <select
                  id="review-type"
                  v-model="reviewTypeFilter"
                  class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                  @change="fetchReviews(1)"
                >
                  <option v-for="option in REVIEWER_TYPE_FILTERS" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>

                <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 cursor-pointer select-none">
                  <input
                    v-model="reviewWithPhoto"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                    @change="fetchReviews(1)"
                  />
                  <span class="text-sm font-semibold text-gray-700">With photos</span>
                </label>

                <button
                  v-if="hasActiveReviewFilters"
                  type="button"
                  class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition"
                  @click="clearReviewFilters"
                >
                  Clear filters
                </button>
              </div>
            </div>

            <div v-if="loadingReviews" class="text-center py-8 text-gray-500">Loading reviews…</div>
            <div v-else-if="!reviewSummary.total_reviews && !hasActiveReviewFilters" class="text-center text-gray-500 italic py-8">
              No reviews yet. Be the first to share your experience!
            </div>
            <div v-else-if="!communityReviews.length" class="text-center py-10 rounded-2xl border border-dashed border-gray-200 bg-white">
              <p class="text-base font-semibold text-gray-700">No reviews match your filters.</p>
              <p class="text-sm text-gray-500 mt-2">Try clearing filters or choosing another rating.</p>
              <button
                type="button"
                class="mt-4 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-600 transition"
                @click="clearReviewFilters"
              >
                Clear filters
              </button>
            </div>
            <div v-else>
              <div class="mx-auto max-w-5xl space-y-4">
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
                        <span
                          v-if="reviewProofUrl(review)"
                          class="ml-1 text-xs font-semibold text-violet-700 bg-violet-50 px-2 py-0.5 rounded-full"
                        >
                          Photo attached
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
        v-if="!auth.isVendorUser"
        id="become-vendor"
        class="bg-brand-600 rounded-3xl p-10 text-center text-white"
      >
        <h2 class="text-2xl font-black mb-3">Ready to become a vendor?</h2>
        <p class="text-brand-100 mb-6 max-w-lg mx-auto">
          {{
            auth.isAuthenticated
              ? 'Apply for a vendor booth at our next carboot event and unlock your vendor dashboard.'
              : 'Create your free community account, then apply for a vendor booth at our next carboot event.'
          }}
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
          <template v-if="auth.isAuthenticated">
            <router-link
              :to="auth.bookingPathForUser()"
              class="bg-white text-brand-600 font-black py-3 px-8 rounded-xl hover:bg-brand-50 transition"
            >
              Become a Vendor
            </router-link>
            <router-link
              to="/community#share-feedback"
              class="border border-white/40 text-white font-bold py-3 px-8 rounded-xl hover:bg-white/10 transition"
            >
              Leave a Review
            </router-link>
          </template>
          <template v-else>
            <router-link
              :to="registerPathWithRedirect('/community#share-feedback')"
              class="bg-white text-brand-600 font-black py-3 px-8 rounded-xl hover:bg-brand-50 transition"
            >
              Create Account
            </router-link>
            <router-link
              :to="loginPathWithRedirect('/community#share-feedback')"
              class="border border-white/40 text-white font-bold py-3 px-8 rounded-xl hover:bg-white/10 transition"
            >
              Sign in to Leave a Review
            </router-link>
          </template>
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
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import CommunityFeedback from '../../components/CommunityFeedback.vue';
import EventDetailsModal from '../../components/EventDetailsModal.vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';
import { DEFAULT_EVENT_LOCATION, mapApiEventToCard } from '../../utils/eventDisplay';
import { vendorBookingLink } from '../../utils/vendorBooking';
import { loginPathWithRedirect, registerPathWithRedirect } from '../../utils/postAuthRedirect';
import { resolveStorageUrl } from '../../utils/imageUrl';

const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
  { value: 'highest_rating', label: 'Highest rating' },
  { value: 'lowest_rating', label: 'Lowest rating' },
];

const RATING_FILTERS = [
  { value: 'all', label: 'All ratings' },
  { value: '5', label: '5 stars' },
  { value: '4', label: '4 stars' },
  { value: '3', label: '3 stars' },
  { value: '2_or_below', label: '2 stars & below' },
];

const REVIEWER_TYPE_FILTERS = [
  { value: 'all', label: 'All reviewers' },
  { value: 'Shopper', label: 'Shopper' },
  { value: 'Vendor', label: 'Vendor' },
  { value: 'UUM Student', label: 'UUM Student' },
  { value: 'Local Resident', label: 'Local Resident' },
];

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
const reviewSummary = ref({
  average_rating: 0,
  total_reviews: 0,
  distribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 },
});
const reviewSearch = ref('');
const reviewSort = ref('newest');
const reviewRatingFilter = ref('all');
const reviewTypeFilter = ref('all');
const reviewWithPhoto = ref(false);
const selectedEvent = ref(null);
const showEventModal = ref(false);
const loadingEvents = ref(true);
const loadingReviews = ref(true);
const reviewsSection = ref(null);
const proofLightbox = ref({ open: false, url: null, caption: '' });

let searchDebounceTimer = null;

const hasActiveReviewFilters = computed(() =>
  reviewSearch.value.trim() !== ''
  || reviewRatingFilter.value !== 'all'
  || reviewTypeFilter.value !== 'all'
  || reviewWithPhoto.value
  || reviewSort.value !== 'newest',
);

const resultCountLabel = computed(() => {
  const { total, from, to } = reviewMeta.value;
  if (!total) return hasActiveReviewFilters.value ? 'Showing 0 reviews' : '';
  if (from && to) return `Showing ${from}–${to} of ${total} reviews`;
  return `Showing ${total} of ${total} reviews`;
});

const distributionPercent = (star) => {
  const count = reviewSummary.value.distribution[String(star)] ?? 0;
  const ratedTotal = Object.values(reviewSummary.value.distribution).reduce((sum, n) => sum + n, 0);
  if (!ratedTotal) return '0%';
  return `${Math.round((count / ratedTotal) * 100)}%`;
};

const buildReviewParams = (page = 1) => {
  const params = { page, sort: reviewSort.value };
  const search = reviewSearch.value.trim();
  if (search) params.search = search;
  if (reviewRatingFilter.value !== 'all') params.rating = reviewRatingFilter.value;
  if (reviewTypeFilter.value !== 'all') params.reviewer_type = reviewTypeFilter.value;
  if (reviewWithPhoto.value) params.with_photo = 1;
  return params;
};

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
    const response = await api.get('/feedbacks', { params: buildReviewParams(page) });
    const payload = response.data;

    communityReviews.value = Array.isArray(payload?.data) ? payload.data : [];
    if (payload?.summary) {
      reviewSummary.value = {
        average_rating: payload.summary.average_rating ?? 0,
        total_reviews: payload.summary.total_reviews ?? 0,
        distribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0, ...payload.summary.distribution },
      };
    }
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

const debouncedFetchReviews = () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => fetchReviews(1), 300);
};

const clearReviewFilters = () => {
  reviewSearch.value = '';
  reviewSort.value = 'newest';
  reviewRatingFilter.value = 'all';
  reviewTypeFilter.value = 'all';
  reviewWithPhoto.value = false;
  fetchReviews(1);
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
  await clearReviewFilters();
};

onMounted(async () => {
  await Promise.all([fetchEvents(), fetchReviews()]);
});

onBeforeUnmount(() => {
  clearTimeout(searchDebounceTimer);
});
</script>
