<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use Illuminate\Http\Request;

class AiSettingController extends Controller
{
    public function show()
    {
        $settings = AiSetting::singleton();

        return view('_app.app', [
            'content'     => 'ai-settings.index',
            'headerdata'  => ['pagetitle' => 'AI Agent Settings'],
            'sidenavdata' => ['active' => 'ai-settings'],
            'contentdata' => [
                'settings'        => $settings,
                'hasKey'          => $settings->hasApiKey(),
                'hasOpenAiKey'    => filled(config('services.openai.key')),
                'hasGroqKey'      => filled(config('services.groq.key')),
                'modelsByProvider' => AiSetting::modelsByProvider(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'model'                => 'required|string|in:' . implode(',', AiSetting::allModelIds()),
            'prompt_template'      => 'required|string|min:20',
            'confidence_threshold' => 'required|numeric|min:0|max:1',
            'provider'             => 'required|string|in:openai,groq',
        ]);

        // Checkbox absent from POST when unchecked; batch API only applies to OpenAI
        $validated['use_batch_api']     = $validated['provider'] === 'openai'
            ? $request->boolean('use_batch_api')
            : false;
        $validated['include_reasoning'] = $request->boolean('include_reasoning');

        $settings = AiSetting::singleton();

        $settings->fill($validated)->save();

        return redirect()->route('ai-settings.show')->with('success', 'Settings saved successfully.');
    }
}
