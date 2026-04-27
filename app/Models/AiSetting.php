<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = [
        'model',
        'prompt_template',
        'confidence_threshold',
        'use_batch_api',
        'provider',
        'include_reasoning',
    ];

    protected $casts = [
        'confidence_threshold' => 'float',
        'use_batch_api'        => 'boolean',
        'include_reasoning'    => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Provider / model catalogue
    // -------------------------------------------------------------------------

    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_GROQ   = 'groq';

    /** Models grouped by provider for display. */
    public static function modelsByProvider(): array
    {
        return [
            self::PROVIDER_OPENAI => [
                'gpt-4.1'        => 'GPT-4.1',
                'gpt-4o-mini'    => 'GPT-4o Mini (fast, cheap — recommended)',
                'gpt-4o'         => 'GPT-4o (best quality)',
                'gpt-3.5-turbo'  => 'GPT-3.5 Turbo (legacy)',
            ],
            self::PROVIDER_GROQ => [
                'openai/gpt-oss-120b'                      => 'GPT OSS 120B (Groq, ~500 tps)',
                'openai/gpt-oss-20b'                       => 'GPT OSS 20B (Groq, ~1000 tps, cheapest)',
                'qwen/qwen3-32b'                           => 'Qwen3 32B (Groq, ~400 tps)',
                'meta-llama/llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B (Groq, ~750 tps)',
            ],
        ];
    }

    /** Flat list of all valid model IDs. */
    public static function allModelIds(): array
    {
        $ids = [];
        foreach (self::modelsByProvider() as $models) {
            foreach (array_keys($models) as $id) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    // -------------------------------------------------------------------------
    // API key helpers
    // -------------------------------------------------------------------------

    public function hasApiKey(): bool
    {
        return $this->provider === self::PROVIDER_GROQ
            ? filled(config('services.groq.key'))
            : filled(config('services.openai.key'));
    }

    public function getApiKey(): ?string
    {
        $key = $this->provider === self::PROVIDER_GROQ
            ? config('services.groq.key')
            : config('services.openai.key');

        return filled($key) ? (string) $key : null;
    }

    /** @deprecated Use hasApiKey() */
    public function hasOpenAiApiKey(): bool
    {
        return $this->hasApiKey();
    }

    /** @deprecated Use getApiKey() */
    public function getOpenAiApiKey(): ?string
    {
        return $this->getApiKey();
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function singleton(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'model'                => 'gpt-4o-mini',
            'prompt_template'      => static::defaultPrompt(),
            'confidence_threshold' => 0.5,
            'use_batch_api'        => true,
            'provider'             => self::PROVIDER_OPENAI,
            'include_reasoning'    => true,
        ]);
    }

    public static function defaultPrompt(): string
    {
        return <<<'PROMPT'
You are a mental health text classifier for Bahasa Indonesia. Determine if the following text shows any indication of Depresi (depression), Ansietas (anxiety), or Stres (stress). If none, label it Normal.

Definitions:
- Depresi: persistent sadness, hopelessness, loss of interest, worthlessness, suicidal ideation
- Ansietas: excessive worry, restlessness, fear of future, overthinking, panic
- Stres: feeling overwhelmed, emotional exhaustion, frustration from pressure, burnout

Examples of Depresi:
- "aku cape bgt sama keadaan, mental berantakan, stuck karna takut gagal..."
- "rasanya hidup ini udah ga ada artinya lagi, semua terasa hampa..."

Examples of Ansietas:
- "aku takut bgt kalo semuanya gagal, setiap malam gabisa tidur mikirin..."
- "degdegan terus, rasanya ada yg salah tapi gatau apa..."

Examples of Stres:
- "cape bgt tiap kita ribut, selalu nangis, aku udah berusaha bub..."
- "aku sedih bgt, kurang apa aku, gabisa ngatur lagi, cape sama semuanya..."

Text to classify:
{content}

Respond ONLY with a valid JSON object, no markdown, no extra text:
{"label": "Depresi|Ansietas|Stres|Normal", "confidence": 0.0, "reasoning": "brief explanation"}
PROMPT;
    }

    /**
     * Build the final prompt for a given content string, respecting the
     * include_reasoning toggle. Strips the "reasoning" field from the
     * expected JSON format when reasoning is disabled.
     */
    public function buildPrompt(string $content): string
    {
        $template = $this->prompt_template ?: static::defaultPrompt();
        $prompt   = str_replace('{content}', $content, $template);

        if (! $this->include_reasoning) {
            $prompt = str_replace(
                '"reasoning": "brief explanation"}',
                '}',
                $prompt
            );
        }

        return $prompt;
    }
}
