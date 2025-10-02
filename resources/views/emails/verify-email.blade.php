<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: #273F4F;
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #D9D9D9;
            background-color: white;
            margin: 0 auto;
        }

        .content {
            background-color: #D9D9D9;
            padding: 40px 30px;
            text-align: center;
        }

        .title {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message {
            color: #555;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            background-color: #273F4F;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #1d2c38;
        }

        .footer {
            background-color: #D9D9D9;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #ccc;
        }

        .school-name {
            color: #273F4F;
            font-weight: bold;
            margin-top: 10px;
        }

        .warning {
            font-size: 12px;
            color: #888;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header with Logo -->
    <div class="header">
        <img src="{{ $message->embed($logoPath) }}" alt="CEIT Logo" class="logo">
    </div>

    <!-- Main Content -->
    <div class="content">
        <h1 class="title">Welcome to CEIT Library!</h1>

        <p class="message">
            Hello <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,
        </p>

        <p class="message">
            Thank you for registering with the CEIT Library Management System.
            To complete your account setup and start borrowing books, please verify your email address by clicking the
            button below.
        </p>

        <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>

        <p class="message">
            Once verified and logged in, you'll be able to:
            <br>• Browse our academic paper collection
            <br>• Borrow books and resources
            <br>• Track your borrowing history
            <br>• Access your library account
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>If you did not create an account, no further action is required.</p>
        <p class="school-name">Pamantasan ng Lungsod ng Valenzuela<br>CEIT Library Management System</p>
        <p class="warning">
            This verification link will expire in 60 minutes for security reasons.
            <br>If you're having trouble clicking the button, copy and paste this URL into your browser:
            <br><a href="{{ $verificationUrl }}"
                   style="color: #273F4F; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
    </div>
</div>
</body>
</html>
