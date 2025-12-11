<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Notification::query()->where('user_id', $request->user()->id);

        $notifications = (clone $query)
            ->orderByDesc('created_at')
            ->paginate(15);

        $unreadCount = (clone $query)
            ->where('is_read', false)
            ->count();

        return view('_app.app', [
            'content' => 'notifications.index',
            'headerdata' => ['pagetitle' => 'Notifications'],
            'sidenavdata' => ['active' => 'notifications'],
            'contentdata' => [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        $this->authorizeNotification($request, $notification);

        $notification->update([
            'is_read' => $request->boolean('is_read', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Notification updated']);
        }

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Notification $notification)
    {
        $this->authorizeNotification($request, $notification);
        $notification->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Notification deleted']);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'updated_at' => now(),
            ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Notifications marked as read']);
        }

        return back();
    }

    private function authorizeNotification(Request $request, Notification $notification): void
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
    }
}
