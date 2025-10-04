<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: #273F4F;
            padding: 40px 20px 60px 20px;
            text-align: center;
            position: relative;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #D9D9D9;
            background: #fff;
            margin: 0 auto;
        }

        .content {
            background: #D9D9D9;
            padding: 40px 30px;
            text-align: center;
        }

        .title {
            color: #333;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .message {
            color: #555;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            background: #273F4F;
            color: #fff;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
            transition: background 0.3s;
        }

        .button:hover {
            background: #1d2c38;
        }

        .footer {
            background: #D9D9D9;
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
    <div class="header">
        <img src="{{ $message->embed(public_path('images/ceit-logo.png')) }}" alt="CEIT Logo" class="logo">
    </div>
    <div class="content">
        <h1 class="title">Reset Your Password</h1>
        <p class="message">
            You are receiving this email because we received a password reset request for your account.<br>
            Click the button below to reset your password:
        </p>
        <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        <p class="message">
            This password reset link will expire in 60 minutes.<br>
            If you did not request a password reset, no further action is required.
        </p>
    </div>
    <div class="footer">
        <p class="school-name">Pamantasan ng Lungsod ng Valenzuela<br>CEIT Library Management System</p>
        <p class="warning">
            If you're having trouble clicking the button, copy and paste this URL into your browser:<br>
            <a href="{{ $resetUrl }}" style="color: #273F4F; word-break: break-all;">{{ $resetUrl }}</a>
        </p>
    </div>
</div>
</body>
</html>

