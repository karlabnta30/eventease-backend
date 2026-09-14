<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding: 40px; color: #0f172a; }
        .card { background: #ffffff; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; max-width: 400px; margin: 0 auto; text-align: center; }
        .otp-code { font-size: 2.5rem; font-weight: 900; letter-spacing: 6px; color: #2563eb; margin: 20px 0; }
        .footer { font-size: 0.8rem; color: #64748b; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Verification Code</h2>
        <p>Use the secure one-time password below to complete your authorization on EventEase:</p>
        <div class="otp-code">{{ $otp }}</div>
        <p>This code expires in 10 minutes.</p>
        <div class="footer">If you didn't request this, please ignore this email.</div>
    </div>
</body>
</html>