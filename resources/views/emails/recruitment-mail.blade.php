<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject }}</title>
    <style>
        /* Reset & base */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f0f4f8;
            color: #333333;
            line-height: 1.7;
        }

        /* Wrapper */
        .wrapper {
            max-width: 620px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.10);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            padding: 32px 36px;
            text-align: center;
        }
        .header img {
            max-height: 48px;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p.subtitle {
            color: #bfdbfe;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Body */
        .body {
            padding: 36px 36px 24px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 16px;
        }
        .body-content p {
            margin-bottom: 14px;
            font-size: 15px;
            color: #444444;
        }
        .body-content a {
            color: #2563eb;
            word-break: break-all;
        }

        /* Details table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
            font-size: 14px;
        }
        .details-table th {
            background-color: #eff6ff;
            color: #1e40af;
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            border-bottom: 2px solid #bfdbfe;
            width: 40%;
        }
        .details-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        .details-table tr:last-child td,
        .details-table tr:last-child th {
            border-bottom: none;
        }

        /* Closing note */
        .closing-note {
            background-color: #f0f9ff;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 14px 18px;
            margin-top: 20px;
            font-size: 14px;
            color: #1e40af;
        }

        /* Footer */
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e5e7eb;
            padding: 20px 36px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .footer strong {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        {{-- Header --}}
        <div class="header">
            <h1>Tim Rekrutmen</h1>
            <p class="subtitle">Pemberitahuan Rekrutmen — {{ now()->translatedFormat('d F Y') }}</p>
        </div>

        {{-- Body --}}
        <div class="body">
            <p class="greeting">{{ $greeting }}</p>

            <div class="body-content">
                {!! $bodyHtml !!}
            </div>

            @if (!empty($details))
                <table class="details-table">
                    <tbody>
                        @foreach ($details as $label => $value)
                            <tr>
                                <th>{{ $label }}</th>
                                <td>{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Closing note --}}
            @if ($closingNote)
                <div class="closing-note">
                    {{ $closingNote }}
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh sistem rekrutmen.</p>
            <p style="margin-top: 8px;">
                <strong>© {{ now()->year }} Tim Rekrutmen</strong>
            </p>
        </div>

    </div>
</body>
</html>
