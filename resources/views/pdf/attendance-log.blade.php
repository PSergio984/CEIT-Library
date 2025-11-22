<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Log Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #1a1a1a;
            font-family: Helvetica, Arial, sans-serif;
            font-weight: bold;
        }
        
        .header p {
            font-size: 9px;
            color: #666;
            margin: 2px 0;
        }
        
        .filter-info {
            background-color: #f5f5f5;
            padding: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #4a5568;
        }
        
        .filter-info strong {
            color: #2d3748;
        }
        
        .summary {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table thead {
            background-color: #4a5568;
            color: white;
        }
        
        table th {
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            font-family: Helvetica, Arial, sans-serif;
        }
        
        table td {
            padding: 6px 5px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }
        
        table tbody tr:hover {
            background-color: #edf2f7;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            font-family: Helvetica, Arial, sans-serif;
        }
        
        .status-active {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .role-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            font-family: Helvetica, Arial, sans-serif;
        }
        
        .role-student {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .role-librarian {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .role-admin,
        .role-super_admin {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 11px;
        }
        
        .col-id { width: 5%; }
        .col-name { width: 20%; }
        .col-role { width: 12%; }
        .col-scanned { width: 18%; }
        .col-time { width: 15%; }
        .col-duration { width: 10%; }
        .col-status { width: 10%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Library Attendance Log Report</h1>
        <p>CEIT Library PLV eLib</p>
        <p>Generated on: {{ $generatedAt }}</p>
    </div>
    
    <div class="filter-info">
        <strong>{{ $filterText }}</strong> 
    </div>
    
    <div class="summary">
        <div><strong>Total Records:</strong> {{ $totalRecords }}</div>
        <div><strong>Report Date:</strong> {{ now()->format('F d, Y') }}</div>
    </div>
    
    @if($attendances->isEmpty())
        <div class="no-data">
            <p>No attendance records found</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th class="col-id">#</th>
                    <th class="col-name">Student Name</th>
                    <th class="col-role">Role</th>
                    <th class="col-scanned">Scanned By</th>
                    <th class="col-time">Time In</th>
                    <th class="col-time">Time Out</th>
                    <th class="col-duration">Duration</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attendances as $attendance)
                    <tr>
                        <td class="col-id">{{ $attendance['id'] }}</td>
                        <td class="col-name">{{ $attendance['user_name'] }}</td>
                        <td class="col-role">
                            <span class="role-badge role-{{ strtolower(str_replace(' ', '_', $attendance['role_name'])) }}">
                                {{ $attendance['role_name'] }}
                            </span>
                        </td>
                        <td class="col-scanned">{{ $attendance['scanned_by_name'] }}</td>
                        <td class="col-time">
                            @if($attendance['time_in'])
                                {{ $attendance['time_in']->format('M d, Y H:i') }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td class="col-time">
                            @if($attendance['time_out'])
                                {{ $attendance['time_out']->format('M d, Y H:i') }}
                            @else
                                In Library
                            @endif
                        </td>
                        <td class="col-duration">
                            @if($attendance['duration_minutes'] !== null && $attendance['duration_minutes'] >= 0)
                                @php
                                    $mins = (int)$attendance['duration_minutes'];
                                    $hours = floor($mins / 60);
                                    $remainingMins = $mins % 60;
                                @endphp
                                @if($mins < 1)
                                    < 1m
                                @elseif($hours > 0)
                                    {{ $hours }}h {{ $remainingMins }}m
                                @else
                                    {{ $mins }}m
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="col-status">
                            <span class="status-badge status-{{ $attendance['status'] }}">
                                {{ ucfirst($attendance['status']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    
    <div class="footer">
        <p>CEIT Library PLV eLib.</p>
    </div>
</body>
</html>
