<?php

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\Purok;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('role permission seeder creates least privilege staff roles', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::where('name', UserRole::Captain->value)->exists())->toBeTrue()
        ->and(Role::where('name', UserRole::Secretary->value)->exists())->toBeTrue()
        ->and(Role::where('name', UserRole::PurokChairperson->value)->exists())->toBeTrue()
        ->and(Permission::count())->toBe(count(StaffPermission::cases()));

    $captainRole = Role::findByName(UserRole::Captain->value);
    $secretaryRole = Role::findByName(UserRole::Secretary->value);
    $purokRole = Role::findByName(UserRole::PurokChairperson->value);

    expect($captainRole->hasPermissionTo(StaffPermission::ManageStaffAccounts->value))->toBeTrue()
        ->and($captainRole->hasPermissionTo(StaffPermission::ApproveDocuments->value))->toBeTrue()
        ->and($captainRole->hasPermissionTo(StaffPermission::DownloadDocumentDraftPdf->value))->toBeTrue()
        ->and($captainRole->hasPermissionTo(StaffPermission::CreateDocuments->value))->toBeFalse()
        ->and($captainRole->hasPermissionTo(StaffPermission::UpdateDocuments->value))->toBeFalse()
        ->and($captainRole->hasPermissionTo(StaffPermission::UploadDocumentDraftPdf->value))->toBeFalse()
        ->and($captainRole->hasPermissionTo(StaffPermission::DownloadDocumentQr->value))->toBeFalse()
        ->and($captainRole->hasPermissionTo(StaffPermission::SubmitDocuments->value))->toBeFalse()
        ->and($secretaryRole->hasPermissionTo(StaffPermission::SubmitDocuments->value))->toBeTrue()
        ->and($secretaryRole->hasPermissionTo(StaffPermission::ApproveDocuments->value))->toBeFalse()
        ->and($secretaryRole->hasPermissionTo(StaffPermission::ManageResidentsBarangay->value))->toBeFalse()
        ->and($purokRole->hasPermissionTo(StaffPermission::ManageResidentsAssigned->value))->toBeTrue()
        ->and($purokRole->hasPermissionTo(StaffPermission::ViewDocuments->value))->toBeFalse();
});

test('account seeder populates official barangay staff accounts', function () {
    $this->seed(AccountSeeder::class);

    expect(Purok::query()->count())->toBe(7);

    $captain = User::where('email', 'captain@stafelomina.local')->first();
    expect($captain)->not->toBeNull()
        ->and($captain->name)->toBe('Juan D. Cruz')
        ->and($captain->hasRole(UserRole::Captain->value))->toBeTrue()
        ->and($captain->can(StaffPermission::ApproveDocuments->value))->toBeTrue()
        ->and($captain->can(StaffPermission::CreateDocuments->value))->toBeFalse()
        ->and($captain->can(StaffPermission::SubmitDocuments->value))->toBeFalse()
        ->and($captain->is_active)->toBeTrue()
        ->and($captain->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $captain->password))->toBeTrue();

    $secretary = User::where('email', 'secretary@stafelomina.local')->first();
    expect($secretary)->not->toBeNull()
        ->and($secretary->name)->toBe('Maria L. Santos')
        ->and($secretary->hasRole(UserRole::Secretary->value))->toBeTrue()
        ->and($secretary->can(StaffPermission::SubmitDocuments->value))->toBeTrue()
        ->and($secretary->can(StaffPermission::ApproveDocuments->value))->toBeFalse()
        ->and($secretary->is_active)->toBeTrue()
        ->and($secretary->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $secretary->password))->toBeTrue();

    for ($i = 1; $i <= 7; $i++) {
        $purok = Purok::where('code', "purok-{$i}")->first();
        $purokChair = User::where('email', "purok{$i}@stafelomina.local")->first();

        expect($purok)->not->toBeNull()
            ->and($purok->name)->toBe("Purok {$i}");

        expect($purokChair)->not->toBeNull()
            ->and($purokChair->hasRole(UserRole::PurokChairperson->value))->toBeTrue()
            ->and($purokChair->can(StaffPermission::ManageResidentsAssigned->value))->toBeTrue()
            ->and($purokChair->can(StaffPermission::ViewDocuments->value))->toBeFalse()
            ->and($purokChair->purok_id)->toBe($purok?->id)
            ->and($purokChair->is_active)->toBeTrue()
            ->and($purokChair->email_verified_at)->not->toBeNull()
            ->and(Hash::check('password', $purokChair->password))->toBeTrue();
    }

    $inactiveStaff = User::where('email', 'inactive@stafelomina.local')->first();
    expect($inactiveStaff)->not->toBeNull()
        ->and($inactiveStaff->is_active)->toBeFalse()
        ->and(Hash::check('password', $inactiveStaff->password))->toBeTrue();
});

test('account seeder is idempotent and does not create duplicate accounts', function () {
    $this->seed(AccountSeeder::class);
    $countAfterFirstSeed = User::count();

    $this->seed(AccountSeeder::class);
    $countAfterSecondSeed = User::count();

    expect($countAfterSecondSeed)->toBe($countAfterFirstSeed)
        ->and($countAfterFirstSeed)->toBe(10);

    expect(Purok::query()->count())->toBe(7);
});
