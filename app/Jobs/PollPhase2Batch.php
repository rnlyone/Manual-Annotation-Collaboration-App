<?php

namespace App\Jobs;

use App\Models\AiScreening;
use App\Models\AiSetting;
use App\Models\Phase2Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollPhase2Batch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 1;

    public function handle(): void
    {
        $settings = AiSetting::singleton();
        $apiKey   = $settings->getOpenAiApiKey();

        if (blank($apiKey)) {
            return;
        }

        $pendingRuns = Phase2Run::where('status', 'batch_submitted')
            ->whereNotNull('openai_batch_id')
            ->get();

        foreach ($pendingRuns as $run) {
            $this->pollRun($run, $apiKey, $settings);
        }
    }

    private function pollRun(Phase2Run $run, string $apiKey, AiSetting $settings): void
    {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->get('https://api.openai.com/v1/batches/' . $run->openai_batch_id);

        if (! $response->successful()) {
            Log::warning('PollPhase2Batch: failed to retrieve batch status', [
                'run_id'   => $run->id,
                'batch_id' => $run->openai_batch_id,
                'http'     => $response->status(),
            ]);
            return;
        }

        $batch  = $response->json();
        $status = $batch['status'] ?? 'unknown';

        Log::info('PollPhase2Batch: batch status', [
            'run_id'   => $run->id,
            'batch_id' => $run->openai_batch_id,
            'status'   => $status,
        ]);

        if (in_array($status, ['validating', 'in_progress', 'finalizing'])) {
            // Still running — check again next scheduled poll
            return;
        }

        if (in_array($status, ['failed', 'expired', 'cancelled'])) {
            $errorMsg = $batch['errors']['data'][0]['message'] ?? 'No details';
            $run->update([
                'status'        => 'failed',
                'error_message' => "OpenAI batch {$status}: {$errorMsg}",
                'completed_at'  => now(),
            ]);
            return;
        }

        if ($status === 'completed') {
            $this->processCompletedBatch($run, $batch, $apiKey, $settings);
        }
    }

    private function processCompletedBatch(Phase2Run $run, array $batch, string $apiKey, AiSetting $settings): void
    {
        $outputFileId        = $batch['output_file_id'] ?? null;
        $errorFileId         = $batch['error_file_id'] ?? null;
        $confidenceThreshold = (float) ($settings->confidence_threshold ?? 0.5);

        // Process completed results
        if ($outputFileId) {
            $content = $this->downloadFile($apiKey, $outputFileId);
            if ($content !== null) {
                $this->processOutputLines($run, $content, $confidenceThreshold);
            }
        }

        // Process errored items from the batch error file
        if ($errorFileId) {
            $content = $this->downloadFile($apiKey, $errorFileId);
            if ($content !== null) {
                $this->processErrorLines($run, $content);
            }
        }

        // Random 10% QC sample from unflagged done items
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

        Log::info('PollPhase2Batch: run completed', [
            'run_id'          => $run->id,
            'processed'       => $run->fresh()->processed,
            'flagged'         => $run->fresh()->flagged_count,
            'qc_sample_count' => $qcSampleIds->count(),
        ]);
    }

    private function downloadFile(string $apiKey, string $fileId): ?string
    {
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->get("https://api.openai.com/v1/files/{$fileId}/content");

        if (! $response->successful()) {
            Log::warning('PollPhase2Batch: failed to download file', ['file_id' => $fileId, 'http' => $response->status()]);
            return null;
        }

        return $response->body();
    }

    private function processOutputLines(Phase2Run $run, string $content, float $confidenceThreshold): void
    {
        foreach (explode("\n", trim($content)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }

            [$dataId, $annotationId] = $this->parseCustomId($row['custom_id'] ?? '');
            if ($dataId === null) {
                continue;
            }

            // Skip if already recorded for this run
            if (AiScreening::where('phase2_run_id', $run->id)->where('data_id', $dataId)->exists()) {
                continue;
            }

            // Handle batch-level error on this request
            $apiError   = $row['error'] ?? null;
            $statusCode = $row['response']['status_code'] ?? 0;

            if ($apiError || $statusCode !== 200) {
                AiScreening::create([
                    'phase2_run_id' => $run->id,
                    'data_id'       => $dataId,
                    'annotation_id' => $annotationId,
                    'llm_label'     => null,
                    'confidence'    => null,
                    'reasoning'     => null,
                    'flagged'       => false,
                    'in_qc_sample'  => false,
                    'status'        => 'error',
                    'error_message' => $apiError ? ($apiError['message'] ?? 'Batch error') : "HTTP {$statusCode}",
                ]);
                DB::table('phase2_runs')->where('id', $run->id)->increment('processed');
                continue;
            }

            // Parse LLM JSON response
            $text   = $row['response']['body']['choices'][0]['message']['content'] ?? '';
            $text   = preg_replace('/^```(?:json)?\s*/i', '', trim((string) $text));
            $text   = rtrim((string) $text, '`');
            $text   = trim((string) $text);
            $parsed = json_decode($text, true);

            if (! is_array($parsed) || ! isset($parsed['label'])) {
                AiScreening::create([
                    'phase2_run_id' => $run->id,
                    'data_id'       => $dataId,
                    'annotation_id' => $annotationId,
                    'llm_label'     => null,
                    'confidence'    => null,
                    'reasoning'     => null,
                    'flagged'       => false,
                    'in_qc_sample'  => false,
                    'status'        => 'error',
                    'error_message' => 'Unparseable JSON: ' . substr($text, 0, 200),
                ]);
                DB::table('phase2_runs')->where('id', $run->id)->increment('processed');
                continue;
            }

            $label      = (string) ($parsed['label'] ?? 'Normal');
            $confidence = (float) ($parsed['confidence'] ?? 0.0);
            $reasoning  = (string) ($parsed['reasoning'] ?? '');
            $isDas      = in_array(strtolower($label), ['depresi', 'ansietas', 'stres'], true);
            $flagged    = $isDas && $confidence >= $confidenceThreshold;

            AiScreening::create([
                'phase2_run_id' => $run->id,
                'data_id'       => $dataId,
                'annotation_id' => $annotationId,
                'llm_label'     => $label,
                'confidence'    => $confidence,
                'reasoning'     => $reasoning,
                'flagged'       => $flagged,
                'in_qc_sample'  => false,
                'status'        => 'done',
                'error_message' => null,
            ]);

            DB::table('phase2_runs')->where('id', $run->id)->increment('processed');
            if ($flagged) {
                DB::table('phase2_runs')->where('id', $run->id)->increment('flagged_count');
            }
        }
    }

    private function processErrorLines(Phase2Run $run, string $content): void
    {
        foreach (explode("\n", trim($content)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }

            [$dataId, $annotationId] = $this->parseCustomId($row['custom_id'] ?? '');
            if ($dataId === null) {
                continue;
            }

            if (AiScreening::where('phase2_run_id', $run->id)->where('data_id', $dataId)->exists()) {
                continue;
            }

            AiScreening::create([
                'phase2_run_id' => $run->id,
                'data_id'       => $dataId,
                'annotation_id' => $annotationId,
                'llm_label'     => null,
                'confidence'    => null,
                'reasoning'     => null,
                'flagged'       => false,
                'in_qc_sample'  => false,
                'status'        => 'error',
                'error_message' => $row['error']['message'] ?? 'Unknown batch error',
            ]);
            DB::table('phase2_runs')->where('id', $run->id)->increment('processed');
        }
    }

    /**
     * Parse custom_id format: "r{runId}:d{dataId}:a{annotationId}"
     * Returns [dataId, annotationId] or [null, null] on failure.
     */
    private function parseCustomId(string $customId): array
    {
        if (! preg_match('/^r\d+:d([^:]+):a(\d+)$/', $customId, $m)) {
            return [null, null];
        }
        return [$m[1], (int) $m[2]];
    }
}
