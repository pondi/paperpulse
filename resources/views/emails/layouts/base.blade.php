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
            background-color: #f4f4f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }

        /* Container styles */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .email-header {
            background-color: #2563eb;
            padding: 30px;
            text-align: center;
        }

        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            margin: 0;
            font-weight: 600;
        }

        .email-body {
            padding: 40px 30px;
        }

        .email-footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .email-footer p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* Content styles */
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: #1f2937;
        }

        h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 30px 0 15px 0;
            color: #1f2937;
        }

        p {
            margin: 0 0 16px 0;
            color: #374151;
        }

        /* Button styles */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #6b7280;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .btn-success {
            background-color: #059669;
        }

        .btn-success:hover {
            background-color: #047857;
        }

        .btn-danger {
            background-color: #dc2626;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
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

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
            }
            
            .email-header,
            .email-body,
            .email-footer {
                padding: 20px !important;
            }
            
            .email-header h1 {
                font-size: 24px !important;
            }
            
            h1 {
                font-size: 20px !important;
            }
            
            h2 {
                font-size: 18px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>{{ $app_name ?? config('app.name') }}</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            {!! $content !!}
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                © {{ date('Y') }} {{ $app_name ?? config('app.name') }}. All rights reserved.
            </p>
            <p>
                <a href="{{ $app_url ?? config('app.url') }}" style="color: #6b7280; text-decoration: none;">
                    Visit our website
                </a>
            </p>
        </div>
    </div>
</body>
</html>