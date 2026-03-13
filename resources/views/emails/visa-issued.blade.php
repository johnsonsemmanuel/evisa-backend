<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eVisa Issued</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5f2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .success-box { background: #e8f5e9; border: 2px solid #4caf50; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .btn { display: inline-block; background: #1a5f2a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .info-box { background: #fff; padding: 15px; border-left: 4px solid #1a5f2a; margin: 15px 0; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🇬🇭 Ghana eVisa Portal</h1>
    </div>
    <div class="content">
        <div class="success-box">
            <h2 style="color: #4caf50; margin: 0;">✓ Your eVisa Has Been Issued</h2>
            <p style="margin: 10px 0 0;">Reference: <strong>{{ $reference_number }}</strong></p>
        </div>

        <p>Dear {{ $applicant_name }},</p>
        <p>Great news! Your electronic visa for the Republic of Ghana has been officially <strong>issued</strong> and is ready for download.</p>

        <div class="info-box">
            <strong>eVisa Details:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Visa Type: {{ $visa_type ?? 'N/A' }}</li>
                <li>Reference Number: {{ $reference_number }}</li>
                <li>Issue Date: {{ now()->format('F j, Y') }}</li>
            </ul>
        </div>

        <h3>Download Your eVisa</h3>
        <p>Please log in to your dashboard to download your official eVisa document. Print a copy to present upon arrival in Ghana.</p>

        <center>
            <a href="{{ config('app.frontend_url') }}/dashboard/applicant/applications" class="btn">Go to Dashboard</a>
        </center>

        <div class="warning">
            <strong>Important Reminders:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Print your eVisa and carry it with your valid passport</li>
                <li>Ensure your passport is valid for at least 6 months beyond your travel dates</li>
                <li>Present both documents at immigration upon arrival</li>
                <li>The eVisa must be used within the validity period indicated on the document</li>
                <li>A valid Yellow Fever vaccination certificate is required at port of entry</li>
            </ul>
        </div>

        <p>We wish you a pleasant journey to Ghana. Akwaaba!</p>
    </div>
    <div class="footer">
        <p>Ghana Immigration Service | Ministry of Interior</p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
