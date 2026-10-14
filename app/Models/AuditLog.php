<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $summary
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $actor
 * @property-read Model $auditable
 */
#[Fillable([
    'actor_id',
    'action',
    'auditable_type',
    'auditable_id',
    'summary',
    'context',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(User $actor, string $action, Model $auditable, string $summary, array $context = []): self
    {
        return self::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'summary' => $summary,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['q']), function (Builder $query) use ($filters): void {
                $search = mb_strtolower(trim((string) $filters['q']));

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(summary) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(action) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('actor', function (Builder $query) use ($search): void {
                            $query
                                ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%']);
                        });

                    if (is_numeric($search)) {
                        $query->orWhere('auditable_id', (int) $search);
                    }
                });
            })
            ->when(isset($filters['actor_id']) && $filters['actor_id'] !== '', function (Builder $query) use ($filters): void {
                if ($filters['actor_id'] === 'system') {
                    $query->whereNull('actor_id');
                } elseif (is_numeric($filters['actor_id'])) {
                    $query->where('actor_id', (int) $filters['actor_id']);
                }
            })
            ->when(! empty($filters['action']), function (Builder $query) use ($filters): void {
                $query->where('action', (string) $filters['action']);
            })
            ->when(! empty($filters['entity_type']), function (Builder $query) use ($filters): void {
                $normalized = self::normalizeEntityType((string) $filters['entity_type']);
                if ($normalized !== null) {
                    $query->where('auditable_type', $normalized);
                }
            })
            ->when(! empty($filters['date_from']), function (Builder $query) use ($filters): void {
                $query->where('created_at', '>=', Carbon::parse((string) $filters['date_from'])->startOfDay());
            })
            ->when(! empty($filters['date_to']), function (Builder $query) use ($filters): void {
                $query->where('created_at', '<=', Carbon::parse((string) $filters['date_to'])->endOfDay());
            })
            ->when(($filters['sort'] ?? '') === 'oldest', function (Builder $query): void {
                $query->orderBy('created_at')->orderBy('id');
            }, function (Builder $query): void {
                $query->latest('created_at')->latest('id');
            });
    }

    public static function normalizeEntityType(?string $type): ?string
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'document', 'documentrecord', 'document_record', strtolower(DocumentRecord::class) => DocumentRecord::class,
            'resident', strtolower(Resident::class) => Resident::class,
            'household', strtolower(Household::class) => Household::class,
            'staff', 'user', strtolower(User::class) => User::class,
            'rule', 'categorization_rule', 'categorizationrule', strtolower(CategorizationRule::class) => CategorizationRule::class,
            default => class_exists($type) ? $type : null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function entityTypeOptions(): array
    {
        return [
            DocumentRecord::class => 'Document Records',
            Resident::class => 'Residents',
            Household::class => 'Households',
            User::class => 'Staff Accounts',
            CategorizationRule::class => 'Categorization Rules',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function predefinedActions(): array
    {
        return [
            'document_submitted' => 'Document Submitted',
            'document_returned' => 'Document Returned',
            'document_approved' => 'Document Approved',
            'document_archived' => 'Document Archived',
            'document_revoked' => 'Document Revoked',
            'document_superseded' => 'Document Superseded',
            'document_category_suggested' => 'Category Suggested',
            'document_category_overridden' => 'Category Overridden',
            'resident_created' => 'Resident Created',
            'resident_updated' => 'Resident Updated',
            'resident_status_changed' => 'Resident Status Changed',
            'household_created' => 'Household Created',
            'household_updated' => 'Household Updated',
            'account_created' => 'Staff Account Created',
            'account_updated' => 'Staff Account Updated',
            'account_role_changed' => 'Staff Role Changed',
            'account_activated' => 'Staff Account Activated',
            'account_deactivated' => 'Staff Account Deactivated',
            'account_password_reset' => 'Staff Password Reset',
            'categorization_rule_created' => 'Categorization Rule Created',
            'categorization_rule_updated' => 'Categorization Rule Updated',
            'categorization_rule_activated' => 'Categorization Rule Activated',
            'categorization_rule_deactivated' => 'Categorization Rule Deactivated',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        $actions = self::predefinedActions();

        $existing = self::query()
            ->distinct()
            ->pluck('action')
            ->filter()
            ->all();

        foreach ($existing as $action) {
            if (! isset($actions[$action])) {
                $actions[$action] = str($action)->headline()->toString();
            }
        }

        asort($actions);

        return $actions;
    }

    public function actionLabel(): string
    {
        $options = self::predefinedActions();

        return $options[$this->action] ?? str($this->action)->headline()->toString();
    }

    public function entityLabel(): string
    {
        return match ($this->auditable_type) {
            DocumentRecord::class => 'Document Record',
            Resident::class => 'Resident',
            Household::class => 'Household',
            User::class => 'Staff Account',
            CategorizationRule::class => 'Categorization Rule',
            default => class_basename($this->auditable_type),
        };
    }

    public function actionBadgeClasses(): string
    {
        return match ($this->action) {
            'document_approved', 'account_activated', 'categorization_rule_activated' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'document_submitted', 'resident_created', 'household_created', 'account_created', 'categorization_rule_created' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800',
            'document_returned', 'resident_updated', 'household_updated', 'account_updated', 'document_category_suggested', 'document_category_overridden', 'categorization_rule_updated' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'document_revoked', 'account_deactivated', 'categorization_rule_deactivated' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            'document_archived', 'document_superseded', 'resident_status_changed', 'account_role_changed', 'account_password_reset' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    public function entityRoute(): ?string
    {
        return match ($this->auditable_type) {
            DocumentRecord::class => route('documents.show', $this->auditable_id),
            Resident::class => route('residents.index', ['search' => $this->auditable_id]),
            Household::class => route('households.index', ['search' => $this->auditable_id]),
            User::class => route('staff-accounts.index'),
            CategorizationRule::class => route('categorization-rules.index'),
            default => null,
        };
    }
}
