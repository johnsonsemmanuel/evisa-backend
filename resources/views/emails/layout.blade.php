<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'Ghana eVisa')</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: #0057A8; color: #fff; padding: 24px 20px; text-align: center; }
        .header .crest { font-size: 14px; letter-spacing: 0.05em; margin: 0 0 4px 0; opacity: 0.95; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header .divider { height: 2px; background: #C8A951; width: 80px; margin: 12px auto 0; }
        .content { padding: 28px 24px; }
        .content h2 { color: #0057A8; font-size: 18px; margin: 0 0 16px 0; }
        .content p { margin: 0 0 14px 0; }
        .reference-box { background: #f0f6fc; border: 1px solid #0057A8; border-radius: 6px; padding: 16px; text-align: center; margin: 20px 0; }
        .reference-number { font-size: 22px; font-weight: 700; color: #0057A8; letter-spacing: 1px; }
        .info-box { background: #fafafa; border-left: 4px solid #C8A951; padding: 14px 16px; margin: 16px 0; }
        .footer { background: #1a1a1a; color: #999; padding: 20px 24px; text-align: center; font-size: 12px; line-height: 1.5; }
        .footer a { color: #C8A951; text-decoration: none; }
        .btn { display: inline-block; background: #0057A8; color: #fff !important; padding: 12px 28px; text-decoration: none; border-radius: 4px; margin: 12px 0; font-weight: 600; }
        .btn:hover { background: #004080; }
        @media only screen and (max-width: 600px) {
            .content { padding: 20px 16px; }
            .reference-number { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <p class="crest">Republic of Ghana</p>
            <h1>Ministry of Foreign Affairs</h1>
            <div class="divider"></div>
            <p style="margin: 8px 0 0; font-size: 13px;">Ghana eVisa Portal</p>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p>Ministry of Foreign Affairs | Republic of Ghana</p>
            <p>Support: <a href="mailto:{{ config('mail.support_email', config('mail.from.address')) }}">{{ config('mail.support_email', config('mail.from.address')) }}</a></p>
            <p style="margin-top: 8px;">This is an automated message. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
