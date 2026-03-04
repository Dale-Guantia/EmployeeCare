<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Issue;

class IssueTableSeeder extends Seeder
{
    public function run(): void
    {
        $admin_issues = [
            ['name' => 'Administrative Case/Complaint', 'icon' => 'scale'],
            ['name' => 'Overtime Request-Related Concern', 'icon' => 'calendar-clock'],
            ['name' => 'Policy on Overtime Services Inquiries', 'icon' => 'scroll-text'],
            ['name' => 'Annual SALN Submission/Inquiries', 'icon' => 'file-input'],
            ['name' => 'Grievance Concern', 'icon' => 'message-square-warning'],
            ['name' => 'Extension of Service', 'icon' => 'file-cog'],
            ['name' => 'Sexual Harassment Complaint', 'icon' => 'alert-triangle'],
            ['name' => 'Reassignment', 'icon' => 'refresh-cw'],
            ['name' => 'Recall', 'icon' => 'undo-2'],
            ['name' => 'Pre-Termination', 'icon' => 'ban'],
            ['name' => 'Data Privacy', 'icon' => 'folder-lock'],
            ['name' => 'HR Coordinator Replacement', 'icon' => 'users'],
        ];

        $payroll_issues = [
            ['name' => 'Salary Deduction (LWOP/UL)', 'icon' => 'banknote-arrow-down'],
            ['name' => 'Salary Adjustment/ Differential', 'icon' => 'file-sliders'],
            ['name' => 'No Salary Received', 'icon' => 'banknote-x'],
            ['name' => 'No Benefits/ Bonuses Received', 'icon' => 'hand-coins'],
            ['name' => 'Overtime Pay/Allowance', 'icon' => 'banknote'],
            ['name' => 'Hazard Pay, Subsistence and Laundry Allowance', 'icon' => 'shield-check'],
            ['name' => 'Request for Certificate of Last Salary Received', 'icon' => 'file-badge'],
            ['name' => 'Request for Certificate of No Bonus/Benefits Received', 'icon' => 'file-badge'],
            ['name' => 'Salary Underpayment/ Overpayment', 'icon' => 'banknote'],
            ['name' => 'Status of First Salary', 'icon' => 'clock'],
            ['name' => 'Terminal Leave Clearance', 'icon' => 'badge-check'],
            ['name' => 'Status of Special/ Other Payroll', 'icon' => 'archive'],
            ['name' => 'Status of Last Salary', 'icon' => 'banknote'],
        ];

        $records_issues = [
            ['name' => 'DTR Rectification', 'icon' => 'square-pen'],
            ['name' => 'Request of Certificate of LWOP', 'icon' => 'file-badge'],
            ['name' => 'Request of Certificate of No LWOP', 'icon' => 'file-badge'],
            ['name' => 'Request of Certificate of Leave Credits', 'icon' => 'file-badge'],
            ['name' => 'Request of Document from 201 File', 'icon' => 'folder-open'],
            ['name' => 'Salary Deductions Concerns', 'icon' => 'banknote-arrow-down'],
            ['name' => 'Leave Application for Certain Types of Leave', 'icon' => 'calendar-days'],
            ['name' => 'Tagging of Employees', 'icon' => 'tag'],
            ['name' => 'Schedule for i-Face/Biometrics Registration', 'icon' => 'fingerprint'],
            ['name' => 'Incident Report On i-Face/Biometric Malfunction', 'icon' => 'wrench'],
            ['name' => 'Transfer of Leave Credits from Other Agency', 'icon' => 'arrow-left-right'],
            ['name' => 'Terminal Leave Benefits', 'icon' => 'badge-check'],
        ];

        $claims_issues = [
            ['name' => 'Approval of Loan', 'icon' => 'check-circle'],
            ['name' => 'Loan Stoppage', 'icon' => 'stop-circle'],
            ['name' => 'Loan Deduction', 'icon' => 'minus-circle'],
            ['name' => 'Loan Availment', 'icon' => 'credit-card'],
            ['name' => 'GSIS Loan', 'icon' => 'library'],
            ['name' => 'Policy Loan', 'icon' => 'file-text'],
            ['name' => 'PAG-IBIG Loan', 'icon' => 'home'],
            ['name' => 'PAG-IBIG MP2', 'icon' => 'home'],
            ['name' => 'PAG-IBIG Contribution Upgrade', 'icon' => 'arrow-up-circle'],
            ['name' => 'Landbank Loan', 'icon' => 'banknote'],
            ['name' => 'EPP Loan', 'icon' => 'square'],
            ['name' => 'GSIS Reconciliation', 'icon' => 'arrow-left-right'],
            ['name' => 'Membership Requirements', 'icon' => 'clipboard-check'],
            ['name' => 'GSIS BP Number', 'icon' => 'id-card'],
            ['name' => 'PAG-IBIG Number', 'icon' => 'id-card'],
            ['name' => 'PHILHEALTH Number', 'icon' => 'id-card'],
            ['name' => 'GSIS Claim', 'icon' => 'clipboard'],
            ['name' => 'PHILHEALTH Claim', 'icon' => 'clipboard'],
            ['name' => 'GSIS Touch', 'icon' => 'smartphone'],
            ['name' => 'Rewards and Recognition', 'icon' => 'trophy'],
            ['name' => 'APE Concerns', 'icon' => 'users'],
            ['name' => 'Financial Literacy Seminar', 'icon' => 'graduation-cap'],
            ['name' => 'PASIGLAKAS', 'icon' => 'sparkles'],
            ['name' => 'CSS Fun Run', 'icon' => 'sparkles'],
            ['name' => 'Employee Fun Run', 'icon' => 'sparkles'],
            ['name' => 'Lost access in the loyalty award/   incentive module', 'icon' => 'lock-open'],
            ['name' => 'Summary list of Pag-IBIG MP2 number', 'icon' => 'list'],
            ['name' => 'Loan deduction', 'icon' => 'minus-circle'],
            ['name' => 'Loans in monitoring index not reflected in payroll', 'icon' => 'alert-triangle'],
            ['name' => 'Loan schedule with payments & balance', 'icon' => 'calendar-days'],
        ];

        $rsp_issues = [
            ['name' => 'Request for Employment Records', 'icon' => 'file'],
            ['name' => 'Status of Request for Employment Records', 'icon' => 'clock'],
            ['name' => 'Status of Job application', 'icon' => 'briefcase'],
            ['name' => 'Status of Recruitment Request Form', 'icon' => 'file-text'],
            ['name' => 'Status of Renewal/ Non-Renewal of Employees', 'icon' => 'refresh-cw'],
            ['name' => 'Status of Update on Personal Information', 'icon' => 'user'],
            ['name' => 'Status of Update on Separated employees', 'icon' => 'user-minus'],
            ['name' => 'Access of status from Admin Div', 'icon' => 'building'],
            ['name' => 'Access of status for memos of Dropped/ Suspension', 'icon' => 'file-down'],
            ['name' => 'Access of status for receiving of Death Cert', 'icon' => 'inbox'],
        ];

        $lnd_issues = [
            ['name' => 'ID request', 'icon' => 'id-card'],
            ['name' => 'Official Business (OB form) training-related', 'icon' => 'briefcase'],
            ['name' => 'Scholarship Concern', 'icon' => 'graduation-cap'],
            ['name' => 'Study Leave Concern', 'icon' => 'book-open'],
            ['name' => 'Activity Design Concerns', 'icon' => 'brush'],
            ['name' => 'Training / Travel Order Concern', 'icon' => 'truck'],
            ['name' => 'External Trainings Participation', 'icon' => 'users'],
        ];

        $pm_issues = [
            ['name' => 'Certified True Copy of Performance Ratings', 'icon' => 'copy'],
            ['name' => 'OPCR/DPCR and IPCR Appeals', 'icon' => 'cog'],
            ['name' => 'Request for System Access Extension', 'icon' => 'arrow-up-right'],
            ['name' => 'Log-In Credential Issues', 'icon' => 'key-round'],
            ['name' => 'Request to Edit Submitted OPCR/DPCR/IPCR', 'icon' => 'pencil'],
            ['name' => 'Request to Submit OPCR/DPCR/IPCR (New Employees)', 'icon' => 'send'],
            ['name' => 'Request for Technical Assistance', 'icon' => 'wrench'],
            ['name' => 'Technical Issues/Glitch', 'icon' => 'bug'],
            ['name' => 'Access of OPCR/DPCR/IPCR Issue', 'icon' => 'lock-open'],
        ];

        $it_issues = [
            ['name' => 'Office assignment/ Retagging', 'icon' => 'tag'],
            ['name' => 'GEMS account password reset', 'icon' => 'rotate-ccw-key'],
        ];

        $division_mapping = [
            2 => $it_issues,
            3 => $admin_issues,
            4 => $payroll_issues,
            5 => $records_issues,
            6 => $claims_issues,
            7 => $rsp_issues,
            8 => $lnd_issues,
            9 => $pm_issues,
        ];

        foreach ($division_mapping as $division_id => $issues) {
            foreach ($issues as $issue) {
                Issue::create([
                    'department_id'     => 1,
                    'division_id'       => $division_id,
                    'issue_description' => $issue['name'],
                    'icon'              => 'lucide-' . $issue['icon'],
                ]);
            }
        }
    }
}
