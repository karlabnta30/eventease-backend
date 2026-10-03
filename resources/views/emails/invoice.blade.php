<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; color: #333; background: #f8fafc; padding: 20px; }
        .invoice-box { max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #eaeaea; }
        .header { text-align: center; border-bottom: 2px solid #eaeaea; padding-bottom: 15px; margin-bottom: 20px; }
        .details { margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #eaeaea; text-align: left; font-size: 0.9rem; }
        .total { font-weight: bold; text-align: right; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <h2 style="margin: 0; color: #10b981;">EventEase</h2>
            <p style="margin: 5px 0 0; color: #64748b; font-size: 0.85rem;">Official Customer Invoice & Receipt</p>
        </div>
        <div class="details">
            <p><strong>Transaction ID:</strong> {{ $paymentData['transaction_id'] ?? 'EVT-' . time() }}</p>
            <p><strong>Date:</strong> {{ now()->toFormattedDateString() }}</p>
            <p><strong>Customer Name:</strong> {{ $paymentData['customer_name'] }}</p>
        </div>
        <table class="table">
            <tr>
                <th>Description</th>
                <th>Amount</th>
            </tr>
            <tr>
                <td>{{ $paymentData['service_name'] ?? 'Event Booking Service' }}</td>
                <td>₱{{ number_format($paymentData['amount'], 2) }}</td>
            </tr>
        </table>
        <div class="total">
            Total Paid: ₱{{ number_format($paymentData['amount'], 2) }}
        </div>
        <p style="text-align: center; color: #94a3b8; font-size: 0.75rem; margin-top: 30px;">Thank you for using EventEase! This is a system-generated receipt.</p>
    </div>
</body>
</html>