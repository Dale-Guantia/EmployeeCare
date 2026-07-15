{{-- This file is used to store sidebar items, inside the Backpack admin panel --}}
@if(!backpack_user()->hasRole('employee'))
    <li class="nav-item">
        <a class="nav-link" href="{{ backpack_url('dashboard') }}">
            <i class="nav-icon la la-home"></i> {{ trans('backpack::base.dashboard') }}
        </a>
    </li>
@endif

@can('ticket.view')
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('ticket') }}">
        <i class="nav-icon la la-ticket-alt"></i> Tickets
    </a>
</li>
@endcan

@can('issue.view')
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('department') }}">
        <i class="nav-icon la la-building"></i> Departments
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('division') }}">
        <i class="nav-icon la la-sitemap"></i> Divisions
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('issue') }}">
        <i class="nav-icon la la-exclamation-circle"></i> Issues
    </a>
</li>
@endcan

@can('priority.view')
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('priority') }}">
        <i class="nav-icon la la-sort-amount-up"></i> Priorities
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('status') }}">
        <i class="nav-icon la la-flag"></i> Status
    </a>
</li>
<li class="nav-item nav-dropdown">
    <a class="nav-link nav-dropdown-toggle" href="#">
        <i class="nav-icon la la-chart-bar"></i> Reports
    </a>
    <ul class="nav-dropdown-items">
        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('reports') }}">
                <i class="nav-icon la la-chart-bar"></i> Employee Care
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('survey-reports') }}">
                <i class="nav-icon la la-chart-bar"></i> CSS
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('arta-survey-reports') }}">
                <i class="nav-icon la la-chart-bar"></i> ARTA
            </a>
        </li>
    </ul>
</li>
@endcan

@can('user.view')
<li class="nav-item nav-dropdown">
    <a class="nav-link nav-dropdown-toggle" href="#">
        <i class="nav-icon la la-user-lock"></i> Authentication
    </a>
    <ul class="nav-dropdown-items">
        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('user') }}">
                <i class="nav-icon la la-users"></i> Users
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('role') }}">
                <i class="nav-icon la la-id-badge"></i> Roles
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ backpack_url('permission') }}">
                <i class="nav-icon la la-key"></i> Permissions
            </a>
        </li>
    </ul>
</li>
@endcan

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('notifications') }}">
        <i class="nav-icon la la-bell"></i> Notifications
    </a>
</li>

{{-- HR Policy Documents — only visible to admin, dept_head, div_head --}}
@if(backpack_user()->hasAnyRole(['admin', 'dept_head', 'div_head']))
<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('hr-policy-documents') }}">
        <i class="nav-icon la la-file-pdf"></i> Policy Documents
    </a>
</li>
@endif

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('hr-assistant') }}">
        <i class="nav-icon la la-robot"></i> HR Assistant
    </a>
</li>


