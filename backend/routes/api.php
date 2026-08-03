<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingPassVerificationController;
use App\Http\Controllers\Api\BossAnalyticsController;
use App\Http\Controllers\Api\CarbootEventController;
use App\Http\Controllers\Api\CmartGeneratedReportController;
use App\Http\Controllers\Api\CmartReportEventOptionsController;
use App\Http\Controllers\Api\CmartReportRequestController;
use App\Http\Controllers\Api\EventDayController;
use App\Http\Controllers\Api\EventRegistrationController;
use App\Http\Controllers\Api\EventSiteController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemReservationController;
use App\Http\Controllers\Api\ManagementNotificationController;
use App\Http\Controllers\Api\ManagementReportsController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\NewsPostController;
use App\Http\Controllers\Api\OrganizerBookingSiteAssignmentController;
use App\Http\Controllers\Api\OrganizerEventLayoutController;
use App\Http\Controllers\Api\OrganizerEventLayoutRowController;
use App\Http\Controllers\Api\OrganizerEventLayoutSiteController;
use App\Http\Controllers\Api\OrganizerEventAnalyticsController;
use App\Http\Controllers\Api\OrganizerEventAnalyticsDataSourceController;
use App\Http\Controllers\Api\OrganizerGeneratedReportController;
use App\Http\Controllers\Api\OrganizerItemReservationController;
use App\Http\Controllers\Api\OrganizerReleasedDayRecoveryController;
use App\Http\Controllers\Api\OrganizerReportRequestController;
use App\Http\Controllers\Api\OrganizerSurveyImportController;
use App\Http\Controllers\Api\OrganizerVendorCategoryController;
use App\Http\Controllers\Api\PublicEventLayoutController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\StaffOperationsController;
use App\Http\Controllers\Api\UserBookingPreferenceController;
use App\Http\Controllers\Api\VendorAnalyticsController;
use App\Http\Controllers\Api\VendorBusinessProfileController;
use App\Http\Controllers\Api\VendorCategoryController;
use App\Http\Controllers\Api\VendorEventPassController;
use App\Http\Controllers\Api\VendorEventSiteAvailabilityController;
use App\Http\Controllers\Api\VendorHistoryController;
use App\Http\Controllers\Api\VendorItemController;
use App\Http\Controllers\Api\VendorItemReservationController;
use App\Http\Controllers\Api\VendorProfileController;
use App\Support\ManagementCapability;
use App\Support\ManagementRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API endpoints consumed by the Vue frontend and protected by Sanctum/RBAC.
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ==========================================
// --- NEW ROUTE: GOOGLE SIGN-IN ---
// ==========================================
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::get('/spaces', [SpaceController::class, 'index']);
Route::get('/spaces/{space}', [SpaceController::class, 'show']);

Route::get('/feedbacks', [FeedbackController::class, 'index']);
Route::post('/feedback/{id}/helpful', [FeedbackController::class, 'markHelpful']);

Route::get('/events', [CarbootEventController::class, 'publicIndex']);
Route::get('/events/{event}/layout', [PublicEventLayoutController::class, 'show']);
Route::get('/events/{carboot_event}', [CarbootEventController::class, 'publicShow']);
Route::get('/news', [NewsPostController::class, 'publicIndex']);

Route::get('/marketplace/items', [MarketplaceController::class, 'index']);
Route::get('/marketplace/items/{vendor_item}', [MarketplaceController::class, 'show']);

Route::get('/vendor-categories', [VendorCategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Community event RSVP — pessimistic locking prevents overbooking under concurrency.
    Route::post('/events/{carboot_event}/register', [EventRegistrationController::class, 'register']);

    Route::get('/bookings/{booking}/pdf', [BookingController::class, 'generatePdf']);

    Route::get('/booking-preferences/me', [UserBookingPreferenceController::class, 'show']);
    Route::put('/booking-preferences/me', [UserBookingPreferenceController::class, 'update']);
    Route::delete('/booking-preferences/me', [UserBookingPreferenceController::class, 'destroy']);

    Route::middleware('role:community')->group(function () {
        Route::get('/vendor/analytics/me', [VendorAnalyticsController::class, 'me']);
        Route::get('/vendor/analytics/report', [VendorAnalyticsController::class, 'report']);
        Route::get('/vendor/history-receipts', [VendorHistoryController::class, 'historyReceipts']);

        Route::get('/vendor/profile', [VendorProfileController::class, 'show']);
        Route::patch('/vendor/profile', [VendorProfileController::class, 'update']);

        Route::get('/vendor/business-profile', [VendorBusinessProfileController::class, 'show']);
        Route::put('/vendor/business-profile', [VendorBusinessProfileController::class, 'update']);
        Route::post('/vendor/business-profile/logo', [VendorBusinessProfileController::class, 'uploadLogo']);

        Route::get('/vendor/event-passes', [VendorEventPassController::class, 'index']);
        Route::get('/vendor/event-passes/{booking}', [VendorEventPassController::class, 'show']);

        Route::get('/vendor/items', [VendorItemController::class, 'index']);
        Route::post('/vendor/items', [VendorItemController::class, 'store']);
        Route::get('/vendor/items/{vendor_item}', [VendorItemController::class, 'show']);
        Route::put('/vendor/items/{vendor_item}', [VendorItemController::class, 'update']);
        Route::delete('/vendor/items/{vendor_item}', [VendorItemController::class, 'destroy']);

        Route::post('/reservations', [ItemReservationController::class, 'store']);
        Route::get('/reservations/me', [ItemReservationController::class, 'mine']);
        Route::get('/reservations/{item_reservation}', [ItemReservationController::class, 'show']);
        Route::post('/reservations/{item_reservation}/cancel', [ItemReservationController::class, 'cancel']);

        Route::get('/vendor/item-reservations', [VendorItemReservationController::class, 'index']);
        Route::get('/vendor/item-reservations/{item_reservation}', [VendorItemReservationController::class, 'show']);
        Route::post('/vendor/item-reservations/{item_reservation}/cancel', [VendorItemReservationController::class, 'cancel']);
        Route::post('/vendor/item-reservations/{item_reservation}/complete', [VendorItemReservationController::class, 'complete']);

        Route::get('/vendor/bookings', [BookingController::class, 'mine']);
        Route::get('/vendor/bookings/{booking}', [BookingController::class, 'vendorShow']);
        Route::patch('/vendor/bookings/{booking}', [BookingController::class, 'vendorUpdate']);
        Route::patch('/vendor/bookings/{booking}/resubmit', [BookingController::class, 'resubmit']);
        Route::post('/vendor/bookings/{booking}/cancel', [BookingController::class, 'vendorCancel']);
        Route::patch('/bookings/{booking}/withdraw', [BookingController::class, 'withdraw']);
        Route::post('/vendor/bookings/{booking}/request-change', [BookingController::class, 'vendorRequestChange']);
        Route::post('/vendor/bookings/{booking}/request-cancellation', [BookingController::class, 'vendorRequestCancellation']);
        Route::post('/vendor/bookings/{booking}/submit-payment', [BookingController::class, 'vendorSubmitPayment']);
        Route::post('/vendor/bookings/{booking}/demo-payment', [BookingController::class, 'vendorDemoPayment']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/vendor/events/{carboot_event}/site-availability', [VendorEventSiteAvailabilityController::class, 'show']);
    });

    Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::carbootOperationalRoles()))->group(function () {
        // Canonical Organizer operations routes (Phase 1.3C PR3).
        Route::prefix('organizer')->group(function () {
            Route::get('/feedbacks', [FeedbackController::class, 'staffIndex']);
            Route::get('/bookings/registry', [BookingController::class, 'staffRegistry']);
            Route::patch('/bookings/{booking}/attendance-exception', [BookingController::class, 'applyAttendanceException']);
            Route::get('/bookings/{booking}/category-placement', [OrganizerBookingSiteAssignmentController::class, 'categoryPlacement']);
            Route::get('/bookings/{booking}/site-reassignment-options', [OrganizerBookingSiteAssignmentController::class, 'reassignmentOptions']);
            Route::patch('/bookings/{booking}/site-assignment', [OrganizerBookingSiteAssignmentController::class, 'reassign']);
            Route::get('/released-day-recovery', [OrganizerReleasedDayRecoveryController::class, 'index']);
            Route::get('/operations-summary', [StaffOperationsController::class, 'operationsSummary']);
            Route::get('/bookings/{booking}/verify', [BookingPassVerificationController::class, 'verify']);
            Route::post('/bookings/{booking}/check-in', [BookingPassVerificationController::class, 'checkIn']);

            // Phase 2A.4 — physical event-site foundation (Organizer only).
            Route::get('/events/{carboot_event}/sites', [EventSiteController::class, 'index']);
            Route::post('/events/{carboot_event}/sites', [EventSiteController::class, 'store']);
            Route::post('/events/{carboot_event}/sites/generate', [EventSiteController::class, 'generate']);
            Route::get('/event-sites/{event_site}', [EventSiteController::class, 'show']);
            Route::put('/event-sites/{event_site}', [EventSiteController::class, 'update']);
            Route::patch('/event-sites/{event_site}', [EventSiteController::class, 'update']);
            Route::delete('/event-sites/{event_site}', [EventSiteController::class, 'destroy']);

            // Phase 2A.5 — Organizer-defined operational event days.
            Route::get('/events/{carboot_event}/days', [EventDayController::class, 'index']);
            Route::post('/events/{carboot_event}/days', [EventDayController::class, 'store']);
            Route::post('/events/{carboot_event}/days/generate', [EventDayController::class, 'generate']);
            Route::get('/event-days/{event_day}', [EventDayController::class, 'show']);
            Route::put('/event-days/{event_day}', [EventDayController::class, 'update']);
            Route::patch('/event-days/{event_day}', [EventDayController::class, 'update']);
            Route::delete('/event-days/{event_day}', [EventDayController::class, 'destroy']);

            // Phase 3.5 — Organizer category-based layout, readiness and locking.
            Route::get('/vendor-categories', [OrganizerVendorCategoryController::class, 'index']);
            Route::get('/events/{carboot_event}/layout', [OrganizerEventLayoutController::class, 'show']);
            Route::get('/events/{carboot_event}/layout/readiness', [OrganizerEventLayoutController::class, 'readiness']);
            Route::post('/events/{carboot_event}/layout/standard-template', [OrganizerEventLayoutController::class, 'generateStandardTemplate']);
            Route::post('/events/{carboot_event}/layout/publish', [OrganizerEventLayoutController::class, 'publish']);
            Route::post('/events/{carboot_event}/layout/unpublish', [OrganizerEventLayoutController::class, 'unpublish']);

            Route::post('/events/{carboot_event}/layout/rows', [OrganizerEventLayoutRowController::class, 'store']);
            Route::patch('/events/{carboot_event}/layout/rows/reorder', [OrganizerEventLayoutRowController::class, 'reorder']);
            Route::patch('/events/{carboot_event}/layout/rows/{row}', [OrganizerEventLayoutRowController::class, 'update']);
            Route::delete('/events/{carboot_event}/layout/rows/{row}', [OrganizerEventLayoutRowController::class, 'destroy']);
            Route::patch('/events/{carboot_event}/layout/rows/{row}/archive', [OrganizerEventLayoutRowController::class, 'archive']);
            Route::patch('/events/{carboot_event}/layout/rows/{row}/unarchive', [OrganizerEventLayoutRowController::class, 'unarchive']);

            // Phase 4.3 — manual reservation service-fee lifecycle (Organizer only).
            Route::get('/events/{carboot_event}/item-reservations', [OrganizerItemReservationController::class, 'index']);
            Route::get('/item-reservations/{item_reservation}', [OrganizerItemReservationController::class, 'show']);
            Route::get('/item-reservations/{item_reservation}/audits', [OrganizerItemReservationController::class, 'audits']);
            Route::post('/item-reservations/{item_reservation}/confirm-charge', [OrganizerItemReservationController::class, 'confirmCharge']);
            Route::post('/item-reservations/{item_reservation}/waive-charge', [OrganizerItemReservationController::class, 'waiveCharge']);
            Route::post('/item-reservations/{item_reservation}/cancel', [OrganizerItemReservationController::class, 'cancel']);
            Route::post('/item-reservations/{item_reservation}/expire', [OrganizerItemReservationController::class, 'expire']);
            Route::post('/item-reservations/{item_reservation}/complete', [OrganizerItemReservationController::class, 'complete']);

            Route::post('/events/{carboot_event}/layout/rows/{row}/sites', [OrganizerEventLayoutSiteController::class, 'store']);
            Route::post('/events/{carboot_event}/layout/rows/{row}/sites/generate', [OrganizerEventLayoutSiteController::class, 'generate']);
            Route::patch('/events/{carboot_event}/layout/rows/{row}/sites/reorder', [OrganizerEventLayoutSiteController::class, 'reorder']);
            Route::patch('/events/{carboot_event}/layout/sites/{site}', [OrganizerEventLayoutSiteController::class, 'update']);
            Route::delete('/events/{carboot_event}/layout/sites/{site}', [OrganizerEventLayoutSiteController::class, 'destroy']);
        });

        // Deprecated PR2 compatibility — remove after external clients migrate.
        Route::get('/staff/feedbacks', [FeedbackController::class, 'staffIndex']);
        Route::get('/staff/bookings', [BookingController::class, 'staffRegistry']);
        Route::get('/staff/operations-summary', [StaffOperationsController::class, 'operationsSummary']);
        Route::get('/staff/bookings/{booking}/verify', [BookingPassVerificationController::class, 'verify']);
        Route::post('/staff/bookings/{booking}/check-in', [BookingPassVerificationController::class, 'checkIn']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}/verify-payment', [BookingController::class, 'verifyBookingPayment']);

        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);

        Route::get('/feedbacks/{feedback}', [FeedbackController::class, 'show']);
        Route::put('/feedbacks/{feedback}', [FeedbackController::class, 'update']);
        Route::patch('/feedbacks/{feedback}', [FeedbackController::class, 'update']);
        Route::post('/feedbacks/{feedback}/reviewed', [FeedbackController::class, 'markReviewed']);
        Route::put('/feedbacks/{feedback}/official-reply', [FeedbackController::class, 'updateOfficialReply']);

        Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::organizerEquivalentRoles()))->group(function () {
            Route::post('/feedbacks/{feedback}/official-reply/publish', [FeedbackController::class, 'publishOfficialReply']);
            Route::delete('/feedbacks/{feedback}', [FeedbackController::class, 'destroy']);
        });

        Route::apiResource('carboot-events', CarbootEventController::class);
    });

    Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::cmartActivityRoles()))->group(function () {
        Route::apiResource('news-posts', NewsPostController::class);
    });

    Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::managementWorkspaceRoles()))->group(function () {
        Route::get('/management/notifications', [ManagementNotificationController::class, 'index']);
        Route::get('/management/notifications/unread-count', [ManagementNotificationController::class, 'unreadCount']);
        Route::post('/management/notifications/mark-all-read', [ManagementNotificationController::class, 'markAllRead']);
        Route::post('/management/notifications/{id}/read', [ManagementNotificationController::class, 'markRead']);
    });

    Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::carbootOperationalRoles()))->group(function () {
        // Operational overview (live queue counts) — Organizer-only; not a published report.
        Route::get('/management/reports/operational-overview', [ManagementReportsController::class, 'operationalOverview']);
        Route::get('/organizer/generated-reports/{generated_report}/pdf', [OrganizerGeneratedReportController::class, 'downloadPdf']);
    });

    // CMart Management — report requests + event options for the request form.
    Route::middleware('role:'.ManagementRole::CMART_MANAGEMENT)->group(function () {
        Route::get('/cmart/report-events', [CmartReportEventOptionsController::class, 'index']);
        Route::get('/cmart/report-requests', [CmartReportRequestController::class, 'index']);
        Route::post('/cmart/report-requests', [CmartReportRequestController::class, 'store']);
        Route::get('/cmart/report-requests/{report_request}', [CmartReportRequestController::class, 'show']);
        Route::post('/cmart/report-requests/{report_request}/cancel', [CmartReportRequestController::class, 'cancel']);
    });

    // CMart Management — consume published / superseded generated reports.
    Route::middleware([
        'role:'.ManagementRole::CMART_MANAGEMENT,
        'capability:'.ManagementCapability::GENERATED_REPORTS,
    ])->group(function () {
        Route::get('/cmart/generated-reports', [CmartGeneratedReportController::class, 'index']);
        Route::get('/cmart/generated-reports/{generated_report}', [CmartGeneratedReportController::class, 'show']);
        Route::get('/cmart/generated-reports/{generated_report}/pdf', [CmartGeneratedReportController::class, 'downloadPdf']);
        Route::post('/cmart/generated-reports/{generated_report}/mark-viewed', [CmartGeneratedReportController::class, 'markViewed']);
    });

    // Organizer report centre — request handling + draft / publish / revise.
    Route::middleware('role:'.ManagementRole::routeRoleList(ManagementRole::organizerEquivalentRoles()))->group(function () {
        Route::get('/organizer/report-requests', [OrganizerReportRequestController::class, 'index']);
        Route::get('/organizer/report-requests/{report_request}', [OrganizerReportRequestController::class, 'show']);
        Route::post('/organizer/report-requests/{report_request}/acknowledge', [OrganizerReportRequestController::class, 'acknowledge']);
        Route::post('/organizer/report-requests/{report_request}/start-preparation', [OrganizerReportRequestController::class, 'startPreparation']);
        Route::post('/organizer/report-requests/{report_request}/decline', [OrganizerReportRequestController::class, 'decline']);

        Route::get('/organizer/generated-reports', [OrganizerGeneratedReportController::class, 'index']);
        Route::post('/organizer/generated-reports', [OrganizerGeneratedReportController::class, 'store']);
        Route::get('/organizer/generated-reports/{generated_report}', [OrganizerGeneratedReportController::class, 'show']);
        Route::patch('/organizer/generated-reports/{generated_report}/narratives', [OrganizerGeneratedReportController::class, 'updateNarratives']);
        Route::post('/organizer/generated-reports/{generated_report}/regenerate', [OrganizerGeneratedReportController::class, 'regenerate']);
        Route::post('/organizer/generated-reports/{generated_report}/publish', [OrganizerGeneratedReportController::class, 'publish']);
        Route::delete('/organizer/generated-reports/{generated_report}', [OrganizerGeneratedReportController::class, 'destroy']);
        Route::post('/organizer/generated-reports/{generated_report}/revise', [OrganizerGeneratedReportController::class, 'revise']);

        // Event-scoped analytics hub + vendor survey import (organizer / super_admin).
        Route::get('/organizer/events/{event}/analytics/overview', [OrganizerEventAnalyticsController::class, 'overview']);
        Route::get('/organizer/events/{event}/analytics/{section}', [OrganizerEventAnalyticsController::class, 'section']);
        Route::post('/organizer/events/{event}/analytics/recompute', [OrganizerEventAnalyticsController::class, 'recompute']);
        Route::put('/organizer/events/{event}/analytics/source-mode', [OrganizerEventAnalyticsDataSourceController::class, 'updateMode']);
        Route::post('/organizer/events/{event}/survey-imports/undo', [OrganizerEventAnalyticsDataSourceController::class, 'undo']);
        Route::post('/organizer/events/{event}/survey-imports/remove-from-analytics', [OrganizerEventAnalyticsDataSourceController::class, 'removeCsv']);
        Route::post('/organizer/events/{event}/survey-imports/{batch}/activate', [OrganizerEventAnalyticsDataSourceController::class, 'activate']);
        Route::post('/organizer/events/{event}/survey-imports/{batch}/exclude', [OrganizerEventAnalyticsDataSourceController::class, 'exclude']);
        Route::post('/organizer/events/{event}/survey-imports/{batch}/archive', [OrganizerEventAnalyticsDataSourceController::class, 'archive']);
        Route::post('/organizer/events/{event}/survey-imports/{batch}/restore', [OrganizerEventAnalyticsDataSourceController::class, 'restore']);
        Route::get('/organizer/events/{event}/survey-imports', [OrganizerSurveyImportController::class, 'index']);
        Route::post('/organizer/events/{event}/survey-imports', [OrganizerSurveyImportController::class, 'store']);
        Route::get('/organizer/events/{event}/survey-imports/{batch}', [OrganizerSurveyImportController::class, 'show']);
    });

    Route::middleware('boss')->group(function () {
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
        Route::post('/profitability', [BookingController::class, 'checkProfitability']);
        Route::get('/boss/analytics/revenue', [BossAnalyticsController::class, 'revenue']);
        Route::get('/boss/analytics/wordcloud/{source}', [BossAnalyticsController::class, 'wordcloud']);
        Route::get('/boss/audit-logs', [AuditLogController::class, 'index']);
        Route::post('/spaces', [SpaceController::class, 'store']);
        Route::put('/spaces/{space}', [SpaceController::class, 'update']);
        Route::patch('/spaces/{space}', [SpaceController::class, 'update']);
        Route::delete('/spaces/{space}', [SpaceController::class, 'destroy']);
    });

    Route::post('/feedback/submit', [FeedbackController::class, 'store'])->middleware('throttle:10,1');
});
