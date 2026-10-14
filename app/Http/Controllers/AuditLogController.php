<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Display a paginated listing of audit logs with search and filtering.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can(StaffPermission::ViewAuditLogs->value), 403);

        $filters = $this->extractFilters($request);

        $query = AuditLog::query()
            ->with('actor')
            ->filter($filters);

        $auditLogs = (clone $query)->paginate(25)->withQueryString();

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->distinct()->select('actor_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('audit-logs.index', [
            'auditLogs' => $auditLogs,
            'filters' => $filters,
            'actors' => $actors,
            'actions' => AuditLog::actionOptions(),
            'entityTypes' => AuditLog::entityTypeOptions(),
            'totalCount' => $auditLogs->total(),
            'hasActiveFilters' => $this->hasActiveFilters($filters),
        ]);
    }

    /**
     * Display a clean, formal printable audit trail report.
     */
    public function print(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can(StaffPermission::ViewAuditLogs->value), 403);

        $filters = $this->extractFilters($request);

        $logs = AuditLog::query()
            ->with('actor')
            ->filter($filters)
            ->limit(1000)
            ->get();

        $summaryStats = $this->computeSummaryStats($logs, $filters);
        $captain = User::role(UserRole::Captain->value)->first();

        return view('audit-logs.print', [
            'logs' => $logs,
            'filters' => $filters,
            'summaryStats' => $summaryStats,
            'user' => $user,
            'captain' => $captain,
            'generatedAt' => Carbon::now(),
            'autoPrint' => $request->boolean('auto_print'),
        ]);
    }

    /**
     * Export the filtered audit logs as a downloadable CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can(StaffPermission::ViewAuditLogs->value), 403);

        $filters = $this->extractFilters($request);
        $filename = 'audit-logs-sta-felomina-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Log ID',
                'Timestamp (UTC)',
                'Timestamp (Local)',
                'Actor Name',
                'Actor Email',
                'Actor Role',
                'Action Code',
                'Action Description',
                'Entity Type',
                'Entity ID',
                'Summary',
                'Context Details',
            ]);

            AuditLog::query()
                ->with('actor')
                ->filter($filters)
                ->chunk(250, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->created_at?->toIso8601String() ?? '',
                            $log->created_at?->format('Y-m-d H:i:s') ?? '',
                            $log->actor !== null ? $log->actor->name : 'System',
                            $log->actor !== null ? $log->actor->email : 'N/A',
                            $log->actor !== null ? $log->actor->staffRoleLabel() : 'Automated Service',
                            $log->action,
                            $log->actionLabel(),
                            $log->entityLabel(),
                            $log->auditable_id,
                            $log->summary,
                            $log->context !== null ? json_encode($log->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * @return array{
     *     q: string,
     *     actor_id: string,
     *     action: string,
     *     entity_type: string,
     *     date_from: string,
     *     date_to: string,
     *     sort: string
     * }
     */
    protected function extractFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'actor_id' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ]);

        return [
            'q' => (string) ($validated['q'] ?? ''),
            'actor_id' => (string) ($validated['actor_id'] ?? ''),
            'action' => (string) ($validated['action'] ?? ''),
            'entity_type' => (string) ($validated['entity_type'] ?? ''),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
            'sort' => (string) ($validated['sort'] ?? 'newest'),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function hasActiveFilters(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['actor_id'] !== ''
            || $filters['action'] !== ''
            || $filters['entity_type'] !== ''
            || $filters['date_from'] !== ''
            || $filters['date_to'] !== '';
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    protected function computeSummaryStats($logs, array $filters): array
    {
        $uniqueActors = $logs->pluck('actor_id')->unique()->filter()->count();
        if ($logs->contains(fn (AuditLog $log) => $log->actor_id === null)) {
            $uniqueActors += 1;
        }

        $actionCounts = [];
        foreach ($logs as $log) {
            $actionCounts[$log->action] = ($actionCounts[$log->action] ?? 0) + 1;
        }
        arsort($actionCounts);
        $topAction = array_key_first($actionCounts);
        $topActionLabel = $topAction !== null ? (AuditLog::predefinedActions()[$topAction] ?? str($topAction)->headline()->toString()) : 'None';

        $uniqueEntities = $logs->groupBy(fn (AuditLog $log) => $log->auditable_type.':'.$log->auditable_id)->count();

        // Coverage period label
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
            $periodLabel = Carbon::parse($filters['date_from'])->format('M d, Y').' — '.Carbon::parse($filters['date_to'])->format('M d, Y');
        } elseif ($filters['date_from'] !== '') {
            $periodLabel = 'From '.Carbon::parse($filters['date_from'])->format('M d, Y');
        } elseif ($filters['date_to'] !== '') {
            $periodLabel = 'Until '.Carbon::parse($filters['date_to'])->format('M d, Y');
        } else {
            $periodLabel = 'All Historical Records';
        }

        // Scope label
        $scopeParts = [];
        if ($filters['actor_id'] !== '') {
            if ($filters['actor_id'] === 'system') {
                $scopeParts[] = 'Actor: System';
            } else {
                $actor = User::find($filters['actor_id']);
                $scopeParts[] = 'Actor: '.($actor !== null ? $actor->name : '#'.$filters['actor_id']);
            }
        }
        if ($filters['action'] !== '') {
            $scopeParts[] = 'Action: '.(AuditLog::predefinedActions()[$filters['action']] ?? str($filters['action'])->headline()->toString());
        }
        if ($filters['entity_type'] !== '') {
            $entityOptions = AuditLog::entityTypeOptions();
            $norm = AuditLog::normalizeEntityType($filters['entity_type']);
            $scopeParts[] = 'Entity: '.($norm ? ($entityOptions[$norm] ?? class_basename($norm)) : $filters['entity_type']);
        }
        if ($filters['q'] !== '') {
            $scopeParts[] = 'Search: "'.$filters['q'].'"';
        }

        $scopeLabel = $scopeParts !== [] ? implode(' • ', $scopeParts) : 'All Recorded System Events';

        return [
            'total_count' => $logs->count(),
            'unique_actors_count' => $uniqueActors,
            'unique_entities_count' => $uniqueEntities,
            'dominant_action' => $topActionLabel,
            'period_label' => $periodLabel,
            'scope_label' => $scopeLabel,
        ];
    }
}
