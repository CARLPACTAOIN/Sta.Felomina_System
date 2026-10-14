<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Enums\StaffPermission;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportEngine
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     records: Collection<int, DocumentRecord>|LengthAwarePaginator<int, DocumentRecord>,
     *     total_count: int,
     *     status_counts: array<string, int>,
     *     dominant_category: string,
     *     period_label: string,
     * }
     */
    public function documentReport(User $user, array $filters, ?int $perPage = 25): array
    {
        abort_unless($user->can(StaffPermission::ViewDocumentReports->value), 403, 'Unauthorized to view document reports.');

        $query = DocumentRecord::query()->with(['type', 'category', 'creator', 'approver']);

        $year = ! empty($filters['year']) ? (int) $filters['year'] : null;
        $month = ! empty($filters['month']) ? (int) $filters['month'] : null;
        $categoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;
        $typeId = ! empty($filters['type_id']) ? (int) $filters['type_id'] : null;
        $status = ! empty($filters['status']) ? (string) $filters['status'] : null;
        $dateFrom = ! empty($filters['date_from']) ? (string) $filters['date_from'] : null;
        $dateTo = ! empty($filters['date_to']) ? (string) $filters['date_to'] : null;

        if ($year !== null) {
            $query->where('document_year', $year);
        }

        if ($month !== null && $month >= 1 && $month <= 12) {
            $query->whereMonth('document_date', $month);
        }

        if ($categoryId !== null) {
            $query->where('document_category_id', $categoryId);
        }

        if ($typeId !== null) {
            $query->where('document_type_id', $typeId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($dateFrom !== null) {
            $query->whereDate('document_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('document_date', '<=', $dateTo);
        }

        $totalCount = (clone $query)->count();

        // Calculate status counts
        $statusGroupCounts = (clone $query)
            ->reorder()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $statusCounts = [];
        foreach (DocumentStatus::cases() as $case) {
            $statusCounts[$case->value] = (int) ($statusGroupCounts[$case->value] ?? 0);
        }

        // Calculate dominant category
        $topCategory = (clone $query)
            ->reorder()
            ->select('document_category_id', DB::raw('count(*) as count'))
            ->groupBy('document_category_id')
            ->orderByDesc('count')
            ->first();

        $dominantCategory = 'None';
        if ($topCategory !== null) {
            $cat = DocumentCategory::find($topCategory->document_category_id);
            if ($cat !== null) {
                $dominantCategory = $cat->name;
            }
        }

        // Determine period label
        $periodLabel = $this->buildDocumentPeriodLabel($year, $month, $dateFrom, $dateTo);

        $query->latest('document_date')->latest('id');

        $records = $perPage !== null
            ? $query->paginate($perPage)->withQueryString()
            : $query->get();

        return [
            'records' => $records,
            'total_count' => $totalCount,
            'status_counts' => $statusCounts,
            'dominant_category' => $dominantCategory,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     records: Collection<int, Resident>|LengthAwarePaginator<int, Resident>,
     *     total_count: int,
     *     active_count: int,
     *     voter_count: int,
     *     household_count: int,
     *     purok_label: string,
     * }
     */
    public function residentReport(User $user, array $filters, ?int $perPage = 25): array
    {
        $query = Resident::query()->with(['purok', 'household']);

        $purokId = null;
        if ($user->isPurokChairperson()) {
            abort_if($user->purok_id === null, 403, 'Purok Chairperson has no assigned purok.');
            $purokId = $user->purok_id;
        } elseif (! empty($filters['purok_id'])) {
            $purokId = (int) $filters['purok_id'];
        }

        if ($purokId !== null) {
            $query->where('purok_id', $purokId);
            $purok = Purok::find($purokId);
            $purokLabel = $purok ? $purok->name : 'Assigned Purok';
        } else {
            $purokLabel = 'Barangay Sta. Felomina (All Puroks)';
        }

        $status = ! empty($filters['status']) ? (string) $filters['status'] : null;
        $voterStatus = ! empty($filters['voter_status']) ? (string) $filters['voter_status'] : null;
        $sex = ! empty($filters['sex']) ? (string) $filters['sex'] : null;
        $civilStatus = ! empty($filters['civil_status']) ? (string) $filters['civil_status'] : null;

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($voterStatus === 'voter') {
            $query->where('is_registered_voter', true);
        } elseif ($voterStatus === 'non_voter') {
            $query->where('is_registered_voter', false);
        }

        if ($sex !== null) {
            $query->where('sex', $sex);
        }

        if ($civilStatus !== null) {
            $query->where('civil_status', $civilStatus);
        }

        $totalCount = (clone $query)->count();
        $activeCount = (clone $query)->where('status', ResidentStatus::Active)->count();
        $voterCount = (clone $query)->where('is_registered_voter', true)->count();
        $householdCount = (clone $query)->distinct('household_id')->count('household_id');

        $query->orderBy('last_name')->orderBy('first_name')->latest('id');

        $records = $perPage !== null
            ? $query->paginate($perPage)->withQueryString()
            : $query->get();

        return [
            'records' => $records,
            'total_count' => $totalCount,
            'active_count' => $activeCount,
            'voter_count' => $voterCount,
            'household_count' => $householdCount,
            'purok_label' => $purokLabel,
        ];
    }

    private function buildDocumentPeriodLabel(?int $year, ?int $month, ?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom !== null && $dateTo !== null) {
            return Carbon::parse($dateFrom)->format('M d, Y').' – '.Carbon::parse($dateTo)->format('M d, Y');
        }

        if ($dateFrom !== null) {
            return 'From '.Carbon::parse($dateFrom)->format('M d, Y');
        }

        if ($dateTo !== null) {
            return 'Until '.Carbon::parse($dateTo)->format('M d, Y');
        }

        if ($year !== null && $month !== null) {
            return Carbon::createFromDate($year, $month, 1)->format('F Y');
        }

        if ($year !== null) {
            return "Year {$year}";
        }

        return 'All Records';
    }
}
