import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/router.js';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import './assets/main.css';

const app = createApp(App);

// Configure toast notification behaviors
const toastOptions = {
    position: "top-right",
    timeout: 3000,
    closeOnClick: true,
    pauseOnFocusLoss: true,
    pauseOnHover: true,
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: "button",
    icon: true,
    rtl: false
};

app.use(createPinia());
app.use(router);
app.use(Toast, toastOptions);
app.mount('#app');