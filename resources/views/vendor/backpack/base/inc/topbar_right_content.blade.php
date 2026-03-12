{{-- This file is used to store topbar (right) items --}}


{{-- <li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-bell"></i><span class="badge badge-pill badge-danger">5</span></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-list"></i></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-map"></i></a></li> --}}
<li class="nav-item dropdown">
    <a class="nav-link" data-toggle="dropdown" href="#">
        <i class="la la-bell" style="font-size: 1.5rem"></i>
        <span class="badge badge-danger navbar-badge" id="notificationCount">0</span>
    </a>

    <div class="dropdown-menu dropdown-menu-right dropdown-lg">
        <span class="dropdown-header" id="notificationHeader">0 Notifications</span>

        <div class="dropdown-divider"></div>

        <div id="notificationList"></div>

        <div class="dropdown-divider"></div>

        <a href="/employee-care/notifications" class="dropdown-item dropdown-footer">
            View all notifications
        </a>
    </div>
</li>
