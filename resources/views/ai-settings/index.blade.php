@php
    $settings        = $contentdata['settings'];
    $hasOpenAiKey    = $contentdata['hasOpenAiKey'];
    $hasGroqKey      = $contentdata['hasGroqKey'];
    $modelsByProvider = $contentdata['modelsByProvider'];
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb breadcrumb-style1">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">AI Agent Settings</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible mb-4" role="alert">
            <i class="ti ti-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('ai-settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row g-4">

            {{-- Provider & Model Card --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="ti ti-cpu me-2 text-primary"></i>Provider &amp; Model</h5>
                    </div>
                    <div class="card-body">

                        {{-- Provider selector --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Provider</label>
                            <div class="d-flex gap-3 flex-wrap" id="providerRadios">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="provider" id="provider_openai"
                                           value="openai" {{ $settings->provider === 'openai' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="provider_openai">
                                        <strong>OpenAI</strong>
                                        @if($hasOpenAiKey)
                                            <span class="badge bg-label-success ms-1">key set</span>
                                        @else
                                            <span class="badge bg-label-warning ms-1">no key</span>
                                        @endif
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="provider" id="provider_groq"
                                           value="groq" {{ $settings->provider === 'groq' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="provider_groq">
                                        <strong>Groq</strong>
                                        @if($hasGroqKey)
                                            <span class="badge bg-label-success ms-1">key set</span>
                                        @else
                                            <span class="badge bg-label-warning ms-1">no key</span>
                                        @endif
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Model select with grouped options --}}
                        <div class="mb-3">
                            <label class="form-label" for="model">Model</label>
                            <select class="form-select @error('model') is-invalid @enderror" id="model" name="model">
                                @foreach($modelsByProvider as $providerKey => $models)
                                    <optgroup label="{{ strtoupper($providerKey) }}"
                                              data-provider="{{ $providerKey }}"
                                              class="provider-group provider-{{ $providerKey }}">
                                        @foreach($models as $val => $label)
                                            <option value="{{ $val }}"
                                                    data-provider="{{ $providerKey }}"
                                                    {{ $settings->model === $val ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('model')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- API Key status --}}
                        <div id="keyStatus">
                            <div id="keyStatus_openai" class="{{ $settings->provider === 'openai' ? '' : 'd-none' }}">
                                @if($hasOpenAiKey)
                                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                                        <i class="ti ti-shield-check fs-5"></i>
                                        <span><code>OPENAI_API_KEY</code> is configured.</span>
                                    </div>
                                @else
                                    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                                        <i class="ti ti-alert-triangle fs-5"></i>
                                        <span>Set <code>OPENAI_API_KEY</code> in <code>.env</code>.
                                            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">Get key</a></span>
                                    </div>
                                @endif
                            </div>
                            <div id="keyStatus_groq" class="{{ $settings->provider === 'groq' ? '' : 'd-none' }}">
                                @if($hasGroqKey)
                                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                                        <i class="ti ti-shield-check fs-5"></i>
                                        <span><code>GROQ_API_KEY</code> is configured.</span>
                                    </div>
                                @else
                                    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                                        <i class="ti ti-alert-triangle fs-5"></i>
                                        <span>Set <code>GROQ_API_KEY</code> in <code>.env</code>.
                                            <a href="https://console.groq.com/keys" target="_blank" rel="noopener">Get key</a></span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="confidence_threshold">
                                Confidence Threshold
                                <span class="text-muted fw-normal" id="threshold-display">({{ number_format($settings->confidence_threshold, 2) }})</span>
                            </label>
                            <input type="range" class="form-range" min="0" max="1" step="0.05"
                                   id="confidence_threshold" name="confidence_threshold"
                                   value="{{ $settings->confidence_threshold }}"
                                   oninput="document.getElementById('threshold-display').textContent='('+parseFloat(this.value).toFixed(2)+')'">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">0.00 — flag everything</small>
                                <small class="text-muted">1.00 — flag only certainties</small>
                            </div>
                            <div class="form-text">Items where LLM confidence ≥ threshold AND label ≠ Normal are flagged for Phase 3.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Prompt Card --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="ti ti-message-dots me-2 text-primary"></i>Screening Prompt</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetPromptBtn">
                            Reset to default
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-3 py-2">
                            <i class="ti ti-info-circle fs-5 mt-1 flex-shrink-0"></i>
                            <span class="small">Use <code>{content}</code> as the placeholder for the text being classified. The LLM must respond with a JSON object: <code>{"label": "...", "confidence": 0.0, "reasoning": "..."}</code></span>
                        </div>
                        <textarea class="form-control flex-grow-1 @error('prompt_template') is-invalid @enderror font-monospace"
                                  id="prompt_template" name="prompt_template"
                                  style="min-height: 340px; font-size: 0.8rem; resize: none;"
                                  placeholder="Enter the LLM prompt...">{{ old('prompt_template', $settings->prompt_template) }}</textarea>
                        @error('prompt_template')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

        </div>

        {{-- API Method Card (OpenAI only) --}}
        <div class="col-12" id="batchApiCard" {{ $settings->provider === 'groq' ? 'style=display:none' : '' }}>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-brand-openai me-2 text-success"></i>API Method</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold mb-1">Use OpenAI Batch API</div>
                            <div class="text-muted small">
                                <strong>Batch API (recommended):</strong> Submits all items as a single asynchronous batch job. Up to 50% cheaper and not subject to rate limits — results arrive within 24 hours via background polling.<br>
                                <strong>Standard (synchronous):</strong> Calls OpenAI one item at a time during the queue job. Faster for small packages but uses more quota and may hit rate limits on large datasets.
                            </div>
                        </div>
                        <div class="form-check form-switch form-check-lg ms-auto flex-shrink-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="use_batch_api" name="use_batch_api" value="1"
                                   {{ $settings->use_batch_api ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="use_batch_api">
                                {{ $settings->use_batch_api ? 'Batch API' : 'Standard' }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reasoning Card --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-brain me-2 text-info"></i>Response Options</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold mb-1">Include Reasoning</div>
                            <div class="text-muted small">
                                When enabled, the LLM also returns a brief explanation for its label. Disabling this reduces token usage and speeds up responses.
                            </div>
                        </div>
                        <div class="form-check form-switch form-check-lg ms-auto flex-shrink-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="include_reasoning" name="include_reasoning" value="1"
                                   {{ $settings->include_reasoning ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="include_reasoning" id="reasoningLabel">
                                {{ $settings->include_reasoning ? 'Enabled' : 'Disabled' }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i>Save Settings
            </button>
        </div>
    </form>

</div>

<script>
    const defaultPrompt = {{ Js::from(\App\Models\AiSetting::defaultPrompt()) }};
    document.getElementById('resetPromptBtn')?.addEventListener('click', function () {
        document.getElementById('prompt_template').value = defaultPrompt;
    });

    // Batch toggle label
    const batchToggle = document.getElementById('use_batch_api');
    const batchLabel  = document.querySelector('label[for="use_batch_api"]');
    if (batchToggle && batchLabel) {
        batchToggle.addEventListener('change', function () {
            batchLabel.textContent = this.checked ? 'Batch API' : 'Standard';
        });
    }

    // Reasoning toggle label
    const reasoningToggle = document.getElementById('include_reasoning');
    const reasoningLabel  = document.getElementById('reasoningLabel');
    if (reasoningToggle && reasoningLabel) {
        reasoningToggle.addEventListener('change', function () {
            reasoningLabel.textContent = this.checked ? 'Enabled' : 'Disabled';
        });
    }

    // Provider switching: filter model options + show/hide key status + show/hide batch card
    const modelSelect  = document.getElementById('model');
    const batchCard    = document.getElementById('batchApiCard');
    const providerRadios = document.querySelectorAll('input[name="provider"]');

    function applyProvider(provider) {
        // Filter optgroups in model select
        modelSelect.querySelectorAll('optgroup').forEach(function(og) {
            og.disabled = og.dataset.provider !== provider;
            og.style.display = og.dataset.provider !== provider ? 'none' : '';
        });

        // If current selection belongs to wrong provider, auto-select first from new provider
        const selectedOption = modelSelect.options[modelSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.provider !== provider) {
            const firstInProvider = modelSelect.querySelector('option[data-provider="' + provider + '"]');
            if (firstInProvider) {
                firstInProvider.selected = true;
            }
        }

        // Key status panels
        document.getElementById('keyStatus_openai').classList.toggle('d-none', provider !== 'openai');
        document.getElementById('keyStatus_groq').classList.toggle('d-none', provider !== 'groq');

        // Batch API card — only relevant for OpenAI
        if (batchCard) {
            batchCard.style.display = provider === 'groq' ? 'none' : '';
        }
    }

    providerRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            applyProvider(this.value);
        });
    });

    // Run once on load to sync initial state
    const initialProvider = document.querySelector('input[name="provider"]:checked')?.value ?? 'openai';
    applyProvider(initialProvider);
</script>
