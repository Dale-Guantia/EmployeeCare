<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ARTA Raw Responses Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin-top: 50px; margin-bottom: 50px; font-size: 9px; /* Reduced font size to fit 16 columns */ }
        .header { position: fixed; top: -30px; left: 0px; right: 0px; text-align: center; }
        .footer { position: fixed; bottom: -30px; left: 0px; right: 0px; text-align: center; }
        h2 { margin: 0; padding-bottom: 5px;}
        table { width: 100%; border-collapse: collapse; font-size: 9px; margin: 0; margin-top: -25px;}
        th, td { border: 1px solid #777; padding: 4px; text-align: center; word-wrap: break-word;}
        th { background-color: #0e2f66; color: white; font-weight: bold;}
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2 class="text-center">ARTA SURVEY RESPONSES</h2>
        <p class="text-center" style="font-size: 11px; margin-bottom: 10px; margin-top: 0;">
            @if($reportStartDate && $reportEndDate)
                Report for: {{ $reportStartDate }} to {{ $reportEndDate }}
            @else
                Overall Report
            @endif
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Service</th>
                <th>CC1</th>
                <th>CC2</th>
                <th>CC3</th>
                <th>SQD0</th>
                <th>SQD1</th>
                <th>SQD2</th>
                <th>SQD3</th>
                <th>SQD4</th>
                <th>SQD5</th>
                <th>SQD6</th>
                <th>SQD7</th>
                <th>SQD8</th>
            </tr>
        </thead>
        <tbody>
            @forelse($responses as $response)
                <tr>
                    <td>{{ optional($response->created_at)->format('m/d/Y') ?? 'N/A' }}</td>
                    <td>{{ $response->client_type ?? 'N/A' }}</td>
                    <td>{{ $response->service_availed ?? 'N/A' }}</td>
                    <td>{{ $response->cc1 ?? 'N/A' }}</td>
                    <td>{{ $response->cc2 ?? 'N/A' }}</td>
                    <td>{{ $response->cc3 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd0 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd1 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd2 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd3 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd4 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd5 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd6 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd7 ?? 'N/A' }}</td>
                    <td>{{ $response->sqd8 ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="text-align: center; padding: 15px;">No raw ARTA survey data found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
