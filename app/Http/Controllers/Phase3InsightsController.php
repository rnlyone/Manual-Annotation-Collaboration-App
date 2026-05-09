<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\AiScreening;
use App\Models\Category;
use App\Models\PackageData;
use App\Models\Phase2Run;

class Phase3InsightsController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->pluck('name', 'id');

        $runs = Phase2Run::whereNotNull('phase3_package_id')
            ->where('status', 'completed')
            ->with(['phase3Package:id,name', 'sourcePackage:id,name'])
            ->get();

        if ($runs->isEmpty()) {
            return view('_app.app', [
                'content'     => 'phase3.insights',
                'headerdata'  => ['pagetitle' => 'Phase 3 Insights'],
                'sidenavdata' => ['active' => 'phase3.insights'],
                'contentdata' => ['empty' => true],
            ]);
        }

        $phase3PackageIds = $runs->pluck('phase3_package_id');

        // ── Bulk-load all base data (≈5 queries total) ───────────────────────

        $allPhase3Items = PackageData::whereIn('package_id', $phase3PackageIds)
            ->get(['package_id', 'data_id']);

        $totalItems = $allPhase3Items->count();

        // All Phase 3 annotations scoped to their package via annotated_at
        $allAnnotations = Annotation::whereIn('annotated_at', $phase3PackageIds)
            ->get(['id', 'data_id', 'annotated_at', 'category_ids', 'user_id']);

        // Group by composite key "data_id|package_id"
        $annotsByItemKey = $allAnnotations->groupBy(
            fn ($a) => $a->data_id . '|' . $a->annotated_at
        );

        // ai_screenings per run, only for items that ended up in Phase 3
        $phase3DataIdsByPackage = $allPhase3Items->groupBy('package_id')
            ->map(fn ($items) => $items->pluck('data_id'));

        $allScreenings = collect();
        foreach ($runs as $run) {
            $pkgDataIds = $phase3DataIdsByPackage[$run->phase3_package_id] ?? collect();
            if ($pkgDataIds->isEmpty()) {
                continue;
            }

            $screenings = AiScreening::where('phase2_run_id', $run->id)
                ->whereIn('data_id', $pkgDataIds)
                ->get(['data_id', 'flagged', 'in_qc_sample', 'llm_label', 'phase1_label'])
                ->each(fn ($s) => $s->phase3_package_id = $run->phase3_package_id);

            $allScreenings = $allScreenings->merge($screenings);
        }

        // Index screenings by composite key
        $screeningsByItemKey = $allScreenings->keyBy(
            fn ($s) => $s->data_id . '|' . $s->phase3_package_id
        );

        // ── Annotation progress buckets (0 / 1 / 2 / 3+) ────────────────────

        $progressBuckets = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($allPhase3Items as $item) {
            $key    = $item->data_id . '|' . $item->package_id;
            $count  = isset($annotsByItemKey[$key]) ? $annotsByItemKey[$key]->count() : 0;
            $bucket = min($count, 3);
            $progressBuckets[$bucket]++;
        }

        // ── Source breakdown ──────────────────────────────────────────────────

        $sourceBreakdown = ['llm_flagged' => 0, 'qc_sample' => 0, 'non_normal' => 0];
        foreach ($allPhase3Items as $item) {
            $key       = $item->data_id . '|' . $item->package_id;
            $screening = $screeningsByItemKey->get($key);

            if (! $screening) {
                $sourceBreakdown['non_normal']++;
            } elseif ($screening->flagged) {
                $sourceBreakdown['llm_flagged']++;
            } elseif ($screening->in_qc_sample) {
                $sourceBreakdown['qc_sample']++;
            } else {
                $sourceBreakdown['non_normal']++;
            }
        }

        // ── Inter-Annotator Agreement (items with ≥ 3 annotations) ───────────
        //
        // Binary classification: Normal (empty category_ids) vs DAS (non-empty).
        // Fleiss' κ for binary with k=3 raters:
        //   P_obs = (1/N) * Σ_i  [n_das(n_das-1) + n_norm(n_norm-1)] / [k(k-1)]
        //   p_das  = (Σ n_das) / (N * k)
        //   P_exp  = p_das² + (1-p_das)²
        //   κ      = (P_obs - P_exp) / (1 - P_exp)

        $iaaStats   = ['full_das' => 0, 'full_normal' => 0, 'majority_das' => 0, 'majority_normal' => 0];
        $kappaSumPi = 0.0;
        $kappaDasVotes  = 0;
        $kappaTotalVotes = 0;
        $iaaTotal   = 0;

        foreach ($allPhase3Items as $item) {
            $key    = $item->data_id . '|' . $item->package_id;
            $annots = $annotsByItemKey[$key] ?? collect();
            if ($annots->count() < 3) {
                continue;
            }

            $first3      = $annots->take(3);
            $dasVotes    = $first3->filter(fn ($a) => ! empty($a->category_ids))->count();
            $normalVotes = 3 - $dasVotes;

            if ($dasVotes === 3)         $iaaStats['full_das']++;
            elseif ($normalVotes === 3)  $iaaStats['full_normal']++;
            elseif ($dasVotes >= 2)      $iaaStats['majority_das']++;
            else                         $iaaStats['majority_normal']++;

            $kappaSumPi      += ($dasVotes * ($dasVotes - 1) + $normalVotes * ($normalVotes - 1)) / 6;
            $kappaDasVotes   += $dasVotes;
            $kappaTotalVotes += 3;
            $iaaTotal++;
        }

        $fleissKappa      = null;
        $kappaInterpret   = null;
        $pctFullAgreement = 0;
        $fullAgreement    = $iaaStats['full_das'] + $iaaStats['full_normal'];

        if ($iaaTotal > 0) {
            $pObs = $kappaSumPi / $iaaTotal;
            $pDas = $kappaTotalVotes > 0 ? $kappaDasVotes / $kappaTotalVotes : 0;
            $pExp = $pDas ** 2 + (1 - $pDas) ** 2;

            $fleissKappa      = $pExp < 1 ? round(($pObs - $pExp) / (1 - $pExp), 3) : 1.0;
            $pctFullAgreement = round($fullAgreement / $iaaTotal * 100, 1);

            if ($fleissKappa < 0)          $kappaInterpret = ['label' => 'Less than chance',    'color' => 'danger'];
            elseif ($fleissKappa < 0.20)   $kappaInterpret = ['label' => 'Slight agreement',    'color' => 'danger'];
            elseif ($fleissKappa < 0.40)   $kappaInterpret = ['label' => 'Fair agreement',      'color' => 'warning'];
            elseif ($fleissKappa < 0.60)   $kappaInterpret = ['label' => 'Moderate agreement',  'color' => 'info'];
            elseif ($fleissKappa < 0.80)   $kappaInterpret = ['label' => 'Substantial agreement', 'color' => 'primary'];
            else                           $kappaInterpret = ['label' => 'Almost perfect',       'color' => 'success'];
        }

        // ── LLM vs Human comparison ───────────────────────────────────────────

        $llmComparison = [
            'flagged_confirmed_das'    => 0,
            'flagged_confirmed_normal' => 0,
            'flagged_no_votes'         => 0,
            'qc_das_found'             => 0,
            'qc_confirmed_normal'      => 0,
            'qc_no_votes'              => 0,
        ];

        foreach ($allPhase3Items as $item) {
            $key       = $item->data_id . '|' . $item->package_id;
            $screening = $screeningsByItemKey->get($key);
            if (! $screening || (! $screening->flagged && ! $screening->in_qc_sample)) {
                continue;
            }

            $annots = $annotsByItemKey[$key] ?? collect();
            if ($annots->isEmpty()) {
                $screening->flagged ? $llmComparison['flagged_no_votes']++ : $llmComparison['qc_no_votes']++;
                continue;
            }

            $dasVotes      = $annots->filter(fn ($a) => ! empty($a->category_ids))->count();
            $majorityIsDas = $dasVotes > ($annots->count() / 2);

            if ($screening->flagged) {
                $majorityIsDas ? $llmComparison['flagged_confirmed_das']++ : $llmComparison['flagged_confirmed_normal']++;
            } else {
                $majorityIsDas ? $llmComparison['qc_das_found']++ : $llmComparison['qc_confirmed_normal']++;
            }
        }

        $flaggedAnnotated = $llmComparison['flagged_confirmed_das'] + $llmComparison['flagged_confirmed_normal'];
        $llmPrecision     = $flaggedAnnotated > 0
            ? round($llmComparison['flagged_confirmed_das'] / $flaggedAnnotated * 100, 1)
            : null;

        $qcAnnotated = $llmComparison['qc_das_found'] + $llmComparison['qc_confirmed_normal'];
        $fnrEstimate = $qcAnnotated > 0
            ? round($llmComparison['qc_das_found'] / $qcAnnotated * 100, 1)
            : null;

        // ── Category label frequency (all Phase 3 annotations) ───────────────

        $categoryFrequency = [];
        $normalAnnotCount  = 0;
        foreach ($allAnnotations as $annotation) {
            if (empty($annotation->category_ids)) {
                $normalAnnotCount++;
            } else {
                foreach ($annotation->category_ids as $catId) {
                    $name = $categories[$catId] ?? "Category #{$catId}";
                    $categoryFrequency[$name] = ($categoryFrequency[$name] ?? 0) + 1;
                }
            }
        }
        arsort($categoryFrequency);

        // ── LLM label distribution ────────────────────────────────────────────

        $llmLabelCounts = $allScreenings->groupBy('llm_label')
            ->map(fn ($g) => $g->count())
            ->sortDesc();

        // ── Per-run summary table ─────────────────────────────────────────────

        $runSummaries = $runs->map(function ($run) use ($phase3DataIdsByPackage, $annotsByItemKey, $screeningsByItemKey) {
            $pkgDataIds = $phase3DataIdsByPackage[$run->phase3_package_id] ?? collect();
            $total      = $pkgDataIds->count();
            $fullyDone  = 0;
            $started    = 0;

            foreach ($pkgDataIds as $dataId) {
                $key   = $dataId . '|' . $run->phase3_package_id;
                $count = isset($annotsByItemKey[$key]) ? $annotsByItemKey[$key]->count() : 0;
                if ($count >= 3) $fullyDone++;
                if ($count >= 1) $started++;
            }

            return [
                'run_id'         => $run->id,
                'source'         => $run->sourcePackage?->name ?? '—',
                'phase3_package' => $run->phase3Package?->name ?? '—',
                'total'          => $total,
                'started'        => $started,
                'fully_done'     => $fullyDone,
                'progress_pct'   => $total > 0 ? round($started / $total * 100, 1) : 0,
            ];
        });

        return view('_app.app', [
            'content'     => 'phase3.insights',
            'headerdata'  => ['pagetitle' => 'Phase 3 Insights'],
            'sidenavdata' => ['active' => 'phase3.insights'],
            'contentdata' => [
                'empty'              => false,
                'runs'               => $runSummaries,
                'totalItems'         => $totalItems,
                'progressBuckets'    => $progressBuckets,
                'sourceBreakdown'    => $sourceBreakdown,
                'iaaStats'           => $iaaStats,
                'iaaTotal'           => $iaaTotal,
                'fleissKappa'        => $fleissKappa,
                'kappaInterpret'     => $kappaInterpret,
                'pctFullAgreement'   => $pctFullAgreement,
                'fullAgreement'      => $fullAgreement,
                'llmComparison'      => $llmComparison,
                'llmPrecision'       => $llmPrecision,
                'fnrEstimate'        => $fnrEstimate,
                'flaggedAnnotated'   => $flaggedAnnotated,
                'qcAnnotated'        => $qcAnnotated,
                'categoryFrequency'  => $categoryFrequency,
                'normalAnnotCount'   => $normalAnnotCount,
                'llmLabelCounts'     => $llmLabelCounts,
            ],
        ]);
    }
}
