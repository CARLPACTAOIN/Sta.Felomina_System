<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $type === 'document' ? __('Official Document Archive Report') : __('Official Resident Summary Report') }} — Barangay Sta. Felomina</title>
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
            font-size: 11pt;
            padding: 24px;
        }

        .report-sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 20mm;
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
            padding-bottom: 14px;
            margin-bottom: 18px;
            position: relative;
        }

        .letterhead h4 {
            font-size: 10pt;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
        }

        .letterhead h3 {
            font-size: 11pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
        }

        .letterhead h2 {
            font-size: 15pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #103b2b;
            margin: 4px 0 2px 0;
        }

        .letterhead p {
            font-size: 9pt;
            color: #6b7280;
            font-style: italic;
        }

        /* Title & Meta */
        .report-header {
            text-align: center;
            margin-bottom: 18px;
        }

        .report-header h1 {
            font-size: 13pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #111827;
        }

        .report-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 9.5pt;
        }

        .meta-item strong {
            color: #374151;
        }

        /* Summary Stats Strip */
        .summary-boxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .summary-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            background: #ffffff;
        }

        .summary-box .label {
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
        }

        .summary-box .val {
            font-size: 14pt;
            font-weight: 700;
            color: #103b2b;
            margin-top: 2px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 9pt;
        }

        th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 7px 9px;
            text-align: left;
            font-weight: 700;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1e293b;
        }

        td {
            border: 1px solid #e2e8f0;
            padding: 6px 9px;
            vertical-align: top;
            color: #334155;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 8.5pt;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #d1d5db;
        }

        /* Signatures */
        .signatures {
            margin-top: 40px;
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
            font-size: 9pt;
            color: #4b5563;
            margin-bottom: 45px;
            text-align: left;
        }

        .signature-name {
            font-size: 10.5pt;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #111827;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 200px;
        }

        .signature-role {
            font-size: 8.5pt;
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
                margin: 15mm 12mm;
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
        <a href="{{ route('reports.index') }}" class="btn btn-outline">
            &larr; {{ __('Back to Reports') }}
        </a>
        <div style="display: flex; gap: 8px;">
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
            <h1>
                @if ($type === 'document')
                    {{ __('Official Document Archive Report') }}
                @else
                    {{ __('Official Resident Profile Report') }}
                @endif
            </h1>
        </div>

        <!-- Metadata Grid -->
        <div class="report-meta-grid">
            <div class="meta-item">
                @if ($type === 'document')
                    <strong>{{ __('Coverage Period:') }}</strong> {{ $report['period_label'] }}
                @else
                    <strong>{{ __('Purok Scope:') }}</strong> {{ $report['purok_label'] }}
                @endif
            </div>
            <div class="meta-item" style="text-align: right;">
                <strong>{{ __('Date Generated:') }}</strong> {{ $generatedAt->format('F d, Y • h:i A') }}
            </div>
            <div class="meta-item">
                <strong>{{ __('Prepared By:') }}</strong> {{ $user->name }} ({{ $user->role->label() }})
            </div>
            <div class="meta-item" style="text-align: right;">
                <strong>{{ __('Total Records:') }}</strong> {{ $report['total_count'] }}
            </div>
        </div>

        <!-- Summary Boxes Strip -->
        <div class="summary-boxes">
            @if ($type === 'document')
                <div class="summary-box">
                    <div class="label">{{ __('Total Records') }}</div>
                    <div class="val">{{ $report['total_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Published') }}</div>
                    <div class="val">{{ $report['status_counts']['published'] ?? 0 }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Dominant Category') }}</div>
                    <div class="val" style="font-size: 11pt;">{{ $report['dominant_category'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Pending / Draft') }}</div>
                    <div class="val">{{ ($report['status_counts']['pending_approval'] ?? 0) + ($report['status_counts']['draft'] ?? 0) }}</div>
                </div>
            @else
                <div class="summary-box">
                    <div class="label">{{ __('Total Residents') }}</div>
                    <div class="val">{{ $report['total_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Active Status') }}</div>
                    <div class="val">{{ $report['active_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Registered Voters') }}</div>
                    <div class="val">{{ $report['voter_count'] }}</div>
                </div>
                <div class="summary-box">
                    <div class="label">{{ __('Households') }}</div>
                    <div class="val">{{ $report['household_count'] }}</div>
                </div>
            @endif
        </div>

        <!-- Tabular Records -->
        @if ($type === 'document')
            <table>
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th style="width: 130px;">{{ __('Document No.') }}</th>
                        <th>{{ __('Title / Subject') }}</th>
                        <th style="width: 100px;">{{ __('Type') }}</th>
                        <th style="width: 120px;">{{ __('Category') }}</th>
                        <th style="width: 90px;">{{ __('Date') }}</th>
                        <th style="width: 80px; text-align: center;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['records'] as $doc)
                        <tr>
                            <td style="text-align: center; color: #6b7280;">{{ $loop->iteration }}</td>
                            <td class="mono">{{ $doc->document_number }}</td>
                            <td>
                                <strong>{{ $doc->title }}</strong>
                                <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">{{ $doc->subject }}</div>
                            </td>
                            <td>{{ $doc->type->name }}</td>
                            <td>{{ $doc->category->name }}</td>
                            <td>{{ $doc->document_date->format('M d, Y') }}</td>
                            <td style="text-align: center;">
                                <span class="badge">{{ $doc->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #6b7280;">
                                {{ __('No document records match the selected report criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th style="width: 110px;">{{ __('Resident Code') }}</th>
                        <th>{{ __('Full Name') }}</th>
                        <th style="width: 100px;">{{ __('Purok') }}</th>
                        <th style="width: 95px;">{{ __('Household') }}</th>
                        <th style="width: 70px;">{{ __('Sex / Age') }}</th>
                        <th style="width: 70px; text-align: center;">{{ __('Voter') }}</th>
                        <th style="width: 75px; text-align: center;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['records'] as $res)
                        <tr>
                            <td style="text-align: center; color: #6b7280;">{{ $loop->iteration }}</td>
                            <td class="mono">{{ $res->resident_code }}</td>
                            <td><strong>{{ $res->last_name }}, {{ $res->first_name }} {{ $res->middle_name }}</strong></td>
                            <td>{{ $res->purok->name }}</td>
                            <td class="mono" style="font-size: 8pt;">{{ $res->household->household_code }}</td>
                            <td>{{ $res->sex }} / {{ $res->birthdate->age }}</td>
                            <td style="text-align: center;">
                                {{ $res->is_registered_voter ? __('Yes') : __('No') }}
                            </td>
                            <td style="text-align: center;">
                                <span class="badge">{{ $res->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px; color: #6b7280;">
                                {{ __('No resident records match the selected report criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        <!-- Formal Certification / Signature Blocks -->
        @php
            $captain = \App\Models\User::where('role', \App\Enums\UserRole::Captain)->first();
            $captainName = $captain ? $captain->name : 'HON. BARANGAY CAPTAIN';
        @endphp
        <footer class="signatures">
            <div class="signature-block">
                <p class="signature-label">{{ __('Prepared and Certified Correct by:') }}</p>
                <div class="signature-name">{{ $user->name }}</div>
                <p class="signature-role">{{ $user->role->label() }}<br>Barangay Sta. Felomina</p>
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
