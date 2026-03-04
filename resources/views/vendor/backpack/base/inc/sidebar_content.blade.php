{{-- This file is used to store sidebar items, inside the Backpack admin panel --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

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
