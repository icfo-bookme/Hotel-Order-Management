<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoiceNumber }} | BookMe Travel Agency</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f6; color: #18243b; font: 13px/1.55 Arial, sans-serif; }
        .sheet { max-width: 900px; margin: 28px auto; padding: 42px; background: white; }
        .toolbar { max-width: 900px; margin: 20px auto 0; text-align: right; }
        .toolbar button { border: 0; border-radius: 6px; padding: 10px 20px; background: #153c75; color: white; cursor: pointer; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 3px solid #153c75; padding-bottom: 24px; }
        .header > div, .details > div { min-width: 0; overflow-wrap: anywhere; }
        .logo { width: 150px; height: auto; }
        h1 { margin: 0; font-size: 28px; letter-spacing: 2px; color: #153c75; }
        h2 { margin: 12px 0 0; font-size: 17px; }
        p { margin: 4px 0; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .details { display: flex; justify-content: space-between; gap: 24px; margin: 24px 0; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0 24px; }
        th { background: #edf2f8; text-align: left; font-size: 11px; padding: 10px 8px; }
        td { padding: 12px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
        .table-wrap { min-width: 0; }
        .payment-table { table-layout: fixed; border: 1px solid #e2e8f0; margin-top: 10px; }
        .payment-table th, .payment-table td { padding: 11px 14px; vertical-align: middle; border-right: 1px solid #e2e8f0; }
        .payment-table th:last-child, .payment-table td:last-child { border-right: 0; }
        .payment-table th { color: #334155; font-weight: 600; font-size: 12px; }
        .payment-table tbody tr:nth-child(even) { background: #f8fafc; }
        .payment-table .money { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .section-title { margin: 24px 0 8px; font-size: 15px; }
        .money { white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }
        .summary { width: 340px; max-width: 100%; margin: 0 0 24px auto; }
        .summary p { display: flex; justify-content: space-between; gap: 16px; padding: 7px 0; }
        .summary .due { border-top: 2px solid #153c75; font-size: 17px; font-weight: bold; color: #153c75; }
        .notes { border-top: 1px solid #e2e8f0; padding-top: 18px; margin-top: 24px; text-align: center; font-size: 12px; line-height: 1.7; color: #64748b; }
        tr { break-inside: avoid; }
        thead { display: table-header-group; }
        @page { size: A4; margin: 14mm; }
        @media print {
            body { background: white; }
            .sheet { margin: 0; padding: 0; max-width: none; }
            .toolbar { display: none; }
            th { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .summary, .notes { break-inside: avoid; }
        }
        @media screen and (max-width: 640px) {
            .sheet { padding: 18px; margin: 10px; }
            .toolbar { margin: 12px 10px; }
            .header, .details { flex-direction: column; gap: 16px; }
            .right { text-align: left; }
            .summary { width: 100%; }
            .summary .due { font-size: 15px; }
            .summary p > :last-child { overflow-wrap: anywhere; text-align: right; min-width: 0; }
            .responsive-table, .responsive-table tbody { display: block; width: 100%; }
            .responsive-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
            .responsive-table colgroup { display: none; }
            .responsive-table { border: 0; }
            .responsive-table tbody tr { display: block; margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; }
            .responsive-table tbody td { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 12px; padding: 8px 0; text-align: left; white-space: normal; }
            .responsive-table tbody td::before { content: attr(data-label); font-size: 11px; font-weight: 600; color: #64748b; }
            .responsive-table tbody td:last-child { border-bottom: 0; }
            .payment-table tbody td { border-right: 0; }
            .payment-table .money { white-space: normal; overflow-wrap: anywhere; }
            .responsive-table .charge-label { display: block; }
            .responsive-table .charge-label::before { content: none; }
            .notes { overflow-wrap: anywhere; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" id="print-invoice">Print Invoice</button></div>
    <main class="sheet" data-booking-invoice>
        <header class="header">
            <div>
                <img src="{{ asset('bookme-logo.png') }}" width="150" height="41" alt="BookMe" class="logo">
                <h2>BookMe Travel Agency</h2>
                <p class="muted">Hotel Booking Invoice</p>
            </div>
            <div class="right">
                <h1>INVOICE</h1>
                <p><strong>{{ $invoiceNumber }}</strong></p>
                <p class="muted">Issued: {{ $booking->created_at->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</p>
                <p class="muted">Booking #{{ $booking->id }} / Sale #{{ $booking->sales_id }}</p>
                <p>Status: {{ ucfirst($booking->status) }}</p>
            </div>
        </header>
        <section class="details">
            <div>
                <p class="label">Billed To</p>
                <p><strong>{{ $booking->sale->customer_name }}</strong></p>
                <p>Mobile: {{ $booking->sale->customer_mobile }}</p>
                @if ($booking->sale->whatsapp)<p>WhatsApp: {{ $booking->sale->whatsapp }}</p>@endif
                @if ($booking->sale->email)<p>{{ $booking->sale->email }}</p>@endif
            </div>
            <div class="right">
                <p class="label">Booking Details</p>
                <p>Booked by: {{ $booking->creator?->name ?? '-' }}</p>
                <p>Currency: BDT</p>
            </div>
        </section>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Hotel / Room</th><th>Check-in / Check-out</th><th>Nights</th><th class="money">Rate / Night</th><th class="money">Amount (BDT)</th></tr></thead>
                <tbody>
                    @foreach ($booking->rooms as $room)
                        <tr>
                            <td data-label="Hotel / Room"><div><strong>{{ $room->hotel_name }}</strong><p class="muted">{{ $room->room_name }}</p></div></td>
                            <td data-label="Check-in / Check-out"><div>{{ $room->check_in_date->format('d M Y') }}<br>{{ $room->check_out_date->format('d M Y') }}</div></td>
                            <td data-label="Nights">{{ $room->nights }}</td>
                            <td data-label="Rate / Night" class="money">{{ $room->customer_price_per_night }}</td>
                            <td data-label="Amount (BDT)" class="money">{{ \App\Support\Money::format(\App\Support\Money::cents($room->customer_price_per_night) * $room->nights) }}</td>
                        </tr>
                        @if ($room->reschedules->isNotEmpty())
                            <tr><td colspan="4" class="charge-label">Reschedule charges — {{ $room->room_name }}</td><td data-label="Amount (BDT)" class="money">{{ \App\Support\Money::format($room->reschedules->sum(fn ($item) => \App\Support\Money::cents($item->customer_fee))) }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <section class="summary">
            <p><span>Total (BDT)</span><strong>{{ $total }}</strong></p>
            <p><span>Received (BDT)</span><strong>{{ $received }}</strong></p>
            <p class="due"><span>Due</span><span>{{ $due }}</span></p>
        </section>
        @if ($payments->isNotEmpty())
            <h2 class="section-title">Payment Details</h2>
            <div class="table-wrap"><table class="payment-table responsive-table">
                <colgroup><col style="width: 25%"><col style="width: 25%"><col style="width: 25%"><col style="width: 25%"></colgroup>
                <thead><tr><th scope="col">Paid At</th><th scope="col">Method</th><th scope="col">Reference</th><th scope="col" class="money">Amount (BDT)</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td data-label="Paid At">{{ $payment->paid_at?->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                            <td data-label="Method">{{ ['bkash' => 'bKash', 'nagad' => 'Nagad'][$payment->payment_method] ?? ucfirst($payment->payment_method) }}</td>
                            <td data-label="Reference">{{ $payment->transaction_reference ?: '-' }}</td>
                            <td data-label="Amount (BDT)" class="money"><strong>{{ $payment->amount }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
        <footer class="notes">
            <p>Thank you for booking with BookMe Travel Agency.</p>
            <p>Email: support@bookme.com.bd | Phone: +880 1234 567890</p>
            <p><a href="https://www.bookme.com.bd">https://www.bookme.com.bd</a></p>
            <p>This is a computer-generated invoice. No signature required.</p>
            <p>Generated by: {{ auth()->user()->name }} | Generated on: {{ now('Asia/Dhaka')->format('d M, Y h:i A') }}</p>
        </footer>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        $(document).ready(function initializeInvoice() {
            $('#print-invoice').on('click', printInvoice);
        });
        function printInvoice() { print(); }
        function autoPrintInvoice() {
            if (new URLSearchParams(location.search).get('print') === '1') printInvoice();
        }
        if (document.readyState === 'complete') {
            autoPrintInvoice();
        } else {
            $(document.defaultView).one('load', autoPrintInvoice);
        }
    </script>
</body>
</html>
