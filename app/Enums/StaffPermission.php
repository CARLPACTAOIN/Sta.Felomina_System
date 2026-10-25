<?php

namespace App\Enums;

enum StaffPermission: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewNotifications = 'notifications.view';

    case ViewDocuments = 'documents.view';
    case CreateDocuments = 'documents.create';
    case UpdateDocuments = 'documents.update';
    case UploadDocumentDraftPdf = 'documents.upload-draft-pdf';
    case DownloadDocumentDraftPdf = 'documents.download-draft-pdf';
    case DownloadDocumentQr = 'documents.download-qr';
    case SubmitDocuments = 'documents.submit';
    case ReviewDocuments = 'documents.review';
    case ReturnDocuments = 'documents.return';
    case ApproveDocuments = 'documents.approve';
    case ArchiveDocuments = 'documents.archive';
    case RevokeDocuments = 'documents.revoke';
    case SupersedeDocuments = 'documents.supersede';

    case ManageCategorizationRules = 'categorization-rules.manage';
    case ManageStaffAccounts = 'staff.manage';
    case ManagePuroks = 'puroks.manage';
    case ViewAuditLogs = 'audit-logs.view';

    case ViewResidentDocumentRequestsBarangay = 'resident-document-requests.view.barangay';
    case ViewResidentDocumentRequestsAssigned = 'resident-document-requests.view.assigned';
    case ManageResidentDocumentRequestsAssigned = 'resident-document-requests.manage.assigned';
    case ProcessResidentDocumentRequests = 'resident-document-requests.process';
    case RecordResidentDocumentRequestPayments = 'resident-document-requests.record-payment';
    case ReleaseResidentDocumentRequests = 'resident-document-requests.release';
    case ConfigureRequestableDocumentTypes = 'resident-document-requests.configure-types';

    case ViewReports = 'reports.view';
    case ViewDocumentReports = 'reports.documents.view';
    case ViewResidentReports = 'reports.residents.view';

    case ViewResidentsBarangay = 'residents.view.barangay';
    case ViewResidentsAssigned = 'residents.view.assigned';
    case ManageResidentsBarangay = 'residents.manage.barangay';
    case ManageResidentsAssigned = 'residents.manage.assigned';

    case ViewHouseholdsBarangay = 'households.view.barangay';
    case ViewHouseholdsAssigned = 'households.view.assigned';
    case ManageHouseholdsBarangay = 'households.manage.barangay';
    case ManageHouseholdsAssigned = 'households.manage.assigned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
