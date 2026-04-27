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

class RunPhase2Screening implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(private readonly int $runId) {}

    public function handle(): void
    {
        $run = Phase2Run::find($this->runId);

        if (! $run || in_array($run->status, ['completed', 'cancelled', 'failed', 'batch_submitted'])) {
            return;
        }

        $settings = AiSetting::singleton();

        if (! $settings->hasOpenAiApiKey()) {
            $this->failRun($run, 'OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.');
            return;
        }

        $apiKey = $settings->getOpenAiApiKey();

        if (blank($apiKey)) {
            $this->failRun($run, 'OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.');
            return;
        }

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

        if ($normalItems->isEmpty()) {
            $run->update(['status' => 'completed', 'completed_at' => now()]);
            return;
        }

        // Pre-load content for all Normal items
        $dataContents   = DB::table('data')
            ->whereIn('id', $normalItems->pluck('data_id'))
            ->pluck('content', 'id');
        $model          = $settings->model ?? 'gpt-4o-mini';

        // Build JSONL — custom_id format: "r{runId}:d{dataId}:a{annotationId}"
        $lines = [];
        foreach ($normalItems as $item) {
            $content  = $dataContents->get($item['data_id'], '');
            $prompt   = $settings->buildPrompt($content);
            $customId = 'r' . $run->id . ':d' . $item['data_id'] . ':a' . $item['annotation_id'];

            $lines[] = json_encode([
                'custom_id' => $customId,
                'method'    => 'POST',
                'url'       => '/v1/chat/completions',
                'body'      => [
                    'model'       => $model,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1,
                    'max_tokens'  => 256,
                ],
            ]);
        }

        $jsonlContent = implode("\n", $lines);

        // Upload JSONL file to OpenAI Files API
        $uploadResponse = Http::withToken($apiKey)
            ->timeout(60)
            ->attach('file', $jsonlContent, 'batch_input.jsonl', ['Content-Type' => 'application/jsonl'])
            ->post('https://api.openai.com/v1/files', ['purpose' => 'batch']);

        if (! $uploadResponse->successful()) {
            $this->failRun($run, 'Failed to upload batch file: ' . substr($uploadResponse->body(), 0, 300));
            return;
        }

        $fileId = $uploadResponse->json('id');
        if (! $fileId) {
            $this->failRun($run, 'No file ID returned from OpenAI Files API.');
            return;
        }

        // Create the batch
        $batchResponse = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/batches', [
                'input_file_id'     => $fileId,
                'endpoint'          => '/v1/chat/completions',
                'completion_window' => '24h',
                'metadata'          => ['phase2_run_id' => (string) $run->id],
            ]);

        if (! $batchResponse->successful()) {
            $this->failRun($run, 'Failed to create batch: ' . substr($batchResponse->body(), 0, 300));
            return;
        }

        $batchId = $batchResponse->json('id');
        if (! $batchId) {
            $this->failRun($run, 'No batch ID returned from OpenAI Batches API.');
            return;
        }

        Log::info('Phase2 batch submitted', ['run_id' => $run->id, 'batch_id' => $batchId]);

        $run->update([
            'status'          => 'batch_submitted',
            'openai_batch_id' => $batchId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $run = Phase2Run::find($this->runId);
        if ($run) {
            $this->failRun($run, $exception->getMessage());
        }
        Log::error('RunPhase2Screening failed', ['run_id' => $this->runId, 'error' => $exception->getMessage()]);
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
