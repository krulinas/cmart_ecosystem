<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Invoice;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * FR1 & FR2: Handle Vendor Registration, Pricing, and WhatsApp Integration
     */
    public function store(Request $request)
    {
        // 1. Validate the incoming data from Vue.js
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'space_id' => 'required|exists:spaces,id',
            'booking_date' => 'required|date',
        ]);

        // 2. Fetch the requested space to get the automated price (FR1)
        $space = Space::findOrFail($validated['space_id']);

        // 3. Create the Booking (Default status is 'Pending')
        $booking = Booking::create([
            'user_id' => $validated['user_id'],
            'space_id' => $space->id,
            'booking_date' => $validated['booking_date'],
            'approval_status' => 'Pending',
            // FR2: Inject the official WhatsApp group link directly into the booking record
            'whatsapp_link' => 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE' 
        ]);

        // 4. Automatically generate the Invoice based on the Space price
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => $space->price,
            'payment_status' => 'Unpaid'
        ]);

        return response()->json([
            'message' => 'Booking submitted successfully! Please join the WhatsApp group. Processing takes 3-5 business days.',
            'booking' => $booking,
            'invoice' => $invoice
        ], 201);
    }

    /**
     * FR3: Admin Approval Logic
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'approval_status' => 'required|in:Pending,Approved,Rejected'
        ]);

        $booking->update(['approval_status' => $validated['approval_status']]);

        // If approved, you would normally trigger an email/WhatsApp API here.
        
        return response()->json([
            'message' => 'Booking status updated to ' . $validated['approval_status'],
            'booking' => $booking
        ]);
    }

    /**
     * FR4: Custom Profitability Logic (Admin Tool)
     * Compares Event Space Revenue vs Regular Parking Revenue
     */
    public function checkProfitability(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'parking_lots_used' => 'required|numeric',
            'regular_parking_rate' => 'required|numeric', // e.g., RM 1.00 per hour
            'hours_occupied' => 'required|numeric'
        ]);

        $space = Space::findOrFail($validated['space_id']);

        // Calculate Revenue
        $eventRevenue = $space->price;
        $parkingRevenue = $validated['parking_lots_used'] * $validated['regular_parking_rate'] * $validated['hours_occupied'];

        $isProfitable = $eventRevenue > $parkingRevenue;
        $profitMargin = $eventRevenue - $parkingRevenue;

        return response()->json([
            'event_revenue' => $eventRevenue,
            'lost_parking_revenue' => $parkingRevenue,
            'is_profitable' => $isProfitable,
            'net_profit' => $profitMargin,
            'message' => $isProfitable ? 'Approved: Event yields higher profit.' : 'Warning: Regular parking is more profitable.'
        ]);
    }

    // Standard methods (index, show, destroy) omitted for brevity but they exist to fetch data!
    public function index() { return Booking::with(['user', 'space', 'invoice'])->get(); }
    public function show($id) { return Booking::with(['user', 'space', 'invoice'])->findOrFail($id); }
    public function destroy($id) { Booking::destroy($id); return response()->json(['message' => 'Deleted']); }
}