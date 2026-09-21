# -*- coding: utf-8 -*-
"""Wire remaining Phase 3 Staff/Analytics/ItemReservations components to existing i18n keys."""
from pathlib import Path

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend\src")


def apply(path: Path, pairs, label):
    text = path.read_text(encoding="utf-8")
    ok = miss = 0
    for old, new in pairs:
        if old in text:
            text = text.replace(old, new)
            ok += 1
        else:
            miss += 1
            print(f"  MISS [{label}]: {old[:90]!r}")
    path.write_text(text, encoding="utf-8")
    print(f"{label}: {ok}/{ok+miss}")
    return text


# ---------------------------------------------------------------------------
# FeedbackDetailModal
# ---------------------------------------------------------------------------
fd = ROOT / "components/management/FeedbackDetailModal.vue"
apply(
    fd,
    [
        (
            "Feedback #{{ item.id }}",
            "{{ t('staff.feedbackDetail.title', { id: item.id }) }}",
        ),
        (
            'aria-label="Close feedback details"',
            ":aria-label=\"t('staff.feedbackDetail.closeAria')\"",
        ),
        (
            "{{ item.user_name || 'Community Member' }}",
            "{{ item.user_name || t('staff.feedbackDetail.communityMember') }}",
        ),
        (
            "{{ item.is_hidden ? 'Hidden' : 'Visible' }}",
            "{{ item.is_hidden ? t('staff.feedbackDetail.hidden') : t('staff.feedbackDetail.visible') }}",
        ),
        (
            "{{ item.reviewed_at ? 'Reviewed' : 'Unreviewed' }}",
            "{{ item.reviewed_at ? t('staff.feedbackDetail.reviewed') : t('staff.feedbackDetail.unreviewed') }}",
        ),
        (
            "{{ feedbackImages.length }} photo{{ feedbackImages.length === 1 ? '' : 's' }}",
            "{{ t('staff.feedbackDetail.photoCount', { n: feedbackImages.length }) }}",
        ),
        ("Reply Draft", "{{ t('staff.feedbackDetail.replyDraft') }}"),
        ("Reply Published", "{{ t('staff.feedbackDetail.replyPublished') }}"),
        (
            "Reviewed by {{ item.reviewed_by_name }} · {{ formatDate(item.reviewed_at) }}",
            "{{ t('staff.feedbackDetail.reviewedBy', { name: item.reviewed_by_name, date: formatDate(item.reviewed_at) }) }}",
        ),
        ("Attachments", "{{ t('staff.feedbackDetail.attachments') }}"),
        (
            ':alt="`Photo attachment ${index + 1} from ${item.user_name || \'community member\'}`"',
            ":alt=\"t('staff.feedbackDetail.photoAlt', { n: index + 1, name: item.user_name || t('staff.feedbackDetail.communityMember') })\"",
        ),
        ("Remove\n                  </button>", "{{ t('staff.feedbackDetail.remove') }}\n                  </button>"),
        ("Official CMart Reply", "{{ t('staff.feedbackDetail.officialReply') }}"),
        (
            "Manager/HQ approval required before public display.",
            "{{ t('staff.feedbackDetail.managerApprovalRequired') }}",
        ),
        (
            "This reply is published. Contact a manager to edit or remove it.",
            "{{ t('staff.feedbackDetail.publishedContactManager') }}",
        ),
        (
            'placeholder="Draft an official CMart response…"',
            ":placeholder=\"t('staff.feedbackDetail.replyPlaceholder')\"",
        ),
        (
            "{{ savingReply ? 'Saving…' : 'Save Draft' }}",
            "{{ savingReply ? t('staff.feedbackDetail.saving') : t('staff.feedbackDetail.saveDraft') }}",
        ),
        (
            "{{ publishingReply ? 'Publishing…' : 'Publish Reply' }}",
            "{{ publishingReply ? t('staff.feedbackDetail.publishing') : t('staff.feedbackDetail.publishReply') }}",
        ),
        (
            "Published by {{ item.official_reply.by_name || 'CMart' }} · {{ formatDate(item.official_reply.published_at) }}",
            "{{ t('staff.feedbackDetail.publishedBy', { name: item.official_reply.by_name || 'CMart', date: formatDate(item.official_reply.published_at) }) }}",
        ),
        (
            "{{ item.is_hidden ? 'Unhide' : 'Hide' }}",
            "{{ item.is_hidden ? t('staff.feedbackDetail.unhide') : t('staff.feedbackDetail.hide') }}",
        ),
        ("Mark Reviewed", "{{ t('staff.feedbackDetail.markReviewed') }}"),
        ("Delete\n            </button>", "{{ t('staff.feedbackDetail.delete') }}\n            </button>"),
        (
            "import { computed, ref, watch } from 'vue';\nimport { resolveStorageUrl } from '../../utils/imageUrl';",
            "import { computed, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { resolveStorageUrl } from '../../utils/imageUrl';\n\nconst { t } = useI18n();",
        ),
    ],
    "FeedbackDetailModal",
)

# ---------------------------------------------------------------------------
# OrganizerItemReservationsPanel
# ---------------------------------------------------------------------------
ir = ROOT / "views/dashboards/organizer/OrganizerItemReservationsPanel.vue"
apply(
    ir,
    [
        ("Item Reservations", "{{ t('organizer.itemReservations.eyebrow') }}"),
        ("Event reservation queue", "{{ t('organizer.itemReservations.title') }}"),
        (
            "Reconcile manual off-platform service fees. The platform records Organizer confirmation only and never processes payment.",
            "{{ t('organizer.itemReservations.lead') }}",
        ),
        (
            "{{ loading ? 'Refreshing…' : 'Refresh' }}",
            "{{ loading ? t('organizer.itemReservations.refreshing') : t('organizer.itemReservations.refresh') }}",
        ),
        (
            '<label class="ml-label" for="organizer-reservation-event">Event</label>',
            '<label class="ml-label" for="organizer-reservation-event">{{ t(\'organizer.itemReservations.event\') }}</label>',
        ),
        (
            '<option value="">— Select event —</option>',
            '<option value="">{{ t(\'organizer.itemReservations.selectEvent\') }}</option>',
        ),
        (
            '<label class="ml-label" for="organizer-reservation-status">Reservation status</label>',
            '<label class="ml-label" for="organizer-reservation-status">{{ t(\'organizer.itemReservations.reservationStatus\') }}</label>',
        ),
        (
            '<label class="ml-label" for="organizer-charge-status">Charge status</label>',
            '<label class="ml-label" for="organizer-charge-status">{{ t(\'organizer.itemReservations.chargeStatus\') }}</label>',
        ),
        (
            '<option value="">All</option>',
            '<option value="">{{ t(\'organizer.itemReservations.all\') }}</option>',
        ),
        (
            "Select an event to load its reservation queue.",
            "{{ t('organizer.itemReservations.selectEventPrompt') }}",
        ),
        ("Loading reservations…", "{{ t('organizer.itemReservations.loading') }}"),
        ("Try Again", "{{ t('organizer.itemReservations.tryAgain') }}"),
        (
            "No reservations match the current filters.",
            "{{ t('organizer.itemReservations.empty') }}",
        ),
        (">Reference</th>", ">{{ t('organizer.itemReservations.colReference') }}</th>"),
        (">Item</th>", ">{{ t('organizer.itemReservations.colItem') }}</th>"),
        (">Vendor</th>", ">{{ t('organizer.itemReservations.colVendor') }}</th>"),
        (">Reserver</th>", ">{{ t('organizer.itemReservations.colReserver') }}</th>"),
        (">Status</th>", ">{{ t('organizer.itemReservations.colStatus') }}</th>"),
        (">Fee</th>", ">{{ t('organizer.itemReservations.colFee') }}</th>"),
        (">Action</th>", ">{{ t('organizer.itemReservations.colAction') }}</th>"),
        ("Details\n              </button>", "{{ t('organizer.itemReservations.details') }}\n              </button>"),
        (
            "Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total",
            "{{ t('organizer.itemReservations.pageInfo', { current: meta.current_page, last: meta.last_page, total: meta.total }) }}",
        ),
        ("Previous\n          </button>", "{{ t('organizer.itemReservations.previous') }}\n          </button>"),
        ("Next\n          </button>", "{{ t('organizer.itemReservations.next') }}\n          </button>"),
        (
            "{{ detail?.public_reference || 'Reservation detail' }}",
            "{{ detail?.public_reference || t('organizer.itemReservations.detailFallback') }}",
        ),
        (
            "Manual off-platform fee reconciliation — not a payment gateway receipt.",
            "{{ t('organizer.itemReservations.detailLead') }}",
        ),
        (
            '@click="closeDetail">Close</button>',
            "@click=\"closeDetail\">{{ t('organizer.itemReservations.close') }}</button>",
        ),
        ("Loading detail…", "{{ t('organizer.itemReservations.loadingDetail') }}"),
        (
            '<dt class="text-xs font-bold uppercase text-ink-400">Item</dt>',
            '<dt class="text-xs font-bold uppercase text-ink-400">{{ t(\'organizer.itemReservations.item\') }}</dt>',
        ),
        (
            '<dt class="text-xs font-bold uppercase text-ink-400">Vendor</dt>',
            '<dt class="text-xs font-bold uppercase text-ink-400">{{ t(\'organizer.itemReservations.vendor\') }}</dt>',
        ),
        (
            '<dt class="text-xs font-bold uppercase text-ink-400">Reserving user</dt>',
            '<dt class="text-xs font-bold uppercase text-ink-400">{{ t(\'organizer.itemReservations.reservingUser\') }}</dt>',
        ),
        (
            '<dt class="text-xs font-bold uppercase text-ink-400">Statuses</dt>',
            '<dt class="text-xs font-bold uppercase text-ink-400">{{ t(\'organizer.itemReservations.statuses\') }}</dt>',
        ),
        (
            '<dt class="text-xs font-bold uppercase text-ink-400">Service fee</dt>',
            '<dt class="text-xs font-bold uppercase text-ink-400">{{ t(\'organizer.itemReservations.serviceFee\') }}</dt>',
        ),
        ("Charge evidence", "{{ t('organizer.itemReservations.chargeEvidence') }}"),
        (
            "Confirmation note: {{ detail.charge_confirmation?.note || '—' }}",
            "{{ t('organizer.itemReservations.confirmationNote', { value: detail.charge_confirmation?.note || '—' }) }}",
        ),
        (
            "Confirmed by: {{ detail.charge_confirmation?.confirmed_by || '—' }} · {{ formatReservationTimestamp(detail.charge_confirmation?.confirmed_at) }}",
            "{{ t('organizer.itemReservations.confirmedBy', { who: detail.charge_confirmation?.confirmed_by || '—', when: formatReservationTimestamp(detail.charge_confirmation?.confirmed_at) }) }}",
        ),
        (
            "Waiver reason: {{ detail.charge_waiver?.reason || '—' }}",
            "{{ t('organizer.itemReservations.waiverReason', { value: detail.charge_waiver?.reason || '—' }) }}",
        ),
        (
            "Waived by: {{ detail.charge_waiver?.waived_by || '—' }} · {{ formatReservationTimestamp(detail.charge_waiver?.waived_at) }}",
            "{{ t('organizer.itemReservations.waivedBy', { who: detail.charge_waiver?.waived_by || '—', when: formatReservationTimestamp(detail.charge_waiver?.waived_at) }) }}",
        ),
        (
            "Cancelled by: {{ detail.cancelled_by || '—' }} · {{ formatReservationTimestamp(detail.cancelled_at) }}",
            "{{ t('organizer.itemReservations.cancelledBy', { who: detail.cancelled_by || '—', when: formatReservationTimestamp(detail.cancelled_at) }) }}",
        ),
        (
            "Expired by: {{ detail.expired_by || '—' }} · {{ formatReservationTimestamp(detail.expired_at) }}",
            "{{ t('organizer.itemReservations.expiredBy', { who: detail.expired_by || '—', when: formatReservationTimestamp(detail.expired_at) }) }}",
        ),
        (
            "Completed by: {{ detail.completed_by || '—' }} · {{ formatReservationTimestamp(detail.completed_at) }}",
            "{{ t('organizer.itemReservations.completedBy', { who: detail.completed_by || '—', when: formatReservationTimestamp(detail.completed_at) }) }}",
        ),
        ("Confirm charge", "{{ t('organizer.itemReservations.confirmCharge') }}"),
        ("Waive charge", "{{ t('organizer.itemReservations.waiveCharge') }}"),
        # Cancel / Manual expiry / Mark collected buttons — context-specific later via script section
        (
            "I acknowledge that the manually confirmed service fee will not be refunded by the platform.",
            "{{ t('organizer.itemReservations.noRefundAck') }}",
        ),
        ("{{ mutating ? 'Saving…' : 'Submit' }}", "{{ mutating ? t('organizer.itemReservations.saving') : t('organizer.itemReservations.submit') }}"),
        ("Audit timeline", "{{ t('organizer.itemReservations.auditTimeline') }}"),
        ("No audit entries yet.", "{{ t('organizer.itemReservations.noAudits') }}"),
        (
            "{{ audit.actor || 'System' }}",
            "{{ audit.actor || t('organizer.itemReservations.systemActor') }}",
        ),
        (
            "import { computed, onMounted, ref, watch } from 'vue';\nimport { useRoute, useRouter } from 'vue-router';\nimport { useToast } from 'vue-toastification';",
            "import { computed, onMounted, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport { useRoute, useRouter } from 'vue-router';\nimport { useToast } from 'vue-toastification';",
        ),
        (
            "const toast = useToast();",
            "const { t } = useI18n();\nconst toast = useToast();",
        ),
        (
            """const actionTitle = computed(() => ({
  confirm: 'Record manual charge confirmation',
  waive: 'Waive service fee',
  cancel: 'Cancel active reservation',
  expire: 'Manually expire reservation',
  complete: 'Mark item collected',
}[actionMode.value] || ''));""",
            """const actionTitle = computed(() => ({
  confirm: t('organizer.itemReservations.actionConfirmTitle'),
  waive: t('organizer.itemReservations.actionWaiveTitle'),
  cancel: t('organizer.itemReservations.actionCancelTitle'),
  expire: t('organizer.itemReservations.actionExpireTitle'),
  complete: t('organizer.itemReservations.actionCompleteTitle'),
}[actionMode.value] || ''));""",
        ),
        (
            """const actionHelp = computed(() => ({
  confirm: 'Record that the Organizer received the service fee off-platform. This does not process payment.',
  waive: 'Waive the required service fee and confirm the reservation. Not the same as a zero-fee not_required charge.',
  cancel: 'Clear the active hold. Confirmed charge history remains; the platform issues no refund.',
  expire: 'Manually expire this active reservation. This is not an automatic timeout.',
  complete: 'Confirm the reserved item was handed over. The item becomes inactive.',
}[actionMode.value] || ''));""",
            """const actionHelp = computed(() => ({
  confirm: t('organizer.itemReservations.actionConfirmHelp'),
  waive: t('organizer.itemReservations.actionWaiveHelp'),
  cancel: t('organizer.itemReservations.actionCancelHelp'),
  expire: t('organizer.itemReservations.actionExpireHelp'),
  complete: t('organizer.itemReservations.actionCompleteHelp'),
}[actionMode.value] || ''));""",
        ),
        (
            "const noteLabel = computed(() => (actionMode.value === 'confirm' ? 'Confirmation note' : 'Reason'));",
            "const noteLabel = computed(() => (actionMode.value === 'confirm' ? t('organizer.itemReservations.confirmationNoteLabel') : t('organizer.itemReservations.reasonLabel')));",
        ),
        (
            "reservationErrorMessage(error, 'Unable to load events.')",
            "reservationErrorMessage(error, t('organizer.itemReservations.unableLoadEvents'))",
        ),
        (
            "reservationErrorMessage(error, 'Unable to load the reservation queue.')",
            "reservationErrorMessage(error, t('organizer.itemReservations.unableLoadQueue'))",
        ),
        (
            "reservationErrorMessage(error, 'Unable to load reservation detail.')",
            "reservationErrorMessage(error, t('organizer.itemReservations.unableLoadDetail'))",
        ),
        ("toast.success('Reservation updated.');", "toast.success(t('organizer.itemReservations.updated'));"),
        (
            "reservationErrorMessage(error, 'Unable to update this reservation.')",
            "reservationErrorMessage(error, t('organizer.itemReservations.unableUpdate'))",
        ),
    ],
    "ItemReservations",
)

# Fix remaining action buttons that may have ambiguous Cancel
irt = ir.read_text(encoding="utf-8")
# cancel reservation button label (not close)
for old, new in [
    (">Cancel</button>\n            <button\n              v-if=\"canOrganizerCancelOrExpire(detail)\"",
     ">{{ t('organizer.itemReservations.cancel') }}</button>\n            <button\n              v-if=\"canOrganizerCancelOrExpire(detail)\""),
    (">Manual expiry</button>", ">{{ t('organizer.itemReservations.manualExpiry') }}</button>"),
    (">Mark collected</button>", ">{{ t('organizer.itemReservations.markCollected') }}</button>"),
    (">Back</button>", ">{{ t('organizer.itemReservations.back') }}</button>"),
]:
    if old in irt:
        irt = irt.replace(old, new)
    else:
        print(f"  MISS [IR buttons]: {old[:60]!r}")
# Also handle Cancel action start button if pattern differs
if "Manual expiry" in irt:
    irt = irt.replace("Manual expiry", "{{ t('organizer.itemReservations.manualExpiry') }}")
if "Mark collected" in irt and "markCollected" not in irt.split("Mark collected")[0][-80:]:
    # only replace remaining plain text occurrences in template
    irt = irt.replace(">Mark collected<", ">{{ t('organizer.itemReservations.markCollected') }}<")
ir.write_text(irt, encoding="utf-8")
print("ItemReservations button polish done")

print("phase A done")
