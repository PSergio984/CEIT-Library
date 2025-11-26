<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Assignment Alert</title>
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
            background: #EF4444;
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: #fff;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .header-title {
            color: #fff;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .content {
            padding: 40px 30px;
        }

        .alert-box {
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .alert-title {
            color: #991B1B;
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }

        .alert-message {
            color: #7F1D1D;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .week-info {
            background: #F3F4F6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .week-info h3 {
            color: #1F2937;
            font-size: 16px;
            margin: 0 0 15px 0;
            font-weight: bold;
        }

        .date-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .date-item {
            background: #fff;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 3px solid #FBBF24;
            color: #374151;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-item:last-child {
            margin-bottom: 0;
        }

        .date-icon {
            font-size: 18px;
        }

        .action-section {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background: #273F4F;
            color: #fff;
            padding: 15px 35px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .button:hover {
            background: #1d2c38;
        }

        .info-text {
            color: #6B7280;
            font-size: 14px;
            line-height: 1.6;
            margin: 20px 0;
        }

        .stats-box {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .stats-number {
            font-size: 36px;
            font-weight: bold;
            color: #92400E;
            margin: 0;
        }

        .stats-label {
            font-size: 14px;
            color: #78350F;
            margin: 5px 0 0 0;
        }

        .footer {
            background: #F9FAFB;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #E5E7EB;
        }

        .school-name {
            color: #273F4F;
            font-weight: bold;
            margin: 0 0 10px 0;
            font-size: 14px;
        }

        .footer-note {
            font-size: 12px;
            color: #9CA3AF;
            margin: 10px 0 0 0;
        }

        .urgent-tag {
            display: inline-block;
            background: #DC2626;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 class="header-title">Librarian Assignment Alert</h1>
        </div>

        <div class="content">
            <div class="alert-box">
                <h2 class="alert-title">Action Required - Unassigned Librarian Duty Days</h2>
                <p class="alert-message">
                    There are <strong>{{ count($unassignedDates) }}</strong> day(s) without assigned librarians during
                    the week of
                    <strong>{{ $weekStart->format('M d') }} - {{ $weekEnd->format('M d, Y') }}</strong>.
                </p>
            </div>

            <div class="stats-box">
                <p class="stats-number">{{ count($unassignedDates) }}</p>
                <p class="stats-label">Unassigned Day(s) This Week</p>
            </div>

            <div class="week-info">
                <h3>Dates Without Assigned Librarians:</h3>
                <ul class="date-list">
                    @foreach ($unassignedDates as $date)
                        @if ($date->isFuture() || $date->isToday())
                            <li class="date-item">
                                <span class="date-icon"></span>
                                <strong>{{ $date->format('l, F j, Y') }}</strong>
                                @if ($date->isToday())
                                    <span class="urgent-tag">TODAY</span>
                                @elseif($date->isTomorrow())
                                    <span class="urgent-tag">TOMORROW</span>
                                @elseif($date->isFuture())
                                    @php
                                        $daysAway = max(1, (int) $date->diffInDays(now()));
                                    @endphp
                                    <span class="urgent-tag">{{ $daysAway }} DAYS</span>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <p class="info-text">
                <strong>Important:</strong> Each librarian duty day requires a batch of <strong>5 students</strong>
                to be assigned.
                Please assign librarian batches to ensure proper library coverage and avoid service disruptions.
            </p>

            <div class="action-section">
                <a href="{{ rtrim(config('app.url'), '/') }}/admin/assign-librarians" class="button">
                    Assign Librarians Now
                </a>
            </div>

            <p class="info-text" style="text-align: center; color: #9CA3AF; font-size: 13px;">
                <strong>Quick Tip:</strong> You can assign the same batch to multiple dates at once to save time.
                Remember, Sundays are automatically excluded as they are not available for librarian duty.
            </p>

            @if (count($unassignedDates) >= 5)
                <div class="alert-box" style="background: #FEFCE8; border-left-color: #EAB308;">
                    <p class="alert-message" style="color: #854D0E;">
                        <strong>Critical:</strong> Most of the week is unassigned. This requires immediate attention
                        to maintain library operations.
                    </p>
                </div>
            @endif
        </div>

        <div class="footer">
            <p class="school-name">
                Pamantasan ng Lungsod ng Valenzuela<br>
                CEIT Library Management System
            </p>
            <p class="footer-note">
                This is an automated reminder sent 3 days in advance. If you have questions, please contact the system
                administrator.<br>
                <em>Sent on {{ now()->format('F j, Y \a\t g:i A') }}</em>
            </p>
        </div>
    </div>
</body>

</html>
