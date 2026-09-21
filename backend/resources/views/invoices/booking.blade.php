<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        .status-Pending_Organizer { background: #fef3c7; color: #92400e; }
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
    @php
        $gen = $generatedAt->copy()->timezone('Asia/Kuala_Lumpur')->locale(app()->getLocale());
        $bookingDate = \Carbon\Carbon::parse($booking->booking_date)->timezone('Asia/Kuala_Lumpur')->locale(app()->getLocale());
        $submittedAt = optional($booking->created_at)?->copy()->timezone('Asia/Kuala_Lumpur')->locale(app()->getLocale());
    @endphp
    <div class="header">
        <div class="meta">
            {{ __('invoice.generated') }}: {{ $gen->translatedFormat('d M Y, H:i') }}<br>
            {{ __('invoice.document') }}: BOOKING-{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}
        </div>
        <div class="brand">Carboot@CMart</div>
        <div class="doc-title">{{ __('invoice.doc_title') }}</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="section">
                    <h3>{{ __('invoice.vendor') }}</h3>
                    <div class="field"><span class="label">{{ __('invoice.name') }}:</span> <span class="value">{{ $booking->user?->name ?? '—' }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.email') }}:</span> <span class="value">{{ $booking->user?->email ?? '—' }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.phone') }}:</span> <span class="value">{{ $booking->user?->phone_number ?? '—' }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.vendor_status') }}:</span> <span class="value">{{ ucfirst($booking->user?->vendor_status ?? 'none') }}</span></div>
                </div>
            </td>
            <td>
                <div class="section">
                    <h3>{{ __('invoice.booking') }}</h3>
                    <div class="field"><span class="label">{{ __('invoice.booking_id') }}:</span> <span class="value">#{{ $booking->id }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.booking_date') }}:</span> <span class="value">{{ $bookingDate->translatedFormat('d M Y') }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.product_category') }}:</span> <span class="value">{{ $booking->product_category ?? __('invoice.others') }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.product_details') }}:</span> <span class="value">{{ $booking->product_details ?? '—' }}</span></div>
                    <div class="field"><span class="label">{{ __('invoice.submitted') }}:</span> <span class="value">{{ $submittedAt?->translatedFormat('d M Y, H:i') ?? '—' }}</span></div>
                    <div class="field">
                        <span class="label">{{ __('invoice.status') }}:</span>
                        <span class="status status-{{ $booking->approval_status }}">
                            {{ str_replace('_', ' ', $booking->approval_status) }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h3>{{ __('invoice.items') }}</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>{{ __('invoice.description') }}</th>
                    <th class="text-right" style="width: 130px;">{{ __('invoice.amount_rm') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $siteQuantity = (int) ($booking->site_quantity ?? 1);
                    $unitSitePrice = (float) ($booking->unit_site_price ?? $booking->carbootEvent?->site_price ?? 0);
                    $lineAmount = (float) ($booking->invoice?->amount ?? ($unitSitePrice * max($siteQuantity, 1)));
                @endphp
                <tr>
                    <td>
                        {{ __('invoice.parking_sites', ['qty' => $siteQuantity, 'unit' => number_format($unitSitePrice, 2)]) }}<br>
                        <span style="color: #64748b; font-size: 10px;">
                            {{ __('invoice.reserved_for', ['date' => $bookingDate->translatedFormat('d M Y')]) }}
                        </span>
                    </td>
                    <td class="text-right">{{ number_format($lineAmount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>{{ __('invoice.total_due') }}</td>
                    <td class="text-right">
                        RM {{ number_format($booking->invoice?->amount ?? $lineAmount, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>{{ __('invoice.payment') }}</h3>
        <div class="field"><span class="label">{{ __('invoice.payment_status') }}:</span> <span class="value">{{ $booking->invoice?->payment_status ?? __('invoice.unpaid') }}</span></div>
        <div class="field"><span class="label">{{ __('invoice.invoice_id') }}:</span> <span class="value">{{ $booking->invoice?->id ? '#' . str_pad($booking->invoice->id, 6, '0', STR_PAD_LEFT) : '—' }}</span></div>
    </div>

    <div class="section">
        <h3>{{ __('invoice.approval_pipeline') }}</h3>
        <div class="field" style="color: #64748b;">
            {{ __('invoice.approval_note') }}
        </div>
    </div>

    <div class="footer">
        {{ __('invoice.footer') }}
    </div>
</body>
</html>
