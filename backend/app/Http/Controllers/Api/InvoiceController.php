<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::with(['booking.user', 'booking.space'])
            ->latest()
            ->get();
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load(['booking.user', 'booking.space']);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:Unpaid,Paid,Refunded,Pending Verification',
            'amount' => 'sometimes|numeric|min:0',
        ]);

        $invoice->update($validated);

        return response()->json([
            'message' => '200 OK: Invoice updated successfully.',
            'invoice' => $invoice->fresh(['booking.user', 'booking.space']),
        ]);
    }
}
