@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Pagination\Paginator $notifications */
    $notifications = $contentdata['notifications'] ?? $notifications ?? null;
    $unreadCount = $contentdata['unreadCount'] ?? 0;
    $typeBadges = [
        'package_data_added' => ['label' => 'Data Added', 'class' => 'bg-label-primary'],
        'package_data_removed' => ['label' => 'Data Removed', 'class' => 'bg-label-warning'],
        'package_user_assigned' => ['label' => 'User Assigned', 'class' => 'bg-label-success'],
        'package_user_unassigned' => ['label' => 'User Removed', 'class' => 'bg-label-danger'],
        'annotation_requeue' => ['label' => 'Annotation Returned', 'class' => 'bg-label-info'],
    ];
@endphp

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-1">Notifications</h4>
            <p class="text-muted mb-0">Package assignment updates and user changes.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form action="{{ route('notifications.markAllRead') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary" {{ $unreadCount === 0 ? 'disabled' : '' }}>
                    Mark all as read
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 160px;">Type</th>
                        <th>Message</th>
                        <th style="width: 200px;">When</th>
                        <th style="width: 210px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($notifications?->items() ?? []) as $notification)
                        @php
                            $meta = $typeBadges[$notification->type] ?? [
                                'label' => ucwords(str_replace('_', ' ', $notification->type ?? 'info')),
                                'class' => 'bg-label-secondary',
                            ];
                        @endphp
                        <tr class="{{ $notification->is_read ? '' : 'table-active' }}">
                            <td>
                                <span class="badge {{ $meta['class'] }}">
                                    {{ $meta['label'] }}
                                </span>
                            </td>
                            <td>{{ $notification->message }}</td>
                            <td>{{ optional($notification->created_at)->toDayDateTimeString() ?? '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('notifications.update', $notification) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_read" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" {{ $notification->is_read ? 'disabled' : '' }}>
                                        Mark as read
                                    </button>
                                </form>
                                <form action="{{ route('notifications.destroy', $notification) }}" method="POST" class="d-inline ms-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-text-secondary text-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">You're all caught up.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $notifications?->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
