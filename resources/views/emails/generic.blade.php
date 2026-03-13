<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ghana eVisa Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5f2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🇬🇭 Ghana eVisa Portal</h1>
    </div>
    <div class="content">
        @if(isset($applicant_name))
        <p>Dear {{ $applicant_name }},</p>
        @endif

        @if(isset($message))
        <p>{{ $message }}</p>
        @endif

        @if(isset($reference_number))
        <p>Reference Number: <strong>{{ $reference_number }}</strong></p>
        @endif
    </div>
    <div class="footer">
        <p>Ghana Immigration Service | Ministry of Interior</p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
