<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\AiScreening;
use App\Models\Data;
use App\Models\Package;
use App\Models\PackageData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Phase3LeftoverController extends Controller
{

    /**
     * Resolve leftover phase3 data.
     *
     * Leftover = in a phase3 package + incomplete annotations
     *            (fewer than annotatorRoleCount distinct annotator-role users).
     *
     * @return array{data_ids: \Illuminate\Support\Collection, annotation_counts: \Illuminate\Support\Collection, annotator_role_count: int, llm_disagree_counts: \Illuminate\Support\Collection}
     */
    private function resolveLeftoverData(): array
    {
        $annotatorRoleIds   = User::where('role', 'annotator')->pluck('id');
        $annotatorRoleCount = $annotatorRoleIds->count();

        $allPhase3PackageIds = Package::where('type', 'phase3')->pluck('id');

        if ($allPhase3PackageIds->isEmpty()) {
            return [
                'data_ids'            => collect(),
                'annotation_counts'   => collect(),
                'annotator_role_count' => $annotatorRoleCount,
                'llm_disagree_counts' => collect(),
            ];
        }

        // All data IDs that belong to ANY phase3 package
        $allPhase3DataIds = DB::table('package_data')
            ->whereIn('package_id', $allPhase3PackageIds)
            ->pluck('data_id')
            ->unique();

        if ($allPhase3DataIds->isEmpty()) {
            return [
                'data_ids'            => collect(),
                'annotation_counts'   => collect(),
                'annotator_role_count' => $annotatorRoleCount,
                'llm_disagree_counts' => collect(),
            ];
        }

        // Count distinct annotator-role users per data_id (mirrors phase3 insights counting)
        $annotationCounts = DB::table('annotations')
            ->whereIn('data_id', $allPhase3DataIds)
            ->whereIn('user_id', $annotatorRoleIds)
            ->select('data_id', DB::raw('COUNT(DISTINCT user_id) as annotator_count'))
            ->groupBy('data_id')
            ->pluck('annotator_count', 'data_id');

        // Keep only incomplete data items
        $incompleteDataIds = $annotatorRoleCount > 0
            ? $allPhase3DataIds->filter(fn ($id) => (int) $annotationCounts->get($id, 0) < $annotatorRoleCount)->values()
            : $allPhase3DataIds->values();

        $llmDisagreeCounts = $this->computeLlmDisagreeCounts($incompleteDataIds, $annotatorRoleIds);

        return [
            'data_ids'            => $incompleteDataIds,
            'annotation_counts'   => $annotationCounts,
            'annotator_role_count' => $annotatorRoleCount,
            'llm_disagree_counts' => $llmDisagreeCounts,
        ];
    }

    /**
     * For each data_id, count how many distinct annotator-role users
     * have a label that disagrees with the LLM label (Normal vs non-Normal).
     *
     * @param  \Illuminate\Support\Collection  $dataIds
     * @param  \Illuminate\Support\Collection  $annotatorRoleIds
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function computeLlmDisagreeCounts(
        \Illuminate\Support\Collection $dataIds,
        \Illuminate\Support\Collection $annotatorRoleIds
    ): \Illuminate\Support\Collection {
        if ($dataIds->isEmpty() || $annotatorRoleIds->isEmpty()) {
            return collect();
        }

        // Latest LLM label per data_id
        $llmLabels = AiScreening::whereIn('data_id', $dataIds)
            ->whereNotNull('llm_label')
            ->orderBy('id', 'desc')
            ->get(['data_id', 'llm_label'])
            ->groupBy('data_id')
            ->map(fn ($g) => $g->first()->llm_label);

        // All annotator-role annotations (with category_ids) for these items
        $annotations = Annotation::whereIn('data_id', $dataIds)
            ->whereIn('user_id', $annotatorRoleIds)
            ->get(['data_id', 'user_id', 'category_ids']);

        return $annotations
            ->groupBy('data_id')
            ->map(function ($dataAnnots, $dataId) use ($llmLabels) {
                $llmLabel = $llmLabels->get($dataId);
                if ($llmLabel === null) {
                    return 0; // No LLM label — cannot determine disagreement
                }

                $llmIsNormal = strtolower((string) $llmLabel) === 'normal';

                return $dataAnnots
                    ->groupBy('user_id')
                    ->filter(function ($userAnnots) use ($llmIsNormal) {
                        $catIds = collect($userAnnots)
                            ->flatMap(fn ($a) => $a->category_ids ?? [])
                            ->filter()
                            ->values();
                        $humanIsNormal = $catIds->isEmpty();
                        return $humanIsNormal !== $llmIsNormal;
                    })
                    ->count();
            });
    }

    /**
     * Leftover data page.
     */
    public function index()
    {
        $result             = $this->resolveLeftoverData();
        $dataIds            = $result['data_ids'];
        $annotationCounts   = $result['annotation_counts'];
        $annotatorRoleCount = $result['annotator_role_count'];
        $llmDisagreeCounts  = $result['llm_disagree_counts'];

        $totalLeftover = $dataIds->count();

        // Breakdown: how many items have exactly N annotators (0 … annotatorRoleCount-1)
        $byAnnotatorCount = [];
        for ($i = 0; $i < $annotatorRoleCount; $i++) {
            $byAnnotatorCount[$i] = $dataIds->filter(
                fn ($id) => (int) $annotationCounts->get($id, 0) === $i
            )->count();
        }

        // Disagree stats: items where 1 or 2 annotators disagree with LLM label
        $disagreeStats = [
            1 => $llmDisagreeCounts->filter(fn ($c) => (int) $c === 1)->count(),
            2 => $llmDisagreeCounts->filter(fn ($c) => (int) $c === 2)->count(),
        ];

        $headscript = '
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

        return view('_app.app', [
            'content'     => 'phase3.leftover',
            'headerdata'  => ['pagetitle' => 'Phase 3 Leftover Data', 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'phase3.leftover'],
            'contentdata' => [
                'totalLeftover'       => $totalLeftover,
                'byAnnotatorCount'    => $byAnnotatorCount,
                'disagreeStats'       => $disagreeStats,
                'annotatorRoleCount'  => $annotatorRoleCount,
                'allLeftoverIds'      => $dataIds->all(),
                'tableUrl'            => route('phase3.leftover.table'),
                'createPackageUrl'    => route('phase3.leftover.create-package'),
            ],
        ]);
    }

    /**
     * Server-side DataTables data for leftover items.
     */
    public function tableData(Request $request)
    {
        $result             = $this->resolveLeftoverData();
        $allDataIds         = $result['data_ids'];
        $annotationCounts   = $result['annotation_counts'];
        $annotatorRoleCount = $result['annotator_role_count'];
        $llmDisagreeCounts  = $result['llm_disagree_counts'];

        if ($allDataIds->isEmpty()) {
            return response()->json([
                'draw'            => (int) $request->input('draw'),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ]);
        }

        $recordsTotal = $allDataIds->count();

        // Optional sub-filter by exact annotator count
        $filterCount = $request->input('annotator_count_filter', '');
        if ($filterCount !== '' && is_numeric($filterCount)) {
            $filteredIds = $allDataIds->filter(
                fn ($id) => (int) $annotationCounts->get($id, 0) === (int) $filterCount
            )->values();
        } else {
            $filteredIds = $allDataIds;
        }

        // Optional disagree filter: disagree_1=1 and/or disagree_2=1
        $disagreeValues = collect();
        if ($request->boolean('disagree_1')) {
            $disagreeValues->push(1);
        }
        if ($request->boolean('disagree_2')) {
            $disagreeValues->push(2);
        }
        if ($disagreeValues->isNotEmpty()) {
            $filteredIds = $filteredIds->filter(
                fn ($id) => $disagreeValues->contains((int) $llmDisagreeCounts->get($id, 0))
            )->values();
        }

        $baseQuery = Data::query()
            ->select(['id', 'content', 'created_at'])
            ->whereIn('id', $filteredIds->all());

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $baseQuery->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('content', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0) {
            $length = 25;
        }
        $length = min($length, 500);

        $items = $baseQuery->orderBy('created_at', 'desc')->skip($start)->take($length)->get();

        // Fetch annotator names for items on this page
        $annotatorRoleIdList = User::where('role', 'annotator')->pluck('id');
        $userNames           = User::whereIn('id', $annotatorRoleIdList)->pluck('name', 'id');

        $annotsByData = collect();
        if ($items->isNotEmpty()) {
            $annotsByData = Annotation::whereIn('data_id', $items->pluck('id'))
                ->whereIn('user_id', $annotatorRoleIdList)
                ->get(['data_id', 'user_id'])
                ->groupBy('data_id')
                ->map(fn ($rows) => $rows->pluck('user_id')->unique()
                    ->map(fn ($uid) => $userNames->get($uid, 'Unknown'))
                    ->values()->all()
                );
        }

        $data = $items->map(function ($item) use ($annotationCounts, $annotsByData, $llmDisagreeCounts) {
            $content = strlen($item->content) > 120
                ? substr($item->content, 0, 120) . '...'
                : $item->content;

            return [
                'id'                => (string) $item->id,
                'content'           => $content,
                'annotator_count'   => (int) $annotationCounts->get($item->id, 0),
                'llm_disagree_count' => (int) $llmDisagreeCounts->get($item->id, 0),
                'annotators'        => $annotsByData->get($item->id, []),
                'created_at'        => $item->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Create a new phase3 package from selected leftover data items.
     */
    public function createPackage(Request $request)
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'package_name' => 'required|string|max:255|unique:packages,name',
            'data_ids'     => 'required|array|min:1',
            'data_ids.*'   => 'string|exists:data,id',
        ]);

        $package = Package::create([
            'name' => $validated['package_name'],
            'type' => 'phase3',
        ]);

        $dataIds = collect($validated['data_ids'])->unique()->values();

        $dataIds->each(function ($dataId) use ($package) {
            PackageData::firstOrCreate([
                'package_id' => $package->id,
                'data_id'    => $dataId,
            ]);
        });

        return response()->json([
            'message'      => "Package '{$package->name}' created with {$dataIds->count()} data items.",
            'package_id'   => $package->id,
            'package_name' => $package->name,
            'assign_url'   => route('packages.edit', $package->id),
            'count'        => $dataIds->count(),
        ], 201);
    }
}
