<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

        if ($includePackagesCount) {
            $query->withCount(['packageAssignments as packages_count']);
        }

        if ($onlyUnassigned) {
            $query->whereDoesntHave('packageAssignments');
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
