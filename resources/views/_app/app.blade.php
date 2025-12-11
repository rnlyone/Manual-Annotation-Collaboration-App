<!DOCTYPE html>
<html lang="en" class="light-style layout-navbar-fixed layout-menu-fixed layout-compact " dir="ltr"
    data-theme="theme-default" data-assets-path="/assets/" data-template="vertical-menu-template" data-style="light">


@include('_app.header', ['headerdata' => $headerdata ?? null])

<body>


    <!-- ?PROD Only: Google Tag Manager (noscript) (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5J3LMKC" height="0" width="0"
            style="display: none; visibility: hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar  ">
        <div class="layout-container">

            @include('_app.sidenav', ['sidenavdata' => $sidenavdata ?? null])

            <!-- Layout container -->
            <div class="layout-page">
                @include('_app.topnav', ['topnavdata' => $topnavdata ?? null])
                <!-- Content wrapper -->
                <div class="content-wrapper">

                    {{-- Content --}}
                    @include($content, ['contentdata' => $contentdata ?? null])

                    @include('_app.footer', ['footerdata' => $footerdata ?? null])

</body>

</html>

