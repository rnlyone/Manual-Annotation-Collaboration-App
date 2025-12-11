<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Category;
use App\Models\SessionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const ALLOWED_RANGES = [7, 30, 90, 180];

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $range = (int) $request->input('range', 30);
        $rangeDays = in_array($range, self::ALLOWED_RANGES, true) ? $range : 30;
        $rangeStart = Carbon::now()->subDays($rangeDays);

        $annotationTrend = Annotation::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $trendChart = [
            'labels' => $annotationTrend->keys()->values()->all(),
            'series' => $annotationTrend->values()->all(),
        ];

        $topContributors = Annotation::query()
            ->select('users.id', 'users.name', DB::raw('COUNT(annotations.id) as total'))
            ->join('users', 'users.id', '=', 'annotations.user_id')
            ->where('annotations.created_at', '>=', $rangeStart)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $contributorsChart = [
            'labels' => $topContributors->pluck('name')->all(),
            'series' => $topContributors->pluck('total')->all(),
        ];

        $categoryNames = Category::pluck('name', 'id');
        $categoryCounts = [];

        Annotation::query()
            ->where('created_at', '>=', $rangeStart)
            ->select(['id', 'category_ids'])
            ->chunkById(500, function ($annotations) use (&$categoryCounts, $categoryNames) {
                foreach ($annotations as $annotation) {
                    $categoryIds = is_array($annotation->category_ids) ? $annotation->category_ids : [];
                    foreach ($categoryIds as $categoryId) {
                        $name = $categoryNames[$categoryId] ?? 'Unmapped';
                        $categoryCounts[$name] = ($categoryCounts[$name] ?? 0) + 1;
                    }
                }
            });

        $categoryChart = collect($categoryCounts)
            ->sortDesc()
            ->take(8);

        $recentSessions = SessionLog::with(['user:id,name', 'package:id,name'])
            ->where('created_at', '>=', $rangeStart)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'user_id', 'package_id', 'created_at', 'ended_at']);

        $rangeAnnotationCount = Annotation::where('created_at', '>=', $rangeStart)->count();
        $myAnnotationCount = Annotation::where('user_id', $user->id)
            ->where('created_at', '>=', $rangeStart)
            ->count();

        $personalStats = [
            'my_annotations' => $myAnnotationCount,
            'avg_per_day' => $rangeDays > 0 ? round($myAnnotationCount / $rangeDays, 1) : 0,
            'open_sessions' => SessionLog::where('user_id', $user->id)
                ->whereNull('ended_at')
                ->count(),
            'last_annotation' => optional(
                Annotation::where('user_id', $user->id)
                    ->latest('created_at')
                    ->first()
            )->created_at,
        ];

        $activeAnnotators = Annotation::where('created_at', '>=', $rangeStart)
            ->distinct('user_id')
            ->count('user_id');

        $activePackages = DB::table('annotations')
            ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
            ->where('annotations.created_at', '>=', $rangeStart)
            ->distinct('package_data.package_id')
            ->count('package_data.package_id');

        $globalStats = [
            'total_annotations' => Annotation::count(),
            'range_annotations' => $rangeAnnotationCount,
            'active_annotators' => $activeAnnotators,
            'active_packages' => $activePackages,
            'annotations_per_package' => $activePackages > 0 ? round($rangeAnnotationCount / $activePackages, 1) : 0,
            'open_sessions' => SessionLog::whereNull('ended_at')->count(),
        ];

        $suggestions = [
            'personal' => [
                'Surface a streak meter to show how many consecutive days the annotator contributed.',
                'Highlight the most common categories they personally tagged to guide calibration.',
                'Show throughput vs. team average to help users self-calibrate workload.',
            ],
            'admin' => [
                'Monitor idle packages (assigned but untouched for N days) to rebalance workloads.',
                'Display category-level imbalance to quickly identify under-reviewed labels.',
                'Track validation-to-annotation ratio to plan QA bandwidth.',
            ],
        ];

        return view('_app.app', [
            'content' => 'dashboard.index',
            'headerdata' => ['pagetitle' => 'Dashboard Insights'],
            'sidenavdata' => ['active' => 'dashboard'],
            'dashboardData' => [
                'rangeDays' => $rangeDays,
                'rangeStartLabel' => $rangeStart->format('M d, Y'),
                'trendChart' => $trendChart,
                'contributorsChart' => $contributorsChart,
                'categoryChart' => [
                    'labels' => $categoryChart->keys()->values()->all(),
                    'series' => $categoryChart->values()->all(),
                ],
                'recentSessions' => $recentSessions,
                'personalStats' => $personalStats,
                'globalStats' => $globalStats,
                'suggestions' => $suggestions,
                'rangeOptions' => self::ALLOWED_RANGES,
            ],
        ]);
    }
}
