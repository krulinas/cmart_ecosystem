import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: {
    Accept: 'application/json',
  },
});

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
      localStorage.removeItem('carboot_cmart_token');
      localStorage.removeItem('carboot_cmart_user');
    }

    if (error.response?.status === 403 && error.response?.data?.message) {
      error.forbiddenMessage = error.response.data.message;
    }

    return Promise.reject(error);
  },
);

export default api;
