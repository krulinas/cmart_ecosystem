<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking #{{ $booking->id }} — Carboot@CMart</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .header {
            border-bottom: 2px solid #ea580c;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .brand { color: #ea580c; font-size: 18px; font-weight: bold; letter-spacing: 0.6px; }
        .doc-title { font-size: 22px; font-weight: bold; margin-top: 4px; color: #0f172a; }
        .meta { float: right; font-size: 10px; color: #64748b; text-align: right; line-height: 1.5; }

        .grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grid td { vertical-align: top; padding: 0; width: 50%; }

        .section { margin-top: 18px; }
        .section h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: #64748b;
            margin: 0 0 6px 0;
            font-weight: bold;
        }
        .field { margin-bottom: 3px; }
        .label { color: #64748b; }
        .value { color: #0f172a; font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        table.items th {
            background: #f8fafc;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .total-row td {
            font-weight: bold;
            font-size: 13px;
            border-top: 2px solid #1f2937;
            border-bottom: none;
            padding-top: 12px;
        }

        .status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }
        .status-Pending_Staff   { background: #fef3c7; color: #92400e; }
        .status-Pending_Boss    { background: #dbeafe; color: #1e40af; }
        .status-Needs_Revision  { background: #fde68a; color: #78350f; }
        .status-Approved        { background: #d1fae5; color: #065f46; }
        .status-Rejected        { background: #fee2e2; color: #991b1b; }

        .footer {
            position: fixed;
            bottom: 12px;
            left: 40px;
            right: 40px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="meta">
            Generated: {{ $generatedAt->format('d M Y, H:i') }}<br>
            Document: BOOKING-{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}
        </div>
        <div class="brand">Carboot@CMart</div>
        <div class="doc-title">Booking Summary &amp; Invoice</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="section">
                    <h3>Vendor</h3>
                    <div class="field"><span class="label">Name:</span> <span class="value">{{ $booking->user?->name ?? '—' }}</span></div>
                    <div class="field"><span class="label">Email:</span> <span class="value">{{ $booking->user?->email ?? '—' }}</span></div>
                    <div class="field"><span class="label">Phone:</span> <span class="value">{{ $booking->user?->phone_number ?? '—' }}</span></div>
                    <div class="field"><span class="label">Vendor Status:</span> <span class="value">{{ ucfirst($booking->user?->vendor_status ?? 'none') }}</span></div>
                </div>
            </td>
            <td>
                <div class="section">
                    <h3>Booking</h3>
                    <div class="field"><span class="label">Booking ID:</span> <span class="value">#{{ $booking->id }}</span></div>
                    <div class="field"><span class="label">Booking Date:</span> <span class="value">{{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }}</span></div>
                    <div class="field"><span class="label">Product Category:</span> <span class="value">{{ $booking->product_category ?? 'Others' }}</span></div>
                    <div class="field"><span class="label">Submitted:</span> <span class="value">{{ optional($booking->created_at)->format('d M Y, H:i') ?? '—' }}</span></div>
                    <div class="field">
                        <span class="label">Status:</span>
                        <span class="status status-{{ $booking->approval_status }}">
                            {{ str_replace('_', ' ', $booking->approval_status) }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h3>Items</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right" style="width: 130px;">Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Carboot Space — {{ $booking->space?->space_size ?? '—' }}<br>
                        <span style="color: #64748b; font-size: 10px;">
                            Reserved for {{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }}
                        </span>
                    </td>
                    <td class="text-right">{{ number_format($booking->space?->price ?? 0, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Due</td>
                    <td class="text-right">
                        RM {{ number_format($booking->invoice?->amount ?? $booking->space?->price ?? 0, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Payment</h3>
        <div class="field"><span class="label">Payment Status:</span> <span class="value">{{ $booking->invoice?->payment_status ?? 'Unpaid' }}</span></div>
        <div class="field"><span class="label">Invoice ID:</span> <span class="value">{{ $booking->invoice?->id ? '#' . str_pad($booking->invoice->id, 6, '0', STR_PAD_LEFT) : '—' }}</span></div>
    </div>

    <div class="section">
        <h3>Approval Pipeline</h3>
        <div class="field" style="color: #64748b;">
            Tier 1 (CMart Staff) reviews submissions in <span class="value">Pending_Staff</span>.
            Cleared submissions move to <span class="value">Pending_Boss</span> for Tier 2 (CMart Admin) approval.
            Rejected submissions are returned as <span class="value">Needs_Revision</span>.
        </div>
    </div>

    <div class="footer">
        This document is computer-generated and does not require a signature. Carboot@CMart · Changlun, Kedah.
    </div>
</body>
</html>
