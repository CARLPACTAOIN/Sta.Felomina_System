<?php

use App\Enums\UserRole;
use App\Models\Purok;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Hash;

test('account seeder populates official barangay staff accounts', function () {
    $this->seed(AccountSeeder::class);

    expect(Purok::query()->count())->toBe(7);

    $captain = User::where('email', 'captain@stafelomina.local')->first();
    expect($captain)->not->toBeNull()
        ->and($captain->name)->toBe('Juan D. Cruz')
        ->and($captain->role)->toBe(UserRole::Captain)
        ->and($captain->is_active)->toBeTrue()
        ->and($captain->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $captain->password))->toBeTrue();

    $secretary = User::where('email', 'secretary@stafelomina.local')->first();
    expect($secretary)->not->toBeNull()
        ->and($secretary->name)->toBe('Maria L. Santos')
        ->and($secretary->role)->toBe(UserRole::Secretary)
        ->and($secretary->is_active)->toBeTrue()
        ->and($secretary->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $secretary->password))->toBeTrue();

    for ($i = 1; $i <= 7; $i++) {
        $purok = Purok::where('code', "purok-{$i}")->first();
        $purokChair = User::where('email', "purok{$i}@stafelomina.local")->first();

        expect($purok)->not->toBeNull()
            ->and($purok->name)->toBe("Purok {$i}");

        expect($purokChair)->not->toBeNull()
            ->and($purokChair->role)->toBe(UserRole::PurokChairperson)
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
