<?php

namespace App\Http\Controllers;

use App\Jobs\RunPhase2Screening;
use App\Jobs\RunPhase2ScreeningGroq;
use App\Jobs\RunPhase2ScreeningSync;
use App\Models\AiScreening;
use App\Models\AiSetting;
use App\Models\Annotation;
use App\Models\Package;
use App\Models\PackageData;
use App\Models\Phase2Run;
use App\Models\UserPackage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $hasApiKey = AiSetting::singleton()->hasOpenAiApiKey();

        return view('_app.app', [
            'content'     => 'phase2.index',
            'headerdata'  => ['pagetitle' => 'Phase 2 — LLM Screening'],
            'sidenavdata' => ['active' => 'phase2'],
            'contentdata' => compact('runs', 'packages', 'hasApiKey'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_package_id' => 'required|integer|exists:packages,id',
        ]);

        $package = Package::findOrFail($validated['source_package_id']);

        if ($package->type === 'phase3') {
            return back()->withErrors(['source_package_id' => 'Cannot run Phase 2 on a Phase 3 package.']);
        }

        // Prevent duplicate active runs on the same package.
        // batch_submitted runs are passive (waiting on OpenAI); only block a new
        // batch run from being submitted alongside one — not a standard sync run.
        $settings       = AiSetting::singleton();
        $blockingStatuses = ['pending', 'running'];
        if ($settings->use_batch_api) {
            $blockingStatuses[] = 'batch_submitted';
        }

        $alreadyRunning = Phase2Run::where('source_package_id', $package->id)
            ->whereIn('status', $blockingStatuses)
            ->exists();

        if ($alreadyRunning) {
            return back()->withErrors(['source_package_id' => 'A Phase 2 run for this package is already in progress.']);
        }

        $run = Phase2Run::create([
            'source_package_id' => $package->id,
            'status'            => 'pending',
        ]);

        if ($settings->provider === AiSetting::PROVIDER_GROQ) {
            RunPhase2ScreeningGroq::dispatch($run->id);
        } elseif ($settings->use_batch_api) {
            RunPhase2Screening::dispatchSync($run->id);
        } else {
            RunPhase2ScreeningSync::dispatch($run->id);
        }

        return redirect()->route('phase2.show', $run->id)
            ->with('success', 'Phase 2 screening started. Refresh this page to check progress.');
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

        $errorCount = AiScreening::where('phase2_run_id', $run->id)
            ->where('status', 'error')
            ->count();

        return view('_app.app', [
            'content'     => 'phase2.show',
            'headerdata'  => ['pagetitle' => 'Phase 2 Run #' . $run->id],
            'sidenavdata' => ['active' => 'phase2'],
            'contentdata' => compact('run', 'screenings', 'lcr', 'fnr', 'errorCount'),
        ]);
    }

    public function cancel(Phase2Run $run)
    {
        if (! in_array($run->status, ['pending', 'running', 'batch_submitted'])) {
            return back()->withErrors(['run' => 'This run cannot be cancelled.']);
        }

        // Cancel the OpenAI batch if one is in flight
        if ($run->openai_batch_id && $run->status === 'batch_submitted') {
            $apiKey = AiSetting::singleton()->getOpenAiApiKey();
            if ($apiKey) {
                Http::withToken($apiKey)
                    ->timeout(15)
                    ->post("https://api.openai.com/v1/batches/{$run->openai_batch_id}/cancel");
            }
        }

        $run->update(['status' => 'cancelled', 'completed_at' => now()]);

        return redirect()->route('phase2.index')->with('success', 'Run cancelled.');
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

    public function retryErrors(Phase2Run $run)
    {
        if (! in_array($run->status, ['completed', 'failed'])) {
            return back()->withErrors(['run' => 'Can only retry errors on completed or failed runs.']);
        }

        $errorCount   = AiScreening::where('phase2_run_id', $run->id)->where('status', 'error')->count();
        $missingCount = max(0, $run->total_normal - AiScreening::where('phase2_run_id', $run->id)->count());
        $totalToRetry = $errorCount + $missingCount;

        if ($totalToRetry === 0) {
            return back()->with('success', 'No error or missing items to retry.');
        }

        if ($errorCount > 0) {
            AiScreening::where('phase2_run_id', $run->id)
                ->where('status', 'error')
                ->delete();
            $run->decrement('processed', $errorCount);
        }

        $run->update(['status' => 'pending', 'error_message' => null]);

        $settings = AiSetting::singleton();
        if ($settings->provider === AiSetting::PROVIDER_GROQ) {
            RunPhase2ScreeningGroq::dispatch($run->id);
        } elseif ($settings->use_batch_api) {
            RunPhase2Screening::dispatchSync($run->id);
        } else {
            RunPhase2ScreeningSync::dispatch($run->id);
        }

        return redirect()->route('phase2.show', $run->id)
            ->with('success', "Retrying {$totalToRetry} item(s) ({$errorCount} errors, {$missingCount} missing).");
    }
}
