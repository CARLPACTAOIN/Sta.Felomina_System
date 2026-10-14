<?php

namespace Database\Seeders;

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StaffPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $captain = Role::findOrCreate(UserRole::Captain->value);
        $secretary = Role::findOrCreate(UserRole::Secretary->value);
        $purokChairperson = Role::findOrCreate(UserRole::PurokChairperson->value);

        $captain->syncPermissions([
            StaffPermission::ViewDashboard->value,
            StaffPermission::ViewNotifications->value,
            StaffPermission::ViewDocuments->value,
            StaffPermission::DownloadDocumentDraftPdf->value,
            StaffPermission::ReviewDocuments->value,
            StaffPermission::ReturnDocuments->value,
            StaffPermission::ApproveDocuments->value,
            StaffPermission::ArchiveDocuments->value,
            StaffPermission::RevokeDocuments->value,
            StaffPermission::SupersedeDocuments->value,
            StaffPermission::ManageCategorizationRules->value,
            StaffPermission::ManageStaffAccounts->value,
            StaffPermission::ViewAuditLogs->value,
            StaffPermission::ViewReports->value,
            StaffPermission::ViewDocumentReports->value,
            StaffPermission::ViewResidentReports->value,
            StaffPermission::ViewResidentsBarangay->value,
            StaffPermission::ManageResidentsBarangay->value,
            StaffPermission::ViewHouseholdsBarangay->value,
            StaffPermission::ManageHouseholdsBarangay->value,
        ]);

        $secretary->syncPermissions([
            StaffPermission::ViewDashboard->value,
            StaffPermission::ViewNotifications->value,
            StaffPermission::ViewDocuments->value,
            StaffPermission::CreateDocuments->value,
            StaffPermission::UpdateDocuments->value,
            StaffPermission::UploadDocumentDraftPdf->value,
            StaffPermission::DownloadDocumentDraftPdf->value,
            StaffPermission::DownloadDocumentQr->value,
            StaffPermission::SubmitDocuments->value,
            StaffPermission::ManageCategorizationRules->value,
            StaffPermission::ViewReports->value,
            StaffPermission::ViewDocumentReports->value,
            StaffPermission::ViewResidentReports->value,
            StaffPermission::ViewResidentsBarangay->value,
            StaffPermission::ViewHouseholdsBarangay->value,
        ]);

        $purokChairperson->syncPermissions([
            StaffPermission::ViewDashboard->value,
            StaffPermission::ViewNotifications->value,
            StaffPermission::ViewReports->value,
            StaffPermission::ViewResidentReports->value,
            StaffPermission::ViewResidentsAssigned->value,
            StaffPermission::ManageResidentsAssigned->value,
            StaffPermission::ViewHouseholdsAssigned->value,
            StaffPermission::ManageHouseholdsAssigned->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
