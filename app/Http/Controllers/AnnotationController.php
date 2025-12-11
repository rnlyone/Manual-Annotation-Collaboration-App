<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackageData;
use App\Models\SessionLog;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnnotationController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        $assignedPackages = collect();

        if ($user) {
            $assignedPackageIds = UserPackage::where('user_id', $user->id)->pluck('package_id');

            if ($assignedPackageIds->isNotEmpty()) {
                $dataCounts = PackageData::select('package_id', DB::raw('COUNT(*) as total'))
                    ->whereIn('package_id', $assignedPackageIds)
                    ->groupBy('package_id')
                    ->pluck('total', 'package_id');

                $userAnnotationCounts = Annotation::query()
                    ->select('package_data.package_id', DB::raw('COUNT(annotations.id) as total'))
                    ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
                    ->where('annotations.user_id', $user->id)
                    ->whereIn('package_data.package_id', $assignedPackageIds)
                    ->groupBy('package_data.package_id')
                    ->pluck('total', 'package_data.package_id');

                $overallAnnotationCounts = Annotation::query()
                    ->select('package_data.package_id', DB::raw('COUNT(annotations.id) as total'))
                    ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
                    ->whereIn('package_data.package_id', $assignedPackageIds)
                    ->groupBy('package_data.package_id')
                    ->pluck('total', 'package_data.package_id');

                $assignedPackages = Package::whereIn('id', $assignedPackageIds)
                    ->orderBy('name')
                    ->get()
                    ->map(function (Package $package) use ($dataCounts, $userAnnotationCounts, $overallAnnotationCounts) {
                        $total = (int) ($dataCounts[$package->id] ?? 0);
                        $userAnnotated = (int) ($userAnnotationCounts[$package->id] ?? 0);
                        $overallAnnotated = (int) ($overallAnnotationCounts[$package->id] ?? 0);
                        $overallProgress = $total > 0 ? round(($overallAnnotated / $total) * 100, 1) : 0;
                        $remaining = max($total - $overallAnnotated, 0);

                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'data_total' => $total,
                            'user_annotated' => $userAnnotated,
                            'overall_annotated' => $overallAnnotated,
                            'remaining' => $remaining,
                            'overall_progress' => $overallProgress,
                        ];
                    });
            }
        }

        return view('_app.app', [
            'content' => 'annotation.index',
            'headerdata' => ['pagetitle' => 'Annotation'],
            'sidenavdata' => ['active' => 'annotations'],
            'assignedPackages' => $assignedPackages,
        ]);
    }

    /**
     * Show the annotation workbench for a given package.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $packageId = $request->integer('package_id');
        $package = $packageId ? Package::findOrFail($packageId) : null;

        if (! $package) {
            $firstAssignment = UserPackage::where('user_id', $user->id)->first();
            if ($firstAssignment) {
                $package = Package::find($firstAssignment->package_id);
            }
        }

        abort_if(! $package, 404, 'Package not found or not assigned to you.');
        $this->ensurePackageAccess($package, $user->id);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $initialItem = $this->getInitialWorkItem($package, $user->id);
        $sessionLog = $this->startAnnotationSession($package, $user->id);

        return view('_app.app', [
            'content' => 'annotation.annotate',
            'headerdata' => ['pagetitle' => 'Annotate'],
            'sidenavdata' => ['active' => 'annotations'],
            'workbenchPackage' => $package,
            'categories' => $categories,
            'initialWorkItem' => $initialItem,
            'sessionLog' => $sessionLog,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Annotation $annotation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Annotation $annotation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Annotation $annotation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Annotation $annotation)
    {
        //
    }

    public function manage()
    {
        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css">
            <link rel="stylesheet" href="/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css">
            <link rel="stylesheet" href="/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css">
            <link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';

        $headscript = '<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
            <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
            <script src="/assets/vendor/libs/datatables/jquery.dataTables.js"></script>
            <script src="/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
            <script src="/assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
            <script src="/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>
            <script src="/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

        $packages = Package::orderBy('name')->get(['id', 'name']);
        $users = User::orderBy('name')->get(['id', 'name']);

        $packageTotals = PackageData::select('package_id', DB::raw('COUNT(*) as total_items'))
            ->groupBy('package_id')
            ->pluck('total_items', 'package_id');

        $packageAnnotatedCounts = Annotation::select('package_data.package_id', DB::raw('COUNT(DISTINCT annotations.data_id) as annotated_items'))
            ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
            ->groupBy('package_data.package_id')
            ->pluck('annotated_items', 'package_data.package_id');

        $packageAnnotators = UserPackage::query()
            ->select(['user_packages.package_id', 'users.name'])
            ->join('users', 'users.id', '=', 'user_packages.user_id')
            ->orderBy('users.name')
            ->get()
            ->groupBy('package_id')
            ->map(function ($group) {
                return $group->pluck('name')->unique()->values();
            });

        $packageSummaries = $packages->map(function (Package $package) use ($packageTotals, $packageAnnotatedCounts, $packageAnnotators) {
            $total = (int) ($packageTotals[$package->id] ?? 0);
            $annotated = (int) ($packageAnnotatedCounts[$package->id] ?? 0);
            if ($total > 0) {
                $annotated = min($annotated, $total);
            }
            $remaining = max($total - $annotated, 0);
            $progress = $total > 0 ? round(($annotated / $total) * 100, 1) : 0;

            return [
                'id' => $package->id,
                'name' => $package->name,
                'total_items' => $total,
                'annotated_items' => $annotated,
                'remaining' => $remaining,
                'progress_percent' => $progress,
                'annotators' => $packageAnnotators->get($package->id, collect())->all(),
            ];
        });

        $sessions = SessionLog::with(['user:id,name', 'package:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (SessionLog $session) {
                return [
                    'id' => $session->id,
                    'label' => sprintf(
                        '#%d · %s · %s · %s',
                        $session->id,
                        $session->package?->name ?? 'Unknown Package',
                        $session->user?->name ?? 'Unknown User',
                        $session->created_at?->format('Y-m-d H:i') ?? 'N/A'
                    ),
                ];
            });

        return view('_app.app', [
            'content' => 'annotation.management.manage',
            'headerdata' => ['pagetitle' => 'Annotation Management', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'annotations.manage'],
            'contentdata' => [
                'packages' => $packages,
                'sessions' => $sessions,
                'users' => $users,
                'packageSummaries' => $packageSummaries,
            ],
        ]);
    }

    public function managementTable(Request $request): JsonResponse
    {
        $columns = [
            0 => 'annotations.id',
            1 => 'annotations.data_id',
            2 => 'packages.name',
            3 => 'users.name',
            4 => 'annotations.updated_at',
        ];

        $scope = $request->input('scope', 'all');
        $packageId = $request->integer('package_id');
        $sessionId = $request->integer('session_id');
        $userId = $request->integer('user_id');

        $baseQuery = $this->buildManagementQuery();

        $session = null;
        if ($scope === 'package' && $packageId) {
            $baseQuery->where('package_data.package_id', $packageId);
        }

        if ($scope === 'session') {
            $session = SessionLog::with('package')->find($sessionId);
            if (! $session) {
                return response()->json([
                    'draw' => (int) $request->input('draw'),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                ]);
            }

            $annotationIds = collect($session->annotation_datas ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($annotationIds->isEmpty()) {
                return response()->json([
                    'draw' => (int) $request->input('draw'),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                ]);
            }

            $baseQuery->whereIn('annotations.id', $annotationIds);
        }

        if ($userId) {
            $baseQuery->where('annotations.user_id', $userId);
        }

        $countQuery = (clone $baseQuery)->cloneWithout(['orders'])->cloneWithoutBindings(['order']);
        $recordsTotal = $countQuery->count('annotations.id');

        $filteredQuery = clone $baseQuery;
        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $filteredQuery->where(function ($query) use ($searchValue) {
                $query->where('annotations.data_id', 'like', "%{$searchValue}%")
                    ->orWhere('packages.name', 'like', "%{$searchValue}%")
                    ->orWhere('users.name', 'like', "%{$searchValue}%")
                    ->orWhere('data.content', 'like', "%{$searchValue}%");
            });
        }

        $filteredCountQuery = (clone $filteredQuery)->cloneWithout(['orders'])->cloneWithoutBindings(['order']);
        $recordsFiltered = $filteredCountQuery->count('annotations.id');

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0) {
            $length = 25;
        }
        $length = min($length, 500);

        $orderColumnIndex = (int) $request->input('order.0.column', 4);
        $orderColumn = $columns[$orderColumnIndex] ?? 'annotations.updated_at';
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $rows = $filteredQuery
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $annotationIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $sessionLabels = $this->sessionLabelsForAnnotations($annotationIds);

        $categoryIdsInResult = $rows->pluck('category_ids')
            ->flatMap(fn ($ids) => $this->normalizeCategoryIds($ids))
            ->map(function ($id) {
                return is_numeric($id) ? (int) $id : null;
            })
            ->filter(fn ($id) => $id !== null)
            ->unique();

        $categoryLookup = $categoryIdsInResult->isNotEmpty()
            ? Category::whereIn('id', $categoryIdsInResult)->pluck('name', 'id')->map(fn ($name) => (string) $name)->all()
            : [];

        $data = $rows->map(function ($row) use ($sessionLabels, $categoryLookup) {
            $content = $row->data_content ?? '';
            $categories = collect($this->normalizeCategoryIds($row->category_ids ?? []))
                ->map(function ($id) {
                    return is_numeric($id) ? (int) $id : null;
                })
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => $categoryLookup[$id] ?? "Category {$id}")
                ->values()
                ->all();

            return [
                'id' => (int) $row->id,
                'package' => $row->package_name ?? '—',
                'package_id' => $row->package_id,
                'annotator' => $row->user_name ?? '—',
                'annotator_id' => $row->user_id,
                'annotated_at' => optional($row->annotated_at)->format('Y-m-d H:i:s') ?? '—',
                'annotated_at_human' => optional($row->annotated_at)->diffForHumans() ?? '—',
                'session' => $sessionLabels[$row->id] ?? null,
                'categories' => $categories,
                'content' => $content,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function noCategoryAnnotationIds(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'all');
        $packageId = $request->integer('package_id');
        $sessionId = $request->integer('session_id');
        $userId = $request->integer('user_id');

        $query = $this->buildManagementQuery();

        if ($scope === 'package' && $packageId) {
            $query->where('package_data.package_id', $packageId);
        }

        if ($scope === 'session') {
            $session = SessionLog::find($sessionId);
            if (! $session) {
                return response()->json(['count' => 0, 'ids' => []]);
            }

            $sessionAnnotationIds = collect($session->annotation_datas ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($sessionAnnotationIds->isEmpty()) {
                return response()->json(['count' => 0, 'ids' => []]);
            }

            $query->whereIn('annotations.id', $sessionAnnotationIds);
        }

        if ($userId) {
            $query->where('annotations.user_id', $userId);
        }

        $rows = $query->get(['annotations.id', 'annotations.category_ids']);

        $ids = $rows
            ->filter(function ($row) {
                $resolved = collect($this->normalizeCategoryIds($row->category_ids ?? []))
                    ->map(function ($id) {
                        if (is_numeric($id)) {
                            return (int) $id;
                        }
                        return null;
                    })
                    ->filter(fn ($id) => $id !== null);

                return $resolved->isEmpty();
            })
            ->map(fn ($row) => (int) $row->id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        return response()->json([
            'count' => $ids->count(),
            'ids' => $ids,
        ]);
    }

    public function sessionMap(Request $request, Package $package): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensurePackageAccess($package, $user->id);

        $sessionLogId = session(SessionLogController::SESSION_KEY);
        if (! $sessionLogId) {
            return response()->json(['items' => []]);
        }

        $sessionLog = SessionLog::where('id', $sessionLogId)
            ->where('user_id', $user->id)
            ->where('package_id', $package->id)
            ->first();

        if (! $sessionLog) {
            return response()->json(['items' => []]);
        }

        $annotationIds = collect($sessionLog->annotation_datas ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($annotationIds->isEmpty()) {
            return response()->json(['items' => []]);
        }

        $annotations = Annotation::query()
            ->select([
                'annotations.id',
                'annotations.data_id',
                'annotations.category_ids',
                'annotations.updated_at',
                'data.content',
            ])
            ->leftJoin('data', 'data.id', '=', 'annotations.data_id')
            ->whereIn('annotations.id', $annotationIds)
            ->get()
            ->keyBy('id');

        $items = $annotationIds
            ->map(function ($annotationId, $index) use ($annotations) {
                $annotation = $annotations->get($annotationId);
                if (! $annotation) {
                    return null;
                }

                return [
                    'number' => $index + 1,
                    'annotation_id' => (int) $annotation->id,
                    'data_id' => $annotation->data_id,
                    'content' => $annotation->content,
                    'category_ids' => array_map('strval', $this->normalizeCategoryIds($annotation->category_ids ?? [])),
                    'updated_at' => optional($annotation->updated_at)->toIso8601String(),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['items' => $items]);
    }

    public function requeue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'annotation_ids' => 'required|array|min:1',
            'annotation_ids.*' => 'integer|exists:annotations,id',
        ]);

        $annotationIds = collect($validated['annotation_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($annotationIds->isEmpty()) {
            return response()->json([
                'message' => 'No annotations selected.',
            ], 422);
        }

        $annotationDetails = Annotation::query()
            ->select([
                'annotations.id',
                'annotations.user_id',
                'annotations.data_id',
                'package_data.package_id',
                'packages.name as package_name',
            ])
            ->leftJoin('package_data', 'package_data.data_id', '=', 'annotations.data_id')
            ->leftJoin('packages', 'packages.id', '=', 'package_data.package_id')
            ->whereIn('annotations.id', $annotationIds)
            ->get();

        if ($annotationDetails->isEmpty()) {
            return response()->json([
                'message' => 'No annotations found for the provided selection.',
            ], 404);
        }

        DB::transaction(function () use ($annotationIds) {
            Annotation::whereIn('id', $annotationIds)->delete();
        });

        $annotationDetails
            ->filter(fn ($row) => $row->user_id)
            ->groupBy(function ($row) {
                $packageKey = $row->package_id ?? 'unknown';
                return $row->user_id.'|'.$packageKey;
            })
            ->each(function ($group) {
                $first = $group->first();
                $userId = (int) $first->user_id;
                $packageName = $first->package_name ?? 'a package';
                $count = $group->count();

                $message = $count === 1
                    ? "1 annotation in {$packageName} was returned for re-annotation."
                    : "{$count} annotations in {$packageName} were returned for re-annotation.";

                $this->notificationService->sendToUsers([$userId], $message, 'annotation_requeue');
            });

        return response()->json([
            'message' => 'Annotations returned for re-annotation.',
            'removed_count' => $annotationIds->count(),
        ]);
    }

    public function workItem(Request $request, Package $package): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensurePackageAccess($package, $user->id);

        $direction = $request->input('direction', 'next');
        $cursor = $request->input('cursor');
        $cursorId = $cursor ? (int) $cursor : null;

        $item = $this->resolveWorkItem($package, $user->id, $cursorId, $direction);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function saveSelection(Request $request, Package $package): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->ensurePackageAccess($package, $user->id);

        $validated = $request->validate([
            'data_id' => 'required|string|exists:data,id',
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        $categoryIds = collect($validated['category_ids'] ?? [])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $annotation = Annotation::firstOrNew([
            'data_id' => $validated['data_id'],
            'user_id' => $user->id,
        ]);

        $annotation->category_ids = $categoryIds;
        $annotation->select_start = $annotation->select_start ?? 0;
        $annotation->select_end = $annotation->select_end ?? 0;
        $annotation->save();

        $this->recordAnnotationInSession($annotation->id, $user->id);

        return response()->json([
            'message' => 'Annotation saved successfully.',
            'annotation_id' => $annotation->id,
            'category_ids' => $annotation->category_ids ?? [],
        ]);
    }

    private function buildManagementQuery()
    {
        return Annotation::query()
            ->select([
                'annotations.id',
                'annotations.data_id',
                'annotations.category_ids',
                'annotations.updated_at as annotated_at',
                'annotations.user_id',
                'users.name as user_name',
                'package_data.package_id',
                'packages.name as package_name',
                'data.content as data_content',
            ])
            ->leftJoin('users', 'users.id', '=', 'annotations.user_id')
            ->leftJoin('package_data', 'package_data.data_id', '=', 'annotations.data_id')
            ->leftJoin('packages', 'packages.id', '=', 'package_data.package_id')
            ->leftJoin('data', 'data.id', '=', 'annotations.data_id');
    }

    private function sessionLabelsForAnnotations(Collection $annotationIds): array
    {
        if ($annotationIds->isEmpty()) {
            return [];
        }

        $sessions = SessionLog::with(['package:id,name'])
            ->orderByDesc('created_at')
            ->where(function ($query) use ($annotationIds) {
                foreach ($annotationIds as $annotationId) {
                    $query->orWhereJsonContains('annotation_datas', (int) $annotationId);
                }
            })
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $annotationSet = array_fill_keys($annotationIds->map(fn ($id) => (int) $id)->all(), true);
        $labels = [];

        foreach ($sessions as $session) {
            $label = sprintf(
                '#%d · %s · %s',
                $session->id,
                $session->package?->name ?? 'Package',
                $session->created_at?->format('Y-m-d H:i') ?? '—'
            );

            foreach ($session->annotation_datas ?? [] as $annotationId) {
                $annotationId = (int) $annotationId;
                if (! isset($annotationSet[$annotationId]) || isset($labels[$annotationId])) {
                    continue;
                }

                $labels[$annotationId] = $label;
            }
        }

        return $labels;
    }

    private function normalizeCategoryIds($raw): array
    {
        if ($raw instanceof Collection) {
            return $raw->toArray();
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function ensurePackageAccess(Package $package, int $userId): void
    {
        $hasAccess = UserPackage::where('package_id', $package->id)
            ->where('user_id', $userId)
            ->exists();

        abort_if(! $hasAccess, 403, 'You are not assigned to this package.');
    }

    private function packageDataQuery(Package $package)
    {
        return PackageData::query()
            ->select([
                'package_data.id as pivot_id',
                'package_data.data_id',
                'data.content',
                'data.created_at',
            ])
            ->join('data', 'data.id', '=', 'package_data.data_id')
            ->where('package_data.package_id', $package->id);
    }

    private function unannotatedPackageDataQuery(Package $package)
    {
        return $this->packageDataQuery($package)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('annotations')
                    ->whereColumn('annotations.data_id', 'package_data.data_id');
            });
    }

    private function formatWorkItem(object $record, int $userId): array
    {
        $annotation = Annotation::where('data_id', $record->data_id)
            ->where('user_id', $userId)
            ->first();

        return [
            'pivot_id' => (int) $record->pivot_id,
            'data_id' => (string) $record->data_id,
            'content' => $record->content,
            'category_ids' => $annotation && is_array($annotation->category_ids)
                ? array_map('strval', $annotation->category_ids)
                : [],
            'annotation_id' => $annotation?->id,
            'updated_at' => optional($annotation?->updated_at)->toIso8601String(),
        ];
    }

    private function getInitialWorkItem(Package $package, int $userId): ?array
    {
        $unannotated = $this->unannotatedPackageDataQuery($package)
            ->orderBy('package_data.id')
            ->first();

        return $unannotated ? $this->formatWorkItem($unannotated, $userId) : null;
    }

    private function resolveWorkItem(Package $package, int $userId, ?int $cursorPivotId, string $direction): ?array
    {
        $direction = in_array($direction, ['prev', 'previous'], true) ? 'prev' : 'next';

        $query = $this->unannotatedPackageDataQuery($package);

        if ($cursorPivotId) {
            if ($direction === 'next') {
                $query->where('package_data.id', '>', $cursorPivotId)
                    ->orderBy('package_data.id', 'asc');
            } else {
                $query->where('package_data.id', '<', $cursorPivotId)
                    ->orderBy('package_data.id', 'desc');
            }
        } else {
            $query->orderBy('package_data.id', 'asc');
        }

        $record = $query->first();

        return $record ? $this->formatWorkItem($record, $userId) : null;
    }

    private function startAnnotationSession(Package $package, int $userId): SessionLog
    {
        SessionLog::where('user_id', $userId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $sessionLog = SessionLog::create([
            'user_id' => $userId,
            'package_id' => $package->id,
            'annotation_datas' => [],
        ]);

        session()->put(SessionLogController::SESSION_KEY, $sessionLog->id);

        return $sessionLog;
    }

    private function recordAnnotationInSession(?int $annotationId, int $userId): void
    {
        if (! $annotationId) {
            return;
        }

        $sessionLogId = session(SessionLogController::SESSION_KEY);

        if (! $sessionLogId) {
            return;
        }

        $sessionLog = SessionLog::where('id', $sessionLogId)
            ->where('user_id', $userId)
            ->first();

        if (! $sessionLog) {
            return;
        }

        $existing = collect($sessionLog->annotation_datas ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (in_array($annotationId, $existing, true)) {
            return;
        }

        $sessionLog->annotation_datas = array_values(array_merge($existing, [$annotationId]));
        $sessionLog->save();
    }
}
