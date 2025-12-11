<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">


    <div class="app-brand demo ">
        <a href="index.html" class="app-brand-link">
            <span class="app-brand-logo demo">
                <svg width="32" height="22" viewBox="0 0 32 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.00172773 0V6.85398C0.00172773 6.85398 -0.133178 9.01207 1.98092 10.8388L13.6912 21.9964L19.7809 21.9181L18.8042 9.88248L16.4951 7.17289L9.23799 0H0.00172773Z"
                        fill="#7367F0" />
                    <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                        d="M7.69824 16.4364L12.5199 3.23696L16.5541 7.25596L7.69824 16.4364Z" fill="#161616" />
                    <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
                        d="M8.07751 15.9175L13.9419 4.63989L16.5849 7.28475L8.07751 15.9175Z" fill="#161616" />
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M7.77295 16.3566L23.6563 0H32V6.88383C32 6.88383 31.8262 9.17836 30.6591 10.4057L19.7824 22H13.6938L7.77295 16.3566Z"
                        fill="#7367F0" />
                </svg>
            </span>
            <span class="app-brand-text demo menu-text fw-bold">EDMI-ID</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="ti menu-toggle-icon d-none d-xl-block align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-md align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>



    <ul class="menu-inner py-1">
        <!-- Dashboards -->
        <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'dashboard' ? 'active' : ''}}">
            <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div data-i18n="Dashboards">Dashboards</div>
            </a>
        </li>

        <!-- Annotations -->
        <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'annotations' ? 'active' : ''}}">
            <a href="{{ route('annotations.index') }}" class="menu-link">
                {{-- icon pencil --}}
                <i class="menu-icon tf-icons ti ti-pencil"></i>
                <div data-i18n="Annotations">Annotations</div>
            </a>
        </li>

        <!-- Validations -->
        <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'validations' ? 'active' : ''}} disabled">
            <a href="#" class="menu-link" tabindex="-1" aria-disabled="true">
                {{-- checkbox --}}
                <i class="menu-icon tf-icons ti ti-checkbox"></i>
                <div data-i18n="Validations">Validations</div>
            </a>
        </li>

        {{-- admin only --}}
        @if (auth()->user()->role == 'admin')
        <!-- User Management -->
                <!-- Settings -->
        <li class="menu-item {{isset($sidenavdata['active']) && in_array($sidenavdata['active'], ['packages', 'categories', 'data', 'users', 'annotations.manage']) ? 'active open' : ''}}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons ti ti-settings"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'annotations.manage' ? 'active' : ''}}">
                    <a href="{{route('annotations.manage')}}" class="menu-link">
                        <div data-i18n="Annotation Management">Annotation Management</div>
                    </a>
                </li>
                <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'packages' ? 'active' : ''}}">
                    <a href="{{route('packages.index')}}" class="menu-link">
                        <div data-i18n="Package Management">Package Management</div>
                    </a>
                </li>
                <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'categories' ? 'active' : ''}}">
                    <a href="{{route('categories.index')}}" class="menu-link">
                        <div data-i18n="Category Management">Category Management</div>
                    </a>
                </li>
                <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'data' ? 'active' : ''}}">
                    <a href="{{route('data.index')}}" class="menu-link">
                        <div data-i18n="Data Management">Data Management</div>
                    </a>
                </li>
                <li class="menu-item {{isset($sidenavdata['active']) && $sidenavdata['active'] == 'users' ? 'active' : ''}}">
                    <a href="{{route('users.index')}}" class="menu-link">
                        <div data-i18n="User Management">User Management</div>
                    </a>
                </li>
            </ul>
        </li>
        @endif

    </ul>



</aside>
