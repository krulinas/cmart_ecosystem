<template>
  <div class="space-y-6 pb-10" data-testid="organizer-bookings-root">
    <div v-if="loading && !hasLoaded" class="rounded-2xl border border-ink-200 bg-white py-16 text-center shadow-sm">
      <div class="mx-auto h-10 w-10 animate-pulse rounded-full bg-gradient-to-br" :class="theme.loaderPulse || 'from-blue-100 to-indigo-100'" />
      <p class="mt-4 text-sm font-medium text-ink-500">Loading bookings…</p>
    </div>

    <div v-else-if="loadError && !hasLoaded" class="rounded-2xl border border-rose-200 bg-rose-50/60 px-6 py-12 text-center">
      <p class="text-sm font-semibold text-rose-800">Unable to load bookings</p>
      <p class="mt-1 text-sm text-rose-700/80">{{ loadError }}</p>
    </div>

    <template v-else>
      <!-- KPI overview -->
      <section>
        <div class="mb-4">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink-500">Operations overview</h2>
          <p class="text-xs text-ink-400 mt-0.5">{{ overviewHint }}</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <ManagementKpiCard
            v-for="card in kpiCards"
            :key="card.key"
            :title="card.title"
            :description="card.description"
            :value="card.value"
            :icon="card.icon"
            :accent="card.accent"
          />
        </div>
      </section>

      <div class="flex flex-wrap gap-2" data-testid="organizer-bookings-tabs">
        <button
          type="button"
          class="rounded-full px-4 py-2 text-sm font-semibold transition"
          :class="activeWorkspaceTab === 'bookings' ? (theme.tabActive || 'bg-blue-700 text-white shadow-sm') : (theme.tabIdle || 'bg-white text-ink-600 ring-1 ring-ink-200')"
          data-testid="organizer-tab-bookings"
          @click="activeWorkspaceTab = 'bookings'"
        >
          Bookings Registry
        </button>
        <button
          type="button"
          class="rounded-full px-4 py-2 text-sm font-semibold transition"
          :class="activeWorkspaceTab === 'recovery' ? (theme.tabActive || 'bg-blue-700 text-white shadow-sm') : (theme.tabIdle || 'bg-white text-ink-600 ring-1 ring-ink-200')"
          data-testid="organizer-tab-released-recovery"
          @click="switchToRecoveryTab"
        >
          Released-Day Recovery
        </button>
      </div>

      <template v-if="activeWorkspaceTab === 'bookings'">
      <section class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm">
        <div class="px-5 py-4 text-white sm:px-6" :class="theme.queueHeader">
          <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">Active queue</p>
              <h2 class="text-lg font-extrabold tracking-tight">{{ queueTitle }}</h2>
              <p class="mt-1 text-sm text-white/80 max-w-2xl">{{ queueDescription }}</p>
            </div>
            <span class="mt-2 inline-flex w-fit items-center rounded-full bg-white/15 px-3 py-1 text-xs font-bold ring-1 ring-white/25 sm:mt-0">
              {{ queueBookings.length }} pending
            </span>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <ManagementEmptyState
            v-if="hasLoaded && !queueBookings.length"
            :title="emptyQueueTitle"
            :description="emptyQueueDescription"
            :icon="emptyQueueIcon"
            :accent="themeAccent"
          />

          <div v-else class="overflow-x-auto rounded-xl border border-ink-100">
            <table class="min-w-full text-sm">
              <thead class="bg-ink-50/80">
                <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500">
                  <th class="px-4 py-3 font-semibold">Booking</th>
                  <th class="px-4 py-3 font-semibold">Vendor product</th>
                  <th class="px-4 py-3 font-semibold">Space / Sites</th>
                  <th class="px-4 py-3 font-semibold">Event date</th>
                  <th class="px-4 py-3 font-semibold">Status</th>
                  <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-ink-100">
                <tr
                  v-for="b in queueBookings"
                  :key="'q-' + b.id"
                  data-testid="organizer-booking-row"
                  data-booking-section="queue"
                  :data-booking-id="b.id"
                  :data-booking-status="b.approval_status"
                  class="transition hover:bg-ink-50/50"
                >
                  <td class="px-4 py-3.5">
                    <div class="font-bold text-ink-900">#{{ b.id }}</div>
                    <div class="text-xs text-ink-500">{{ vendorLabel(b) }}</div>
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="font-medium text-ink-800">{{ b.product_category || 'Others' }}</div>
                    <div class="max-w-xs truncate text-xs text-ink-500">{{ b.product_details || '—' }}</div>
                  </td>
                  <td class="px-4 py-3.5 text-ink-700">
                    <div>{{ physicalSiteSummary(b) }}</div>
                    <div v-if="b.site_selection?.allocation_status" class="text-xs text-ink-500">
                      {{ allocationStatusLabel(b.site_selection.allocation_status) }}
                    </div>
                    <div
                      v-if="withdrawalSummaryLine(b)"
                      class="text-xs text-slate-600"
                      data-testid="organizer-withdrawal-summary"
                    >
                      {{ withdrawalSummaryLine(b) }}
                    </div>
                  </td>
                  <td class="px-4 py-3.5 whitespace-nowrap text-ink-700">{{ formatBookingDate(b.booking_date) }}</td>
                  <td class="px-4 py-3.5">
                    <ManagementStatusChip :status="b.approval_status" data-testid="organizer-booking-status" />
                  </td>
                  <td class="px-4 py-3.5">
                    <div class="flex justify-end gap-1.5 flex-wrap">
                      <button
                        class="ml-btn-ghost text-xs px-3 py-1.5"
                        data-testid="organizer-booking-view-details"
                        :data-booking-id="b.id"
                        @click="openBookingDetails(b.id)"
                      >
                        Details
                      </button>
                      <button class="ml-btn-ghost text-xs px-3 py-1.5" @click="viewPdf(b.id)">PDF</button>
                      <template v-if="canApproveBookings && !isTerminalBookingStatus(b.approval_status)">
                        <button
                          class="ml-btn-success text-xs px-3 py-1.5"
                          data-testid="organizer-booking-action-approve"
                          :data-booking-id="b.id"
                          @click="updateStatus(b.id, 'Approved')"
                        >
                          Approve
                        </button>
                        <button
                          class="ml-btn-danger text-xs px-3 py-1.5"
                          data-testid="organizer-booking-action-reject"
                          :data-booking-id="b.id"
                          @click="updateStatus(b.id, 'Rejected')"
                        >
                          Reject
                        </button>
                        <button
                          class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                          data-testid="organizer-booking-action-needs-revision"
                          :data-booking-id="b.id"
                          @click="requestRevision(b.id)"
                        >
                          Revision
                        </button>
                      </template>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Registry -->
      <section
        class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm"
        data-testid="management-payment-records-root"
      >
        <div class="border-b border-ink-100 px-5 py-4 sm:px-6">
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-lg font-extrabold text-ink-900">All Bookings Registry</h2>
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                    :class="registryBadgeClass"
                  >
                    {{ registryLabel }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-ink-500">{{ registryDescription }}</p>
              </div>
              <button
                type="button"
                class="ml-btn-ghost shrink-0 self-start text-xs"
                :disabled="registryLoading || !hasActiveFilters"
                @click="resetFilters"
              >
                Clear filters
              </button>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-7">
              <div class="relative xl:col-span-2">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 text-xs">⌕</span>
                <input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Search vendor, booth, ID, status…"
                  data-testid="organizer-bookings-search"
                  class="ml-input w-full pl-8 text-sm"
                />
              </div>
              <select v-model="statusFilter" class="ml-input text-sm" data-testid="organizer-status-filter">
                <option value="all">All statuses</option>
                <option value="Pending_Organizer">Organizer queue</option>
                <option value="Needs_Revision">Needs revision</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
                <option value="Cancelled">Cancelled</option>
                <option value="Withdrawn">Withdrawn</option>
              </select>
              <select v-model="paymentFilter" class="ml-input text-sm" data-testid="organizer-payment-filter">
                <option value="all">All payments</option>
                <option value="Paid">Paid</option>
                <option value="Unpaid">Unpaid</option>
                <option value="Pending Verification">Payment Submitted</option>
              </select>
              <select v-model="noRefundFilter" class="ml-input text-sm" data-testid="organizer-no-refund-filter">
                <option value="all">All refund policies</option>
                <option value="yes">No-refund applied</option>
                <option value="no">No-refund not applicable</option>
              </select>
              <select v-model="eventFilter" class="ml-input text-sm">
                <option value="all">All events</option>
                <option v-for="ev in eventOptions" :key="ev.id" :value="String(ev.id)">
                  {{ ev.title }}
                </option>
              </select>
              <select v-model="sortBy" class="ml-input text-sm">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="status">Status</option>
                <option value="event">Event date</option>
                <option value="vendor">Vendor name</option>
                <option value="amount">Amount</option>
              </select>
            </div>
          </div>
        </div>

        <div class="relative overflow-x-auto">
          <div
            v-if="registryLoading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 backdrop-blur-[1px]"
          >
            <div class="flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm text-ink-600 shadow-sm">
              <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700" />
              Updating registry…
            </div>
          </div>

          <div v-if="registryError" class="px-5 py-10 text-center">
            <p class="text-sm font-semibold text-rose-800">Unable to load registry results</p>
            <p class="mt-1 text-sm text-rose-700/80">{{ registryError }}</p>
          </div>

          <table v-else class="min-w-full text-sm">
            <thead class="bg-ink-50/60">
              <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500">
                <th class="px-4 py-3 font-semibold">ID</th>
                <th class="px-4 py-3 font-semibold">Vendor</th>
                <th class="px-4 py-3 font-semibold">Category</th>
                <th class="px-4 py-3 font-semibold">Details</th>
                <th class="px-4 py-3 font-semibold">Space / Sites</th>
                <th class="px-4 py-3 font-semibold">Date</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Payment</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
              <tr
                v-for="b in registryBookings"
                :key="b.id"
                data-testid="organizer-booking-row"
                data-booking-section="registry"
                :data-booking-id="b.id"
                :data-booking-status="b.approval_status"
                :data-booking-payment-status="b.invoice?.payment_status || ''"
                class="transition hover:bg-ink-50/40"
              >
                <td class="px-4 py-3.5 font-bold text-ink-900">#{{ b.id }}</td>
                <td class="px-4 py-3.5 text-ink-800">{{ vendorLabel(b) }}</td>
                <td class="px-4 py-3.5 text-ink-700">{{ b.product_category || 'Others' }}</td>
                <td class="px-4 py-3.5 max-w-[200px] truncate text-ink-600">{{ b.product_details || '—' }}</td>
                <td class="px-4 py-3.5 text-ink-700">
                  <div>{{ physicalSiteSummary(b) }}</div>
                  <div v-if="b.site_selection?.allocation_status" class="text-xs text-ink-500">
                    {{ allocationStatusLabel(b.site_selection.allocation_status) }}
                  </div>
                  <div
                    v-if="withdrawalSummaryLine(b)"
                    class="text-xs text-slate-600"
                    data-testid="organizer-withdrawal-summary"
                  >
                    {{ withdrawalSummaryLine(b) }}
                  </div>
                </td>
                <td class="px-4 py-3.5 whitespace-nowrap text-ink-700">{{ formatBookingDate(b.booking_date) }}</td>
                <td class="px-4 py-3.5">
                  <ManagementStatusChip :status="b.approval_status" data-testid="organizer-booking-status" />
                </td>
                <td class="px-4 py-3.5">
                  <div class="flex flex-col items-start gap-2">
                    <span
                      v-if="b.invoice?.payment_status"
                      data-testid="management-payment-status"
                      class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                      :class="paymentStatusBadgeClass(b.invoice.payment_status)"
                    >
                      {{ b.invoice.payment_status }}
                    </span>
                    <span v-else class="text-ink-400">—</span>
                    <button
                      v-if="b.invoice?.payment_proof_present"
                      type="button"
                      data-testid="payment-proof-link"
                      class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                      @click="openPaymentProofReview(b, { allowVerify: canVerifyPayment(b) })"
                    >
                      View payment proof
                    </button>
                    <button
                      v-if="canVerifyPayment(b)"
                      type="button"
                      class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                      data-testid="verify-payment-button"
                      :data-booking-id="b.id"
                      @click="openPaymentProofReview(b, { allowVerify: true })"
                    >
                      Verify Paid
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3.5 text-right">
                  <div class="flex justify-end gap-2">
                    <button
                      class="ml-btn-ghost text-xs px-3 py-1.5"
                      data-testid="organizer-booking-view-details"
                      :data-booking-id="b.id"
                      @click="openBookingDetails(b.id)"
                    >
                      Details
                    </button>
                    <button
                      v-if="canDeleteBookings"
                      class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                      data-testid="organizer-booking-action-delete"
                      :data-booking-id="b.id"
                      @click="deleteBooking(b.id)"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="hasLoaded && !registryLoading && !registryBookings.length">
                <td colspan="9" class="px-4 py-0">
                  <div class="py-12">
                    <ManagementEmptyState
                      title="No bookings found"
                      :description="registryEmptyDescription"
                      icon="⌕"
                      accent="cyan"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-ink-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-wrap items-center gap-3 text-sm text-ink-500">
            <span v-if="pagination.total">
              Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }}
            </span>
            <span v-else>No results</span>
            <label class="inline-flex items-center gap-2">
              <span class="text-xs font-medium uppercase tracking-wide text-ink-400">Per page</span>
              <select v-model.number="perPage" class="ml-input w-20 py-1.5 text-sm">
                <option :value="10">10</option>
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </label>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="ml-btn-ghost px-3 py-1.5 text-sm"
              :disabled="registryLoading || pagination.current_page <= 1"
              @click="goToPage(pagination.current_page - 1)"
            >
              Previous
            </button>
            <span class="text-sm font-medium text-ink-600">
              Page {{ pagination.current_page }} of {{ pagination.last_page || 1 }}
            </span>
            <button
              type="button"
              class="ml-btn-ghost px-3 py-1.5 text-sm"
              :disabled="registryLoading || pagination.current_page >= pagination.last_page"
              @click="goToPage(pagination.current_page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </section>
      </template>

      <OrganizerReleasedDayRecoveryPanel v-else ref="recoveryPanel" />
    </template>

    <Teleport to="body">
      <div
        v-if="showPaymentVerifyModal && paymentVerifyTarget"
        class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-4"
        data-testid="verify-payment-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-proof-review-title"
        @keydown.esc.stop.prevent="closePaymentVerifyModal"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.55)] backdrop-blur-[2px]" @click="closePaymentVerifyModal" />
        <div class="relative z-10 flex max-h-[min(92vh,56rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
          <div class="flex items-start justify-between gap-3 border-b border-ink-100 px-5 py-4">
            <div>
              <h3 id="payment-proof-review-title" class="text-lg font-extrabold text-ink-900">
                Review payment proof
              </h3>
              <p class="mt-1 text-sm text-ink-500">
                Booking #{{ paymentVerifyTarget.id }}
              </p>
            </div>
            <button
              type="button"
              class="ml-btn-ghost px-2 py-1 text-sm"
              aria-label="Close payment proof review"
              @click="closePaymentVerifyModal"
            >
              ×
            </button>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-400">Vendor / business</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">{{ vendorLabel(paymentVerifyTarget) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-400">Invoice amount</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">{{ formatInvoiceAmount(paymentVerifyTarget.invoice?.amount) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-400">Submitted</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">
                  {{ formatSubmittedAt(paymentVerifyTarget.invoice?.payment_submitted_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-400">Payment status</dt>
                <dd class="mt-0.5">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="paymentStatusBadgeClass(paymentVerifyTarget.invoice?.payment_status)"
                  >
                    {{ paymentVerifyTarget.invoice?.payment_status || '—' }}
                  </span>
                </dd>
              </div>
            </dl>

            <div
              class="overflow-hidden rounded-xl border border-ink-200 bg-ink-50"
              data-testid="payment-proof-preview"
            >
              <div v-if="proofLoading" class="flex min-h-[12rem] items-center justify-center px-4 py-10 text-sm text-ink-500">
                Loading payment proof…
              </div>
              <div
                v-else-if="proofError"
                class="flex min-h-[12rem] flex-col items-center justify-center gap-3 px-4 py-10 text-center"
              >
                <p class="text-sm text-rose-700" data-testid="payment-proof-error">{{ proofError }}</p>
                <button
                  type="button"
                  class="ml-btn-ghost text-sm"
                  data-testid="payment-proof-retry"
                  @click="loadPaymentProof(paymentVerifyTarget.id)"
                >
                  Retry
                </button>
              </div>
              <img
                v-else-if="proofObjectUrl"
                :src="proofObjectUrl"
                :alt="`Payment proof for booking #${paymentVerifyTarget.id}`"
                class="mx-auto max-h-[min(55vh,28rem)] w-full object-contain"
                data-testid="payment-proof-image"
                @load="onProofImageLoad"
                @error="onProofImageError"
              />
            </div>

            <p v-if="paymentReviewAllowVerify" class="text-sm text-ink-600">
              Confirm that you have reviewed this payment proof and verified the payment as received.
              The vendor receipt and event pass will be unlocked.
            </p>
          </div>

          <div class="flex flex-wrap justify-end gap-3 border-t border-ink-100 px-5 py-4">
            <button type="button" class="ml-btn-ghost text-sm" @click="closePaymentVerifyModal">
              {{ paymentReviewAllowVerify ? 'Cancel' : 'Close' }}
            </button>
            <button
              v-if="paymentReviewAllowVerify"
              type="button"
              class="ml-btn-primary text-sm"
              data-testid="confirm-verify-payment-button"
              :disabled="!canConfirmPaid"
              @click="confirmVerifyPayment"
            >
              {{ verifyingPayment ? 'Verifying…' : 'Confirm Paid' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <OrganizerWithdrawalReconciliationModal
      v-model="showBookingDetails"
      :booking-id="selectedBookingId"
      :booking="selectedBooking"
      :loading="bookingDetailsLoading"
      :error="bookingDetailsError"
      @booking-updated="handleBookingDetailsUpdated"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import ManagementKpiCard from '../../../components/management/ManagementKpiCard.vue';
import ManagementEmptyState from '../../../components/management/ManagementEmptyState.vue';
import ManagementStatusChip from '../../../components/management/ManagementStatusChip.vue';
import OrganizerWithdrawalReconciliationModal from '../../../components/organizer/OrganizerWithdrawalReconciliationModal.vue';
import OrganizerReleasedDayRecoveryPanel from '../../../components/organizer/OrganizerReleasedDayRecoveryPanel.vue';
import { useManagementAccess } from '../../../composables/useManagementAccess';
import { formatBookingDate, isTerminalBookingStatus, siteLabelsForBooking, allocationStatusLabel, organizerWithdrawalSummary, statusLabel } from '../../../utils/bookingDisplay';

const emit = defineEmits(['refreshed']);

const toast = useToast();
const {
  canApproveBookings,
  canVerifyPayments,
  canDeleteBookings,
  bookingsListEndpoint,
  workspaceTheme,
} = useManagementAccess();

const theme = computed(() => workspaceTheme.value);
const themeAccent = computed(() => (theme.value.accentName === 'cyan' ? 'cyan' : 'blue'));

const summary = ref({
  pending_organizer: 0,
  needs_revision: 0,
  approved: 0,
  rejected: 0,
  cancelled: 0,
  withdrawn: 0,
});
const queueBookings = ref([]);
const registryBookings = ref([]);
const eventOptions = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
});

const searchQuery = ref('');
const debouncedSearch = ref('');
const statusFilter = ref('all');
const paymentFilter = ref('all');
const noRefundFilter = ref('all');
const eventFilter = ref('all');
const sortBy = ref('newest');
const perPage = ref(15);
const currentPage = ref(1);

const loading = ref(false);
const registryLoading = ref(false);
const hasLoaded = ref(false);
const loadError = ref(null);
const registryError = ref(null);

let suppressRegistryFetch = false;
let searchDebounceTimer = null;

const vendorLabel = (booking) =>
  booking.user?.business_profile?.business_name
  || booking.user?.businessProfile?.business_name
  || booking.user?.name
  || '—';

const physicalSiteSummary = (booking) => {
  const labels = siteLabelsForBooking(booking);
  if (labels) return labels;
  return booking.site_quantity != null
    ? `${booking.site_quantity} parking site${Number(booking.site_quantity) === 1 ? '' : 's'}`
    : (booking.booth_type_label || 'Parking site');
};

const withdrawalSummaryLine = (booking) => {
  const summary = organizerWithdrawalSummary(booking);
  if (!summary) return '';
  const parts = ['Withdrawn'];
  if (summary.paymentState === 'paid' || summary.paymentState === 'payment_submitted') {
    parts.push('No refund');
  }
  if (summary.sitesReleased) parts.push('Sites released');
  return parts.join(' · ');
};

const paymentStatusBadgeClass = (status) => {
  if (status === 'Paid') return 'bg-emerald-50 text-emerald-800';
  if (status === 'Pending Verification') return 'bg-sky-50 text-sky-800';
  return 'bg-amber-50 text-amber-800';
};

const canVerifyPayment = (booking) =>
  canVerifyPayments.value
  && booking.approval_status === 'Approved'
  && booking.invoice?.payment_status === 'Pending Verification';

const paymentVerifyTarget = ref(null);
const showPaymentVerifyModal = ref(false);
const verifyingPayment = ref(false);
const paymentReviewAllowVerify = ref(false);
const proofLoading = ref(false);
const proofError = ref('');
const proofObjectUrl = ref('');
const proofImageReady = ref(false);
let proofRequestToken = 0;

const canConfirmPaid = computed(() =>
  Boolean(
    paymentReviewAllowVerify.value
    && proofImageReady.value
    && proofObjectUrl.value
    && !proofLoading.value
    && !proofError.value
    && !verifyingPayment.value,
  ),
);

const formatInvoiceAmount = (amount) => {
  const numeric = Number(amount);
  if (!Number.isFinite(numeric)) return 'RM —';
  return `RM ${numeric.toFixed(2)}`;
};

const formatSubmittedAt = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return String(value);
  }
};

const revokeProofObjectUrl = () => {
  if (proofObjectUrl.value) {
    URL.revokeObjectURL(proofObjectUrl.value);
    proofObjectUrl.value = '';
  }
  proofImageReady.value = false;
};

const extractProofErrorMessage = async (error) => {
  if (error?.forbiddenMessage) return error.forbiddenMessage;
  const data = error?.response?.data;
  if (!data) return 'Unable to load payment proof.';
  if (typeof data === 'string') {
    try {
      const parsed = JSON.parse(data);
      if (parsed?.message) return parsed.message;
    } catch {
      // fall through
    }
  }
  if (data instanceof Blob) {
    try {
      const text = await data.text();
      const parsed = JSON.parse(text);
      if (parsed?.message) return parsed.message;
    } catch {
      // fall through
    }
  }
  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message;
  }
  return 'Unable to load payment proof.';
};

const loadPaymentProof = async (bookingId) => {
  if (!bookingId) return;
  const token = ++proofRequestToken;
  proofLoading.value = true;
  proofError.value = '';
  proofImageReady.value = false;
  revokeProofObjectUrl();

  try {
    const response = await api.get(`/bookings/${bookingId}/payment-proof`, {
      responseType: 'blob',
    });
    if (token !== proofRequestToken) return;

    const contentType = String(response.headers?.['content-type'] || '');
    if (contentType.includes('application/json')) {
      const text = await response.data.text();
      let message = 'Unable to load payment proof.';
      try {
        message = JSON.parse(text)?.message || message;
      } catch {
        // keep default
      }
      proofError.value = message;
      return;
    }

    proofObjectUrl.value = URL.createObjectURL(response.data);
  } catch (error) {
    if (token !== proofRequestToken) return;
    proofError.value = await extractProofErrorMessage(error);
  } finally {
    if (token === proofRequestToken) {
      proofLoading.value = false;
    }
  }
};

const onProofImageLoad = () => {
  proofImageReady.value = true;
  proofError.value = '';
};

const onProofImageError = () => {
  proofImageReady.value = false;
  revokeProofObjectUrl();
  proofError.value = 'The payment proof image could not be displayed.';
};

const openPaymentProofReview = (booking, { allowVerify = false } = {}) => {
  paymentVerifyTarget.value = booking;
  paymentReviewAllowVerify.value = Boolean(allowVerify && canVerifyPayment(booking));
  showPaymentVerifyModal.value = true;
  loadPaymentProof(booking.id);
};

const closePaymentVerifyModal = () => {
  proofRequestToken += 1;
  showPaymentVerifyModal.value = false;
  paymentVerifyTarget.value = null;
  paymentReviewAllowVerify.value = false;
  proofLoading.value = false;
  proofError.value = '';
  verifyingPayment.value = false;
  revokeProofObjectUrl();
};

const confirmVerifyPayment = async () => {
  const booking = paymentVerifyTarget.value;
  if (!booking || !canConfirmPaid.value) return;

  verifyingPayment.value = true;
  try {
    await api.patch(`/bookings/${booking.id}/verify-payment`);
    toast.success(`Payment for booking #${booking.id} marked as Paid.`);
    closePaymentVerifyModal();
    await fetchBookings();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to verify payment.');
    }
  } finally {
    verifyingPayment.value = false;
  }
};

const showBookingDetails = ref(false);
const activeWorkspaceTab = ref('bookings');
const recoveryPanel = ref(null);
const selectedBookingId = ref(null);
const selectedBooking = ref(null);
const bookingDetailsLoading = ref(false);
const bookingDetailsError = ref('');

const openBookingDetails = async (bookingId) => {
  selectedBookingId.value = bookingId;
  selectedBooking.value = null;
  bookingDetailsError.value = '';
  bookingDetailsLoading.value = true;
  showBookingDetails.value = true;

  try {
    const { data } = await api.get(`/bookings/${bookingId}`);
    selectedBooking.value = data;
  } catch (error) {
    bookingDetailsError.value =
      error.forbiddenMessage || error.response?.data?.message || 'Unable to load booking reconciliation.';
  } finally {
    bookingDetailsLoading.value = false;
  }
};

const handleBookingDetailsUpdated = async (booking) => {
  selectedBooking.value = booking;
  toast.success('Attendance exception applied.');
  await fetchBookings();
  emit('refreshed');
};

const hasActiveFilters = computed(() =>
  Boolean(
    debouncedSearch.value.trim()
    || statusFilter.value !== 'all'
    || paymentFilter.value !== 'all'
    || noRefundFilter.value !== 'all'
    || eventFilter.value !== 'all'
    || sortBy.value !== 'newest'
    || perPage.value !== 15,
  ),
);

const kpi = computed(() => ({
  pendingOrganizer: summary.value.pending_organizer ?? 0,
  needsRevision: summary.value.needs_revision ?? 0,
  approved: summary.value.approved ?? 0,
  rejected: summary.value.rejected ?? 0,
}));

const kpiCards = computed(() => [
  {
    key: 'organizer',
    title: 'Awaiting Organizer Review',
    description: 'New vendor requests in your direct review queue.',
    value: kpi.value.pendingOrganizer,
    icon: 'O1',
    accent: themeAccent.value,
  },
  {
    key: 'revision',
    title: 'Needs Vendor Revision',
    description: 'Returned to vendors for corrections.',
    value: kpi.value.needsRevision,
    icon: 'Rv',
    accent: 'amber',
  },
  {
    key: 'approved',
    title: 'Approved Bookings',
    description: 'Confirmed vendor slots in the registry.',
    value: kpi.value.approved,
    icon: 'Ok',
    accent: 'emerald',
  },
  {
    key: 'rejected',
    title: 'Rejected Bookings',
    description: 'Declined vendor slot requests.',
    value: kpi.value.rejected,
    icon: 'X',
    accent: 'rose',
  },
]);

const overviewHint = 'Direct organizer operations — review submissions and verify payments.';

const queueTitle = 'Organizer Review Queue';

const queueDescription =
  'Review new vendor submissions directly — approve, reject, or request revisions without staff escalation.';

const emptyQueueTitle = 'Queue clear — no organizer reviews pending';

const emptyQueueDescription =
  'No bookings awaiting organizer review. New vendor requests will appear here.';

const emptyQueueIcon = '✓';

const registryLabel = computed(() => theme.value.registryLabel);
const registryDescription = computed(() => theme.value.registryDescription);
const registryBadgeClass = computed(() => theme.value.accentSoft || 'bg-blue-50 text-blue-900 ring-blue-200');

const registryEmptyDescription = computed(() => {
  if (hasActiveFilters.value) {
    return 'No bookings match your current search or filters. Try clearing filters to see more results.';
  }
  return 'Bookings will appear here once vendors submit slot requests through the portal.';
});

const buildQueryParams = () => {
  const params = {
    page: currentPage.value,
    per_page: perPage.value,
    sort: sortBy.value,
  };

  if (debouncedSearch.value.trim()) {
    params.search = debouncedSearch.value.trim();
  }
  if (statusFilter.value !== 'all') {
    params.status = statusFilter.value;
  }
  if (paymentFilter.value !== 'all') {
    params.payment_status = paymentFilter.value;
  }
  if (noRefundFilter.value !== 'all') {
    params.no_refund_applied = noRefundFilter.value === 'yes' ? 1 : 0;
  }
  if (eventFilter.value !== 'all') {
    params.event_id = eventFilter.value;
  }

  return params;
};

const applyResponse = (payload) => {
  registryBookings.value = payload.data ?? [];
  pagination.value = {
    current_page: payload.meta?.current_page ?? 1,
    last_page: payload.meta?.last_page ?? 1,
    per_page: payload.meta?.per_page ?? perPage.value,
    total: payload.meta?.total ?? 0,
    from: payload.meta?.from ?? null,
    to: payload.meta?.to ?? null,
  };
  summary.value = payload.summary ?? summary.value;
  queueBookings.value = payload.queue ?? [];
};

const loadEventOptions = async () => {
  try {
    const { data } = await api.get('/carboot-events');
    eventOptions.value = Array.isArray(data) ? data : [];
  } catch {
    eventOptions.value = [];
  }
};

const fetchRegistry = async () => {
  if (suppressRegistryFetch || !hasLoaded.value) return;

  registryLoading.value = true;
  registryError.value = null;

  try {
    const { data } = await api.get(bookingsListEndpoint, { params: buildQueryParams() });
    applyResponse(data);
  } catch (e) {
    registryError.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load registry results.';
    if (!e.forbiddenMessage) {
      toast.error(registryError.value);
    }
  } finally {
    registryLoading.value = false;
  }
};

const fetchBookings = async () => {
  loading.value = true;
  loadError.value = null;
  registryError.value = null;
  suppressRegistryFetch = true;

  try {
    try {
      const preEvent = sessionStorage.getItem('cmart.bookings.preselectEventId');
      const preStatus = sessionStorage.getItem('cmart.bookings.preselectStatus');
      if (preEvent) {
        eventFilter.value = preEvent;
        sessionStorage.removeItem('cmart.bookings.preselectEventId');
      }
      if (preStatus) {
        statusFilter.value = preStatus;
        sessionStorage.removeItem('cmart.bookings.preselectStatus');
      }
    } catch {
      /* ignore */
    }

    debouncedSearch.value = searchQuery.value.trim();
    const { data } = await api.get(bookingsListEndpoint, { params: buildQueryParams() });
    applyResponse(data);
    hasLoaded.value = true;
    emit('refreshed');

    if (!eventOptions.value.length) {
      await loadEventOptions();
    }
  } catch (e) {
    loadError.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load bookings.';
    if (!e.forbiddenMessage) {
      toast.error(loadError.value);
    }
    throw e;
  } finally {
    loading.value = false;
    suppressRegistryFetch = false;
  }
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  currentPage.value = page;
};

const resetFilters = () => {
  suppressRegistryFetch = true;
  searchQuery.value = '';
  debouncedSearch.value = '';
  statusFilter.value = 'all';
  paymentFilter.value = 'all';
  noRefundFilter.value = 'all';
  eventFilter.value = 'all';
  sortBy.value = 'newest';
  perPage.value = 15;
  currentPage.value = 1;
  suppressRegistryFetch = false;
  fetchRegistry();
};

watch(searchQuery, (value) => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = value.trim();
    currentPage.value = 1;
    fetchRegistry();
  }, 300);
});

watch([statusFilter, paymentFilter, noRefundFilter, eventFilter, sortBy, perPage], () => {
  currentPage.value = 1;
  fetchRegistry();
});

watch(currentPage, (page, previous) => {
  if (previous == null || suppressRegistryFetch) return;
  fetchRegistry();
});

const updateStatus = async (id, status, revisionComment = null) => {
  const payload = { approval_status: status };
  if (revisionComment) payload.revision_comment = revisionComment;
  try {
    await api.put(`/bookings/${id}`, payload);
    toast.success(`Booking #${id} updated to ${statusLabel(status)}.`);
    await fetchBookings();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to update booking.');
    }
  }
};

const requestRevision = async (id) => {
  const comment = window.prompt('Enter formal revision instructions for the vendor.');
  if (!comment?.trim()) {
    toast.error('Revision instructions are required.');
    return;
  }
  await updateStatus(id, 'Needs_Revision', comment.trim());
};

const deleteBooking = async (id) => {
  if (!canDeleteBookings.value) return;
  if (!window.confirm(`Delete booking #${id}? This cannot be undone.`)) return;
  try {
    await api.delete(`/bookings/${id}`);
    toast.success(`Booking #${id} deleted.`);
    await fetchBookings();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to delete booking.');
    }
  }
};

const viewPdf = async (bookingId) => {
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const fileUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to open booking PDF.');
    }
  }
};

onBeforeUnmount(() => {
  clearTimeout(searchDebounceTimer);
  proofRequestToken += 1;
  revokeProofObjectUrl();
});

const switchToRecoveryTab = async () => {
  activeWorkspaceTab.value = 'recovery';
  await nextTick();
  await recoveryPanel.value?.load?.();
};

defineExpose({ fetchBookings, switchToRecoveryTab });
</script>
