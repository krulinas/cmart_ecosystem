<template>
  <div class="min-h-screen flex bg-ink-50">
    <!-- Sidebar -->
    <aside
      class="hidden md:flex md:flex-col w-64 bg-white border-r border-ink-200 shrink-0"
      aria-label="Sidebar"
    >
      <div class="px-6 py-5 border-b border-ink-200">
        <router-link to="/" class="flex items-center gap-2 group">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-white font-extrabold">C</span>
          <div class="leading-tight">
            <div class="text-base font-extrabold text-ink-900 tracking-tight">Carboot@CMart</div>
            <div class="text-[11px] uppercase tracking-wider text-ink-500">Management Portal</div>
          </div>
        </router-link>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
        <div class="px-3 pt-2 pb-1 text-[11px] uppercase tracking-wider text-ink-500 font-semibold">
          {{ workspaceLabel }}
        </div>
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-ink-700 hover:bg-ink-100 hover:text-ink-900 transition"
          active-class="bg-brand-50 text-brand-700 font-semibold"
        >
          <span class="text-lg">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="px-4 py-4 border-t border-ink-200 text-xs text-ink-500">
        <div class="font-semibold text-ink-700">{{ userName }}</div>
        <div>{{ userRoleLabel }}</div>
      </div>
    </aside>

    <!-- Main column -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="bg-white border-b border-ink-200 px-6 py-4 flex items-center justify-between">
        <div>
          <h1 class="text-xl font-extrabold text-ink-900 tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="text-sm text-ink-500">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </header>

      <main class="flex-1 p-6 overflow-x-hidden">
        <slot name="previewBanner" />
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  workspaceLabel: { type: String, default: 'CMart Workspace' },
  userName: { type: String, default: 'CMart Staff' },
  userRoleLabel: { type: String, default: 'Staff' },
  navItems: {
    type: Array,
    default: () => [
      { to: '/admin', label: 'Approval Queue', icon: 'A' },
      { to: '/admin#profitability', label: 'Profitability', icon: 'P' },
      { to: '/admin#analytics', label: 'Analytics', icon: 'R' },
    ],
  },
});
</script>
