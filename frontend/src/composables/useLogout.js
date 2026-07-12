import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

export function useLogout() {
  const router = useRouter();
  const auth = useAuthStore();

  const logout = async () => {
    await auth.logout();
    router.push('/');
  };

  return { logout };
}
