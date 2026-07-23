<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ManagementRole;
use App\Support\ReportNotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app database notifications for management workspace roles.
 */
class ManagementNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertManagementUser($request);

        $types = $this->badgeTypesFor($request->user()->role);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (DatabaseNotification $n) => $this->isReportNotification($n, $types) || $request->boolean('include_all'))
            ->values()
            ->map(fn (DatabaseNotification $n) => $this->present($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->unreadReportCount($request),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->assertManagementUser($request);

        return response()->json([
            'unread_count' => $this->unreadReportCount($request),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->assertManagementUser($request);

        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => '200 OK: Notification marked as read.',
            'notification' => $this->present($notification->fresh()),
            'unread_count' => $this->unreadReportCount($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->assertManagementUser($request);

        // Intentionally not exposed for Report Centre open — avoid clearing unrelated notices.
        return response()->json([
            'message' => '405 Method Not Allowed: Bulk mark-all-read is disabled for report badges.',
        ], 405);
    }

    private function unreadReportCount(Request $request): int
    {
        $types = $this->badgeTypesFor($request->user()->role);

        return $request->user()
            ->unreadNotifications()
            ->get()
            ->filter(fn (DatabaseNotification $n) => $this->isReportNotification($n, $types))
            ->count();
    }

    /**
     * @return list<string>
     */
    private function badgeTypesFor(?string $role): array
    {
        if (ManagementRole::isCmartManagementRole($role)) {
            return ReportNotificationType::cmartBadgeTypes();
        }

        if (ManagementRole::isOrganizerRole($role) || ManagementRole::isSuperAdminRole($role)) {
            // Super Admin may see organizer badge if using organizer centre, but daily recipients exclude them.
            return ReportNotificationType::organizerBadgeTypes();
        }

        return [];
    }

    /**
     * @param  list<string>  $types
     */
    private function isReportNotification(DatabaseNotification $notification, array $types): bool
    {
        $type = $notification->data['type'] ?? null;

        return is_string($type) && in_array($type, $types, true);
    }

    private function assertManagementUser(Request $request): void
    {
        if (! ManagementRole::isManagementUser($request->user()?->role)) {
            abort(403, '403 Forbidden: Management access required.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            'link' => $data['link'] ?? '/admin',
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'data' => $data,
        ];
    }
}
