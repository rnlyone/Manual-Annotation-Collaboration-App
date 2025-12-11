<?php

namespace App\Http\Controllers;

use App\Models\Data;
use App\Models\Package;
use App\Models\PackageData;
use App\Models\UserPackage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';
        $headscript = '<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
                <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script src="/assets/vendor/libs/datatables/jquery.dataTables.js"></script>
                <script src="/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.js"></script>';


        return view('_app.app', [
            'content' => 'package.index',
            'headerdata' => ['pagetitle' => 'Package', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'packages'],
        ]);
    }

    public function tabledata(Request $request)
    {

        if ($request->filled('id')) {
            $package = Package::query()
                ->select(['id', 'name', 'created_at'])
                ->withCount([
                    'dataAssignments as data_count',
                    'userAssignments as user_count',
                ])
                ->find($request->integer('id'));

            if (! $package) {
                return response()->json(['error' => 'Package not found'], 404);
            }

            return response()->json(['data' => $this->formatPackage($package)]);
        }

        $packages = Package::query()
            ->select(['id', 'name', 'created_at'])
            ->withCount([
                'dataAssignments as data_count',
                'userAssignments as user_count',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Package $package) => $this->formatPackage($package));

        return response()->json(['data' => $packages]);
    }

        /**
     * Format package data for JSON response.
     */
    private function formatPackage(Package $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'data_count' => (int) ($package->data_count ?? 0),
            'user_count' => (int) ($package->user_count ?? 0),
            'created_at' => $package->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //store package
        $request->validate([
            'name' => 'required|string|max:255|unique:packages,name',
        ]);

        if ($request->filled('id')) {
            $request->validate([
                'id' => 'required|integer|unique:packages,id',
            ]);
            $package = new Package([
                'id' => $request->integer('id'),
                'name' => $request->input('name'),
            ]);
        } else {
            $package = new Package([
                'name' => $request->input('name'),
            ]);
        }

        $package->save();

        return response()->json(['message' => 'Package created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Package $package)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Package $package)
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
            'content' => 'package.assign',
            'headerdata' => ['pagetitle' => 'Assign Package', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'packages'],
            'package' => $package,
            'assignedDataIds' => PackageData::where('package_id', $package->id)->pluck('data_id')->all(),
            'assignedDataDetails' => Data::query()
                ->select(['data.id', 'data.content'])
                ->withCount(['packageAssignments as packages_count'])
                ->whereIn('data.id', PackageData::where('package_id', $package->id)->select('data_id'))
                ->orderBy('data.created_at', 'desc')
                ->get()
                ->map(function (Data $record) {
                    $content = strlen($record->content) > 100 ? substr($record->content, 0, 100) . '...' : $record->content;

                    return [
                        'id' => (string) $record->id,
                        'content' => $content,
                        'packages_count' => (int) ($record->packages_count ?? 0),
                    ];
                }),
            'assignedUserIds' => UserPackage::where('package_id', $package->id)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Package $package)
    {
        //update package
        $request->validate([
            'name' => 'required|string|max:255|unique:packages,name,'.$package->id,
        ]);

        if ($package->id != $request->integer('id')) {
            $request->validate([
                'id' => 'required|integer|unique:packages,id',
            ]);
            $package->id = $request->integer('id');
        }
        $package->name = $request->input('name');
        $package->save();

        return response()->json(['message' => 'Package updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package)
    {
        //destroy
        $package->delete();
        return response()->json(['message' => 'Package deleted successfully'], 200);
    }

    public function assignedData(Package $package)
    {
        $records = Data::query()
            ->select(['data.id', 'data.content'])
            ->withCount(['packageAssignments as packages_count'])
            ->whereIn('data.id', PackageData::where('package_id', $package->id)->select('data_id'))
            ->orderBy('data.created_at', 'desc')
            ->get()
            ->map(function (Data $record) {
                $content = strlen($record->content) > 100 ? substr($record->content, 0, 100) . '...' : $record->content;

                return [
                    'id' => (string) $record->id,
                    'content' => $content,
                    'packages_count' => (int) ($record->packages_count ?? 0),
                ];
            });

        return response()->json([
            'package_id' => $package->id,
            'data' => $records,
            'data_ids' => $records->pluck('id'),
            'count' => $records->count(),
        ]);
    }

    public function assignData(Request $request, Package $package)
    {
        $validated = $request->validate([
            'data_ids' => 'array',
            'data_ids.*' => 'string|exists:data,id',
            'chunk_index' => 'nullable|integer|min:0',
            'chunk_total' => 'nullable|integer|min:1',
        ]);

        $chunkIndex = $validated['chunk_index'] ?? 0;
        $chunkTotal = $validated['chunk_total'] ?? 1;

        $chunkDataIds = collect($validated['data_ids'] ?? [])->map(fn ($id) => (string) $id);
        $insertedIds = collect();

        try {
            DB::transaction(function () use ($package, $chunkDataIds, &$insertedIds) {
                $existing = PackageData::where('package_id', $package->id)
                    ->whereIn('data_id', $chunkDataIds)
                    ->pluck('data_id');

                $toInsert = $chunkDataIds->diff($existing)->values();

                $toInsert->each(function ($dataId) use ($package) {
                    PackageData::firstOrCreate([
                        'package_id' => $package->id,
                        'data_id' => $dataId,
                    ]);
                });

                $insertedIds = $toInsert;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to sync selected package data chunk', [
                'package_id' => $package->id,
                'chunk_index' => $chunkIndex,
                'chunk_total' => $chunkTotal,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to sync selected data chunk.',
            ], 500);
        }

        if ($insertedIds->isNotEmpty()) {
            $count = $insertedIds->count();
            $message = $count === 1
                ? "1 data item was added to package {$package->name}."
                : "{$count} data items were added to package {$package->name}.";
            $this->notifyPackageUsers($package, $message, 'package_data_added');
        }

        return response()->json([
            'message' => 'Chunk saved successfully.',
            'chunk_index' => $chunkIndex,
            'chunk_total' => $chunkTotal,
        ]);
    }

    public function unassignData(Request $request, Package $package)
    {
        $validated = $request->validate([
            'data_ids' => 'required|array|min:1',
            'data_ids.*' => 'string|exists:data,id',
        ]);

        $dataIds = collect($validated['data_ids'])->map(fn ($id) => (string) $id);
        $removedCount = 0;

        try {
            $removedCount = PackageData::where('package_id', $package->id)
                ->whereIn('data_id', $dataIds)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('Failed to unassign package data', [
                'package_id' => $package->id,
                'data_ids' => $dataIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to save deselections.',
            ], 500);
        }

        if ($removedCount > 0) {
            $message = $removedCount === 1
                ? "1 data item was removed from package {$package->name}."
                : "{$removedCount} data items were removed from package {$package->name}.";
            $this->notifyPackageUsers($package, $message, 'package_data_removed');
        }

        return response()->json([
            'message' => 'Deselections saved successfully.',
            'removed_count' => $dataIds->count(),
        ]);
    }

    public function assignUsers(Request $request, Package $package)
    {
        $validated = $request->validate([
            'user_ids' => 'array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = collect($validated['user_ids'] ?? [])->map(fn ($id) => (int) $id);
        $existingAssignments = UserPackage::where('package_id', $package->id)->pluck('user_id')->map(fn ($id) => (int) $id);
        $addedUserIds = $userIds->diff($existingAssignments)->values();
        $removedUserIds = $existingAssignments->diff($userIds)->values();

        try {
            DB::transaction(function () use ($package, $userIds) {
                if ($userIds->isEmpty()) {
                    UserPackage::where('package_id', $package->id)->delete();
                    return;
                }

                UserPackage::where('package_id', $package->id)
                    ->whereNotIn('user_id', $userIds)
                    ->delete();

                $userIds->each(function ($userId) use ($package) {
                    UserPackage::firstOrCreate([
                        'package_id' => $package->id,
                        'user_id' => $userId,
                    ]);
                });
            });
        } catch (\Throwable $e) {
            Log::error('Failed to assign users to package', [
                'package_id' => $package->id,
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to save user assignments.',
            ], 500);
        }

        $assignedCount = UserPackage::where('package_id', $package->id)->count();

        if ($addedUserIds->isNotEmpty()) {
            $message = "You have been assigned to package {$package->name}.";
            $this->notificationService->sendToUsers($addedUserIds, $message, 'package_user_assigned');
        }

        if ($removedUserIds->isNotEmpty()) {
            $message = "You have been removed from package {$package->name}.";
            $this->notificationService->sendToUsers($removedUserIds, $message, 'package_user_unassigned');
        }

        return response()->json([
            'message' => 'User assignments saved successfully.',
            'assigned_count' => $assignedCount,
            'submitted_count' => $userIds->count(),
        ]);
    }

    private function notifyPackageUsers(Package $package, string $message, string $type): void
    {
        $userIds = UserPackage::where('package_id', $package->id)->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $this->notificationService->sendToUsers($userIds, $message, $type);
    }
}
