<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        h1 {
            color: #2d6a4f;
            font-size: 22px;
        }
        p {
            color: #444444;
            line-height: 1.6;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #aaaaaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SMTP Test Email</h1>
        <p>Hello, <strong>{{ $recipientName }}</strong>!</p>
        <p>{{ $body }}</p>
        <p>
            This email was sent at <strong>{{ now()->format('d M Y H:i:s') }}</strong>
            using the SMTP configuration from your <code>.env</code> file.
        </p>
        <div class="footer">
            <p>This is an automated test email. Please ignore it.</p>
        </div>
    </div>
</body>
</html>
