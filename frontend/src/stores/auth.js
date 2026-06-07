import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api from '../services/api';

const readStoredUser = () => {
  try {
    return JSON.parse(localStorage.getItem('carboot_cmart_user')) || null;
  } catch {
    return null;
  }
};

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('carboot_cmart_token'));
  const user = ref(readStoredUser());
  const loading = ref(false);

  const isAuthenticated = computed(() => Boolean(token.value && user.value));
  const role = computed(() => user.value?.role || null);
  const vendorStatus = computed(() => user.value?.vendor_status || 'none');
  const isApprovedVendor = computed(() => role.value === 'community' && vendorStatus.value === 'approved');
  const isCmartWorker = computed(() => ['cmart_staff', 'cmart_admin'].includes(role.value));
  const isBoss = computed(() => role.value === 'cmart_admin');
  const isStaff = computed(() => role.value === 'cmart_staff');

  const persistSession = (payload) => {
    token.value = payload.token;
    user.value = payload.user;
    localStorage.setItem('carboot_cmart_token', payload.token);
    localStorage.setItem('carboot_cmart_user', JSON.stringify(payload.user));
  };

  const clearSession = () => {
    token.value = null;
    user.value = null;
    localStorage.removeItem('carboot_cmart_token');
    localStorage.removeItem('carboot_cmart_user');
  };

  const register = async (form) => {
    loading.value = true;
    try {
      const { data } = await api.post('/auth/register', form);
      persistSession(data);
      return data.user;
    } finally {
      loading.value = false;
    }
  };

  const login = async (credentials) => {
    loading.value = true;
    try {
      const { data } = await api.post('/auth/login', credentials);
      persistSession(data);
      return data.user;
    } finally {
      loading.value = false;
    }
  };

  const fetchMe = async () => {
    if (!token.value) return null;

    const { data } = await api.get('/auth/me');
    user.value = data.user;
    localStorage.setItem('carboot_cmart_user', JSON.stringify(data.user));

    return data.user;
  };

  const logout = async () => {
    try {
      if (token.value) {
        await api.post('/auth/logout');
      }
    } finally {
      clearSession();
    }
  };

  const hasAnyRole = (roles = []) => roles.includes(role.value);

  const homeForUser = () => {
    if (role.value === 'cmart_admin' || role.value === 'cmart_staff') return '/admin';
    if (role.value === 'uum') return '/uum';
    if (role.value === 'community') return '/dashboard';
    return '/';
  };

  return {
    token,
    user,
    loading,
    isAuthenticated,
    role,
    vendorStatus,
    isApprovedVendor,
    isCmartWorker,
    isBoss,
    isStaff,
    register,
    login,
    fetchMe,
    logout,
    hasAnyRole,
    homeForUser,
  };
});
