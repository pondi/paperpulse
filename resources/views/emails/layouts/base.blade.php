<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $app_name ?? config('app.name') }}</title>
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #fef3c7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #3f3f46;
        }

        /* Container styles */
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border: 1px solid #e4e4e7;
        }

        .email-header {
            background-color: #18181b;
            padding: 32px 30px;
            border-bottom: 4px solid #f59e0b;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 16px;
        }

        .logo {
            display: inline-block;
            width: 48px;
            height: 48px;
            background: linear-gradient(to bottom right, #f59e0b, #f97316, #dc2626);
            position: relative;
            transform: rotate(6deg);
        }

        .logo svg {
            width: 28px;
            height: 28px;
            color: #ffffff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-6deg);
        }

        .email-header h1 {
            color: #fafafa;
            font-size: 24px;
            margin: 0;
            font-weight: 700;
            text-align: center;
            letter-spacing: -0.025em;
        }

        .email-body {
            padding: 40px 30px;
            background-color: #ffffff;
        }

        .email-footer {
            background-color: #fafafa;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #e4e4e7;
        }

        .email-footer p {
            margin: 0 0 8px 0;
            color: #71717a;
            font-size: 14px;
        }

        /* Content styles */
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 20px 0;
            color: #18181b;
            letter-spacing: -0.025em;
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px 0;
            color: #27272a;
            letter-spacing: -0.025em;
        }

        p {
            margin: 0 0 16px 0;
            color: #52525b;
            line-height: 1.7;
        }

        /* Button styles */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background-color: #18181b;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 700;
            margin: 20px 0;
            border: none;
            cursor: pointer;
            font-size: 16px;
            letter-spacing: 0.025em;
        }

        .btn:hover {
            background-color: #27272a;
        }

        .btn-secondary {
            background-color: #71717a;
        }

        .btn-secondary:hover {
            background-color: #52525b;
        }

        .btn-success {
            background-color: #16a34a;
        }

        .btn-success:hover {
            background-color: #15803d;
        }

        .btn-danger {
            background-color: #dc2626;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
        }

        .btn-accent {
            background-color: #f59e0b;
            color: #18181b !important;
        }

        .btn-accent:hover {
            background-color: #d97706;
        }

        /* Utility styles */
        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .mt-4 {
            margin-top: 24px;
        }

        .mb-4 {
            margin-bottom: 24px;
        }

        .divider {
            border: 0;
            border-top: 1px solid #e4e4e7;
            margin: 24px 0;
        }

        /* Accent box */
        .accent-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            margin: 20px 0;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
                border-left: none;
                border-right: none;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding: 24px 20px !important;
            }

            .email-header h1 {
                font-size: 20px !important;
            }

            h1 {
                font-size: 20px !important;
            }

            h2 {
                font-size: 18px !important;
            }

            .btn {
                display: block;
                width: 100%;
                padding: 12px 20px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-container">
                <div class="logo">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <h1>{{ $app_name ?? config('app.name') }}</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            {!! $content !!}
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                © {{ date('Y') }} {{ $app_name ?? config('app.name') }}
            </p>
            <p style="margin-bottom: 0;">
                <a href="{{ $app_url ?? config('app.url') }}" style="color: #71717a; text-decoration: none; font-weight: 500;">
                    Visit our website
                </a>
            </p>
        </div>
    </div>
</body>
</html>