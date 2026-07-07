<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-50 lg:flex-row">
    <!-- Mobile section nav -->
    <div class="shrink-0 border-b border-ink-200 bg-white px-4 py-2.5 lg:hidden">
      <div class="flex items-center gap-2 overflow-x-auto pb-0.5">
        <router-link
          v-for="item in flatNavItems"
          :key="item.to"
          :to="item.to"
          class="shrink-0 rounded-full px-3.5 py-2 text-sm font-semibold transition"
          :class="isActiveHash(item.hash) ? theme.navActive : 'bg-ink-100 text-ink-600'"
        >
          {{ item.label }}
        </router-link>
      </div>
    </div>

    <!-- Sidebar — fixed viewport frame -->
    <aside
      class="hidden h-screen w-72 shrink-0 flex-col border-r border-ink-200/80 bg-white shadow-sm lg:flex"
      aria-label="Management sidebar"
    >
      <!-- Brand header -->
      <div class="shrink-0 px-4 py-4 text-white" :class="theme.sidebarHeaderBg">
        <router-link :to="homeLink" class="group flex items-center gap-3">
          <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-extrabold text-white shadow-lg ring-2 ring-white/20"
            :class="theme.logoBg"
          >
            C
          </span>
          <div class="min-w-0 leading-tight">
            <div class="truncate text-sm font-extrabold tracking-tight">Carboot@CMart</div>
            <div class="text-[10px] uppercase tracking-[0.2em] text-white/70">Management Portal</div>
          </div>
        </router-link>

        <div class="mt-3 space-y-1.5">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-white/60">Workspace</div>
          <div class="text-sm font-bold leading-snug">{{ branchName }}</div>
          <div class="flex flex-wrap gap-1.5">
            <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white ring-1 ring-white/25">
              {{ tierBadge }}
            </span>
            <span
              v-if="department"
              class="inline-flex items-center rounded-full bg-black/20 px-2.5 py-0.5 text-[10px] font-semibold text-white/90"
            >
              {{ department }}
            </span>
          </div>
        </div>
      </div>

      <!-- Grouped navigation — scrolls internally when needed -->
      <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3">
        <div v-for="group in navGroups" :key="group.id">
          <div class="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-ink-400">
            {{ group.label }}
          </div>
          <div class="space-y-0.5">
            <router-link
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[15px] text-ink-600 transition"
              :class="[theme.navHover, isActiveHash(item.hash) ? theme.navActive : '']"
            >
              <span
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[10px] font-extrabold"
                :class="isActiveHash(item.hash) ? 'bg-white/80 shadow-sm text-cyan-700' : 'bg-ink-100 text-ink-500'"
              >
                {{ item.shortIcon }}
              </span>
              <span class="truncate">{{ item.label }}</span>
            </router-link>
          </div>
        </div>
      </nav>

      <!-- User footer — always pinned at bottom -->
      <div class="shrink-0 border-t border-ink-100 px-3 py-3">
        <div class="rounded-xl bg-ink-50 p-3 ring-1 ring-ink-100">
          <div class="flex items-start gap-2.5">
            <div
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold text-white"
              :class="theme.logoBg"
            >
              {{ userInitials }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-bold text-ink-900">{{ userName }}</div>
              <div class="truncate text-xs text-ink-500">{{ userRoleLabel }}</div>
              <div class="mt-1.5 flex flex-wrap items-center gap-1">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                  :class="theme.badgeBg"
                >
                  {{ roleBadge }}
                </span>
              </div>
              <div v-if="branchName" class="mt-1 truncate text-[10px] font-medium text-ink-400">
                {{ branchName }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main column — header fixed, content scrolls -->
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
      <header
        class="z-10 shrink-0 border-b px-4 py-3.5 sm:px-6 lg:px-8"
        :class="[theme.heroGradient, theme.heroBorder]"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1"
                :class="theme.tierBadgeBg"
              >
                {{ tierBadge }}
              </span>
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
                :class="theme.branchBadgeBg"
              >
                {{ branchName }}
              </span>
              <span
                v-if="previewMode"
                class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-900 ring-1 ring-amber-200"
              >
                Staff preview active
              </span>
            </div>
            <div>
              <h1 class="text-xl font-extrabold tracking-tight sm:text-2xl" :class="theme.heroTitle">
                {{ workspaceTitle }}
              </h1>
              <p class="mt-0.5 max-w-2xl text-sm leading-relaxed" :class="theme.heroSubtitle">
                {{ workspaceSubtitle }}
              </p>
              <p v-if="sectionSubtitle" class="mt-1 text-xs font-medium text-ink-500">
                {{ sectionSubtitle }}
              </p>
            </div>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
          </div>
        </div>
      </header>

      <main class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-4 py-5 sm:px-6 lg:px-8">
        <slot name="previewBanner" />
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const homeLink = computed(() => auth.homeForUser());

const props = defineProps({
  theme: { type: Object, required: true },
  navGroups: { type: Array, default: () => [] },
  flatNavItems: { type: Array, default: () => [] },
  workspaceTitle: { type: String, required: true },
  workspaceSubtitle: { type: String, default: '' },
  sectionSubtitle: { type: String, default: '' },
  userName: { type: String, default: 'CMart Staff' },
  userRoleLabel: { type: String, default: 'Staff' },
  roleBadge: { type: String, default: '' },
  tierBadge: { type: String, default: 'Tier 1' },
  branchName: { type: String, default: 'CMart Main Branch' },
  department: { type: String, default: '' },
  previewMode: { type: Boolean, default: false },
});

const userInitials = computed(() => {
  const parts = (props.userName || 'CS').trim().split(/\s+/);
  if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  return (parts[0]?.slice(0, 2) || 'CS').toUpperCase();
});

const isActiveHash = (hash) => {
  const current = (route.hash || '#bookings').replace('#', '');
  return current === hash;
};
</script>
