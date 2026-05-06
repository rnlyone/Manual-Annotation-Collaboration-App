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
            'contentdata' => compact('runs', 'packages'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'source_package_id' => 'required|integer|exists:packages,id',
            'csv_file'          => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $package = Package::findOrFail($request->input('source_package_id'));

        if ($package->type === 'phase3') {
            return back()->withErrors(['source_package_id' => 'Cannot run Phase 2 on a Phase 3 package.']);
        }

        // Parse CSV
        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = array_map('strtolower', array_map('trim', fgetcsv($handle)));

        $required = ['id', 'llm_label', 'llm_confidence', 'llm_reasoning'];
        foreach ($required as $col) {
            if (! in_array($col, $header)) {
                fclose($handle);
                return back()->withErrors(['csv_file' => "CSV is missing required column: {$col}"]);  
            }
        }

        $idIdx         = array_search('id', $header);
        $labelIdx      = array_search('llm_label', $header);
        $confidenceIdx = array_search('llm_confidence', $header);
        $reasoningIdx  = array_search('llm_reasoning', $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) <= max($idIdx, $labelIdx, $confidenceIdx, $reasoningIdx)) {
                continue;
            }
            $rows[] = [
                'data_id'    => trim($line[$idIdx]),
                'llm_label'  => trim($line[$labelIdx]),
                'confidence' => is_numeric(trim($line[$confidenceIdx])) ? (float) trim($line[$confidenceIdx]) : null,
                'reasoning'  => trim($line[$reasoningIdx]) ?: null,
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'CSV file contains no data rows.']);
        }

        // Validate all data_ids exist in the source package
        $packageDataIds = PackageData::where('package_id', $package->id)
            ->pluck('data_id')
            ->flip();

        $invalidIds = array_filter(array_column($rows, 'data_id'), fn ($id) => ! $packageDataIds->has($id));
        if (! empty($invalidIds)) {
            $sample = implode(', ', array_slice($invalidIds, 0, 3));
            return back()->withErrors(['csv_file' => "Some IDs in the CSV are not in the selected package: {$sample}" . (count($invalidIds) > 3 ? ' ...' : '')]);
        }

        // Determine QC sample (10% random of Normal-labeled items)
        $normalIndices = array_keys(array_filter($rows, fn ($r) => strtolower($r['llm_label']) === 'normal'));
        $qcCount       = max(1, (int) round(count($normalIndices) * 0.1));
        $qcIndices     = array_flip((array) array_rand($normalIndices, min($qcCount, count($normalIndices))));
        $qcActualIndices = [];
        foreach (array_values($normalIndices) as $i => $rowIdx) {
            if (isset($qcIndices[$i])) {
                $qcActualIndices[$rowIdx] = true;
            }
        }

        $runId = null;
        DB::transaction(function () use ($package, $rows, $qcActualIndices, $packageDataIds, &$runId) {
            $totalNormal    = 0;
            $flaggedCount   = 0;
            $qcSampleCount  = 0;
            $now            = now();

            $run = Phase2Run::create([
                'source_package_id' => $package->id,
                'status'            => 'running',
                'started_at'        => $now,
            ]);

            // total_non_normal = items in package not in CSV
            $csvIds = array_flip(array_column($rows, 'data_id'));
            $totalNonNormal = $packageDataIds->filter(fn ($_, $id) => ! isset($csvIds[$id]))->count();

            $inserts = [];
            foreach ($rows as $idx => $row) {
                $flagged     = strtolower($row['llm_label']) !== 'normal';
                $inQcSample  = isset($qcActualIndices[$idx]);

                if (! $flagged) {
                    $totalNormal++;
                }
                if ($flagged) {
                    $flaggedCount++;
                }
                if ($inQcSample) {
                    $qcSampleCount++;
                }

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
                'status'          => 'completed',
                'total_normal'    => $totalNormal + $flaggedCount, // all csv rows were originally Normal
                'total_non_normal'=> $totalNonNormal,
                'processed'       => count($rows),
                'flagged_count'   => $flaggedCount,
                'qc_sample_count' => $qcSampleCount,
                'completed_at'    => now(),
            ]);

            $completedRun = $run;
            $runId = $run->id;
        });

        return redirect()->route('phase2.show', $runId)
            ->with('success', 'CSV imported successfully. Run completed.');
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
                ->whereRaw("JSON_LENGTH(annotations.category_ids) > 0")
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
                ->whereRaw("JSON_LENGTH(annotations.category_ids) > 0")
                ->distinct('annotations.data_id')
                ->count('annotations.data_id');

            $fnr = $run->qc_sample_count > 0
                ? round($foundDasInQc / $run->qc_sample_count, 3)
                : null;
        }

        return view('_app.app', [
            'content'     => 'phase2.show',
            'headerdata'  => ['pagetitle' => 'Phase 2 Run #' . $run->id],
            'sidenavdata' => ['active' => 'phase2'],
            'contentdata' => compact('run', 'screenings', 'lcr', 'fnr'),
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

            // 3) Non-Normal items from Phase 1 source package
            //    (items in source package that are NOT in ai_screenings = non-Normal)
            $screened = AiScreening::where('phase2_run_id', $run->id)->pluck('data_id')->flip();
            $allPackageDataIds = PackageData::where('package_id', $run->source_package_id)->pluck('data_id');
            $nonNormalDataIds  = $allPackageDataIds->filter(fn ($id) => ! $screened->has($id));

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

}
