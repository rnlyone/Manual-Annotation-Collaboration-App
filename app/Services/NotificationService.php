<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Persist a notification message for each provided user ID.
     */
    public function sendToUsers(Collection|array $userIds, string $message, string $type = 'info'): void
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => ! is_null($id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $timestamp = now();

        $payload = $ids->map(fn ($id) => [
            'user_id' => (int) $id,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        Notification::insert($payload->all());
    }
}
