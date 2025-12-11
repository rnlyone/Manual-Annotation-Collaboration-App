<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('_app.topnav', function ($view) {
            if (! Auth::check()) {
                $view->with('topnavNotifications', collect());
                $view->with('topnavUnreadCount', 0);

                return;
            }

            $userId = Auth::id();

            $notifications = Notification::query()
                ->where('user_id', $userId)
                ->latest()
                ->limit(10)
                ->get();

            $unreadCount = Notification::query()
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count();

            $view->with('topnavNotifications', $notifications);
            $view->with('topnavUnreadCount', $unreadCount);
        });
    }
}
