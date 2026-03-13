<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eVisa - {{ $reference }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a1a2e;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-size: 13px;
            line-height: 1.6;
            min-height: 100vh;
        }
        .page-wrapper {
            max-width: 650px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Modern Card Design */
        .visa-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 135, 81, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        /* Decorative Elements */
        .visa-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(0, 135, 81, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        /* Header Section */
        .header {
            background: linear-gradient(135deg, #006B3F 0%, #004D2C 50%, #003D23 100%);
            color: #fff;
            padding: 28px 32px;
            position: relative;
            overflow: hidden;
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 1;
        }
        .header-left {
            flex: 1;
        }
        .country-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
        }
        .country-badge .flag {
            font-size: 16px;
        }
        .visa-title {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 4px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .visa-subtitle {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 400;
        }
        .header-right {
            text-align: right;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 18px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .ref-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .ref-number {
            font-size: 16px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        /* Status Banner */
        .status-banner {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            color: #fff;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .status-icon {
            width: 22px;
            height: 22px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        /* Main Content */
        .main-content {
            padding: 32px;
        }
        
        /* Info Grid - Modern Cards */
        .info-section {
            margin-bottom: 28px;
        }
        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 14px;
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .info-card.full-width {
            grid-column: span 2;
        }
        .info-card.highlight {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: #a7f3d0;
        }
        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .info-value.mono {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        /* QR Code Section - Prominent */
        .qr-section {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 20px;
            padding: 28px;
            margin-top: 24px;
            display: flex;
            align-items: center;
            gap: 28px;
            color: #fff;
        }
        .qr-code-wrapper {
            background: #fff;
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
        }
        .qr-code-wrapper img {
            display: block;
            width: 160px;
            height: 160px;
        }
        .qr-info {
            flex: 1;
        }
        .qr-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .qr-title .icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .qr-description {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .qr-code-text {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .verify-url {
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
        }
        .verify-url span {
            color: #10b981;
            font-weight: 600;
        }
        
        /* Security Notice */
        .security-notice {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #fbbf24;
            border-radius: 14px;
            padding: 18px 22px;
            margin-top: 24px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .security-icon {
            width: 36px;
            height: 36px;
            background: #f59e0b;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .security-text {
            flex: 1;
        }
        .security-text strong {
            display: block;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .security-text p {
            font-size: 11px;
            color: #78350f;
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            background: #f8fafc;
            padding: 20px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .footer-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #006B3F 0%, #004D2C 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            font-weight: 700;
        }
        .footer-text {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }
        .footer-text strong {
            display: block;
            color: #1e293b;
            font-weight: 700;
        }
        .footer-right {
            text-align: right;
            font-size: 10px;
            color: #94a3b8;
        }
        .footer-right p {
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="visa-card">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="country-badge">
                            <span class="flag">🇬🇭</span>
                            <span>REPUBLIC OF GHANA</span>
                        </div>
                        <h1 class="visa-title">Electronic Visa</h1>
                        <p class="visa-subtitle">Ghana Immigration Service • Official Document</p>
                    </div>
                    <div class="header-right">
                        <div class="ref-label">Reference No.</div>
                        <div class="ref-number">{{ $reference }}</div>
                    </div>
                </div>
            </div>
            
            <!-- Status Banner -->
            <div class="status-banner">
                <span class="status-icon">✓</span>
                <span>Visa Approved & Valid for Entry</span>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Traveler Information -->
                <div class="info-section">
                    <div class="section-title">Traveler Information</div>
                    <div class="info-grid">
                        <div class="info-card full-width highlight">
                            <div class="info-label">Full Name (as in passport)</div>
                            <div class="info-value">{{ $full_name }}</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Passport Number</div>
                            <div class="info-value mono">{{ $passport_number }}</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Nationality</div>
                            <div class="info-value">{{ $nationality }}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Visa Details -->
                <div class="info-section">
                    <div class="section-title">Visa Details</div>
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-label">Visa Type</div>
                            <div class="info-value">{{ $visa_type }}</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Duration of Stay</div>
                            <div class="info-value">{{ $duration }} Days</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Date of Issue</div>
                            <div class="info-value">{{ $issued_at }}</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Valid Until</div>
                            <div class="info-value">{{ $valid_until }}</div>
                        </div>
                        <div class="info-card full-width">
                            <div class="info-label">Expected Arrival Date</div>
                            <div class="info-value">{{ $arrival_date }}</div>
                        </div>
                    </div>
                </div>
                
                <!-- QR Code Verification -->
                <div class="qr-section">
                    <div class="qr-code-wrapper">
                        <img src="{{ $qr_image }}" alt="Verification QR Code" />
                    </div>
                    <div class="qr-info">
                        <div class="qr-title">
                            <span class="icon">🔐</span>
                            <span>Digital Verification</span>
                        </div>
                        <p class="qr-description">
                            Scan this QR code at any Ghana port of entry to instantly verify the authenticity of this electronic visa. Border officers can validate your entry authorization in seconds.
                        </p>
                        <div class="qr-code-text">{{ $qr_code }}</div>
                        <p class="verify-url">Verify online: <span>verify.ghanaevisa.gov.gh</span></p>
                    </div>
                </div>
                
                <!-- Security Notice -->
                <div class="security-notice">
                    <div class="security-icon">⚠️</div>
                    <div class="security-text">
                        <strong>Important Security Notice</strong>
                        <p>This electronic visa must be printed and presented with your valid passport upon arrival. Tampering with or forging this document is a criminal offence under Ghanaian law and may result in prosecution, deportation, and travel bans.</p>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div class="footer-left">
                    <div class="footer-logo">GH</div>
                    <div class="footer-text">
                        <strong>Ghana Immigration Service</strong>
                        Ministry of the Interior
                    </div>
                </div>
                <div class="footer-right">
                    <p>Document generated electronically</p>
                    <p>Valid without physical signature</p>
                    <p>&copy; {{ date('Y') }} Government of Ghana</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
