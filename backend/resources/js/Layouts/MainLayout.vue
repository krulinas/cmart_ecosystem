<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';

const sidebarOpen = ref(false);
const profileOpen = ref(false);
const profileRef = ref(null);

function closeSidebarOnNavigate() {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
        sidebarOpen.value = false;
    }
}

function toggleProfile() {
    profileOpen.value = !profileOpen.value;
}

function handleClickOutside(event) {
    if (profileRef.value && !profileRef.value.contains(event.target)) {
        profileOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const navItems = [
    {
        label: 'Dashboard',
        to: '/dashboard',
        icon: 'dashboard',
    },
    {
        label: 'Vendors',
        to: '/vendors',
        icon: 'vendors',
    },
    {
        label: 'Bookings',
        to: '/bookings',
        icon: 'bookings',
    },
    {
        label: 'Settings',
        to: '/settings',
        icon: 'settings',
    },
];
</script>

<template>
    <div class="min-h-screen bg-gray-50 antialiased">
        <!-- Mobile overlay -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            leave-active-class="transition-opacity duration-200 ease-in"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed left-0 top-0 z-50 flex h-full w-[min(18rem,85vw)] flex-col border-r border-white/5 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 shadow-[4px_0_24px_-8px_rgba(15,23,42,0.35)] transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-16 shrink-0 items-center border-b border-white/5 px-6">
                <span
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.35em] text-white/95"
                >
                    CMART ADMIN
                </span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.to"
                    :to="item.to"
                    custom
                    v-slot="{ href, navigate, isActive }"
                >
                    <a
                        :href="href"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
                        :class="
                            isActive
                                ? 'bg-white/[0.11] text-white shadow-[inset_3px_0_0_0] shadow-amber-400/95 ring-1 ring-white/10'
                                : 'text-slate-400 hover:bg-white/[0.06] hover:text-white'
                        "
                        @click="
                            (e) => {
                                navigate(e);
                                closeSidebarOnNavigate();
                            }
                        "
                    >
                        <!-- Dashboard -->
                        <span
                            v-if="item.icon === 'dashboard'"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-current transition-colors group-hover:bg-white/10"
                            aria-hidden="true"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.75"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                                />
                            </svg>
                        </span>
                        <!-- Vendors -->
                        <span
                            v-else-if="item.icon === 'vendors'"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-current transition-colors group-hover:bg-white/10"
                            aria-hidden="true"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.75"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                />
                            </svg>
                        </span>
                        <!-- Bookings -->
                        <span
                            v-else-if="item.icon === 'bookings'"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-current transition-colors group-hover:bg-white/10"
                            aria-hidden="true"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.75"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </span>
                        <!-- Settings -->
                        <span
                            v-else
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-current transition-colors group-hover:bg-white/10"
                            aria-hidden="true"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.75"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.75"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </span>
                        {{ item.label }}
                    </a>
                </RouterLink>
            </nav>

            <div class="border-t border-white/5 p-4">
                <p class="text-xs text-slate-500">© CMART</p>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-h-screen flex-col lg:pl-72">
            <!-- Top navbar -->
            <header
                class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-gray-200/80 bg-white/90 px-4 shadow-sm backdrop-blur-md sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 lg:hidden"
                    aria-label="Toggle sidebar"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <div class="relative min-w-0 flex-1 max-w-xl">
                    <span
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </span>
                    <input
                        type="search"
                        placeholder="Search..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/80 py-2 pl-10 pr-4 text-sm text-gray-900 placeholder:text-gray-400 transition focus:border-slate-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                    />
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                        aria-label="Notifications"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                        <span
                            class="absolute right-2 top-2 h-2 w-2 rounded-full bg-amber-500 ring-2 ring-white"
                        />
                    </button>

                    <div ref="profileRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white py-1.5 pl-2 pr-3 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                            :aria-expanded="profileOpen"
                            aria-haspopup="true"
                            @click.stop="toggleProfile"
                        >
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-slate-700 to-slate-900 text-sm font-semibold text-white"
                            >
                                AD
                            </span>
                            <span class="hidden text-left text-sm sm:block">
                                <span class="block font-medium text-gray-900">Admin</span>
                                <span class="block text-xs text-gray-500">Administrator</span>
                            </span>
                            <svg
                                class="hidden h-4 w-4 text-gray-400 sm:block"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </button>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            leave-active-class="transition duration-100 ease-in"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div
                                v-show="profileOpen"
                                class="absolute right-0 mt-2 w-52 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5"
                                role="menu"
                            >
                                <a
                                    href="#"
                                    class="block px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50"
                                    role="menuitem"
                                    @click="profileOpen = false"
                                >
                                    Your profile
                                </a>
                                <a
                                    href="#"
                                    class="block px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50"
                                    role="menuitem"
                                    @click="profileOpen = false"
                                >
                                    Account settings
                                </a>
                                <hr class="my-1 border-gray-100" />
                                <a
                                    href="#"
                                    class="block px-4 py-2.5 text-sm text-red-600 transition hover:bg-red-50"
                                    role="menuitem"
                                    @click="profileOpen = false"
                                >
                                    Sign out
                                </a>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
