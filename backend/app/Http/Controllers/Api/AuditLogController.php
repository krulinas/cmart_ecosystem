<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $logs = BookingAuditLog::query()
            ->with([
                'actor:id,name,email,role',
                'booking:id,user_id,space_id,product_category,approval_status',
                'booking.user:id,name',
                'booking.space:id,space_size',
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json($logs);
    }
}
