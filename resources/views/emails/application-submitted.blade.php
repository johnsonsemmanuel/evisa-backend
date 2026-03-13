<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5f2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .reference { background: #e8f5e9; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: center; }
        .reference-number { font-size: 24px; font-weight: bold; color: #1a5f2a; letter-spacing: 2px; }
        .btn { display: inline-block; background: #1a5f2a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .info-box { background: #fff; padding: 15px; border-left: 4px solid #1a5f2a; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🇬🇭 Ghana eVisa Portal</h1>
    </div>
    <div class="content">
        <h2>Application Submitted Successfully</h2>
        <p>Dear {{ $applicant_name }},</p>
        <p>Thank you for submitting your visa application to the Ghana Immigration Service. Your application has been received and is now being processed.</p>
        
        <div class="reference">
            <p style="margin: 0; color: #666;">Your Reference Number</p>
            <p class="reference-number">{{ $reference_number }}</p>
            <p style="margin: 0; font-size: 12px; color: #999;">Please save this number for tracking your application</p>
        </div>

        <div class="info-box">
            <strong>Application Details:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Visa Type: {{ $visa_type ?? 'N/A' }}</li>
                <li>Submitted: {{ now()->format('F j, Y \a\t H:i') }}</li>
                <li>Status: Under Review</li>
            </ul>
        </div>

        <h3>What Happens Next?</h3>
        <ol>
            <li>Your application will be reviewed by our officers</li>
            <li>You may receive requests for additional information</li>
            <li>Once approved, your eVisa will be emailed to you</li>
        </ol>

        <p>You can track your application status at any time using your reference number.</p>
        
        <center>
            <a href="{{ config('app.frontend_url') }}/track" class="btn">Track Application</a>
        </center>
    </div>
    <div class="footer">
        <p>Ghana Immigration Service | Ministry of Interior</p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
