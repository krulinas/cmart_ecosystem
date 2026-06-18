<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">{{ editingId ? 'Edit Post' : 'Create News Post' }}</h2>
      <form @submit.prevent="save" class="space-y-3">
        <div>
          <label class="ml-label">Title</label>
          <input v-model="form.title" required class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Category</label>
          <input v-model="form.category" required class="ml-input" placeholder="Announcement" />
        </div>
        <div>
          <label class="ml-label">Excerpt</label>
          <textarea v-model="form.excerpt" required rows="2" class="ml-input"></textarea>
        </div>
        <div>
          <label class="ml-label">Body (optional)</label>
          <textarea v-model="form.body" rows="4" class="ml-input"></textarea>
        </div>
        <MultiImageUploadField
          ref="imageField"
          label="News images (optional)"
          :existing="editingImages"
          :legacy-field="legacyImagePath"
          @update:files="imageFiles = $event"
          @update:removeIds="removeImageIds = $event"
        />
        <div>
          <label class="ml-label">External image URL (optional fallback)</label>
          <input v-model="form.image_url" type="url" class="ml-input" placeholder="https://..." />
          <p class="text-xs text-ink-500 mt-1">Used only when no uploaded images are set.</p>
        </div>
        <div>
          <label class="ml-label">Published at</label>
          <input v-model="form.published_at" type="datetime-local" class="ml-input" />
        </div>
        <label class="flex items-center gap-2 text-sm font-medium text-ink-700">
          <input v-model="form.is_published" type="checkbox" class="rounded" />
          Published on community portal
        </label>
        <div class="flex gap-2">
          <button type="submit" class="ml-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save Post' }}</button>
          <button v-if="editingId" type="button" class="ml-btn-ghost" @click="resetForm">Cancel Edit</button>
        </div>
      </form>
    </section>

    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">All News Posts</h2>
      <div v-if="loading && !hasLoaded" class="text-ink-500 text-sm">Loading news posts…</div>
      <div v-else-if="hasLoaded && !posts.length" class="text-ink-500 text-sm">No news posts yet.</div>
      <ul v-else class="space-y-3">
        <li
          v-for="post in posts"
          :key="post.id"
          tabindex="0"
          role="button"
          :aria-label="`View news post: ${post.title}`"
          class="rounded-lg border border-ink-200 p-3 cursor-pointer hover:border-brand-300 hover:bg-brand-50/30 hover:ring-2 hover:ring-brand-500/10 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 group"
          @click="openNewsDetails(post)"
          @keydown.enter.prevent="openNewsDetails(post)"
          @keydown.space.prevent="openNewsDetails(post)"
        >
          <div class="flex justify-between gap-3">
            <div class="flex gap-3 min-w-0 pointer-events-none">
              <img
                v-if="post.bannerUrl"
                :src="post.bannerUrl"
                :alt="`${post.title} banner preview`"
                class="w-16 h-16 rounded-lg object-cover object-top border border-ink-200 shrink-0"
              />
              <div v-else class="w-16 h-16 rounded-lg border border-dashed border-ink-200 bg-ink-50 shrink-0 flex items-center justify-center text-[10px] font-bold text-ink-400">
                No image
              </div>
              <div class="min-w-0">
                <div class="font-bold text-ink-900">{{ post.title }}</div>
                <div class="text-xs text-ink-500">
                  {{ post.category }} · {{ post.statusLabel }}
                  <span v-if="post.publishedDateShort"> · {{ post.publishedDateShort }}</span>
                </div>
                <p v-if="post.excerpt" class="text-xs text-ink-500 mt-1 line-clamp-2">{{ post.excerpt }}</p>
                <p class="text-xs text-brand-600 font-semibold mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  Click to preview full post
                </p>
              </div>
            </div>
            <div class="flex flex-col gap-1 shrink-0" @click.stop>
              <button class="ml-btn-ghost text-sm" @click="edit(post)">Edit</button>
              <button class="ml-btn-ghost text-sm text-rose-600" :disabled="deletingId === post.id" @click="remove(post.id)">
                {{ deletingId === post.id ? 'Deleting…' : 'Delete' }}
              </button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <NewsDetailsModal v-model="showNewsModal" :post="selectedNews" show-status />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useToast } from 'vue-toastification';
import NewsDetailsModal from '../../../components/NewsDetailsModal.vue';
import MultiImageUploadField from '../../../components/MultiImageUploadField.vue';
import api from '../../../services/api';
import { mapApiNewsToCard } from '../../../utils/newsDisplay';
import { normalizeNews } from '../../../utils/imageUrl';

const toast = useToast();
const posts = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const selectedNews = ref(null);
const showNewsModal = ref(false);
const imageField = ref(null);
const imageFiles = ref([]);
const removeImageIds = ref([]);
const editingImages = ref([]);
const legacyImagePath = ref('');

const emptyForm = () => ({
  title: '',
  excerpt: '',
  body: '',
  category: 'Announcement',
  image_url: '',
  published_at: '',
  is_published: true,
});

const form = reactive(emptyForm());

const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const extractApiError = (error) => {
  const data = error.response?.data;
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ');
  }
  return data?.message || error.message || 'Request failed.';
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append('title', form.title.trim());
  fd.append('excerpt', form.excerpt.trim());
  fd.append('category', form.category.trim());
  fd.append('body', form.body || '');
  fd.append('is_published', form.is_published ? '1' : '0');
  if (form.image_url) {
    fd.append('image_url', form.image_url.trim());
  }
  if (form.published_at) {
    fd.append('published_at', form.published_at);
  }

  imageFiles.value.forEach((file) => {
    fd.append('images[]', file);
  });

  removeImageIds.value.forEach((id) => {
    fd.append('remove_image_ids[]', String(id));
  });

  if (imageField.value?.hasLegacyRemoval?.()) {
    fd.append('remove_banner', '1');
  }

  return fd;
};

const openNewsDetails = (post) => {
  selectedNews.value = post;
  showNewsModal.value = true;
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/news-posts');
    posts.value = (Array.isArray(data) ? data : []).map(mapApiNewsToCard);
    hasLoaded.value = true;
  } catch (error) {
    console.error('Failed to load news posts:', error);
    toast.error(extractApiError(error));
    throw error;
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  editingId.value = null;
  editingImages.value = [];
  legacyImagePath.value = '';
  imageFiles.value = [];
  removeImageIds.value = [];
  imageField.value?.reset();
  Object.assign(form, emptyForm());
};

const edit = (post) => {
  const normalized = normalizeNews(post);
  editingId.value = normalized.id;
  form.title = normalized.title;
  form.excerpt = normalized.excerpt;
  form.body = normalized.body || '';
  form.category = normalized.category;
  form.image_url = normalized.external_image_url || '';
  form.published_at = toLocalInput(normalized.published_at);
  form.is_published = Boolean(normalized.is_published);
  editingImages.value = normalized.images?.filter((image) => image.id) || [];
  legacyImagePath.value = normalized.image_path || '';
  imageFiles.value = [];
  removeImageIds.value = [];
  imageField.value?.reset();
};

const save = async () => {
  if (!form.title.trim() || !form.excerpt.trim() || !form.category.trim()) {
    toast.error('Title, excerpt, and category are required.');
    return;
  }

  saving.value = true;
  const usesMultipart = imageFiles.value.length > 0
    || removeImageIds.value.length > 0
    || imageField.value?.hasLegacyRemoval?.();

  try {
    if (usesMultipart) {
      const fd = buildFormData();
      if (editingId.value) {
        fd.append('_method', 'PUT');
        await api.post(`/news-posts/${editingId.value}`, fd);
        toast.success('News post updated.');
      } else {
        await api.post('/news-posts', fd);
        toast.success('News post created.');
      }
    } else if (editingId.value) {
      await api.put(`/news-posts/${editingId.value}`, {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        image_url: form.image_url || null,
        published_at: form.published_at || null,
        is_published: form.is_published,
      });
      toast.success('News post updated.');
    } else {
      await api.post('/news-posts', {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        image_url: form.image_url || null,
        published_at: form.published_at || null,
        is_published: form.is_published,
      });
      toast.success('News post created.');
    }

    resetForm();
    await load();
  } catch (error) {
    console.error('Failed to save news post:', error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};

const remove = async (id) => {
  if (!window.confirm('Delete this news post? This cannot be undone.')) return;

  deletingId.value = id;
  try {
    await api.delete(`/news-posts/${id}`);
    toast.success('News post deleted.');
    await load();
  } catch (error) {
    console.error('Failed to delete news post:', error);
    toast.error(extractApiError(error));
  } finally {
    deletingId.value = null;
  }
};

defineExpose({ load });
</script>
