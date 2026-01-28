<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkingReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
            'grouping' => ['nullable', 'in:user,package'],
        ]);

        $filters = $this->resolveFilters($validated);

        $annotators = User::query()
            ->select(['id', 'name', 'username'])
            ->where('role', 'annotator')
            ->orderBy('name')
            ->get();

        $packages = Package::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $payload = $this->buildReportPayload($filters);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        $headscript = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin="anonymous"></script>';

        return view('_app.app', [
            'content' => 'report.working',
            'headerdata' => [
                'pagetitle' => 'Working Session Report',
                'headscript' => $headscript,
            ],
            'sidenavdata' => ['active' => 'reports.working'],
            'contentdata' => [
                'filters' => [
                    'start' => $filters['start_date']->toDateString(),
                    'end' => $filters['end_date']->toDateString(),
                    'user_ids' => $filters['user_ids'],
                    'package_ids' => $filters['package_ids'],
                    'grouping' => $filters['grouping'],
                ],
                'annotators' => $annotators,
                'packages' => $packages,
                'initialPayload' => $payload,
                'endpoint' => route('reports.working'),
            ],
        ]);
    }

    protected function resolveFilters(array $validated): array
    {
        $end = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $start = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : (clone $end)->subDays(13)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
            'user_ids' => collect($validated['user_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'package_ids' => collect($validated['package_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'grouping' => ($validated['grouping'] ?? 'user') === 'package' ? 'package' : 'user',
        ];
    }

    protected function buildReportPayload(array $filters): array
    {
        $sessions = SessionLog::query()
            ->with(['user:id,name,username', 'package:id,name'])
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['user_ids'], fn ($query) => $query->whereIn('user_id', $filters['user_ids']))
            ->when($filters['package_ids'], fn ($query) => $query->whereIn('package_id', $filters['package_ids']))
            ->orderBy('created_at')
            ->get();

        $totalSessions = $sessions->count();
        $durations = $sessions->map(fn (SessionLog $log) => $this->durationMinutes($log));
        $annotationTotals = $sessions->map(fn (SessionLog $log) => $this->annotationCount($log));

        $perUser = $this->buildPerUserStats($sessions);
        $perPackage = $this->buildPerPackageStats($sessions);
        $timeline = $this->buildTimelineSeries($sessions);
        $hourly = $this->buildHourlyDistribution($sessions);
        $recentSessions = $this->buildRecentSessions($sessions);
        $habitInsights = $this->buildHabitInsights($perUser);

        $summary = [
            'range_days' => $filters['start_date']->diffInDays($filters['end_date']) + 1,
            'range_label' => sprintf('%s to %s', $filters['start_date']->toFormattedDateString(), $filters['end_date']->toFormattedDateString()),
            'total_sessions' => $totalSessions,
            'total_annotations' => $annotationTotals->sum(),
            'active_annotators' => $sessions->pluck('user_id')->unique()->count(),
            'avg_session_duration' => round($durations->avg() ?? 0, 1),
            'avg_annotations_per_session' => $totalSessions ? round($annotationTotals->sum() / $totalSessions, 1) : 0,
            'peak_hour' => $hourly['peak_hour'],
            'insights' => $habitInsights,
        ];

        return [
            'summary' => $summary,
            'per_user' => $perUser,
            'per_package' => $perPackage,
            'timeline' => $timeline,
            'hourly' => $hourly,
            'recent_sessions' => $recentSessions,
        ];
    }

    protected function buildPerUserStats(Collection $sessions): array
    {
        return $sessions
            ->groupBy('user_id')
            ->map(function (Collection $records) {
                $durations = $records->map(fn (SessionLog $log) => $this->durationMinutes($log));
                $annotationCounts = $records->map(fn (SessionLog $log) => $this->annotationCount($log));
                $startHours = $records->map(fn (SessionLog $log) => optional($log->created_at)->hour ?? 0);
                $hourCount = $startHours->countBy()->mapWithKeys(fn ($count, $hour) => [(int) $hour => (int) $count])->all();
                $histogram = $this->normalizeHourHistogram($hourCount);
                $peakHour = $this->extractPeakHour($histogram);

                $user = $records->first()->user;

                return [
                    'user_id' => $user?->id,
                    'name' => $user?->name ?? 'Unknown',
                    'username' => $user?->username ?? '—',
                    'total_sessions' => $records->count(),
                    'total_annotations' => $annotationCounts->sum(),
                    'avg_annotations' => round($annotationCounts->avg() ?? 0, 1),
                    'median_annotations' => $this->median($annotationCounts),
                    'avg_duration_minutes' => round($durations->avg() ?? 0, 1),
                    'median_duration_minutes' => $this->median($durations),
                    'avg_start_hour' => round($startHours->avg() ?? 0, 1),
                    'peak_start_hour' => $peakHour,
                    'hour_histogram' => $histogram,
                ];
            })
            ->sortByDesc('total_sessions')
            ->values()
            ->all();
    }

    protected function buildPerPackageStats(Collection $sessions): array
    {
        return $sessions
            ->groupBy('package_id')
            ->map(function (Collection $records) {
                $durations = $records->map(fn (SessionLog $log) => $this->durationMinutes($log));
                $annotationCounts = $records->map(fn (SessionLog $log) => $this->annotationCount($log));
                $package = $records->first()->package;

                return [
                    'package_id' => $package?->id,
                    'name' => $package?->name ?? 'Unassigned',
                    'total_sessions' => $records->count(),
                    'total_annotations' => $annotationCounts->sum(),
                    'avg_duration_minutes' => round($durations->avg() ?? 0, 1),
                    'unique_annotators' => $records->pluck('user_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_sessions')
            ->values()
            ->all();
    }

    protected function buildTimelineSeries(Collection $sessions): array
    {
        $grouped = $sessions
            ->groupBy(fn (SessionLog $log) => optional($log->created_at)->format('Y-m-d') ?? 'Unknown')
            ->sortKeys();

        $labels = [];
        $sessionCounts = [];
        $annotationCounts = [];
        $uniqueUserCounts = [];

        foreach ($grouped as $date => $records) {
            $labels[] = $date;
            $sessionCounts[] = $records->count();
            $annotationCounts[] = $records->sum(fn (SessionLog $log) => $this->annotationCount($log));
            $uniqueUserCounts[] = $records->pluck('user_id')->unique()->count();
        }

        return [
            'labels' => $labels,
            'session_counts' => $sessionCounts,
            'annotation_totals' => $annotationCounts,
            'unique_users' => $uniqueUserCounts,
        ];
    }

    protected function buildHourlyDistribution(Collection $sessions): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($sessions as $log) {
            $hour = optional($log->created_at)->hour;
            if ($hour === null) {
                continue;
            }
            $hours[$hour]++;
        }

        $peakHour = $this->extractPeakHour($hours);

        return [
            'labels' => array_map(fn ($hour) => sprintf('%02d:00', $hour), range(0, 23)),
            'counts' => array_values($hours),
            'peak_hour' => $peakHour,
        ];
    }

    protected function buildRecentSessions(Collection $sessions): array
    {
        return $sessions
            ->sortByDesc('created_at')
            ->take(12)
            ->map(function (SessionLog $log) {
                $duration = $this->durationMinutes($log);

                return [
                    'id' => $log->id,
                    'user' => $log->user?->name ?? 'Unknown',
                    'package' => $log->package?->name ?? 'Unassigned',
                    'annotations' => $this->annotationCount($log),
                    'started_at' => optional($log->created_at)?->toIso8601String(),
                    'ended_at' => optional($log->ended_at)?->toIso8601String(),
                    'duration_minutes' => $duration,
                ];
            })
            ->values()
            ->all();
    }

    protected function buildHabitInsights(array $perUser): array
    {
        if (empty($perUser)) {
            return [];
        }

        $collection = collect($perUser);

        $earlyBird = $collection->sortBy('peak_start_hour')->first();
        $nightOwl = $collection->sortByDesc('peak_start_hour')->first();
        $longRunner = $collection->sortByDesc('avg_duration_minutes')->first();

        $formatHour = fn ($hour) => $hour === null ? '—' : Carbon::createFromTime($hour)->format('g A');

        return array_values(array_filter([
            $earlyBird ? [
                'label' => 'Earliest starter',
                'detail' => sprintf('%s usually logs in around %s', $earlyBird['name'], $formatHour($earlyBird['peak_start_hour'])),
            ] : null,
            $nightOwl ? [
                'label' => 'Night owl',
                'detail' => sprintf('%s peaks near %s', $nightOwl['name'], $formatHour($nightOwl['peak_start_hour'])),
            ] : null,
            $longRunner ? [
                'label' => 'Longest focus stretches',
                'detail' => sprintf('%s averages %s min per session', $longRunner['name'], number_format($longRunner['avg_duration_minutes'], 1)),
            ] : null,
        ]));
    }

    protected function durationMinutes(SessionLog $log): int
    {
        $start = $log->created_at instanceof Carbon ? $log->created_at : Carbon::parse($log->created_at);
        $end = $log->ended_at instanceof Carbon ? $log->ended_at : ($log->ended_at ? Carbon::parse($log->ended_at) : null);

        if (! $end) {
            $end = $log->updated_at instanceof Carbon ? $log->updated_at : Carbon::parse($log->updated_at ?? $log->created_at);
        }

        if (! $start || ! $end) {
            return 0;
        }

        return max(1, $end->diffInMinutes($start));
    }

    protected function annotationCount(SessionLog $log): int
    {
        $data = $log->annotation_datas;

        if (is_array($data)) {
            return count($data);
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? count($decoded) : 0;
        }

        return 0;
    }

    protected function normalizeHourHistogram(array $histogram): array
    {
        $normalized = array_fill(0, 24, 0);

        foreach ($histogram as $hour => $count) {
            if (! is_numeric($hour)) {
                continue;
            }

            $index = (int) $hour;

            if ($index < 0 || $index > 23) {
                continue;
            }

            $normalized[$index] = (int) $count;
        }

        return $normalized;
    }

    protected function extractPeakHour(array $histogram): ?int
    {
        if (empty($histogram)) {
            return null;
        }

        $peakCount = max($histogram);
        $index = array_search($peakCount, $histogram, true);

        return $index === false ? null : (int) $index;
    }

    protected function median(Collection $values): float
    {
        $filtered = $values
            ->filter(fn ($value) => $value !== null)
            ->sort()
            ->values();

        $count = $filtered->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $filtered->get($middle, 0);
        }

        $lower = (float) $filtered->get($middle - 1, 0);
        $upper = (float) $filtered->get($middle, 0);

        return round(($lower + $upper) / 2, 1);
    }
}
