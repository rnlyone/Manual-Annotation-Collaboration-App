<?php

namespace App\Http\Controllers;

use App\Models\AiScreening;
use App\Models\Annotation;
use App\Models\Package;
use App\Models\PackageData;
use App\Models\Phase2Run;
use App\Models\UserPackage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Phase2Controller extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index()
    {
        $runs = Phase2Run::with(['sourcePackage:id,name', 'phase3Package:id,name'])
            ->latest()
            ->get()
            ->map(fn (Phase2Run $run) => [
                'id'                 => $run->id,
                'source_package'     => $run->sourcePackage?->name ?? '—',
                'source_package_id'  => $run->source_package_id,
                'phase3_package'     => $run->phase3Package?->name,
                'phase3_package_id'  => $run->phase3_package_id,
                'status'             => $run->status,
                'total_normal'       => $run->total_normal,
                'total_non_normal'   => $run->total_non_normal,
                'processed'          => $run->processed,
                'flagged_count'      => $run->flagged_count,
                'qc_sample_count'    => $run->qc_sample_count,
                'progress'           => $run->progressPercent(),
                'can_create_phase3'  => $run->canCreatePhase3(),
                'started_at'         => $run->started_at?->format('Y-m-d H:i'),
                'completed_at'       => $run->completed_at?->format('Y-m-d H:i'),
                'error_message'      => $run->error_message,
            ]);

        // Phase 1 packages only (type is null) that have at least some annotations
        $packages = Package::whereNull('type')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('_app.app', [
            'content'     => 'phase2.index',
            'headerdata'  => ['pagetitle' => 'Phase 2 — LLM Screening'],
            'sidenavdata' => ['active' => 'phase2'],
            'contentdata' => compact('runs'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:51200',
        ]);

        // Parse CSV
        // Accepted formats:
        //   Legacy:  id, llm_label, llm_confidence, llm_reasoning
        //   Current: data_id, llm_label, llm_confidence, llm_reasoning
        //            (plus optional: packages, annotation_labels, annotation_label_ids,
        //             content, phase3_group)
        $path   = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = array_map('strtolower', array_map('trim', fgetcsv($handle)));

        // Accept data_id (current format) or id (legacy format)
        $idCol = in_array('data_id', $header) ? 'data_id' : (in_array('id', $header) ? 'id' : null);
        if (! $idCol) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must have a data_id (or id) column.']);
        }

        $required = [$idCol, 'llm_label', 'llm_confidence', 'llm_reasoning'];
        foreach ($required as $col) {
            if (! in_array($col, $header)) {
                fclose($handle);
                return back()->withErrors(['csv_file' => "CSV is missing required column: {$col}"]);
            }
        }

        $idIdx         = array_search($idCol, $header);
        $labelIdx      = array_search('llm_label', $header);
        $confidenceIdx = array_search('llm_confidence', $header);
        $reasoningIdx  = array_search('llm_reasoning', $header);
        // phase3_group column: present in the Groq/Jupyter output CSV
        // Values: 'llm_flagged' | 'normal_sample_10pct'
        $groupIdx = array_search('phase3_group', $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) <= max($idIdx, $labelIdx, $confidenceIdx, $reasoningIdx)) {
                continue;
            }
            $dataId = trim($line[$idIdx]);
            if ($dataId === '') {
                continue;
            }

            $rawLabel = trim($line[$labelIdx]);
            // Normalise multi-label values (e.g. "Depresi|Cemas") — treat as flagged,
            // store the first component as the canonical llm_label.
            $normalised = $this->normaliseLlmLabel($rawLabel);

            $rows[$dataId] = [
                'data_id'       => $dataId,
                'llm_label'     => $normalised,
                'confidence'    => is_numeric(trim($line[$confidenceIdx])) ? (float) trim($line[$confidenceIdx]) : null,
                'reasoning'     => trim($line[$reasoningIdx]) ?: null,
                // phase3_group: if column is present use it, else null (will fall back to random sampling)
                'phase3_group'  => $groupIdx !== false ? strtolower(trim($line[$groupIdx])) : null,
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'CSV file contains no data rows.']);
        }

        // Map each data_id to its Phase 1 package(s)
        // A data_id can appear in multiple Phase 1 packages — we create one run per package
        $dataIds    = array_keys($rows);
        $packageMap = PackageData::whereIn('data_id', $dataIds)
            ->join('packages', 'packages.id', '=', 'package_data.package_id')
            ->whereNull('packages.type')   // Phase 1 packages only
            ->select('package_data.data_id', 'package_data.package_id')
            ->get()
            ->groupBy('package_id');

        if ($packageMap->isEmpty()) {
            return back()->withErrors(['csv_file' => 'None of the IDs in the CSV match any Phase 1 package.']);
        }

        $createdRunIds = [];

        DB::transaction(function () use ($rows, $packageMap, &$createdRunIds) {
            $now = now();

            foreach ($packageMap as $packageId => $packageRows) {
                // Build the subset of CSV rows for this package
                $packageDataIds = $packageRows->pluck('data_id')->flip();
                $subset         = array_values(array_filter($rows, fn ($r) => $packageDataIds->has($r['data_id'])));

                $totalNonNormal = $this->nonNormalDataIdsForPackage($packageId)->count();

                // Determine whether the CSV already carries phase3_group assignments.
                // If even one row has it, we trust the CSV completely; otherwise we fall
                // back to random 10 % sampling of LLM-confirmed Normal rows.
                $hasGroupColumn = collect($subset)->contains(fn ($r) => $r['phase3_group'] !== null);

                // Build QC index when falling back to random sampling
                $qcActualIndices = [];
                if (! $hasGroupColumn) {
                    $normalIndices = array_keys(array_filter($subset, fn ($r) => strtolower($r['llm_label']) === 'normal'));
                    if (! empty($normalIndices)) {
                        $qcCount   = max(1, (int) round(count($normalIndices) * 0.1));
                        $picked    = (array) array_rand($normalIndices, min($qcCount, count($normalIndices)));
                        $qcActualIndices = array_flip($picked);
                    }
                }

                $run = Phase2Run::create([
                    'source_package_id' => $packageId,
                    'status'            => 'running',
                    'started_at'        => $now,
                ]);

                $totalNormal   = 0;
                $flaggedCount  = 0;
                $qcSampleCount = 0;
                $inserts       = [];

                foreach ($subset as $idx => $row) {
                    $flagged = strtolower($row['llm_label']) !== 'normal';

                    if ($hasGroupColumn) {
                        $inQcSample = $row['phase3_group'] === 'normal_sample_10pct';
                    } else {
                        $inQcSample = isset($qcActualIndices[$idx]);
                    }

                    if (! $flagged) $totalNormal++;
                    if ($flagged)   $flaggedCount++;
                    if ($inQcSample) $qcSampleCount++;

                    $inserts[] = [
                        'phase2_run_id' => $run->id,
                        'data_id'       => $row['data_id'],
                        'llm_label'     => $row['llm_label'],
                        'confidence'    => $row['confidence'],
                        'reasoning'     => $row['reasoning'],
                        'flagged'       => $flagged,
                        'in_qc_sample'  => $inQcSample,
                        'status'        => 'done',
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }

                collect($inserts)->chunk(500)->each(fn ($chunk) => AiScreening::insert($chunk->all()));

                $run->update([
                    'status'           => 'completed',
                    'total_normal'     => $totalNormal + $flaggedCount,
                    'total_non_normal' => $totalNonNormal,
                    'processed'        => count($subset),
                    'flagged_count'    => $flaggedCount,
                    'qc_sample_count'  => $qcSampleCount,
                    'completed_at'     => $now,
                ]);

                $createdRunIds[] = $run->id;
            }
        });

        $count = count($createdRunIds);
        return redirect()->route('phase2.index')
            ->with('success', "CSV imported. {$count} run(s) created (one per matched package).");
    }

    public function destroy(Phase2Run $run)
    {
        DB::transaction(function () use ($run) {
            AiScreening::where('phase2_run_id', $run->id)->delete();
            $run->delete();
        });

        return redirect()->route('phase2.index')->with('success', "Run #{$run->id} deleted.");
    }

    public function show(Phase2Run $run)
    {
        $run->load(['sourcePackage:id,name', 'phase3Package:id,name']);

        // Paginated screenings for the detail table
        $screenings = AiScreening::where('phase2_run_id', $run->id)
            ->with(['data:id,content'])
            ->orderByDesc('flagged')
            ->orderByDesc('confidence')
            ->paginate(50);

        // Compute LCR if Phase 3 package exists and has annotations
        $lcr = null;
        if ($run->phase3_package_id && $run->flagged_count > 0) {
            $flaggedDataIds = AiScreening::where('phase2_run_id', $run->id)
                ->where('flagged', true)
                ->pluck('data_id');

            // How many of these flagged items ended up with a non-Normal label in Phase 3?
            $confirmedDasCount = Annotation::query()
                ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
                ->where('package_data.package_id', $run->phase3_package_id)
                ->whereIn('annotations.data_id', $flaggedDataIds)
                ->whereNotNull('annotations.category_ids')
                ->where('annotations.category_ids', '!=', '[]')
                ->where('annotations.category_ids', '!=', '')
                ->distinct('annotations.data_id')
                ->count('annotations.data_id');

            $lcr = $run->flagged_count > 0
                ? round($confirmedDasCount / $run->flagged_count, 3)
                : null;
        }

        // Compute FNR from 10% QC sample if Phase 3 exists
        $fnr = null;
        if ($run->phase3_package_id && $run->qc_sample_count > 0) {
            $qcDataIds = AiScreening::where('phase2_run_id', $run->id)
                ->where('in_qc_sample', true)
                ->pluck('data_id');

            $foundDasInQc = Annotation::query()
                ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
                ->where('package_data.package_id', $run->phase3_package_id)
                ->whereIn('annotations.data_id', $qcDataIds)
                ->whereNotNull('annotations.category_ids')
                ->where('annotations.category_ids', '!=', '[]')
                ->where('annotations.category_ids', '!=', '')
                ->distinct('annotations.data_id')
                ->count('annotations.data_id');

            $fnr = $run->qc_sample_count > 0
                ? round($foundDasInQc / $run->qc_sample_count, 3)
                : null;
        }

        // Live Phase 1 non-normal count from annotations
        // (stored $run->total_non_normal may be stale/zero for old CSV imports)
        $nonNormalDataIds = $this->nonNormalDataIdsForPackage($run->source_package_id);
        $nonNormalCount   = $nonNormalDataIds->count();
        $missingNonNormalCount = 0;

        if ($run->phase3_package_id && $nonNormalCount > 0) {
            $alreadyInPhase3       = PackageData::where('package_id', $run->phase3_package_id)
                ->whereIn('data_id', $nonNormalDataIds)
                ->count();
            $missingNonNormalCount = max(0, $nonNormalCount - $alreadyInPhase3);
        }

        return view('_app.app', [
            'content'     => 'phase2.show',
            'headerdata'  => ['pagetitle' => 'Phase 2 Run #' . $run->id],
            'sidenavdata' => ['active' => 'phase2'],
            'contentdata' => compact('run', 'screenings', 'lcr', 'fnr', 'nonNormalCount', 'missingNonNormalCount'),
        ]);
    }

    public function createPhase3(Phase2Run $run)
    {
        if (! $run->canCreatePhase3()) {
            return back()->withErrors(['run' => 'Cannot create Phase 3 package from this run.']);
        }

        $sourcePackage = Package::findOrFail($run->source_package_id);

        DB::transaction(function () use ($run, $sourcePackage) {
            // Create the Phase 3 package
            $phase3Package = Package::create([
                'name' => 'Phase 3 — ' . $sourcePackage->name,
                'type' => 'phase3',
            ]);

            // Collect data_ids to include:
            // 1) LLM-flagged Normal items
            $flaggedDataIds = AiScreening::where('phase2_run_id', $run->id)
                ->where('flagged', true)
                ->pluck('data_id');

            // 2) 10% QC sample of unflagged Normals
            $qcDataIds = AiScreening::where('phase2_run_id', $run->id)
                ->where('in_qc_sample', true)
                ->pluck('data_id');

            // 3) Phase 1 Non-Normal items from actual Phase 1 annotator annotations.
            $nonNormalDataIds = $this->nonNormalDataIdsForPackage($run->source_package_id);

            $allDataIds = $flaggedDataIds
                ->merge($qcDataIds)
                ->merge($nonNormalDataIds)
                ->unique()
                ->values();

            // Insert into package_data
            $inserts = $allDataIds->map(fn ($dataId) => [
                'package_id' => $phase3Package->id,
                'data_id'    => $dataId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            // Chunk to avoid huge inserts
            collect($inserts)->chunk(500)->each(function ($chunk) {
                PackageData::insertOrIgnore($chunk->all());
            });

            // Assign the same annotators from the source package
            $annotatorIds = UserPackage::where('package_id', $run->source_package_id)->pluck('user_id');
            $userPackageInserts = $annotatorIds->map(fn ($userId) => [
                'user_id'    => $userId,
                'package_id' => $phase3Package->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($userPackageInserts)) {
                UserPackage::insertOrIgnore($userPackageInserts);
            }

            // Update the run to link Phase 3 package
            $run->update(['phase3_package_id' => $phase3Package->id]);

            // Notify annotators
            if ($annotatorIds->isNotEmpty()) {
                $this->notificationService->sendToUsers(
                    $annotatorIds->all(),
                    "Phase 3 re-annotation package \"{$phase3Package->name}\" has been created and assigned to you.",
                    'phase3_assigned'
                );
            }
        });

        return redirect()->route('phase2.show', $run->id)
            ->with('success', 'Phase 3 package created and annotators notified.');
    }

    /**
     * Add any Phase 1 non-normal items that are missing from an existing Phase 3 package.
     * Needed when Phase 3 was created before this annotation-based detection was in place.
     */
    public function syncNonNormalToPhase3(Phase2Run $run)
    {
        if (! $run->phase3_package_id) {
            return back()->withErrors(['run' => 'No Phase 3 package linked to this run.']);
        }

        $nonNormalDataIds = $this->nonNormalDataIdsForPackage($run->source_package_id);

        if ($nonNormalDataIds->isEmpty()) {
            return redirect()->route('phase2.show', $run->id)
                ->with('success', 'No Phase 1 non-normal items found to sync.');
        }

        $inserts = $nonNormalDataIds->map(fn ($dataId) => [
            'package_id' => $run->phase3_package_id,
            'data_id'    => $dataId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        collect($inserts)->chunk(500)->each(function ($chunk) {
            PackageData::insertOrIgnore($chunk->all());
        });

        // Update the stored non-normal count on the run
        $run->update(['total_non_normal' => $nonNormalDataIds->count()]);

        return redirect()->route('phase2.show', $run->id)
            ->with('success', "Synced {$nonNormalDataIds->count()} Phase 1 non-normal items into the Phase 3 package.");
    }

    /**
     * Normalise raw LLM labels to a single canonical value.
     *
     * The Groq/Jupyter output sometimes produces multi-label strings like
     * "Depresi|Cemas" or uses the Indonesian form "Cemas" (instead of "Ansietas").
     * We store the first component so the value fits in a single VARCHAR column.
     * Multi-label rows are always treated as flagged (non-Normal).
     */
    private function normaliseLlmLabel(string $raw): string
    {
        // Take first component when pipe-separated
        $first = trim(explode('|', $raw)[0]);

        // Map Indonesian variants to canonical form
        return match (strtolower($first)) {
            'cemas'   => 'Ansietas',
            'normal'  => 'Normal',
            'depresi' => 'Depresi',
            'stres'   => 'Stres',
            default   => $first ?: $raw,
        };
    }

    /**
     * Return data_ids of Phase 1 non-Normal (DAS) items in the given source package.
     *
     * Scoped to users assigned to that package so we only read Phase 1 labels.
     * The annotation system prevents those users from re-annotating the same items
     * in Phase 3, so their records always reflect the original Phase 1 label.
     *
     * Uses plain string comparisons — compatible with both SQLite and MySQL.
     * Normal = empty category_ids array ([]); anything else = DAS.
     */
    private function nonNormalDataIdsForPackage(int $sourcePackageId): \Illuminate\Support\Collection
    {
        // annotated_at stores the package_id of the package the annotation was made in.
        // Filtering on annotated_at = $sourcePackageId ensures we only count annotations
        // actually submitted during Phase 1 work for this package — never Phase 3 re-annotations
        // of the same data items by the same users.
        return Annotation::query()
            ->select('data_id')
            ->where('annotated_at', $sourcePackageId)
            ->whereNotNull('category_ids')
            ->where('category_ids', '!=', '[]')
            ->where('category_ids', '!=', '')
            ->distinct()
            ->pluck('data_id');
    }

}
