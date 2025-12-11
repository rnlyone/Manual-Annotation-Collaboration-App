<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('annotations.index') }}">Annotations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Session Logs</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Annotation Session Logs</h5>
                        <small class="text-muted">Review and edit your recent annotation sessions</small>
                    </div>
                    <a href="{{ route('annotations.index') }}" class="btn btn-outline-secondary">Back to Annotations</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Package</th>
                                    <th>Started</th>
                                    <th>Ended</th>
                                    <th>Annotations</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sessionLogs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ $log->package->name ?? '—' }}</td>
                                        <td>{{ optional($log->created_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $log->ended_at ? $log->ended_at->format('d M Y H:i') : 'Active' }}</td>
                                        <td>{{ count($log->annotation_datas ?? []) }}</td>
                                        <td>
                                            @if ($log->ended_at)
                                                <span class="badge bg-label-secondary">Closed</span>
                                            @else
                                                <span class="badge bg-label-info">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('session-logs.show', $log) }}" class="btn btn-sm btn-primary">Review</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No session logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $sessionLogs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
