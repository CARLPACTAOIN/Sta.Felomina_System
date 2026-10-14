<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Official Barangay Audit Trail Report') }} — Barangay Sta. Felomina</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #111827;
            background-color: #f8fafc;
            line-height: 1.4;
            font-size: 10pt;
            padding: 24px;
        }

        .report-sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 16mm 18mm;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .action-bar {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background-color 0.15s;
        }

        .btn-primary {
            background-color: #103b2b;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #0b2d20;
        }

        .btn-outline {
            background-color: #ffffff;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-outline:hover {
            background-color: #f3f4f6;
        }

        /* Letterhead */
        .letterhead {
            text-align: center;
            border-bottom: 2px solid #103b2b;
            padding-bottom: 12px;
            margin-bottom: 16px;
            position: relative;
        }

        .letterhead h4 {
            font-size: 9.5pt;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
        }

        .letterhead h3 {
            font-size: 10.5pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
        }

        .letterhead h2 {
            font-size: 14pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #103b2b;
            margin: 3px 0 2px 0;
        }

        .letterhead p {
            font-size: 8.5pt;
            color: #6b7280;
            font-style: italic;
        }

        /* Title & Meta */
        .report-header {
            text-align: center;
            margin-bottom: 14px;
        }

        .report-header h1 {
            font-size: 12.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #111827;
        }

        .report-header p {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 2px;
        }

        .report-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 9pt;
        }

        .meta-item strong {
            color: #374151;
        }

        /* Summary Stats Strip */
        .summary-boxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .summary-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            background: #ffffff;
        }

        .summary-box .label {
            font-size: 7.5pt;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
        }

        .summary-box .val {
            font-size: 13pt;
            font-weight: 700;
            color: #103b2b;
            margin-top: 2px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 8.5pt;
        }

        th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1e293b;
        }

        td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
            color: #334155;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 8pt;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .context-note {
            margin-top: 3px;
            font-size: 7.5pt;
            color: #64748b;
        }

        /* Signatures */
        .signatures {
            margin-top: 36px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-block {
            text-align: center;
        }

        .signature-label {
            font-size: 8.5pt;
            color: #4b5563;
            margin-bottom: 40px;
            text-align: left;
        }

        .signature-name {
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #111827;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 200px;
        }

        .signature-role {
            font-size: 8pt;
            color: #4b5563;
            margin-top: 3px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .report-sheet {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }

            @page {
                size: A4 portrait;
                margin: 14mm 12mm;
            }

            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="action-bar no-print">
        <a href="{{ route('audit-logs.index', $filters) }}" class="btn btn-outline">
            &larr; {{ __('Back to Audit Logs') }}
        </a>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('audit-logs.export', $filters) }}" class="btn btn-outline">
                {{ __('Export CSV') }}
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                {{ __('Print Report / Save as PDF') }}
            </button>
        </div>
    </div>

    <main class="report-sheet">
        <!-- Letterhead -->
        <header class="letterhead">
            <h4>Republic of the Philippines</h4>
            <h3>Province of Cotabato • Municipality of Makilala</h3>
            <h2>BARANGAY STA. FELOMINA</h2>
            <p>Office of the Barangay Captain & Sangguniang Barangay</p>
        </header>

        <!-- Report Heading -->
        <div class="report-header">
            <h1>{{ __('Official Barangay Audit Trail Report') }}</h1>
            <p>{{ __('Tamper-Evident System Event & Operational Activity Log') }}</p>
        </div>

        <!-- Metadata Grid -->
        <div class="report-meta-grid">
            <div class="meta-item">
                <strong>{{ __('Coverage Period:') }}</strong> {{ $summaryStats['period_label'] }}
            </div>
            <div class="meta-item" style="text-align: right;">
                <strong>{{ __('Date Generated:') }}</strong> {{ $generatedAt->format('F d, Y • h:i A') }}
            </div>
            <div class="meta-item">
                <strong>{{ __('Scope / Filters:') }}</strong> {{ $summaryStats['scope_label'] }}
            </div>
            <div class="meta-item" style="text-align: right;">
                <strong>{{ __('Prepared By:') }}</strong> {{ $user->name }} ({{ $user->staffRoleLabel() }})
            </div>
        </div>

        <!-- Summary Boxes Strip -->
        <div class="summary-boxes">
            <div class="summary-box">
                <div class="label">{{ __('Total Logged Events') }}</div>
                <div class="val">{{ number_format($summaryStats['total_count']) }}</div>
            </div>
            <div class="summary-box">
                <div class="label">{{ __('Active Actors') }}</div>
                <div class="val">{{ number_format($summaryStats['unique_actors_count']) }}</div>
            </div>
            <div class="summary-box">
                <div class="label">{{ __('Affected Entities') }}</div>
                <div class="val">{{ number_format($summaryStats['unique_entities_count']) }}</div>
            </div>
            <div class="summary-box">
                <div class="label">{{ __('Top Action Type') }}</div>
                <div class="val" style="font-size: 10pt; line-height: 1.2; margin-top: 4px;">{{ $summaryStats['dominant_action'] }}</div>
            </div>
        </div>

        <!-- Tabular Records -->
        <table>
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">#</th>
                    <th style="width: 105px;">{{ __('Timestamp') }}</th>
                    <th style="width: 110px;">{{ __('Actor') }}</th>
                    <th style="width: 110px;">{{ __('Action') }}</th>
                    <th style="width: 110px;">{{ __('Entity Record') }}</th>
                    <th>{{ __('Summary & Context Details') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td style="text-align: center; color: #6b7280;">{{ $loop->iteration }}</td>
                        <td class="mono">
                            <div>{{ $log->created_at?->format('Y-m-d') }}</div>
                            <div style="font-size: 7.5pt; color: #64748b;">{{ $log->created_at?->format('h:i:s A') }}</div>
                        </td>
                        <td>
                            <strong>{{ $log->actor?->name ?? __('System') }}</strong>
                            <div style="font-size: 7.5pt; color: #64748b;">{{ $log->actor?->staffRoleLabel() ?? __('Automated Service') }}</div>
                        </td>
                        <td>
                            <span class="badge">{{ $log->actionLabel() }}</span>
                        </td>
                        <td>
                            <strong>{{ $log->entityLabel() }}</strong>
                            <div class="mono" style="color: #64748b;">#{{ $log->auditable_id }}</div>
                        </td>
                        <td>
                            <div>{{ $log->summary }}</div>
                            @if ($log->context)
                                <div class="context-note">
                                    @if (isset($log->context['reason']))
                                        <strong>{{ __('Reason:') }}</strong> {{ $log->context['reason'] }}
                                    @endif
                                    @if (isset($log->context['comment']))
                                        <strong>{{ __('Comment:') }}</strong> {{ $log->context['comment'] }}
                                    @endif
                                    @if (isset($log->context['changed_fields']) && is_array($log->context['changed_fields']))
                                        <strong>{{ __('Fields:') }}</strong> {{ implode(', ', $log->context['changed_fields']) }}
                                    @endif
                                    @if (isset($log->context['version_number']))
                                        <strong>{{ __('Version:') }}</strong> v{{ $log->context['version_number'] }}
                                    @endif
                                    @if (isset($log->context['old_status']) && isset($log->context['new_status']))
                                        <strong>{{ __('Status Change:') }}</strong> {{ $log->context['old_status'] }} &rarr; {{ $log->context['new_status'] }}
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: #6b7280;">
                            {{ __('No audit log records match the selected report criteria.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Formal Certification / Signature Blocks -->
        @php
            $captainName = $captain ? $captain->name : 'HON. BARANGAY CAPTAIN';
        @endphp
        <footer class="signatures">
            <div class="signature-block">
                <p class="signature-label">{{ __('Generated and Certified by:') }}</p>
                <div class="signature-name">{{ $user->name }}</div>
                <p class="signature-role">{{ $user->staffRoleLabel() }}<br>Barangay Sta. Felomina</p>
            </div>

            <div class="signature-block">
                <p class="signature-label">{{ __('Noted and Approved by:') }}</p>
                <div class="signature-name">{{ $captainName }}</div>
                <p class="signature-role">{{ __('Punong Barangay') }}<br>Barangay Sta. Felomina</p>
            </div>
        </footer>
    </main>

    @if ($autoPrint)
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    window.print();
                }, 300);
            });
        </script>
    @endif
</body>
</html>
