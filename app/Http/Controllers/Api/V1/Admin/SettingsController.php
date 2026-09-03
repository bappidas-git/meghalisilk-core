<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin settings (guide §13.26). PATCH merges keys into the section.
 */
class SettingsController extends ApiController
{
    public function index(SettingsRepository $settings): JsonResponse
    {
        return $this->respond($settings->adminSettings());
    }

    public function update(Request $request, SettingsRepository $settings, string $section): JsonResponse
    {
        if (! $settings->isSection($section)) {
            throw new NotFoundHttpException('Unknown settings section');
        }

        $data = $request->validate($this->rules($section));

        if ($section === 'social') {
            foreach ($data as $key => $value) {
                $data[$key] = $value ?? '';
            }
        }
        if ($section === 'shipping' && array_key_exists('shiprocketEmail', $data)) {
            $data['shiprocketEmail'] = $data['shiprocketEmail'] ?? '';
        }

        return $this->respond($settings->merge($section, $data));
    }

    private function rules(string $section): array
    {
        return match ($section) {
            'store' => [
                'name' => ['sometimes', 'required', 'string', 'max:150'],
                'tagline' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:191'],
                'phone' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string', 'max:500'],
                'currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
                'currencySymbol' => ['nullable', 'string', 'max:5'],
                'timezone' => ['nullable', 'string', 'max:64'],
                'logo' => ['nullable', 'string', 'max:1000', 'url:http,https'],
                'favicon' => ['nullable', 'string', 'max:1000', 'url:http,https'],
                'taxRate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'taxIncluded' => ['sometimes', 'boolean'],
            ],
            'payment' => [
                'razorpayEnabled' => ['sometimes', 'boolean'],
                'razorpayKeyId' => ['nullable', 'string', 'max:100'],
                'stripeEnabled' => ['sometimes', 'boolean'],
                'stripePublishableKey' => ['nullable', 'string', 'max:200'],
                'codEnabled' => ['sometimes', 'boolean'],
                'codFee' => ['sometimes', 'numeric', 'min:0'],
                'codMinOrder' => ['sometimes', 'numeric', 'min:0'],
                'codMaxOrder' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            ],
            'social' => [
                'facebook' => ['nullable', 'string', 'max:500', 'url:http,https'],
                'instagram' => ['nullable', 'string', 'max:500', 'url:http,https'],
                'twitter' => ['nullable', 'string', 'max:500', 'url:http,https'],
                'youtube' => ['nullable', 'string', 'max:500', 'url:http,https'],
                'whatsapp' => ['nullable', 'string', 'max:500', 'url:http,https'],
            ],
            'shipping' => [
                'shiprocketEnabled' => ['sometimes', 'boolean'],
                'shiprocketEmail' => ['nullable', 'email', 'max:191'],
                'shiprocketPassword' => ['nullable', 'string', 'max:255'],
                'defaultWeight' => ['sometimes', 'numeric', 'min:0'],
                'defaultDimensions' => ['sometimes', 'array'],
                'defaultDimensions.length' => ['nullable', 'numeric', 'min:0'],
                'defaultDimensions.width' => ['nullable', 'numeric', 'min:0'],
                'defaultDimensions.height' => ['nullable', 'numeric', 'min:0'],
            ],
            'notifications' => [
                'orderConfirmationEmail' => ['sometimes', 'boolean'],
                'shippingUpdateEmail' => ['sometimes', 'boolean'],
                'adminNewOrderEmail' => ['sometimes', 'boolean'],
                'adminEmail' => ['nullable', 'email', 'max:191'],
                'lowStockAlert' => ['sometimes', 'boolean'],
                'lowStockEmail' => ['nullable', 'email', 'max:191'],
            ],
            'seo' => [
                'metaTitle' => ['nullable', 'string', 'max:255'],
                'metaDescription' => ['nullable', 'string', 'max:1000'],
                'googleAnalyticsId' => ['nullable', 'string', 'max:50'],
                'facebookPixelId' => ['nullable', 'string', 'max:50'],
            ],
            default => [],
        };
    }
}
