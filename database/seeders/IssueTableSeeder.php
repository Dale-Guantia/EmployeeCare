<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Issue;

class IssueTableSeeder extends Seeder
{
    public function run(): void
    {
        $admin_issues = [
            ['name' => 'Administrative Case/Complaint', 'icon' => 'fas fa-balance-scale'],
            ['name' => 'Overtime Request-Related Concern', 'icon' => 'fas fa-user-clock'],
            ['name' => 'Policy on Overtime Services Inquiries', 'icon' => 'fas fa-user-shield'],
            ['name' => 'Annual SALN Submission/Inquiries', 'icon' => 'fas fa-file-signature'],
            ['name' => 'Grievance Concern', 'icon' => 'fas fa-exclamation-circle'],
            ['name' => 'Extension of Service', 'icon' => 'fas fa-user-plus'],
            ['name' => 'Sexual Harassment Complaint', 'icon' => 'fas fa-exclamation-triangle'],
            ['name' => 'Reassignment', 'icon' => 'fas fa-user-edit'],
            ['name' => 'Recall', 'icon' => 'fas fa-undo'],
            ['name' => 'Pre-Termination', 'icon' => 'fas fa-user-times'],
            ['name' => 'Data Privacy', 'icon' => 'fas fa-exclamation'],
            ['name' => 'HR Coordinator Replacement', 'icon' => 'fas fa-random'],
        ];

        $payroll_issues = [
            ['name' => 'Salary Deduction (LWOP/UL)', 'icon' => 'fas fa-minus-circle'],
            ['name' => 'Salary Adjustment/ Differential', 'icon' => 'fas fa-sliders-h'],
            ['name' => 'No Salary Received', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'No Benefits/ Bonuses Received', 'icon' => 'fas fa-business-time'],
            ['name' => 'Overtime Pay/Allowance', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Hazard Pay, Subsistence and Laundry Allowance', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Request for Certificate of Last Salary Received', 'icon' => 'fas fa-certificate'],
            ['name' => 'Request for Certificate of No Bonus/Benefits Received', 'icon' => 'fas fa-certificate'],
            ['name' => 'Salary Underpayment/ Overpayment', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Status of First Salary', 'icon' => 'far fa-money-bill-alt'],
            ['name' => 'Terminal Leave Clearance', 'icon' => 'fas fa-file-import'],
            ['name' => 'Status of Special/ Other Payroll', 'icon' => 'fas fa-archive'],
            ['name' => 'Status of Last Salary', 'icon' => 'fas fa-money-bill-wave'],
        ];

        $records_issues = [
            ['name' => 'DTR Rectification', 'icon' => 'fas fa-edit'],
            ['name' => 'Request of Certificate of LWOP', 'icon' => 'fas fa-certificate'],
            ['name' => 'Request of Certificate of No LWOP', 'icon' => 'fas fa-certificate'],
            ['name' => 'Request of Certificate of Leave Credits', 'icon' => 'fas fa-certificate'],
            ['name' => 'Request of Document from 201 File', 'icon' => 'fas fa-folder-open'],
            ['name' => 'Salary Deductions Concerns', 'icon' => 'fas fa-minus-circle'],
            ['name' => 'Leave Application for Certain Types of Leave', 'icon' => 'fas fa-calendar-alt'],
            ['name' => 'Tagging of Employees', 'icon' => 'fas fa-user-tag'],
            ['name' => 'Schedule for i-Face/Biometrics Registration', 'icon' => 'fas fa-fingerprint'],
            ['name' => 'Incident Report On i-Face/Biometric Malfunction', 'icon' => 'fas fa-wrench'],
            ['name' => 'Transfer of Leave Credits from Other Agency', 'icon' => 'fas fa-file-export'],
            ['name' => 'Terminal Leave Benefits', 'icon' => 'fas fa-id-badge'],
        ];

        $claims_issues = [
            ['name' => 'Approval of Loan', 'icon' => 'fas fa-check-circle'],
            ['name' => 'Loan Stoppage', 'icon' => 'fas fa-stop-circle'],
            ['name' => 'Loan Deduction', 'icon' => 'fas fa-minus-circle'],
            ['name' => 'Loan Availment', 'icon' => 'fas fa-money-check-alt'],
            ['name' => 'GSIS Loan', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'Policy Loan', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'PAG-IBIG Loan', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'PAG-IBIG MP2', 'icon' => 'fas fa-piggy-bank'],
            ['name' => 'PAG-IBIG Contribution Upgrade', 'icon' => 'fas fa-piggy-bank'],
            ['name' => 'Landbank Loan', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'EPP Loan', 'icon' => 'fas fa-money-bill-wave'],
            ['name' => 'GSIS Reconciliation', 'icon' => 'fas fa-exchange-alt'],
            ['name' => 'Membership Requirements', 'icon' => 'fas fa-clipboard-check'],
            ['name' => 'GSIS BP Number', 'icon' => 'fas fa-id-card'],
            ['name' => 'PAG-IBIG Number', 'icon' => 'fas fa-id-card'],
            ['name' => 'PHILHEALTH Number', 'icon' => 'fas fa-id-card'],
            ['name' => 'GSIS Claim', 'icon' => 'fas fa-hand-holding-usd'],
            ['name' => 'PHILHEALTH Claim', 'icon' => 'fas fa-hand-holding-usd'],
            ['name' => 'GSIS Touch', 'icon' => 'fas fa-hand-pointer'],
            ['name' => 'Rewards and Recognition', 'icon' => 'fas fa-trophy'],
            ['name' => 'APE Concerns', 'icon' => 'fas fa-briefcase-medical'],
            ['name' => 'Financial Literacy Seminar', 'icon' => 'fas fa-headset'],
            ['name' => 'PASIGLAKAS', 'icon' => 'fas fa-basketball-ball'],
            ['name' => 'CSC Fun Run', 'icon' => 'fas fa-walking'],
            ['name' => 'Employee Fun Run', 'icon' => 'fas fa-walking'],
            ['name' => 'Lost access in the loyalty award/   incentive module', 'icon' => 'fas fa-user-lock'],
            ['name' => 'Summary list of Pag-IBIG MP2 number', 'icon' => 'fas fa-list'],
            ['name' => 'Loans in monitoring index not reflected in payroll', 'icon' => 'fas fa-exclamation-triangle'],
            ['name' => 'Loan schedule with payments & balance', 'icon' => 'far fa-calendar-alt'],
        ];

        $rsp_issues = [
            ['name' => 'Request for Employment Records', 'icon' => 'fas fa-file'],
            ['name' => 'Status of Request for Employment Records', 'icon' => 'fas fa-file-export'],
            ['name' => 'Status of Job application', 'icon' => 'fas fa-briefcase'],
            ['name' => 'Status of Recruitment Request Form', 'icon' => 'far fa-file-alt'],
            ['name' => 'Status of Renewal/ Non-Renewal of Employees', 'icon' => 'fas fa-undo'],
            ['name' => 'Status of Update on Personal Information', 'icon' => 'fas fa-user-edit'],
            ['name' => 'Status of Update on Separated employees', 'icon' => 'fas fa-user-alt-slash'],
            ['name' => 'Access of status from Admin Div', 'icon' => 'fas fa-building'],
            ['name' => 'Access of status for memos of Dropped/ Suspension', 'icon' => 'fas fa-exclamation'],
            ['name' => 'Access of status for receiving of Death Cert', 'icon' => 'fas fa-certificate'],
        ];

        $lnd_issues = [
            ['name' => 'ID request', 'icon' => 'fas fa-id-card'],
            ['name' => 'Official Business (OB form) training-related', 'icon' => 'fas fa-briefcase'],
            ['name' => 'Scholarship Concern', 'icon' => 'fas fa-graduation-cap'],
            ['name' => 'Study Leave Concern', 'icon' => 'fas fa-book-reader'],
            ['name' => 'Activity Design Concerns', 'icon' => 'fas fa-paperclip'],
            ['name' => 'Training / Travel Order Concern', 'icon' => 'fas fa-plane-departure'],
            ['name' => 'External Trainings Participation', 'icon' => 'fas fa-users'],
        ];

        $pm_issues = [
            ['name' => 'Certified True Copy of Performance Ratings', 'icon' => 'fas fa-copy'],
            ['name' => 'OPCR/DPCR and IPCR Appeals', 'icon' => 'fas fa-users-cog'],
            ['name' => 'Request for System Access Extension', 'icon' => 'fas fa-plus'],
            ['name' => 'Log-In Credential Issues', 'icon' => 'fas fa-key'],
            ['name' => 'Request to Edit Submitted OPCR/DPCR/IPCR', 'icon' => 'fas fa-edit'],
            ['name' => 'Request to Submit OPCR/DPCR/IPCR (New Employees)', 'icon' => 'fas fa-save'],
            ['name' => 'Request for Technical Assistance', 'icon' => 'fas fa-wrench'],
            ['name' => 'Technical Issues/Glitch', 'icon' => 'fas fa-bug'],
            ['name' => 'Access of OPCR/DPCR/IPCR Issue', 'icon' => 'fas fa-exclamation-circle'],
        ];

        $it_issues = [
            ['name' => 'Office assignment/ Retagging', 'icon' => 'fas fa-user-tag'],
            ['name' => 'GEMS account password reset', 'icon' => 'fas fa-undo'],
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
                    'icon'              => $issue['icon'],
                ]);
            }
        }
    }
}
