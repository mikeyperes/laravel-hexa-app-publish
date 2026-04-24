<?php

namespace hexa_app_publish\Publishing\Settings\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_core\Services\ImageCopyrightBlacklistService;
use hexa_package_pexels\Services\PexelsService;
use hexa_package_unsplash\Services\UnsplashService;
use hexa_package_pixabay\Services\PixabayService;
use hexa_package_gnews\Services\GNewsService;
use hexa_package_newsdata\Services\NewsDataService;
use hexa_package_sapling\Services\SaplingService;
use hexa_package_anthropic\Services\AnthropicService;
use hexa_package_chatgpt\Services\ChatGptService;
use hexa_package_grok\Services\GrokService;
use hexa_package_gemini\Services\GeminiService;
use hexa_package_telegram\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function saveImageCopyrightBlacklist(Request $request, ImageCopyrightBlacklistService $blacklist): JsonResponse
    {
        $validated = $request->validate([
            'keywords' => 'nullable|string|max:5000',
        ]);

        $raw = trim((string) ($validated['keywords'] ?? ''));
        Setting::setValue(
            'image_copyright_blacklist',
            $raw !== '' ? $raw : ImageCopyrightBlacklistService::DEFAULT_RAW,
            'integrations'
        );

        return response()->json([
            'success' => true,
            'message' => 'Image copyright blacklist saved.',
            'keywords' => $blacklist->keywords(),
            'count' => $blacklist->keywordCount(),
            'raw' => $blacklist->raw(),
        ]);
    }

    /**
     * Test an integration API key.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testIntegration(Request $request): JsonResponse
    {
        $service = $request->input('service');

        $result = match ($service) {
            'pexels' => app(PexelsService::class)->testApiKey(),
            'unsplash' => app(UnsplashService::class)->testApiKey(),
            'pixabay' => app(PixabayService::class)->testApiKey(),
            'gnews' => app(GNewsService::class)->testApiKey(),
            'newsdata' => app(NewsDataService::class)->testApiKey(),
            'sapling' => app(SaplingService::class)->testApiKey(),
            'anthropic' => app(AnthropicService::class)->testApiKey(),
            'chatgpt' => app(ChatGptService::class)->testApiKey(),
            'grok' => app(GrokService::class)->testApiKey(),
            'gemini' => app(GeminiService::class)->testApiKey(),
            'telegram' => app(TelegramService::class)->testBotToken(),
            default => ['success' => false, 'message' => "Unknown service: {$service}"],
        };

        \hexa_core\Models\ActivityLog::log('settings', 'test_integration', "Integration test: {$service} — " . ($result['success'] ? 'success' : 'failed: ' . $result['message']));

        return response()->json($result);
    }
}
