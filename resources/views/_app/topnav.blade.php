<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">


    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0   d-xl-none ">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="ti ti-menu-2 ti-md"></i>
        </a>
    </div>


    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">


        @php
            $navNotifications = $topnavNotifications ?? collect();
            $navUnreadCount = $topnavUnreadCount ?? 0;
            $notificationIconMap = [
                'package_data_added' => ['icon' => 'ti ti-database-plus', 'bg' => 'bg-label-primary'],
                'package_data_removed' => ['icon' => 'ti ti-database-minus', 'bg' => 'bg-label-warning'],
                'package_user_assigned' => ['icon' => 'ti ti-user-plus', 'bg' => 'bg-label-success'],
                'package_user_unassigned' => ['icon' => 'ti ti-user-minus', 'bg' => 'bg-label-danger'],
                'annotation_requeue' => ['icon' => 'ti ti-refresh', 'bg' => 'bg-label-info'],
            ];
        @endphp

        <ul class="navbar-nav flex-row align-items-center ms-auto">

            <!-- Style Switcher -->
            <li class="nav-item dropdown-style-switcher dropdown">
                <a class="nav-link btn btn-text-secondary btn-icon rounded-pill dropdown-toggle hide-arrow"
                    href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class='ti ti-md'></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                            <span class="align-middle"><i class='ti ti-sun ti-md me-3'></i>Light</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                            <span class="align-middle"><i class="ti ti-moon-stars ti-md me-3"></i>Dark</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                            <span class="align-middle"><i
                                    class="ti ti-device-desktop-analytics ti-md me-3"></i>System</span>
                        </a>
                    </li>
                </ul>
            </li>
            <!-- / Style Switcher-->

            <!-- Notification -->
            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
                <a class="nav-link btn btn-text-secondary btn-icon rounded-pill dropdown-toggle hide-arrow"
                    href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                    aria-expanded="false">
                    <span class="position-relative">
                        <i class="ti ti-bell ti-md"></i>
                        <span class="badge rounded-pill bg-danger badge-dot badge-notifications border {{ $navUnreadCount ? '' : 'd-none' }}"></span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0">
                    <li class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h6 class="mb-0 me-auto">Notifications</h6>
                            @if ($navUnreadCount > 0)
                                <div class="d-flex align-items-center h6 mb-0">
                                    <span class="badge bg-label-primary me-2">{{ $navUnreadCount }} New</span>
                                    <form action="{{ route('notifications.markAllRead') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-text-secondary rounded-pill btn-icon dropdown-notifications-all"
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read">
                                            <i class="ti ti-mail-opened text-heading"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </li>
                    <li class="dropdown-notifications-list scrollable-container">
                        @if ($navNotifications->isEmpty())
                            <div class="p-4 text-center text-muted">You're all caught up.</div>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($navNotifications as $notification)
                                    @php
                                        $iconMeta = $notificationIconMap[$notification->type] ?? ['icon' => 'ti ti-bell', 'bg' => 'bg-label-secondary'];
                                    @endphp
                                    <li class="list-group-item list-group-item-action dropdown-notifications-item {{ $notification->is_read ? 'marked-as-read' : '' }}">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar">
                                                    <span class="avatar-initial rounded-circle {{ $iconMeta['bg'] }}">
                                                        <i class="{{ $iconMeta['icon'] }}"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="small mb-1">{{ $notification->message }}</h6>
                                                <small class="text-muted">{{ optional($notification->created_at)->diffForHumans() ?? 'just now' }}</small>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                    <li class="border-top">
                        <div class="d-grid p-4">
                            <a class="btn btn-primary btn-sm d-flex" href="{{ route('notifications.index') }}">
                                <small class="align-middle">View all notifications</small>
                            </a>
                        </div>
                    </li>
                </ul>
            </li>
            <!--/ Notification -->

            <!-- User -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <img src="../../assets/img/avatars/14.jpg" alt class="rounded-circle">
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item mt-0" href="pages-account-settings-account.html">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-2">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/avatars/14.jpg" alt class="rounded-circle">
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ Auth::user()->name }}</h6>
                                    <small class="text-muted">{{ Auth::user()->email }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1 mx-n2"></div>
                    </li>
                    <li>
                        <div class="d-grid px-2 pt-2 pb-1">
                            <a class="btn btn-sm btn-danger d-flex" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <small class="align-middle">Logout</small>
                                <i class="ti ti-logout ms-2 ti-14px"></i>
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </li>
                </ul>
            </li>
            <!--/ User -->



        </ul>
    </div>


    <!-- Search Small Screens -->
    <div class="navbar-search-wrapper search-input-wrapper  d-none">
        <input type="text" class="form-control search-input container-xxl border-0" placeholder="Search..."
            aria-label="Search...">
        <i class="ti ti-x search-toggler cursor-pointer"></i>
    </div>



</nav>
