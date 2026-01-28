<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackageData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PackageExportController extends Controller
{
    public function index(): View
    {
        $packages = $this->packageSummaries();
        $metrics = $this->buildMetricSnapshot($packages);

        return view('_app.app', [
            'content' => 'report.package-export',
            'headerdata' => [
                'pagetitle' => 'Package Annotation Export',
            ],
            'sidenavdata' => ['active' => 'reports.package-export'],
            'contentdata' => [
                'packages' => $packages,
                'metrics' => $metrics,
                'exportEndpoint' => route('reports.package-export.download'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'package_ids' => ['required', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
        ]);

        $packageIds = collect($validated['package_ids'] ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($packageIds->isEmpty()) {
            return back()->withErrors(['package_ids' => 'Select at least one package to export.']);
        }

        $packageNames = Package::query()
            ->whereIn('id', $packageIds)
            ->pluck('name', 'id');

        if ($packageNames->isEmpty()) {
            return back()->withErrors(['package_ids' => 'Selected packages are no longer available.']);
        }

        $assignments = PackageData::query()
            ->select(['package_data.package_id', 'package_data.data_id', 'data.content'])
            ->join('data', 'data.id', '=', 'package_data.data_id')
            ->whereIn('package_data.package_id', $packageIds)
            ->orderBy('package_data.package_id')
            ->orderBy('package_data.data_id')
            ->get();

        if ($assignments->isEmpty()) {
            return back()->withErrors(['package_ids' => 'Selected packages do not contain any data rows.']);
        }

        $annotationBuckets = $this->buildAnnotationBuckets($assignments->pluck('data_id')->unique());
        $categoryLookup = $this->resolveCategoryLookup($annotationBuckets);

        $timestamp = Carbon::now()->format('Ymd-His');
        $fileName = sprintf('package-annotations-%s.csv', $timestamp);

        return response()->streamDownload(function () use ($assignments, $annotationBuckets, $categoryLookup, $packageNames): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'package_id',
                'package_name',
                'data_id',
                'annotation_labels',
                'annotation_label_ids',
                'content',
            ]);

            foreach ($assignments as $row) {
                $labelIds = $annotationBuckets->get($row->data_id, collect());
                $labels = $labelIds->map(fn (int $id) => $categoryLookup[$id] ?? (string) $id);

                fputcsv($handle, [
                    $row->package_id,
                    $packageNames[$row->package_id] ?? 'Unknown',
                    $row->data_id,
                    $labels->implode('|'),
                    $labelIds->implode('|'),
                    $row->content,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function packageSummaries(): Collection
    {
        $packages = Package::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $assignmentTotals = PackageData::query()
            ->selectRaw('package_id, count(*) as total_rows')
            ->groupBy('package_id')
            ->pluck('total_rows', 'package_id');

        $annotationStats = PackageData::query()
            ->selectRaw('package_data.package_id, count(distinct annotations.data_id) as annotated_rows, max(annotations.updated_at) as last_annotation_at')
            ->join('annotations', 'annotations.data_id', '=', 'package_data.data_id')
            ->groupBy('package_data.package_id')
            ->get()
            ->keyBy('package_id');

        return $packages
            ->map(function (Package $package) use ($assignmentTotals, $annotationStats) {
                $annotationRow = $annotationStats->get($package->id);
                $annotatedRows = (int) ($annotationRow->annotated_rows ?? 0);
                $lastAnnotationAt = $annotationRow?->last_annotation_at
                    ? Carbon::parse($annotationRow->last_annotation_at)
                    : null;
                $totalRows = (int) ($assignmentTotals[$package->id] ?? 0);

                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'total_rows' => $totalRows,
                    'annotated_rows' => $annotatedRows,
                    'coverage' => $totalRows > 0 ? round(($annotatedRows / $totalRows) * 100, 1) : 0.0,
                    'last_annotation_at' => $lastAnnotationAt,
                ];
            })
            ->values();
    }

    protected function buildMetricSnapshot(Collection $packages): array
    {
        $totalRows = $packages->sum('total_rows');
        $annotatedRows = $packages->sum('annotated_rows');

        return [
            'packageCount' => $packages->count(),
            'annotatedPackages' => $packages->filter(fn ($pkg) => $pkg['annotated_rows'] > 0)->count(),
            'rowCount' => $totalRows,
            'coverage' => $totalRows > 0 ? round(($annotatedRows / $totalRows) * 100, 1) : 0.0,
        ];
    }

    protected function buildAnnotationBuckets(Collection $dataIds): Collection
    {
        if ($dataIds->isEmpty()) {
            return collect();
        }

        return Annotation::query()
            ->select(['data_id', 'category_ids'])
            ->whereIn('data_id', $dataIds)
            ->get()
            ->groupBy('data_id')
            ->map(function (Collection $rows) {
                return $rows
                    ->flatMap(function (Annotation $annotation) {
                        $ids = $annotation->category_ids;

                        if (is_string($ids)) {
                            $decoded = json_decode($ids, true);
                            $ids = is_array($decoded) ? $decoded : [];
                        }

                        return collect($ids)
                            ->filter(fn ($value) => is_numeric($value))
                            ->map(fn ($value) => (int) $value);
                    })
                    ->unique()
                    ->values();
            });
    }

    protected function resolveCategoryLookup(Collection $annotationBuckets): array
    {
        $categoryIds = $annotationBuckets
            ->flatMap(fn (Collection $ids) => $ids)
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->pluck('name', 'id')
            ->all();
    }
}
