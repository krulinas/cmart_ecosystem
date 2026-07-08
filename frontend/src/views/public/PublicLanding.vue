<template>
  <div class="min-h-screen bg-white" data-testid="public-landing-root">
    <AppNavbar variant="public" />

    <!-- Hero -->
    <header class="relative min-h-[85vh] sm:min-h-[72vh] flex items-center justify-center overflow-hidden">
      <div
        class="absolute inset-0 w-full h-full will-change-transform motion-reduce:transform-none"
        :style="videoStyle()"
        aria-hidden="true"
      >
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900/90 via-blue-800/85 to-cyan-600/80"></div>
        <div class="absolute inset-0 bg-black/20"></div>
      </div>

      <div
        class="relative z-10 text-center text-white px-6 py-24 max-w-5xl mx-auto will-change-transform motion-reduce:transform-none"
        :style="contentStyle()"
      >
        <p class="text-base sm:text-lg uppercase tracking-[0.2em] font-bold text-cyan-200 mb-5 drop-shadow-md">
          CMart Kompleks Changlun
        </p>
        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black mb-6 drop-shadow-2xl leading-tight tracking-tight">
          Carboot@CMart
        </h1>
        <p class="text-xl sm:text-2xl mb-12 font-medium max-w-3xl mx-auto text-white/95 leading-relaxed drop-shadow">
          Malaysia's favourite weekend carboot market — browse preloved finds, support local vendors, and enjoy community events at CMart Kompleks Changlun.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
          <router-link
            to="/#events"
            class="w-full sm:w-auto bg-cyan-500 text-white font-extrabold py-4 px-12 min-h-[48px] rounded-full hover:bg-cyan-400 hover:scale-105 transition-all duration-300 shadow-[0_0_20px_rgba(6,182,212,0.4)] text-center text-lg"
          >
            View Upcoming Events
          </router-link>
          <router-link
            :to="bookingCtaLink"
            class="w-full sm:w-auto bg-white/10 backdrop-blur border-2 border-white/40 text-white font-bold py-4 px-12 min-h-[48px] rounded-full hover:bg-white/20 hover:scale-105 transition-all duration-300 text-center text-lg"
          >
            Book a Vendor Space
          </router-link>
        </div>
      </div>
    </header>

    <main>
      <!-- Upcoming Events -->
      <section id="events" ref="eventsSectionRef" data-testid="public-events-root" class="scroll-mt-24 py-16 sm:py-20 px-4 sm:px-6 bg-gray-50">
        <div class="max-w-7xl mx-auto">
          <div class="mb-10 max-w-3xl" :class="eventsHeaderClass('fade')">
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">What's On</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">Upcoming Carboot Events</h2>
            <p class="mt-2 text-base text-gray-600 max-w-xl leading-relaxed">Plan your visit — browse dates, times, and book a vendor space before slots fill up. For the full schedule, visit <router-link to="/calendar" class="font-semibold text-brand-600 hover:underline">Events</router-link>.</p>
          </div>

          <div v-if="loadingEvents" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="n in 3" :key="n" class="bg-white rounded-2xl border border-gray-100 p-6 animate-pulse h-80"></div>
          </div>

          <div v-else-if="!upcomingEvents.length" class="text-center py-16 bg-white rounded-2xl border border-gray-100">
            <p class="text-gray-500 text-lg">No upcoming events scheduled right now.</p>
            <p class="text-gray-400 text-sm mt-2">Check back soon or follow our news for the next market date.</p>
          </div>

          <UpcomingEventsCarousel
            v-else
            :events="upcomingEvents"
            @select="openEventDetails"
          />

          <div
            v-if="!loadingEvents && upcomingEvents.length"
            class="mt-10 rounded-2xl border border-brand-100 bg-white px-6 py-8 text-center shadow-sm"
          >
            <p class="text-gray-700 font-medium">Want to see all available dates?</p>
            <p class="mt-1 text-sm text-gray-500">Open Events for the full calendar and every upcoming carboot date.</p>
            <router-link
              to="/calendar"
              class="mt-4 inline-flex items-center justify-center gap-2 rounded-full border-2 border-brand-600 bg-white px-5 py-2.5 text-sm font-bold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            >
              <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              View all events
            </router-link>
          </div>
        </div>
      </section>

      <!-- Why Visit -->
      <section id="why-visit" class="scroll-mt-24 py-16 sm:py-20 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
          <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Why Visit</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">Carboot@CMart Is For Everyone</h2>
            <p class="mt-3 text-base text-gray-600 leading-relaxed">A relaxed weekend market where shoppers, families, and local entrepreneurs come together.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div
              v-for="benefit in visitBenefits"
              :key="benefit.title"
              class="bg-gray-50 rounded-2xl border border-gray-100 p-7 text-center hover:border-brand-200 hover:shadow-md transition-all duration-300"
            >
              <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4', benefit.iconBg]">
                <component :is="benefit.icon" />
              </div>
              <h3 class="text-lg font-bold text-gray-900 mb-2">{{ benefit.title }}</h3>
              <p class="text-base text-gray-600 leading-relaxed">{{ benefit.description }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Become a Vendor -->
      <section id="vendor" class="scroll-mt-24 py-16 sm:py-20 px-4 sm:px-6 bg-brand-600">
        <div class="max-w-7xl mx-auto">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div class="text-white">
              <span class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-2 block">For Vendors</span>
              <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-4">Become a Carboot Vendor</h2>
              <p class="text-brand-100 text-lg leading-relaxed mb-6">
                Turn your preloved items or small business into weekend income. Carboot@CMart offers affordable booth spaces, a steady flow of visitors, and a supportive community marketplace at CMart Kompleks Changlun.
              </p>
              <ul class="space-y-3 mb-8">
                <li v-for="point in vendorBenefits" :key="point" class="flex items-start gap-3 text-brand-50">
                  <svg class="w-5 h-5 text-brand-200 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{{ point }}</span>
                </li>
              </ul>
              <router-link
                :to="bookingCtaLink"
                class="inline-flex items-center bg-white text-brand-600 font-extrabold py-3.5 px-8 rounded-xl shadow-lg hover:bg-brand-50 hover:-translate-y-0.5 transition-all duration-300"
              >
                Book a Space
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </router-link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div
                v-for="card in vendorCards"
                :key="card.title"
                class="bg-white/10 backdrop-blur border border-white/20 rounded-2xl p-6 text-white"
              >
                <div class="text-3xl mb-3">{{ card.emoji }}</div>
                <h3 class="font-bold text-lg mb-1">{{ card.title }}</h3>
                <p class="text-sm text-brand-100">{{ card.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- News & Updates -->
      <section id="news" data-testid="public-news-root" class="scroll-mt-24 py-16 sm:py-20 px-4 sm:px-6 bg-gray-50">
        <div class="max-w-7xl mx-auto">
          <div class="mb-10">
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Stay Informed</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">News &amp; Updates</h2>
            <p class="mt-2 text-base text-gray-600 max-w-xl leading-relaxed">Announcements, promotions, event updates, and community highlights from Carboot@CMart.</p>
          </div>

          <div v-if="loadingNews" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="n in 3" :key="n" class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-pulse">
              <div class="h-44 bg-gray-200"></div>
              <div class="p-6 space-y-3">
                <div class="h-4 w-24 bg-gray-200 rounded"></div>
                <div class="h-6 w-full bg-gray-200 rounded"></div>
                <div class="h-4 w-full bg-gray-100 rounded"></div>
              </div>
            </div>
          </div>

          <div v-else-if="!newsPosts.length" class="text-center py-16 bg-white rounded-2xl border border-gray-100">
            <p class="text-gray-500">No news posts yet. Check back for announcements and updates.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <article
              v-for="post in newsPosts.slice(0, 6)"
              :key="post.id"
              data-testid="public-news-card"
              tabindex="0"
              role="button"
              :aria-label="`View news: ${post.title}`"
              class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 hover:border-brand-200 hover:ring-2 hover:ring-brand-500/15 transition-all duration-300 flex flex-col cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 group"
              @click="openNewsDetails(post)"
              @keydown.enter.prevent="openNewsDetails(post)"
              @keydown.space.prevent="openNewsDetails(post)"
            >
              <div
                v-if="post.bannerUrl || post.images?.length"
                class="pointer-events-auto shrink-0"
                @click.stop
              >
                <MediaImageGallery
                  :images="post.images || []"
                  :alt-text="`${post.title} news banner`"
                  enable-lightbox
                  compact
                />
              </div>
              <div v-else class="h-[140px] bg-gradient-to-br from-brand-100 to-brand-50 flex items-center justify-center pointer-events-none">
                <span class="text-brand-400 font-black text-4xl">@</span>
              </div>
              <div class="p-6 flex flex-col flex-grow pointer-events-none">
                <div class="flex items-center justify-between gap-2 mb-3">
                  <span class="text-xs font-bold uppercase tracking-wider text-brand-600 bg-brand-50 px-2.5 py-1 rounded-full">
                    {{ post.category }}
                  </span>
                  <time v-if="post.publishedDateShort" class="text-xs text-gray-400 font-medium">{{ post.publishedDateShort }}</time>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2">{{ post.title }}</h3>
                <p class="text-sm text-gray-600 leading-relaxed line-clamp-3 flex-grow">{{ post.excerpt }}</p>
                <p class="text-xs text-brand-600 font-semibold mt-3 opacity-0 group-hover:opacity-100 transition-opacity">
                  Click to read full article
                </p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <!-- About -->
      <section id="about" class="scroll-mt-24 py-16 sm:py-20 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">About Us</span>
              <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight mb-4">Carboot@CMart &amp; CMart Kompleks Changlun</h2>
              <p class="text-gray-600 leading-relaxed mb-4">
                Carboot@CMart is a community-driven weekend carboot market held at CMart Kompleks Changlun in Changlun, Kedah. We bring together local vendors, shoppers, students, and families for a vibrant marketplace experience every weekend.
              </p>
              <p class="text-gray-600 leading-relaxed mb-6">
                Whether you are hunting for preloved bargains, supporting micro-entrepreneurs, or looking for a fun family outing, Carboot@CMart offers an welcoming space to connect, shop, and sell.
              </p>
              <router-link
                to="/community"
                class="inline-flex items-center text-brand-600 font-bold hover:text-brand-700 transition"
              >
                Explore the Community Portal
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </router-link>
            </div>
            <div class="bg-gray-50 rounded-3xl border border-gray-100 p-8 sm:p-10">
              <h3 class="text-xl font-bold text-gray-900 mb-6">Visit Information</h3>
              <dl class="space-y-5">
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Location</dt>
                  <dd class="text-gray-800 font-medium">CMart Kompleks Changlun, Changlun, Kedah</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Market Days</dt>
                  <dd class="text-gray-800 font-medium">Weekend carboot events — see calendar for dates</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">For Vendors</dt>
                  <dd class="text-gray-800 font-medium">Register an account, get approved, then book your booth space online</dd>
                </div>
                <div>
                  <dt class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Contact</dt>
                  <dd class="text-gray-800 font-medium">enquiries@cmart.com.my</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </section>
    </main>

    <SiteFooter />

    <EventDetailsModal
      v-model="showEventModal"
      :event="selectedEvent"
      :booking-link="vendorBookingLink(selectedEvent?.id, auth)"
      booking-label="Book Space"
    />

    <NewsDetailsModal
      v-model="showNewsModal"
      :post="selectedNews"
    />
  </div>
</template>

<script setup>
import { ref, computed, h, onMounted } from 'vue';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import SiteFooter from '../../components/layout/SiteFooter.vue';
import EventDetailsModal from '../../components/EventDetailsModal.vue';
import NewsDetailsModal from '../../components/NewsDetailsModal.vue';
import MediaImageGallery from '../../components/MediaImageGallery.vue';
import UpcomingEventsCarousel from '../../components/public/UpcomingEventsCarousel.vue';
import api from '../../services/api';
import { mapApiEventsToUpcomingCards } from '../../utils/eventDisplay';
import { mapApiNewsToCard } from '../../utils/newsDisplay';
import { vendorBookingLink } from '../../utils/vendorBooking';
import { useAuthStore } from '../../stores/auth';
import { useScrollReveal } from '../../composables/useScrollReveal';
import { useHeroParallax } from '../../composables/useHeroParallax';

const auth = useAuthStore();
const { contentStyle, videoStyle } = useHeroParallax();

const bookingCtaLink = computed(() => vendorBookingLink(null, auth));

const upcomingEvents = ref([]);
const newsPosts = ref([]);
const selectedEvent = ref(null);
const showEventModal = ref(false);
const selectedNews = ref(null);
const showNewsModal = ref(false);
const loadingEvents = ref(true);
const loadingNews = ref(true);

const { targetRef: eventsSectionRef, isVisible: eventsVisible, revealClass: eventsHeaderClass } = useScrollReveal({ threshold: 0.08 });

const openEventDetails = (event) => {
  selectedEvent.value = event;
  showEventModal.value = true;
};

const openNewsDetails = (post) => {
  selectedNews.value = post;
  showNewsModal.value = true;
};

const visitBenefits = [
  {
    title: 'Shop Preloved Items',
    description: 'Discover unique bargains, vintage finds, and everyday essentials at friendly prices.',
    iconBg: 'bg-brand-50 text-brand-600',
    icon: () => h('svg', { class: 'w-7 h-7', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' }),
    ]),
  },
  {
    title: 'Support Local Sellers',
    description: 'Every purchase helps micro-entrepreneurs and weekend traders in the Changlun community.',
    iconBg: 'bg-emerald-50 text-emerald-600',
    icon: () => h('svg', { class: 'w-7 h-7', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' }),
    ]),
  },
  {
    title: 'Enjoy Weekend Activities',
    description: 'Bring the family for a relaxed market atmosphere with food, fun, and community spirit.',
    iconBg: 'bg-amber-50 text-amber-600',
    icon: () => h('svg', { class: 'w-7 h-7', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }),
    ]),
  },
  {
    title: 'Promote Sustainable Living',
    description: 'Give preloved goods a second life and reduce waste through reuse and resale.',
    iconBg: 'bg-violet-50 text-violet-600',
    icon: () => h('svg', { class: 'w-7 h-7', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' }),
    ]),
  },
];

const vendorBenefits = [
  'Affordable booth spaces with flexible sizing options',
  'Steady weekend foot traffic from shoppers and locals',
  'Simple online booking and approval process',
  'Join a growing community of micro-entrepreneurs',
];

const vendorCards = [
  { emoji: '🛒', title: 'Easy Setup', description: 'Book online, get approved, and set up your booth on event day.' },
  { emoji: '📈', title: 'Grow Your Brand', description: 'Reach new customers every weekend at a well-known location.' },
  { emoji: '🤝', title: 'Community Support', description: 'Connect with fellow vendors and regular market visitors.' },
  { emoji: '♻️', title: 'Circular Economy', description: 'Sell preloved goods and contribute to sustainable trade.' },
];

const fetchEvents = async () => {
  loadingEvents.value = true;
  try {
    const { data } = await api.get('/events');
    upcomingEvents.value = mapApiEventsToUpcomingCards(data);
  } catch (error) {
    console.error('Failed to load events:', error);
  } finally {
    loadingEvents.value = false;
  }
};

const fetchNews = async () => {
  loadingNews.value = true;
  try {
    const { data } = await api.get('/news');
    const posts = Array.isArray(data) ? data : [];
    newsPosts.value = posts.map(mapApiNewsToCard);
  } catch (error) {
    console.error('Failed to load news:', error);
  } finally {
    loadingNews.value = false;
  }
};

onMounted(() => {
  fetchEvents();
  fetchNews();
});
</script>
