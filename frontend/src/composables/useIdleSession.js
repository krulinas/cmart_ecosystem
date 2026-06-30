import { onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../stores/auth';

export const IDLE_TIMEOUT_MS = 18 * 60 * 1000;

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart'];

export function useIdleSession() {
  const auth = useAuthStore();
  const router = useRouter();
  const toast = useToast();

  let timerId = null;

  const clearTimer = () => {
    if (timerId !== null) {
      clearTimeout(timerId);
      timerId = null;
    }
  };

  const onIdle = async () => {
    if (!auth.isAuthenticated) return;
    if (!auth.isCmartWorker) return;

    clearTimer();
    await auth.logout();
    toast.info('Your session ended due to inactivity. Please log in again.');
    router.push({ path: '/management/login', query: { reason: 'idle' } });
  };

  const resetTimer = () => {
    clearTimer();
    if (!auth.isAuthenticated || !auth.isCmartWorker) return;

    timerId = setTimeout(onIdle, IDLE_TIMEOUT_MS);
  };

  const onActivity = () => resetTimer();

  const start = () => {
    ACTIVITY_EVENTS.forEach((event) => {
      window.addEventListener(event, onActivity, { passive: true });
    });
    resetTimer();
  };

  const stop = () => {
    clearTimer();
    ACTIVITY_EVENTS.forEach((event) => {
      window.removeEventListener(event, onActivity);
    });
  };

  onMounted(() => {
    watch(
      () => [auth.isAuthenticated, auth.isCmartWorker],
      ([authenticated, worker]) => {
        stop();
        if (authenticated && worker) {
          start();
        }
      },
      { immediate: true },
    );
  });

  onUnmounted(stop);

  return { resetTimer, stop };
}
