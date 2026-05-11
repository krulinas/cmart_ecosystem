<template>
  <WorkspaceShell
    title="UUM Oversight"
    subtitle="Read-only reporting surface for university stakeholders."
    workspace-label="UUM · Oversight"
    user-name="UUM Observer"
    user-role-label="Read-only"
    :nav-items="navItems"
  >
    <div class="ml-card">
      <span class="ml-badge bg-indigo-100 text-indigo-700">Planned Module</span>
      <h2 class="mt-3 text-xl font-extrabold text-ink-900">Oversight Reports</h2>
      <p class="mt-2 text-sm text-ink-500 max-w-2xl">
        This page is protected by the <code class="px-1 rounded bg-ink-100">uum</code> role. It is reserved for
        read-only monthly reports, booking trends, vendor activity, and event performance.
      </p>
    </div>
  </WorkspaceShell>
</template>

<script setup>
import WorkspaceShell from './layouts/WorkspaceShell.vue';

const navItems = [
  { to: '/uum', label: 'Reports', icon: 'R' },
];
</script>
