import axios from 'axios';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../stores/auth';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: {
    Accept: 'application/json',
  },
});

const forbiddenToastState = {
  message: null,
  shownAt: 0,
};

const FORBIDDEN_DEDUPE_MS = 4000;

const maybeShowForbiddenToast = (message) => {
  if (!message) return;

  const toast = useToast();
  const now = Date.now();

  if (forbiddenToastState.message === message && now - forbiddenToastState.shownAt < FORBIDDEN_DEDUPE_MS) {
    return;
  }

  forbiddenToastState.message = message;
  forbiddenToastState.shownAt = now;
  toast.error(message);
};

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('carboot_cmart_token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      try {
        useAuthStore().clearSession();
      } catch {
        localStorage.removeItem('carboot_cmart_token');
        localStorage.removeItem('carboot_cmart_user');
      }
    }

    if (error.response?.status === 403 && error.response?.data?.message) {
      error.forbiddenMessage = error.response.data.message;
      maybeShowForbiddenToast(error.forbiddenMessage);
    }

    return Promise.reject(error);
  },
);

export default api;
