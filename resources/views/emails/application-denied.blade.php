<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa Application Decision</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a5f2a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .decision-box { background: #ffebee; border: 2px solid #f44336; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .info-box { background: #fff; padding: 15px; border-left: 4px solid #666; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🇬🇭 Ghana eVisa Portal</h1>
    </div>
    <div class="content">
        <div class="decision-box">
            <h2 style="color: #f44336; margin: 0;">Application Not Approved</h2>
            <p style="margin: 10px 0 0;">Reference: <strong>{{ $reference_number }}</strong></p>
        </div>

        <p>Dear {{ $applicant_name }},</p>
        <p>We regret to inform you that your visa application has not been approved at this time.</p>
        
        <div class="info-box">
            <strong>Application Details:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Reference Number: {{ $reference_number }}</li>
                <li>Visa Type: {{ $visa_type ?? 'N/A' }}</li>
                <li>Decision Date: {{ now()->format('F j, Y') }}</li>
            </ul>
        </div>

        @if(isset($decision_notes) && $decision_notes)
        <div class="info-box">
            <strong>Reason:</strong>
            <p style="margin: 10px 0 0;">{{ $decision_notes }}</p>
        </div>
        @endif

        <h3>What You Can Do</h3>
        <ul>
            <li>You may submit a new application with updated documentation</li>
            <li>Ensure all required documents are complete and valid</li>
            <li>Contact our support if you have questions about the decision</li>
        </ul>

        <p>If you believe this decision was made in error, you may contact the Ghana Immigration Service for clarification.</p>
    </div>
    <div class="footer">
        <p>Ghana Immigration Service | Ministry of Interior</p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
