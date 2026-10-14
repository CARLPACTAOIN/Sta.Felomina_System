<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $now = now();
        $roleNames = DB::table('users')
            ->select('role')
            ->whereNotNull('role')
            ->distinct()
            ->pluck('role')
            ->filter()
            ->values();

        foreach ($roleNames as $roleName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        DB::table('users')
            ->whereNotNull('role')
            ->orderBy('id')
            ->select(['id', 'role'])
            ->chunkById(100, function ($users) use ($roleIds): void {
                foreach ($users as $user) {
                    $roleId = $roleIds[$user->role] ?? null;

                    if ($roleId === null) {
                        continue;
                    }

                    DB::table('model_has_roles')->updateOrInsert([
                        'role_id' => $roleId,
                        'model_type' => User::class,
                        'model_id' => $user->id,
                    ]);
                }
            });

        Schema::table('users', function ($table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function ($table): void {
            $table->string('role')->default('secretary')->after('password');
        });

        $assignments = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->select(['model_has_roles.model_id', 'roles.name'])
            ->orderBy('model_has_roles.model_id')
            ->get();

        foreach ($assignments as $assignment) {
            DB::table('users')
                ->where('id', $assignment->model_id)
                ->update(['role' => $assignment->name]);
        }
    }
};
