<?php

namespace App\Jobs;

use App\Models\AiScreening;
use App\Models\AiSetting;
use App\Models\Annotation;
use App\Models\PackageData;
use App\Models\Phase2Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunPhase2ScreeningGroq implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries   = 1;

    public function __construct(private readonly int $runId) {}

    public function handle(): void
    {
        $run = Phase2Run::find($this->runId);

        if (! $run || in_array($run->status, ['completed', 'cancelled', 'failed'])) {
            return;
        }

        $settings = AiSetting::singleton();

        $apiKey = config('services.groq.key');
        if (blank($apiKey)) {
            $this->failRun($run, 'Groq API key not configured. Set GROQ_API_KEY in your .env file.');
            return;
        }

        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        $run->update(['status' => 'running', 'started_at' => now()]);

        // Normal category is always id = 0; anything else is non-normal
        $allPackageDataIds = PackageData::where('package_id', $run->source_package_id)
            ->pluck('data_id');

        $annotationsInPackage = Annotation::query()
            ->select('annotations.id', 'annotations.data_id', 'annotations.category_ids')
            ->join('package_data', 'package_data.data_id', '=', 'annotations.data_id')
            ->where('package_data.package_id', $run->source_package_id)
            ->get()
            ->keyBy('data_id');

        $normalItems    = collect();
        $nonNormalItems = collect();

        foreach ($allPackageDataIds as $dataId) {
            $annotation = $annotationsInPackage->get($dataId);
            if (! $annotation) {
                continue;
            }
            $categoryIds = $this->normalizeCategoryIds($annotation->category_ids);
            $isNormal    = empty($categoryIds)
                || (count($categoryIds) === 1 && (int) $categoryIds[0] === 0);

            if ($isNormal) {
                $normalItems->push(['data_id' => $dataId, 'annotation_id' => $annotation->id]);
            } else {
                $nonNormalItems->push(['data_id' => $dataId, 'annotation_id' => $annotation->id]);
            }
        }

        $run->update([
            'total_normal'     => $normalItems->count(),
            'total_non_normal' => $nonNormalItems->count(),
        ]);

        if ($normalItems->isEmpty()) {
            $run->update(['status' => 'completed', 'completed_at' => now()]);
            return;
        }

        // Skip items already recorded in ai_screenings for this run (handles retries)
        $existingDataIds = AiScreening::where('phase2_run_id', $run->id)
            ->pluck('data_id')
            ->flip();
        $normalItems = $normalItems->filter(fn ($i) => ! $existingDataIds->has($i['data_id']))->values();

        $dataContents        = DB::table('data')
            ->whereIn('id', $normalItems->pluck('data_id'))
            ->pluck('content', 'id');
        $confidenceThreshold = (float) ($settings->confidence_threshold ?? 0.5);

        foreach ($normalItems as $item) {
            // Respect cancellation between items
            $currentStatus = Phase2Run::where('id', $run->id)->value('status');
            if ($currentStatus === 'cancelled') {
                return;
            }

            $content = $dataContents->get($item['data_id'], '');
            $prompt  = $settings->buildPrompt($content);
            $result  = $this->callGroq($apiKey, $baseUrl, $settings->model, $prompt);

            $flagged = false;
            if ($result['success']) {
                $label      = $result['label'] ?? 'Normal';
                $confidence = (float) ($result['confidence'] ?? 0.0);
                $reasoning  = $result['reasoning'] ?? '';
                $status     = 'done';

                $isDas   = in_array(strtolower((string) $label), ['depresi', 'ansietas', 'stres'], true);
                $flagged = $isDas && $confidence >= $confidenceThreshold;
            } else {
                $label      = null;
                $confidence = null;
                $reasoning  = null;
                $status     = 'error';
            }

            AiScreening::create([
                'phase2_run_id' => $run->id,
                'data_id'       => $item['data_id'],
                'annotation_id' => $item['annotation_id'],
                'llm_label'     => $label,
                'confidence'    => $confidence,
                'reasoning'     => $reasoning,
                'flagged'       => $flagged,
                'in_qc_sample'  => false,
                'status'        => $status,
                'error_message' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
            ]);

            DB::table('phase2_runs')->where('id', $run->id)->increment('processed');
            if ($flagged) {
                DB::table('phase2_runs')->where('id', $run->id)->increment('flagged_count');
            }
        }

        // Random 10% QC sample from unflagged items
        $unflaggedIds = AiScreening::where('phase2_run_id', $run->id)
            ->where('flagged', false)
            ->where('status', 'done')
            ->pluck('id');
        $qcSampleSize = max(1, (int) ceil($unflaggedIds->count() * 0.10));
        $qcSampleIds  = $unflaggedIds->shuffle()->take($qcSampleSize);

        if ($qcSampleIds->isNotEmpty()) {
            AiScreening::whereIn('id', $qcSampleIds)->update(['in_qc_sample' => true]);
        }

        $run->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'qc_sample_count' => $qcSampleIds->count(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $run = Phase2Run::find($this->runId);
        if ($run) {
            $this->failRun($run, $exception->getMessage());
        }
        Log::error('RunPhase2ScreeningGroq failed', ['run_id' => $this->runId, 'error' => $exception->getMessage()]);
    }

    private function callGroq(string $apiKey, string $baseUrl, string $model, string $prompt): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->retry(2, 1000)
                ->post($baseUrl . '/chat/completions', [
                    'model'       => $model,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1,
                    'max_tokens'  => 256,
                ]);

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 300)];
            }

            $text = $response->json('choices.0.message.content', '');
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim((string) $text));
            $text = rtrim((string) $text, '`');
            $text = trim((string) $text);

            $parsed = json_decode($text, true);

            if (! is_array($parsed) || ! isset($parsed['label'])) {
                return ['success' => false, 'error' => 'Unparseable JSON from LLM: ' . substr($text, 0, 200)];
            }

            return array_merge(['success' => true], $parsed);

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizeCategoryIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => $v !== null));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function failRun(Phase2Run $run, string $message): void
    {
        $run->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
    }
}
