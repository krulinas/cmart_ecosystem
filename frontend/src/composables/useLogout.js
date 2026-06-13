import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';

export function useLogout() {
  const auth = useAuthStore();
  const bossPreview = useBossPreviewStore();
  const router = useRouter();
  const toast = useToast();

  const logout = async () => {
    await auth.logout();
    bossPreview.reset?.();
    toast.success('Logged out successfully.');
    await router.push('/');
  };

  return { logout };
}
