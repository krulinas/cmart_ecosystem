<template>
  <Teleport to="body">
    <div
      v-if="sessionExpired"
      class="fixed inset-0 z-[200] flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="session-expired-title"
      aria-describedby="session-expired-message"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" aria-hidden="true" />
      <div
        class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-black/5"
        tabindex="-1"
        ref="panelRef"
      >
        <h2 id="session-expired-title" class="text-lg font-extrabold text-ink-900">
          Your session has expired
        </h2>
        <p id="session-expired-message" class="mt-2 text-sm text-ink-600">
          For security, please log in again to continue.
        </p>
        <div class="mt-6 flex justify-end">
          <button
            type="button"
            class="ml-btn-primary"
            data-testid="session-expired-login-again"
            @click="logInAgain"
          >
            Log in again
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  sessionExpired,
  sessionExpirySnapshot,
  loginPathForLocation,
  sanitizeReturnPath,
  dismissSessionExpiryModal,
} from '../utils/sessionExpiry';

const router = useRouter();
const panelRef = ref(null);

const logInAgain = () => {
  const snapshot = sessionExpirySnapshot.value;
  const returnPath = sanitizeReturnPath(snapshot?.returnPath);
  const loginPath = snapshot?.loginPath || loginPathForLocation(returnPath || '/');
  const query = returnPath ? { redirect: returnPath } : {};
  dismissSessionExpiryModal();
  router.push({ path: loginPath, query });
};

watch(sessionExpired, async (open) => {
  if (open) {
    await nextTick();
    panelRef.value?.focus();
  }
});
</script>
