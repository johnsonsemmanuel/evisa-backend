<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Additional Information Required</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5f2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .warning-box { background: #fff3e0; border: 2px solid #ff9800; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .btn { display: inline-block; background: #1a5f2a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .info-box { background: #fff; padding: 15px; border-left: 4px solid #ff9800; margin: 15px 0; }
        .urgent { color: #f44336; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🇬🇭 Ghana eVisa Portal</h1>
    </div>
    <div class="content">
        <div class="warning-box">
            <h2 style="color: #ff9800; margin: 0;">⚠️ Action Required</h2>
            <p style="margin: 10px 0 0;">Reference: <strong>{{ $reference_number }}</strong></p>
        </div>

        <p>Dear {{ $applicant_name }},</p>
        <p>Your visa application requires additional information before we can proceed with processing.</p>
        
        @if(isset($request_notes) && $request_notes)
        <div class="info-box">
            <strong>Information Requested:</strong>
            <p style="margin: 10px 0 0;">{{ $request_notes }}</p>
        </div>
        @endif

        <p class="urgent">Please respond within 14 days to avoid delays in processing your application.</p>

        <h3>How to Submit Additional Information</h3>
        <ol>
            <li>Log in to your account on the Ghana eVisa Portal</li>
            <li>Navigate to your application using the reference number above</li>
            <li>Upload the requested documents or provide the required information</li>
            <li>Submit your response</li>
        </ol>
        
        <center>
            <a href="{{ config('app.frontend_url') }}/dashboard/applicant/applications" class="btn">View Application</a>
        </center>

        <p>If you have any questions, please contact our support team.</p>
    </div>
    <div class="footer">
        <p>Ghana Immigration Service | Ministry of Interior</p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
