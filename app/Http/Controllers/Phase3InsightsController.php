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

        // ── Annotation progress buckets ───────────────────────────────────────
        //
        // Count DISTINCT human annotators per Phase 3 item, combining:
        //   Phase 1 annotations (annotated_at = source_package_id)
        //   Phase 3 annotations (annotated_at = phase3_package_id)
        //
        //   1 annotator → Phase 1 only (not yet Phase 3 reviewed)
        //   2 annotators → Phase 3 ongoing (1 Phase 3 reviewer)
        //   3 annotators → Phase 3 complete (2 Phase 3 reviewers)

        $sourcePackageIds  = $runs->pluck('source_package_id');
        $phase3ToSource    = $runs->pluck('source_package_id', 'phase3_package_id');
        $phase3DataIds     = $allPhase3Items->pluck('data_id')->unique();

        // Load Phase 1 annotations (same data_ids, but annotated_at = source package)
        $phase1Annotations = Annotation::whereIn('annotated_at', $sourcePackageIds)
            ->whereIn('data_id', $phase3DataIds)
            ->get(['data_id', 'annotated_at', 'user_id'])
            ->groupBy(fn ($a) => $a->data_id . '|' . $a->annotated_at);

        $progressBuckets = [1 => 0, 2 => 0, 3 => 0];
        foreach ($allPhase3Items as $item) {
            $p3Key    = $item->data_id . '|' . $item->package_id;
            $srcPkgId = $phase3ToSource[$item->package_id] ?? null;
            $p1Key    = $srcPkgId ? ($item->data_id . '|' . $srcPkgId) : null;

            $p1Users = $p1Key && isset($phase1Annotations[$p1Key])
                ? $phase1Annotations[$p1Key]->pluck('user_id')
                : collect();

            $p3Users = isset($annotsByItemKey[$p3Key])
                ? $annotsByItemKey[$p3Key]->pluck('user_id')
                : collect();

            $distinctUsers = $p1Users->merge($p3Users)->unique()->count();
            $progressBuckets[max(1, min($distinctUsers, 3))]++;
        }

        // ── Source breakdown ──────────────────────────────────────────────────

        $sourceBreakdown = ['llm_flagged' => 0, 'qc_sample' => 0, 'non_normal' => 0];
        foreach ($allPhase3Items as $item) {
            $key       = $item->data_id . '|' . $item->package_id;
            $screening = $screeningsByItemKey->get($key);

            // Priority: Phase 1 non-normal label > LLM flagged > QC sample.
            // An item with a non-normal Phase 1 label is in Phase 3 primarily because
            // it was already labelled non-normal by a human — even if the LLM also
            // flagged it. Check that condition first so those items are not swallowed
            // by the LLM-flagged bucket.
            $p1IsNonNormal = $screening && $screening->phase1_label && $screening->phase1_label !== 'Normal';

            if (! $screening || $p1IsNonNormal) {
                $sourceBreakdown['non_normal']++;
            } elseif ($screening->flagged) {
                $sourceBreakdown['llm_flagged']++;
            } elseif ($screening->in_qc_sample) {
                $sourceBreakdown['qc_sample']++;
            } else {
                $sourceBreakdown['non_normal']++;
            }
        }

        // ── Inter-Annotator Agreement (items with ≥ 2 annotations) ───────────
        //
        // Binary classification: Normal (empty category_ids) vs DAS (non-empty).
        // Variable-k Fleiss' κ (works for 2 or 3 raters per item):
        //   For each item i with k_i raters:
        //     P_i   = [n_das(n_das-1) + n_norm(n_norm-1)] / [k_i*(k_i-1)]
        //   p_das   = Σ(n_das_i) / Σ(k_i)
        //   P_obs   = mean(P_i)
        //   P_exp   = p_das² + (1-p_das)²
        //   κ       = (P_obs - P_exp) / (1 - P_exp)

        $iaaStats   = ['full_das' => 0, 'full_normal' => 0, 'majority_das' => 0, 'majority_normal' => 0, 'split' => 0];
        $kappaSumPi = 0.0;
        $kappaDasVotes   = 0;
        $kappaTotalVotes = 0;
        $iaaTotal    = 0;   // items with ≥ 2 annotations
        $iaaTotal3   = 0;   // items with exactly ≥ 3 annotations

        foreach ($allPhase3Items as $item) {
            $key    = $item->data_id . '|' . $item->package_id;
            $annots = $annotsByItemKey[$key] ?? collect();
            $ki     = $annots->count();
            if ($ki < 2) {
                continue;
            }

            $dasVotes    = $annots->filter(fn ($a) => ! empty($a->category_ids))->count();
            $normalVotes = $ki - $dasVotes;

            if ($dasVotes === $ki)         $iaaStats['full_das']++;
            elseif ($normalVotes === $ki)  $iaaStats['full_normal']++;
            elseif ($ki === 2)             $iaaStats['split']++;     // 1-1 tie, only possible with 2 raters
            elseif ($dasVotes > $ki / 2)   $iaaStats['majority_das']++;
            else                           $iaaStats['majority_normal']++;

            // Variable-k Fleiss' κ contribution
            $kappaSumPi      += ($ki > 1) ? ($dasVotes * ($dasVotes - 1) + $normalVotes * ($normalVotes - 1)) / ($ki * ($ki - 1)) : 0;
            $kappaDasVotes   += $dasVotes;
            $kappaTotalVotes += $ki;
            $iaaTotal++;
            if ($ki >= 3) $iaaTotal3++;
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

        // ── LLM label vs Human label cross-tabulation ─────────────────────────
        //
        // For each Phase 3 item that has a screening with llm_label set AND at
        // least one human annotation, we determine the human "majority label"
        // (most-voted category name, or "Normal" if empty-category votes win),
        // then tally into a matrix: llmLabel → humanLabel → count.

        $llmVsHuman = [];   // [ llmLabel => [ humanLabel => count ] ]

        foreach ($allPhase3Items as $item) {
            $key       = $item->data_id . '|' . $item->package_id;
            $screening = $screeningsByItemKey->get($key);
            $annots    = $annotsByItemKey[$key] ?? collect();

            if (! $screening || ! $screening->llm_label || $annots->isEmpty()) {
                continue;
            }

            $llmLabel = $screening->llm_label;

            // Tally human votes per category (Normal counts as its own bucket)
            $humanVotes = ['Normal' => 0];
            foreach ($annots as $annot) {
                if (empty($annot->category_ids)) {
                    $humanVotes['Normal']++;
                } else {
                    foreach ($annot->category_ids as $catId) {
                        $name = $categories[$catId] ?? "Category #{$catId}";
                        $humanVotes[$name] = ($humanVotes[$name] ?? 0) + 1;
                    }
                }
            }

            // Majority human label = highest vote count
            arsort($humanVotes);
            $majorityHumanLabel = array_key_first($humanVotes);

            $llmVsHuman[$llmLabel][$majorityHumanLabel] = ($llmVsHuman[$llmLabel][$majorityHumanLabel] ?? 0) + 1;
        }

        // Sort by LLM label name; collect all human labels seen (for legend)
        ksort($llmVsHuman);
        $allHumanLabels = collect($llmVsHuman)
            ->flatMap(fn ($row) => array_keys($row))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Move "Normal" to the front for consistent chart ordering
        $allHumanLabels = array_unique(array_merge(
            in_array('Normal', $allHumanLabels) ? ['Normal'] : [],
            array_filter($allHumanLabels, fn ($l) => $l !== 'Normal')
        ));

        // ── Per-run summary table ─────────────────────────────────────────────

        $runSummaries = $runs->map(function ($run) use ($phase3DataIdsByPackage, $annotsByItemKey, $screeningsByItemKey, $phase1Annotations, $phase3ToSource) {
            $pkgDataIds = $phase3DataIdsByPackage[$run->phase3_package_id] ?? collect();
            $total      = $pkgDataIds->count();
            $fullyDone  = 0;
            $started    = 0;

            $srcPkgId = $phase3ToSource[$run->phase3_package_id] ?? null;

            foreach ($pkgDataIds as $dataId) {
                $p3Key = $dataId . '|' . $run->phase3_package_id;
                $p1Key = $srcPkgId ? ($dataId . '|' . $srcPkgId) : null;

                $p1Users = $p1Key && isset($phase1Annotations[$p1Key])
                    ? $phase1Annotations[$p1Key]->pluck('user_id')
                    : collect();

                $p3Users = isset($annotsByItemKey[$p3Key])
                    ? $annotsByItemKey[$p3Key]->pluck('user_id')
                    : collect();

                $distinctCount = $p1Users->merge($p3Users)->unique()->count();

                // Consistent with progressBuckets: "complete" = 3 distinct users total
                if ($distinctCount >= 3) $fullyDone++;
                // "started" = at least one Phase 3 annotation exists
                if ($p3Users->isNotEmpty()) $started++;
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
                'iaaTotal3'          => $iaaTotal3,
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
                'llmVsHuman'         => $llmVsHuman,
                'allHumanLabels'     => $allHumanLabels,
            ],
        ]);
    }
}
