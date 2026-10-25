<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Models\AuditLog;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * @return array{
     *     total_documents: int,
     *     pending_approvals: int,
     *     published_documents: int,
     *     archived_revoked_documents: int,
     *     total_residents: int,
     *     request_counts: array<string, int>,
     *     category_distribution: BaseCollection<int, array{name: string, count: int}>,
     *     purok_distribution: BaseCollection<int, array{name: string, count: int}>,
     *     recent_activity: Collection<int, AuditLog>,
     * }
     */
    public function forCaptainOrSecretary(User $user): array
    {
        $totalDocuments = DocumentRecord::query()->count();
        $pendingApprovals = DocumentRecord::query()->where('status', DocumentStatus::PendingApproval)->count();
        $publishedDocuments = DocumentRecord::query()->where('status', DocumentStatus::Published)->count();
        $archivedRevokedDocuments = DocumentRecord::query()
            ->whereIn('status', [DocumentStatus::Archived, DocumentStatus::Revoked, DocumentStatus::Superseded])
            ->count();

        $totalResidents = Resident::query()->count();
        $requestCounts = ResidentDocumentRequest::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->whereIn('status', ['submitted', 'needs_information', 'ready_for_payment', 'released'])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $categoryCounts = DocumentRecord::query()
            ->select('document_category_id', DB::raw('count(*) as aggregate'))
            ->groupBy('document_category_id')
            ->pluck('aggregate', 'document_category_id');

        $categories = DocumentCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentCategory $category): array => [
                'name' => $category->name,
                'count' => (int) ($categoryCounts[$category->id] ?? 0),
            ]);

        $purokCounts = Resident::query()
            ->select('purok_id', DB::raw('count(*) as aggregate'))
            ->groupBy('purok_id')
            ->pluck('aggregate', 'purok_id');

        $puroks = Purok::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Purok $purok): array => [
                'name' => $purok->name,
                'count' => (int) ($purokCounts[$purok->id] ?? 0),
            ]);

        $recentActivity = AuditLog::query()
            ->with('actor')
            ->latest()
            ->limit(6)
            ->get();

        return [
            'total_documents' => $totalDocuments,
            'pending_approvals' => $pendingApprovals,
            'published_documents' => $publishedDocuments,
            'archived_revoked_documents' => $archivedRevokedDocuments,
            'total_residents' => $totalResidents,
            'request_counts' => [
                'submitted' => (int) ($requestCounts['submitted'] ?? 0),
                'needs_information' => (int) ($requestCounts['needs_information'] ?? 0),
                'ready_for_payment' => (int) ($requestCounts['ready_for_payment'] ?? 0),
                'released' => (int) ($requestCounts['released'] ?? 0),
            ],
            'category_distribution' => $categories,
            'purok_distribution' => $puroks,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * @return array{
     *     purok_name: string,
     *     total_residents: int,
     *     active_residents: int,
     *     households: int,
     *     recent_updates: int,
     *     recent_residents: Collection<int, Resident>,
     * }
     */
    public function forPurokChairperson(User $user): array
    {
        $purokId = $user->purok_id;
        $purokName = $user->purok !== null ? $user->purok->name : 'Assigned Purok';

        if ($purokId === null) {
            return [
                'purok_name' => $purokName,
                'total_residents' => 0,
                'active_residents' => 0,
                'households' => 0,
                'recent_updates' => 0,
                'recent_residents' => new Collection,
            ];
        }

        $totalResidents = Resident::query()
            ->where('purok_id', $purokId)
            ->count();

        $activeResidents = Resident::query()
            ->where('purok_id', $purokId)
            ->where('status', ResidentStatus::Active)
            ->count();

        $households = Household::query()
            ->where('purok_id', $purokId)
            ->count();

        $recentUpdates = Resident::query()
            ->where('purok_id', $purokId)
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $recentResidents = Resident::query()
            ->with(['household'])
            ->where('purok_id', $purokId)
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return [
            'purok_name' => $purokName,
            'total_residents' => $totalResidents,
            'active_residents' => $activeResidents,
            'households' => $households,
            'recent_updates' => $recentUpdates,
            'recent_residents' => $recentResidents,
        ];
    }
}
