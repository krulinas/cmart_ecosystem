<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white/95 backdrop-blur-md shadow-md sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        
        <router-link to="/" class="flex items-center">
          <img src="/cmart_logo.png" alt="Carboot@CMart Logo" class="h-12 sm:h-14 w-auto object-contain hover:opacity-90 transition-opacity" />
        </router-link>

        <div class="hidden md:flex items-center space-x-6">
          <router-link to="/" class="text-gray-600 hover:text-brand-600 font-semibold transition">Home</router-link>
          <router-link to="/calendar" class="text-gray-600 hover:text-brand-600 font-semibold transition">Our Calendar</router-link>

          <router-link
            v-if="auth.isAuthenticated"
            :to="auth.homeForUser()"
            class="text-gray-600 hover:text-brand-600 font-semibold transition"
          >Workspace</router-link>

          <router-link
            v-if="!auth.isAuthenticated"
            to="/login"
            class="bg-brand-500 text-white px-5 py-2 rounded-lg shadow hover:bg-brand-600 transition text-sm font-bold"
          >Login</router-link>

          <button
            v-else
            @click="logout"
            class="bg-brand-500 text-white px-5 py-2 rounded-lg shadow hover:bg-brand-600 transition text-sm font-bold"
          >Logout</button>
        </div>

        <div class="md:hidden flex items-center">
          <button @click="toggleMobileMenu" class="text-gray-800 hover:text-brand-600 focus:outline-none transition" aria-label="Toggle menu">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform -translate-y-4 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform -translate-y-4 opacity-0"
      >
        <div v-show="isMobileMenuOpen" class="md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg">
          <div class="px-6 py-4 flex flex-col space-y-4">
            <router-link to="/" @click="isMobileMenuOpen = false" class="text-gray-700 hover:text-brand-600 font-semibold text-lg">Home</router-link>
            <router-link to="/calendar" @click="isMobileMenuOpen = false" class="text-gray-700 hover:text-brand-600 font-semibold text-lg">Our Calendar</router-link>

            <router-link
              v-if="auth.isAuthenticated"
              :to="auth.homeForUser()"
              @click="isMobileMenuOpen = false"
              class="text-gray-700 hover:text-brand-600 font-semibold text-lg"
            >Workspace</router-link>

            <hr class="border-gray-200">

            <router-link
              v-if="!auth.isAuthenticated"
              to="/login"
              @click="isMobileMenuOpen = false"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow hover:bg-brand-600 transition"
            >Vendor Portal / Login</router-link>

            <button
              v-else
              @click="() => { logout(); isMobileMenuOpen = false; }"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow w-full hover:bg-brand-600 transition"
            >Logout</button>
          </div>
        </div>
      </transition>
    </nav>

    <header class="relative min-h-[88vh] sm:min-h-[92vh] flex items-center justify-center overflow-hidden">
      <div class="absolute inset-0 w-full h-full will-change-transform motion-reduce:transform-none" :style="videoStyle()" aria-hidden="true">
        <video ref="heroVideoRef" class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline preload="metadata" poster="https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=1920&auto=format&fit=crop">
          <source src="https://assets.mixkit.co/videos/preview/mixkit-people-walking-in-a-street-market-42783-large.mp4" type="video/mp4" />
        </video>
        <div class="absolute inset-0 bg-gradient-to-br from-brand-900/90 via-brand-800/80 to-brand-600/70"></div>
        <div class="absolute inset-0 bg-black/30"></div>
      </div>

      <div class="relative z-10 text-center text-white px-6 py-20 max-w-4xl mx-auto will-change-transform motion-reduce:transform-none" :style="contentStyle()">
        <p class="text-sm sm:text-base uppercase tracking-[0.2em] font-bold text-brand-200 mb-4 drop-shadow-md">
          Changlun · Weekend Marketplace
        </p>
        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black mb-6 drop-shadow-2xl leading-tight tracking-tight">
          Carboot@CMart
        </h1>
        <p class="text-lg sm:text-xl lg:text-2xl mb-10 font-medium max-w-2xl mx-auto text-white/95 leading-relaxed drop-shadow">
          Discover weekend deals, join the vibrant community, or launch your micro-business at Malaysia's favorite carboot market.
        </p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4 sm:gap-6">
          <router-link to="/vendor-booking" class="w-full sm:w-auto bg-brand-500 text-white font-extrabold py-4 px-10 rounded-full hover:bg-brand-400 hover:scale-105 transition-all duration-300 shadow-[0_0_20px_rgba(var(--color-brand-500),0.4)] text-center text-lg">
            Book a Space Now
          </router-link>
          <router-link to="/register" class="w-full sm:w-auto bg-white/10 backdrop-blur-md border border-white/30 text-white font-bold py-4 px-10 rounded-full shadow-lg hover:bg-white/20 transition-all duration-300 text-center text-lg">
            Explore Community
          </router-link>
        </div>
      </div>

      <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center text-white/70 text-sm font-semibold animate-bounce motion-reduce:animate-none" aria-hidden="true">
        <span>Scroll Down</span>
        <svg class="w-6 h-6 mt-2 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </div>
    </header>

    <main class="py-16 px-4 sm:px-6 max-w-7xl mx-auto space-y-24">
      
      <section ref="newsSectionRef">
        <div class="flex justify-between items-end mb-8" :class="newsHeaderRevealClass('fade')">
          <div>
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">What's Trending</span>
            <h2 class="text-4xl font-extrabold text-gray-900 tracking-tight">
              Latest Updates
            </h2>
          </div>
          <div class="hidden md:flex space-x-3">
            <button @click="scrollNews(-1)" class="p-3 rounded-full bg-white shadow-md text-brand-600 hover:bg-brand-50 hover:scale-110 transition-all focus:outline-none border border-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button @click="scrollNews(1)" class="p-3 rounded-full bg-white shadow-md text-brand-600 hover:bg-brand-50 hover:scale-110 transition-all focus:outline-none border border-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
            </button>
          </div>
        </div>
        
        <div class="relative -mx-4 sm:mx-0">
          <div ref="newsScrollContainer" class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-8 px-4 sm:px-0 hide-scrollbar scroll-smooth">
            <div
              v-for="(item, index) in latestNews"
              :key="item.id"
              class="min-w-[85vw] sm:min-w-[350px] md:min-w-[400px] snap-center bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl transition-all duration-500 ease-out group cursor-pointer"
              :class="staggerCardClass(newsCardsVisible, index)"
              :style="staggerCardStyle(newsCardsVisible, index)"
            >
              <div class="h-56 bg-gray-200 relative overflow-hidden">
                <img :src="item.image" alt="News cover" class="w-full h-full object-cover group-hover:scale-110 transition duration-700" loading="lazy" />
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 to-transparent opacity-60"></div>
                <div class="absolute top-4 left-4 bg-brand-500 text-white text-xs font-black px-3 py-1.5 rounded-full uppercase tracking-wide shadow-lg">
                  {{ item.category }}
                </div>
                <div class="absolute bottom-4 left-4 text-white">
                  <p class="text-xs font-semibold text-gray-200 mb-1 flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> {{ item.date }}</p>
                </div>
              </div>
              <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-brand-600 transition duration-300 leading-tight">{{ item.title }}</h3>
                <p class="text-gray-600 text-sm line-clamp-2 leading-relaxed">{{ item.excerpt }}</p>
                <div class="mt-5 flex items-center text-brand-600 font-bold text-sm group-hover:translate-x-2 transition-transform duration-300">
                  Read Full Story <span class="ml-2">→</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section ref="calendarSectionRef" class="scroll-mt-24">
        <div class="flex justify-between items-end mb-8" :class="calendarHeaderRevealClass('fade')">
          <div>
            <span class="text-brand-600 font-bold uppercase tracking-wider text-sm mb-1 block">Join The Action</span>
            <h2 class="text-4xl font-extrabold text-gray-900 tracking-tight">
              Upcoming Dates
            </h2>
          </div>
          <router-link to="/calendar" class="hidden sm:inline-flex items-center text-sm font-bold text-brand-600 hover:text-brand-700 transition">
            View Full Calendar <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </router-link>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="(event, index) in upcomingEvents"
            :key="event.id"
            class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 ease-out relative overflow-hidden group"
            :class="staggerCardClass(calendarCardsVisible, index)"
            :style="staggerCardStyle(calendarCardsVisible, index)"
          >
            <div class="absolute top-0 left-0 w-full h-1 bg-brand-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            
            <div class="flex items-start space-x-4 mb-5">
              <div class="bg-brand-50 text-brand-600 rounded-xl p-3 text-center min-w-[70px] border border-brand-100 group-hover:bg-brand-500 group-hover:text-white transition-colors duration-300">
                <span class="block text-3xl font-black leading-none mb-1">{{ event.day }}</span>
                <span class="block text-xs uppercase font-bold tracking-widest">{{ event.month }}</span>
              </div>
              <div class="pt-1">
                <h3 class="text-xl font-bold text-gray-900 mb-1">{{ event.title }}</h3>
                <p class="text-sm text-gray-500 flex items-center font-medium">
                  <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ event.time }}
                </p>
              </div>
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-5 border-t border-gray-100/80">
              <span :class="['text-xs font-bold px-4 py-1.5 rounded-full', event.statusClass]">{{ event.status }}</span>
              <router-link to="/vendor-booking" class="inline-flex items-center text-sm font-bold text-brand-600 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-4 py-1.5 rounded-full transition-colors">
                Book Space <span class="ml-1">→</span>
              </router-link>
            </div>
          </div>
        </div>
      </section>

      <section
        ref="feedbackSectionRef"
        class="max-w-5xl mx-auto"
        :class="feedbackRevealClass('fade')"
      >
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden relative">
          <div class="absolute top-0 left-0 w-full h-48 bg-gradient-to-br from-brand-600 to-brand-400"></div>
          <svg class="absolute top-0 right-0 opacity-10 w-64 h-64 transform translate-x-1/3 -translate-y-1/4" fill="currentColor" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50"></circle></svg>
          
          <div class="relative pt-12 pb-8 px-8 text-center text-white z-10">
            <h2 class="text-4xl font-extrabold mb-3 tracking-tight">The Community Voice</h2>
            <p class="text-lg opacity-90 font-medium max-w-2xl mx-auto">See what others are saying, or help us improve the Carboot@CMart experience.</p>
          </div>

          <div class="relative bg-white z-20 rounded-t-[2.5rem] -mt-6 p-8 border-b border-gray-100 shadow-[0_-10px_20px_rgba(0,0,0,0.03)]">
            <CommunityFeedback @submitted="onFeedbackSubmitted" />
          </div>

          <div class="p-8 md:p-12 bg-gray-50/50">
            <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Recent Experiences</h3>

            <div v-if="loadingReviews" class="flex justify-center py-10">
              <div class="animate-pulse flex flex-col items-center">
                <div class="h-10 w-10 bg-brand-200 rounded-full mb-4"></div>
                <div class="h-4 w-32 bg-gray-200 rounded mb-2"></div>
                <div class="h-3 w-24 bg-gray-200 rounded"></div>
              </div>
            </div>

            <div v-else-if="communityReviews.length === 0" class="text-center text-gray-500 italic py-8">
              No reviews yet. Be the first to shape the community!
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div
                v-for="review in communityReviews"
                :key="review.id"
                class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow relative"
              >
                <div class="absolute top-4 right-6 text-gray-100">
                  <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 32 32"><path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14H10c0-1.1.9-2 2-2V8zm16 0c-3.3 0-6 2.7-6 6v10h10V14H20c0-1.1.9-2 2-2V8z"></path></svg>
                </div>
                
                <div class="flex items-center space-x-3 mb-4 relative z-10">
                  <div class="bg-gradient-to-br from-brand-100 to-brand-50 text-brand-600 rounded-full h-12 w-12 shrink-0 flex items-center justify-center font-black text-lg border border-brand-200">
                    {{ displayReviewerName(review).charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-bold text-gray-900">{{ displayReviewerName(review) }}</div>
                    <span v-if="review.reviewer_role" class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-md inline-block mt-1" :class="roleBadgeClass(review.reviewer_role)">
                      {{ review.reviewer_role }}
                    </span>
                  </div>
                </div>
                
                <div class="mb-4 flex space-x-4 text-xs font-bold text-gray-500 relative z-10">
                  <div class="flex items-center">
                    <span class="mr-1">Service:</span>
                    <span class="text-amber-400 text-sm tracking-tighter"><span v-for="star in 5" :key="'s-' + star">{{ star <= (review.service_rating || 0) ? '★' : '☆' }}</span></span>
                  </div>
                  <div class="flex items-center">
                    <span class="mr-1">Value:</span>
                    <span class="text-amber-400 text-sm tracking-tighter"><span v-for="star in 5" :key="'v-' + star">{{ star <= (review.value_rating || 0) ? '★' : '☆' }}</span></span>
                  </div>
                </div>
                
                <p v-if="review.comments" class="text-gray-600 text-sm leading-relaxed relative z-10">"{{ review.comments }}"</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';
import CommunityFeedback from './CommunityFeedback.vue';
import api from './services/api';
import { useScrollReveal } from './composables/useScrollReveal';
import { useHeroParallax } from './composables/useHeroParallax';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const heroVideoRef = ref(null);
const { contentStyle, videoStyle } = useHeroParallax();

// --- Layout Refs & Scroll Reveal ---
const {
  targetRef: feedbackSectionRef,
  revealClass: feedbackRevealClass,
} = useScrollReveal({ threshold: 0.1 });

const {
  targetRef: calendarSectionRef,
  isVisible: calendarCardsVisible,
  revealClass: calendarHeaderRevealClass,
} = useScrollReveal({ threshold: 0.08 });

const {
  targetRef: newsSectionRef,
  isVisible: newsCardsVisible,
  revealClass: newsHeaderRevealClass,
} = useScrollReveal({ threshold: 0.08 });

const staggerCardClass = (visible, index) => {
  const motionSafe = 'motion-reduce:opacity-100 motion-reduce:translate-y-0';
  if (visible) {
    return `opacity-100 translate-y-0 ${motionSafe}`;
  }
  return `opacity-0 translate-y-8 ${motionSafe}`;
};

const staggerCardStyle = (visible, index) => ({
  transitionDelay: visible ? `${Math.min(index * 75, 400)}ms` : '0ms',
});

// --- News Carousel Logic ---
const newsScrollContainer = ref(null);
const scrollNews = (direction) => {
  if (newsScrollContainer.value) {
    const scrollAmount = direction * (window.innerWidth < 640 ? window.innerWidth * 0.85 : 424); 
    newsScrollContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' });
  }
};

const logout = async () => {
  await auth.logout();
  toast.success('Session terminated successfully.');
  router.push('/');
};

const upcomingEvents = ref([]);
const latestNews = ref([]);

const statusClassForEvent = (status) => {
  if (status === 'Available') return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
  if (status === 'Almost Full') return 'bg-amber-100 text-amber-800 border border-amber-200';
  return 'bg-gray-100 text-gray-700 border border-gray-200';
};

const formatEventTime = (startsAt, endsAt) => {
  const start = new Date(startsAt);
  const end = new Date(endsAt);
  const opts = { hour: 'numeric', minute: '2-digit' };
  return `${start.toLocaleTimeString('en-GB', opts)} - ${end.toLocaleTimeString('en-GB', opts)}`;
};

const mapApiEventToCard = (ev) => {
  const start = new Date(ev.starts_at);
  return {
    id: ev.id,
    day: String(start.getDate()),
    month: start.toLocaleString('en-GB', { month: 'short' }),
    title: ev.title,
    time: formatEventTime(ev.starts_at, ev.ends_at),
    status: ev.status,
    statusClass: statusClassForEvent(ev.status),
  };
};

const formatNewsDate = (iso) => {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const fetchPortalContent = async () => {
  try {
    const [eventsRes, newsRes] = await Promise.all([
      api.get('/events'),
      api.get('/news'),
    ]);
    const events = Array.isArray(eventsRes.data) ? eventsRes.data : [];
    const news = Array.isArray(newsRes.data) ? newsRes.data : [];
    upcomingEvents.value = events.slice(0, 6).map(mapApiEventToCard);
    latestNews.value = news.slice(0, 6).map((n) => ({
      id: n.id,
      category: n.category,
      date: formatNewsDate(n.published_at || n.created_at),
      title: n.title,
      excerpt: n.excerpt,
      image: n.image_url || 'https://images.unsplash.com/photo-1556761175-5973dc0f32b7?q=80&w=800&auto=format&fit=crop',
    }));
  } catch (error) {
    console.error('Failed to load portal events/news:', error);
  }
};

const communityReviews = ref([]);
const loadingReviews = ref(true);

const displayReviewerName = (review) => review.user?.name || 'Community Member';

const roleBadgeClass = (role) => {
  const styles = {
    Shopper: 'bg-sky-100 text-sky-800 border border-sky-200',
    Vendor: 'bg-amber-100 text-amber-800 border border-amber-200',
    'UUM Student': 'bg-violet-100 text-violet-800 border border-violet-200',
    'Local Resident': 'bg-emerald-100 text-emerald-800 border border-emerald-200',
  };
  return styles[role] || 'bg-gray-100 text-gray-700 border border-gray-200';
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
  toast.success('Feedback submitted successfully! Thank you for your support.');
  await fetchReviews();
};

onMounted(() => {
  fetchReviews();
  fetchPortalContent();

  const video = heroVideoRef.value;
  if (video) {
    video.play().catch(() => {
      // Autoplay blocked — poster gradient still provides a polished fallback
    });
  }
});

const isMobileMenuOpen = ref(false);
const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
};
</script>

<style>
/* Utility class to hide scrollbar for the carousel but keep functionality */
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>