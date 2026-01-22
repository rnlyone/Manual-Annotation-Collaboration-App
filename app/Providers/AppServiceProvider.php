<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Support\Carbon;
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
            $serverTime = Carbon::now(config('app.timezone'));

            if (! Auth::check()) {
                $view->with('topnavNotifications', collect());
                $view->with('topnavUnreadCount', 0);
                $view->with('topnavServerTime', $serverTime);

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
            $view->with('topnavServerTime', $serverTime);
        });
    }
}
