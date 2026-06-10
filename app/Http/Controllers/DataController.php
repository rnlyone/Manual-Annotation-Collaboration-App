<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\AiScreening;
use App\Models\Category;
use App\Models\Data;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        # return also style and script datatables from vuexy and also sweetalert2
        #Uncaught ReferenceError: jQuery is not defined
        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';
        $headscript = '
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/select/1.6.2/js/dataTables.select.min.js"></script>';

        return view('_app.app', [
            'content' => 'data.index',
            'headerdata' => ['pagetitle' => 'Data Management', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'data'],
        ]);
    }

    public function tabledata(Request $request)
    {
        $columns = [
            0 => 'index',
            1 => 'id',
            2 => 'content',
            3 => 'created_at',
            4 => 'updated_at',
        ];

        $includePackagesCount = $request->boolean('include_packages_count');
        $onlyUnassigned = $request->boolean('only_unassigned');

        $selectedIds = collect();
        if ($request->filled('selected_ids_json')) {
            $decoded = json_decode($request->input('selected_ids_json'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $selectedIds = collect($decoded)->filter()->map(fn ($id) => (string) $id);
            }
        }

        $baseQuery = Data::query()->select(['id', 'content', 'created_at', 'updated_at']);

        if ($includePackagesCount) {
            $baseQuery->withCount(['packageAssignments as packages_count']);
        }

        if ($onlyUnassigned) {
            $baseQuery->whereDoesntHave('packageAssignments');
        }

        if ($selectedIds->isNotEmpty()) {
            $baseQuery->whereNotIn('id', $selectedIds);
        }

        $recordsTotal = (clone $baseQuery)->count();
        $filteredQuery = clone $baseQuery;

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $filteredQuery->where(function ($builder) use ($searchValue) {
                $builder->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('content', 'like', "%{$searchValue}%")
                    ->orWhere('created_at', 'like', "%{$searchValue}%")
                    ->orWhere('updated_at', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $filteredQuery)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0) {
            $length = 25;
        }
        $length = min($length, 500);

        $orderColumnIndex = (int) $request->input('order.0.column', 3);
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
        if ($orderColumn === 'index') {
            $orderColumn = 'created_at';
        }
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $data = $filteredQuery
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($item) use ($includePackagesCount) {
                $content = strlen($item->content) > 100 ? substr($item->content, 0, 100) . '...' : $item->content;

                $payload = [
                    'id' => (string) $item->id,
                    'content' => $content,
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                ];

                if ($includePackagesCount) {
                    $payload['packages_count'] = (int) ($item->packages_count ?? 0);
                }

                return $payload;
            });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function allIds(Request $request)
    {
        $includePackagesCount = $request->boolean('include_packages_count');
        $onlyUnassigned = $request->boolean('only_unassigned');

        $query = Data::query()->select(['id', 'content', 'created_at']);

        $excludeIds = collect();
        if ($request->filled('exclude_ids_json')) {
            $decoded = json_decode($request->input('exclude_ids_json'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $excludeIds = collect($decoded)->filter()->map(fn ($id) => (string) $id);
            }
        }

        if ($includePackagesCount) {
            $query->withCount(['packageAssignments as packages_count']);
        }

        if ($onlyUnassigned) {
            $query->whereDoesntHave('packageAssignments');
        }

        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('id', $excludeIds);
        }

        $searchValue = $request->input('search');
        if ($searchValue) {
            $query->where(function ($builder) use ($searchValue) {
                $builder->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('content', 'like', "%{$searchValue}%")
                    ->orWhere('created_at', 'like', "%{$searchValue}%");
            });
        }

        $records = $query->orderBy('created_at', 'desc')->get()->map(function ($item) use ($includePackagesCount) {
            $content = strlen($item->content) > 100 ? substr($item->content, 0, 100) . '...' : $item->content;

            $payload = [
                'id' => (string) $item->id,
                'content' => $content,
            ];

            if ($includePackagesCount) {
                $payload['packages_count'] = (int) ($item->packages_count ?? 0);
            }

            return $payload;
        });

        return response()->json([
            'count' => $records->count(),
            'data' => $records,
            'data_ids' => $records->pluck('id'),
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
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'id' => 'nullable|string|unique:data,id',
            'content' => 'required|string',
        ]);

        Data::create([
            'id' => $validated['id'] ?? (string) Str::uuid(),
            'content' => $validated['content'],
        ]);

        return response()->json(['message' => 'Data created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Data $data)
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => (string) $data->id,
            'content' => $data->content,
            'created_at' => $data->created_at ? $data->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $data->updated_at ? $data->updated_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Data $data)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Data $data)
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $data->update([
            'content' => $validated['content'],
        ]);

        return response()->json(['message' => 'Data updated successfully'], 200);
    }

    /**
     * Import data from CSV file.
     */
    public function addbycsv(Request $request)
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'id_column' => 'nullable|integer|min:0',
            'content_column' => 'required|integer|min:0',
            'created_at_column' => 'nullable|integer|min:0',
            'skip_duplicates' => 'sometimes|boolean',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['error' => 'Unable to read the uploaded file'], 422);
        }

        $lineNumber = 1;
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            return response()->json(['error' => 'CSV file is empty'], 400);
        }

        $imported = 0;
        $errors = [];

        $idColumnIndex = array_key_exists('id_column', $validated) ? (int) $validated['id_column'] : null;
        $contentColumnIndex = (int) $validated['content_column'];
        $createdAtColumnIndex = array_key_exists('created_at_column', $validated) ? (int) $validated['created_at_column'] : null;
        $skipDuplicates = $request->boolean('skip_duplicates');

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isCsvRowEmpty($row)) {
                continue;
            }

            $id = $idColumnIndex !== null ? $this->normalizeCell($row[$idColumnIndex] ?? null) : null;
            $id = $id ?: (string) Str::uuid();

            $content = $this->normalizeCell($row[$contentColumnIndex] ?? null);
            if ($content === null) {
                $errors[] = "Row {$lineNumber}: Content is required";
                continue;
            }

            if ($skipDuplicates && $idColumnIndex !== null && Data::where('id', $id)->exists()) {
                $errors[] = "Row {$lineNumber}: Skipped duplicate ID {$id}";
                continue;
            }

            $timestamps = [];
            if ($createdAtColumnIndex !== null) {
                $rawCreatedAt = $this->normalizeCell($row[$createdAtColumnIndex] ?? null);
                if ($rawCreatedAt) {
                    try {
                        $parsed = Carbon::parse($rawCreatedAt);
                        $timestamps['created_at'] = $parsed;
                        $timestamps['updated_at'] = $parsed;
                    } catch (\Throwable $exception) {
                        $errors[] = "Row {$lineNumber}: Unable to parse created_at value '{$rawCreatedAt}'";
                    }
                }
            }

            try {
                Data::create(array_merge([
                    'id' => $id,
                    'content' => $content,
                ], $timestamps));
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = "Row {$lineNumber}: " . $exception->getMessage();
            }
        }

        fclose($handle);

        if ($imported === 0 && empty($errors)) {
            return response()->json(['error' => 'No valid rows found in CSV file'], 400);
        }

        return response()->json([
            'message' => "Successfully imported {$imported} records",
            'imported' => $imported,
            'errors' => $errors,
        ], 201);
    }

    protected function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value ?? '');
        }

        $value = trim((string) $value);
        $value = trim($value, " \t\n\r\0\x0B\"\'");

        return $value === '' ? null : $value;
    }

    protected function isCsvRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeCell($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build a filtered Eloquent query for the dataset preview/export.
     *
     * Applies complete_only, incomplete_phase3 and search filters.
     * Handles both DataTables format (search[value]) and plain GET (search=…).
     * The caller is responsible for column selection, ordering and pagination.
     */
    private function buildDatasetFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Data::query()->select(['id', 'content', 'created_at', 'updated_at']);

        // ── Complete annotation filter ──────────────────────────────────────
        if ($request->boolean('complete_only')) {
            $annotatorRoleIds   = User::where('role', 'annotator')->pluck('id');
            $annotatorRoleCount = $annotatorRoleIds->count();

            if ($annotatorRoleCount > 0) {
                $query->whereIn('id', function ($sub) use ($annotatorRoleIds, $annotatorRoleCount) {
                    $sub->select('data_id')
                        ->from('annotations')
                        ->whereIn('user_id', $annotatorRoleIds)
                        ->groupBy('data_id')
                        ->havingRaw('COUNT(DISTINCT user_id) >= ?', [$annotatorRoleCount]);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // ── Incomplete Phase 3 filter ───────────────────────────────────────
        if ($request->boolean('incomplete_phase3')) {
            $annotatorRoleIds      = User::where('role', 'annotator')->pluck('id');
            $annotatorRoleCount    = $annotatorRoleIds->count();
            $phase3AnnotatorsCount = (int) $request->input('phase3_annotators_count', 0);

            $phase3DataIds = DB::table('package_data')
                ->join('packages', 'packages.id', '=', 'package_data.package_id')
                ->where('packages.type', 'phase3')
                ->pluck('package_data.data_id')
                ->unique();

            if ($phase3DataIds->isEmpty() || $annotatorRoleCount === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $annotationCounts = DB::table('annotations')
                    ->whereIn('data_id', $phase3DataIds)
                    ->whereIn('user_id', $annotatorRoleIds)
                    ->select('data_id', DB::raw('COUNT(DISTINCT user_id) as annotator_count'))
                    ->groupBy('data_id')
                    ->pluck('annotator_count', 'data_id');

                $targetDataIds = $phase3DataIds->filter(function ($dataId) use ($annotationCounts, $annotatorRoleCount, $phase3AnnotatorsCount) {
                    $count = (int) $annotationCounts->get($dataId, 0);
                    return $phase3AnnotatorsCount > 0
                        ? $count === $phase3AnnotatorsCount
                        : $count < $annotatorRoleCount;
                })->values();

                // whereRaw('1=0') is cleaner than a fake ID for the empty case
                $targetDataIds->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('id', $targetDataIds->all());
            }
        }

        // ── Search ─────────────────────────────────────────────────────────
        // DataTables sends search[value]; plain export URL sends search=…
        $searchValue = trim((string) ($request->input('search.value') ?: $request->input('search', '')));
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('content', 'like', "%{$searchValue}%");
            });
        }

        return $query;
    }

    /**
     * Dataset preview page.
     */
    public function datasetPreview()
    {
        $totalCount = Data::count();

        $annotatorIds = Annotation::distinct()->pluck('user_id');
        $annotators = User::whereIn('id', $annotatorIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => [
                'id'   => $u->id,
                'name' => $u->name,
                'key'  => 'annotator_' . $u->id,
            ])->values();

        $annotatorRoleCount = User::where('role', 'annotator')->count();

        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';
        $headscript = '
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>';

        return view('_app.app', [
            'content'     => 'data.dataset-preview',
            'headerdata'  => ['pagetitle' => 'Dataset Preview', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'data.dataset-preview'],
            'contentdata' => [
                'totalCount'        => $totalCount,
                'annotators'        => $annotators,
                'annotatorRoleCount' => $annotatorRoleCount,
                'tableDataUrl'      => route('data.dataset-preview-table'),
                'exportUrl'         => route('data.dataset-export'),
            ],
        ]);
    }

    /**
     * Server-side DataTables for dataset preview (includes annotator labels + LLM label).
     */
    public function datasetPreviewTableData(Request $request)
    {
        $dtColumns = [
            0 => 'index',
            1 => 'id',
            2 => 'content',
            3 => 'created_at',
            4 => 'updated_at',
        ];

        $recordsTotal  = Data::count();

        $filteredQuery = $this->buildDatasetFilteredQuery($request)
            ->withCount(['packageAssignments as packages_count']);

        $recordsFiltered = (clone $filteredQuery)->count();

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0) {
            $length = 25;
        }
        $length = min($length, 500);

        $orderColIdx    = (int) $request->input('order.0.column', 3);
        $orderColumn    = $dtColumns[$orderColIdx] ?? 'created_at';
        if ($orderColumn === 'index') {
            $orderColumn = 'created_at';
        }
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $dataItems = (clone $filteredQuery)
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $dataIds = $dataItems->pluck('id');

        // --- All annotator user IDs (for consistent column structure) ---------
        $allAnnotatorIds  = Annotation::distinct()->pluck('user_id');
        $categories       = Category::pluck('name', 'id');
        $annotsByDataUser = collect();

        if ($dataIds->isNotEmpty()) {
            $annotations = Annotation::whereIn('data_id', $dataIds)
                ->get(['data_id', 'user_id', 'category_ids']);

            $annotsByDataUser = $annotations
                ->groupBy('data_id')
                ->map(fn ($dataAnnots) =>
                    $dataAnnots->groupBy('user_id')->map(function ($userAnnots) use ($categories) {
                        $catIds = collect($userAnnots)
                            ->flatMap(fn ($a) => $a->category_ids ?? [])
                            ->unique()
                            ->values();
                        $labels = $catIds->map(fn ($id) => $categories->get($id) ?? (string) $id)->filter();
                        return $labels->isEmpty() ? '-' : $labels->implode(' | ');
                    })
                );
        }

        // --- Latest LLM label per data_id ------------------------------------
        $llmLabels = collect();
        if ($dataIds->isNotEmpty()) {
            $llmLabels = AiScreening::whereIn('data_id', $dataIds)
                ->whereNotNull('llm_label')
                ->orderBy('id', 'desc')
                ->get(['data_id', 'llm_label'])
                ->groupBy('data_id')
                ->map(fn ($g) => $g->first()->llm_label);
        }

        // --- Phase-1 annotator IDs per data_id --------------------------------
        $phase1AnnotatorsByData = collect();
        if ($dataIds->isNotEmpty()) {
            $screenedAnnotationIds = AiScreening::whereIn('data_id', $dataIds)
                ->whereNotNull('annotation_id')
                ->pluck('annotation_id')
                ->unique()
                ->values();

            if ($screenedAnnotationIds->isNotEmpty()) {
                $phase1AnnotatorsByData = Annotation::whereIn('id', $screenedAnnotationIds)
                    ->get(['id', 'data_id', 'user_id'])
                    ->groupBy('data_id')
                    ->map(fn ($rows) => $rows->pluck('user_id')->unique()->values()->all());
            }
        }

        // --- First annotator per data_id --------------------------------------
        $firstAnnotatorNames = collect();
        if ($dataIds->isNotEmpty()) {
            $firstAnnotByData = Annotation::whereIn('data_id', $dataIds)
                ->orderBy('created_at', 'asc')
                ->get(['data_id', 'user_id', 'created_at'])
                ->groupBy('data_id')
                ->map(fn ($annots) => $annots->first()->user_id);

            $userNameMap = User::whereIn('id', $firstAnnotByData->values()->unique()->filter())
                ->pluck('name', 'id');

            $firstAnnotatorNames = $firstAnnotByData
                ->map(fn ($userId) => $userNameMap->get($userId, '-'));
        }

        // --- Phase 3 membership per data_id -----------------------------------
        $phase3DataIdSet = collect();
        if ($dataIds->isNotEmpty()) {
            $phase3DataIdSet = DB::table('package_data')
                ->join('packages', 'packages.id', '=', 'package_data.package_id')
                ->where('packages.type', 'phase3')
                ->whereIn('package_data.data_id', $dataIds->all())
                ->pluck('package_data.data_id')
                ->unique()
                ->flip();
        }

        $data = $dataItems->map(function ($item) use ($annotsByDataUser, $llmLabels, $allAnnotatorIds, $phase1AnnotatorsByData, $firstAnnotatorNames, $phase3DataIdSet) {
            $content = strlen($item->content) > 100
                ? substr($item->content, 0, 100) . '...'
                : $item->content;

            $payload = [
                'id'                   => (string) $item->id,
                'content'              => $content,
                'created_at'           => $item->created_at?->format('Y-m-d H:i:s'),
                'updated_at'           => $item->updated_at?->format('Y-m-d H:i:s'),
                'packages_count'       => (int) ($item->packages_count ?? 0),
                'llm_label'            => $llmLabels->get($item->id, '-') ?? '-',
                'first_annotator'      => $firstAnnotatorNames->get($item->id, '-'),
                'is_phase3'            => $phase3DataIdSet->has($item->id),
                'phase1_annotator_ids' => $phase1AnnotatorsByData->get($item->id, []),
            ];

            foreach ($allAnnotatorIds as $userId) {
                $label = $annotsByDataUser->get($item->id, collect())->get($userId);
                $payload['annotator_' . $userId] = ($label !== null && $label !== '') ? $label : '-';
            }

            return $payload;
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Export dataset as CSV with customizable columns (base + annotator labels + LLM label).
     *
     * Uses the same buildDatasetFilteredQuery() as the preview table so the
     * exported row count always matches recordsFiltered shown in the UI.
     */
    public function datasetExport(Request $request)
    {
        $baseAllowed = ['id', 'content', 'created_at', 'updated_at', 'packages_count', 'llm_label', 'first_annotator', 'phase3_data'];

        $rawColumns    = $request->input('columns', 'id,content,created_at,updated_at');
        $requestedCols = array_map('trim', explode(',', $rawColumns));

        $columns = array_values(array_filter(
            $requestedCols,
            fn ($c) => in_array($c, $baseAllowed, true) || preg_match('/^annotator_\d+$/', $c)
        ));

        if (empty($columns)) {
            $columns = ['id', 'content', 'created_at', 'updated_at'];
        }

        $includePackagesCount  = in_array('packages_count', $columns, true);
        $includeLlmLabel       = in_array('llm_label', $columns, true);
        $includeFirstAnnotator = in_array('first_annotator', $columns, true);
        $includePhase3Data     = in_array('phase3_data', $columns, true);
        $annotatorCols         = array_values(array_filter($columns, fn ($c) => preg_match('/^annotator_\d+$/', $c)));
        $annotatorUserIds      = collect($annotatorCols)
            ->map(fn ($c) => (int) str_replace('annotator_', '', $c))
            ->filter()
            ->values();

        // ── Same filtered query as the preview table ────────────────────────
        $query = $this->buildDatasetFilteredQuery($request);
        if ($includePackagesCount) {
            $query->withCount(['packageAssignments as packages_count']);
        }

        $dataItems = $query->orderBy('created_at', 'desc')->get();
        $dataIds   = $dataItems->pluck('id');

        // ── Resolve computed column data ─────────────────────────────────────
        $annotatorNames = $annotatorUserIds->isNotEmpty()
            ? User::whereIn('id', $annotatorUserIds)->pluck('name', 'id')
            : collect();

        $headers = collect($columns)->map(function ($col) use ($annotatorNames) {
            if (preg_match('/^annotator_(\d+)$/', $col, $m)) {
                return $annotatorNames->get((int) $m[1], 'Annotator ' . $m[1]);
            }
            return match ($col) {
                'first_annotator' => 'First Annotator',
                'phase3_data'     => 'Phase 3 Data',
                default           => $col,
            };
        })->all();

        $annotsByDataUser = collect();
        if ($dataIds->isNotEmpty() && $annotatorUserIds->isNotEmpty()) {
            $categories  = Category::pluck('name', 'id');
            $annotations = Annotation::whereIn('data_id', $dataIds)
                ->whereIn('user_id', $annotatorUserIds)
                ->get(['data_id', 'user_id', 'category_ids']);

            $annotsByDataUser = $annotations
                ->groupBy('data_id')
                ->map(fn ($items) =>
                    $items->groupBy('user_id')->map(function ($userAnnots) use ($categories) {
                        $catIds = collect($userAnnots)
                            ->flatMap(fn ($a) => $a->category_ids ?? [])
                            ->unique()->values();
                        $labels = $catIds->map(fn ($id) => $categories->get($id) ?? (string) $id)->filter();
                        return $labels->isEmpty() ? '-' : $labels->implode(' | ');
                    })
                );
        }

        $llmLabels = collect();
        if ($dataIds->isNotEmpty() && $includeLlmLabel) {
            $llmLabels = AiScreening::whereIn('data_id', $dataIds)
                ->whereNotNull('llm_label')
                ->orderBy('id', 'desc')
                ->get(['data_id', 'llm_label'])
                ->groupBy('data_id')
                ->map(fn ($g) => $g->first()->llm_label);
        }

        $firstAnnotatorsExport = collect();
        if ($dataIds->isNotEmpty() && $includeFirstAnnotator) {
            $firstAnnotByData = Annotation::whereIn('data_id', $dataIds)
                ->orderBy('created_at', 'asc')
                ->get(['data_id', 'user_id', 'created_at'])
                ->groupBy('data_id')
                ->map(fn ($annots) => $annots->first()->user_id);

            $userNameMap = User::whereIn('id', $firstAnnotByData->values()->unique()->filter())
                ->pluck('name', 'id');

            $firstAnnotatorsExport = $firstAnnotByData
                ->map(fn ($userId) => $userNameMap->get($userId, '-'));
        }

        $phase3DataIdSetExport = collect();
        if ($dataIds->isNotEmpty() && $includePhase3Data) {
            $phase3DataIdSetExport = DB::table('package_data')
                ->join('packages', 'packages.id', '=', 'package_data.package_id')
                ->where('packages.type', 'phase3')
                ->whereIn('package_data.data_id', $dataIds->all())
                ->pluck('package_data.data_id')
                ->unique()->flip();
        }

        $timestamp = Carbon::now()->format('Ymd-His');
        $fileName  = "dataset-{$timestamp}.csv";

        return response()->streamDownload(
            function () use ($dataItems, $columns, $headers, $annotsByDataUser, $llmLabels, $firstAnnotatorsExport, $phase3DataIdSetExport) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);

                foreach ($dataItems as $row) {
                    $rowData = [];
                    foreach ($columns as $col) {
                        if (preg_match('/^annotator_(\d+)$/', $col, $m)) {
                            $label = $annotsByDataUser->get($row->id, collect())->get((int) $m[1]);
                            $rowData[] = ($label !== null && $label !== '') ? $label : '-';
                        } elseif ($col === 'first_annotator') {
                            $rowData[] = $firstAnnotatorsExport->get($row->id, '-');
                        } elseif ($col === 'phase3_data') {
                            $rowData[] = $phase3DataIdSetExport->has($row->id) ? 'Yes' : 'No';
                        } elseif ($col === 'llm_label') {
                            $rowData[] = $llmLabels->get($row->id, '-') ?? '-';
                        } elseif ($col === 'packages_count') {
                            $rowData[] = (int) ($row->packages_count ?? 0);
                        } elseif (in_array($col, ['created_at', 'updated_at'], true)) {
                            $rowData[] = $row->$col ? $row->$col->format('Y-m-d H:i:s') : '';
                        } else {
                            $rowData[] = $row->$col ?? '';
                        }
                    }
                    fputcsv($handle, $rowData);
                }

                fclose($handle);
            },
            $fileName,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Data $data)
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data->delete();
        return response()->json(['message' => 'Data deleted successfully'], 200);
    }
}
