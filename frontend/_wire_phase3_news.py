from pathlib import Path
import re

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend\src")


def sub(path, pairs, label):
    text = path.read_text(encoding="utf-8")
    ok = 0
    for old, new in pairs:
        if old in text:
            text = text.replace(old, new)
            ok += 1
        else:
            print(f"  MISS [{label}]: {old[:70]!r}")
    path.write_text(text, encoding="utf-8")
    print(f"{label}: {ok}/{len(pairs)}")


# Fix feedback plural calls
fb = ROOT / "views/dashboards/staff/StaffFeedbackPanel.vue"
t = fb.read_text(encoding="utf-8")
t = t.replace(
    "t('staff.feedback.photoCount', imageCount(item), { n: imageCount(item) })",
    "t('staff.feedback.photoCount', { n: imageCount(item) })",
)
t = t.replace(
    "t('staff.feedback.viewPhotosAria', imageCount(item), { n: imageCount(item), name: item.user_name || t('staff.feedback.communityMember') })",
    "t('staff.feedback.viewPhotosAria', { n: imageCount(item), name: item.user_name || t('staff.feedback.communityMember') })",
)
# openImagePreview may still be English
t = t.replace(
    "alt: `Photo attachment from ${caption || 'community member'}`,\n    caption: caption ? `Photo attachment from ${caption}` : '',",
    "alt: t('staff.feedback.photoAlt', { name: caption || t('staff.feedback.communityMember') }),\n    caption: caption ? t('staff.feedback.photoAttachmentCaption', { name: caption }) : '',",
)
fb.write_text(t, encoding="utf-8")
print("feedback plural/lightbox fixed")

# --- StaffNewsPanel ---
news = ROOT / "views/dashboards/staff/StaffNewsPanel.vue"
sub(
    news,
    [
        ("{{ editingId ? 'Edit Post' : 'Create News Post' }}", "{{ editingId ? t('staff.news.editTitle') : t('staff.news.createTitle') }}"),
        ('<label class="ml-label">Title</label>', '<label class="ml-label">{{ t(\'staff.news.title\') }}</label>'),
        ('<label class="ml-label">Category</label>', '<label class="ml-label">{{ t(\'staff.news.category\') }}</label>'),
        ('placeholder="Announcement"', ":placeholder=\"t('staff.news.categoryPlaceholder')\""),
        ('<label class="ml-label">Short summary</label>', '<label class="ml-label">{{ t(\'staff.news.shortSummary\') }}</label>'),
        ("Shown as a short preview on the Venue News page.", "{{ t('staff.news.shortSummaryHint') }}"),
        ('<label class="ml-label">Full details (optional)</label>', '<label class="ml-label">{{ t(\'staff.news.fullDetails\') }}</label>'),
        ('label="News images (optional)"', ":label=\"t('staff.news.newsImages')\""),
        ('<label class="ml-label" for="news-video-input">Promotional video (optional)</label>', '<label class="ml-label" for="news-video-input">{{ t(\'staff.news.promoVideo\') }}</label>'),
        (
            "One MP4 or WebM video, up to 10 MB. Images remain the cover. Video is secondary and optional.",
            "{{ t('staff.news.promoVideo') === 'x' ? '' : t('staff.news.promoVideoHint') }}",
        ),
        ("{{ videoFileName || 'Current video' }}", "{{ videoFileName || t('staff.news.currentVideo') }}"),
        (">Replace</button>", ">{{ t('staff.news.replace') }}</button>"),
        (">Remove</button>\n            </div>", ">{{ t('staff.news.remove') }}</button>\n            </div>"),
        (
            "Your text was restored. Please select image and video files again after logging in.",
            "{{ t('staff.news.draftRestored') }}",
        ),
        ('<label class="ml-label">External image URL (optional fallback)</label>', '<label class="ml-label">{{ t(\'staff.news.externalImageUrl\') }}</label>'),
        ("Used only when no uploaded images are set.", "{{ t('staff.news.externalImageHint') }}"),
        ('<label class="ml-label">Published at</label>', '<label class="ml-label">{{ t(\'staff.news.publishedAt\') }}</label>'),
        ("Published on community portal", "{{ t('staff.news.publishedOnPortal') }}"),
        ("{{ saving ? 'Saving…' : 'Save Post' }}", "{{ saving ? t('staff.news.saving') : t('staff.news.savePost') }}"),
        (">Cancel Edit</button>", ">{{ t('staff.news.cancelEdit') }}</button>"),
        (">All News Posts</h2>", ">{{ t('staff.news.allPosts') }}</h2>"),
        (">Loading news posts…</div>", ">{{ t('staff.news.loading') }}</div>"),
        (">No news posts yet.</div>", ">{{ t('staff.news.empty') }}</div>"),
        (':aria-label="`View news post: ${post.title}`"', ":aria-label=\"t('staff.news.viewAria', { title: post.title })\""),
        (':alt="`${post.title} banner preview`"', ":alt=\"t('staff.news.bannerAlt', { title: post.title })\""),
        (">\n                  Video\n                </div>", ">\n                  {{ t('staff.news.video') }}\n                </div>"),
        (">No image</div>", ">{{ t('staff.news.noImage') }}</div>"),
        ('aria-label="This post includes a video"', ":aria-label=\"t('staff.news.hasVideoAria')\""),
        ("                  Video\n                </span>", "                  {{ t('staff.news.video') }}\n                </span>"),
        ("Click to preview full post", "{{ t('staff.news.clickToPreview') }}"),
        ('@click="edit(post)">Edit</button>', "@click=\"edit(post)\">{{ t('staff.news.edit') }}</button>"),
        ("{{ deletingId === post.id ? 'Deleting…' : 'Delete' }}", "{{ deletingId === post.id ? t('staff.news.deleting') : t('staff.news.delete') }}"),
        (
            "import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';\nimport { useToast } from 'vue-toastification';",
            "import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { useToast } from 'vue-toastification';",
        ),
        ("const toast = useToast();", "const { t } = useI18n();\nconst toast = useToast();"),
    ],
    "StaffNewsPanel UI",
)

# news toasts / validates
nt = news.read_text(encoding="utf-8")
for old, new in [
    ("'Only MP4 or WebM video files are allowed.'", "t('staff.news.onlyMp4Webm')"),
    ("'Video must be 10 MB or smaller.'", "t('staff.news.videoTooLarge')"),
    ("'Request failed.'", "t('staff.news.requestFailed')"),
    ("'Title, short summary, and category are required.'", "t('staff.news.fieldsRequired')"),
    ("'News post updated.'", "t('staff.news.postUpdated')"),
    ("'News post created.'", "t('staff.news.postCreated')"),
    ("'Delete this news post? This cannot be undone.'", "t('staff.news.deleteConfirm')"),
    ("'News post deleted.'", "t('staff.news.postDeleted')"),
]:
    if old in nt:
        nt = nt.replace(old, new)
# fix botched promoVideoHint
nt = nt.replace(
    "{{ t('staff.news.promoVideo') === 'x' ? '' : t('staff.news.promoVideoHint') }}",
    "{{ t('staff.news.promoVideoHint') }}",
)
news.write_text(nt, encoding="utf-8")
print("StaffNewsPanel toasts done")

print("news ok")
