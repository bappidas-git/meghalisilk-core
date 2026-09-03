<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Key/JSON settings rows (guide §11.26, §15.14). Missing keys fall back to defaults so the
 * API works on an empty database.
 */
class SettingsRepository
{
    public const SECTIONS = ['store', 'shipping', 'payment', 'notifications', 'seo', 'social'];

    public const PUBLIC_SECTIONS = ['store', 'payment', 'social'];

    public const HERO = 'hero_config';

    public const DEALS = 'deals_config';

    public static function defaults(string $section): array
    {
        return match ($section) {
            'store' => [
                'name' => "Meghali's Silk", 'tagline' => '', 'email' => '', 'phone' => '', 'address' => '',
                'currency' => 'INR', 'currencySymbol' => '₹', 'timezone' => 'Asia/Kolkata', 'logo' => null,
                'favicon' => null, 'taxRate' => 0, 'taxIncluded' => false,
            ],
            'shipping' => [
                'shiprocketEnabled' => false, 'shiprocketEmail' => '', 'shiprocketPassword' => '',
                'defaultWeight' => 0.5, 'defaultDimensions' => ['length' => 15, 'width' => 12, 'height' => 8],
            ],
            'payment' => [
                'razorpayEnabled' => false, 'razorpayKeyId' => '', 'stripeEnabled' => false, 'stripePublishableKey' => '',
                'codEnabled' => true, 'codFee' => 0, 'codMinOrder' => 0, 'codMaxOrder' => 0,
            ],
            'notifications' => [
                'orderConfirmationEmail' => true, 'shippingUpdateEmail' => true, 'adminNewOrderEmail' => true,
                'adminEmail' => '', 'lowStockAlert' => true, 'lowStockEmail' => '',
            ],
            'seo' => ['metaTitle' => '', 'metaDescription' => '', 'googleAnalyticsId' => '', 'facebookPixelId' => ''],
            'social' => ['facebook' => '', 'instagram' => '', 'twitter' => '', 'youtube' => '', 'whatsapp' => ''],
            self::HERO => [
                'enabled' => true, 'autoplay' => true, 'intervalMs' => 5000, 'transition' => 'fade', 'pauseOnHover' => true,
                'showControls' => true, 'showCounter' => false, 'showProgress' => true, 'showArrows' => true,
                'overlayOpacity' => 100,
                'heights' => [
                    'desktop' => ['min' => 520, 'vh' => 78, 'max' => 780],
                    'tablet' => ['min' => 480, 'vh' => 70, 'max' => 640],
                    'mobile' => ['min' => 460, 'vh' => 66, 'max' => 600],
                ],
                'secondaryCta' => ['enabled' => false, 'label' => '', 'link' => ''],
                'openers' => ['enabled' => true, 'label' => 'Collections', 'limit' => 8],
            ],
            self::DEALS => [
                'enabled' => false,
                'hero' => ['tag' => '', 'title' => '', 'subtitle' => ''],
                'timer' => ['enabled' => false, 'endAt' => '', 'onExpiry' => 'endOfDay'],
                'featuredCouponIds' => [], 'dealOfTheDayIds' => [], 'featuredProductIds' => [],
            ],
            default => throw new NotFoundHttpException('Unknown settings section'),
        };
    }

    public function isSection(string $section): bool
    {
        return in_array($section, self::SECTIONS, true);
    }

    /** Raw stored section merged over defaults (includes encrypted secrets). */
    public function raw(string $section): array
    {
        $row = Setting::query()->find($section);

        return array_replace(self::defaults($section), $row?->data ?? []);
    }

    /** Section safe to return to an admin (secrets removed). */
    public function get(string $section): array
    {
        $data = $this->raw($section);

        if ($section === 'shipping') {
            unset($data['shiprocketPassword']);
        }

        return $data;
    }

    public function publicSettings(): array
    {
        $out = [];
        foreach (self::PUBLIC_SECTIONS as $section) {
            $out[$section] = $this->get($section);
        }

        return $out;
    }

    public function adminSettings(): array
    {
        $out = [];
        foreach (self::SECTIONS as $section) {
            $out[$section] = $this->get($section);
        }

        return $out;
    }

    /** PATCH semantics: merge keys into the stored section. */
    public function merge(string $section, array $data): array
    {
        if (! $this->isSection($section)) {
            throw new NotFoundHttpException('Unknown settings section');
        }

        if ($section === 'shipping') {
            if (array_key_exists('shiprocketPassword', $data)) {
                $data['shiprocketPassword'] = $data['shiprocketPassword'] === null || $data['shiprocketPassword'] === ''
                    ? ''
                    : Crypt::encryptString((string) $data['shiprocketPassword']);
            }
        }

        $merged = array_replace($this->raw($section), $data);
        $this->store($section, $merged);

        return $this->get($section);
    }

    /** Decrypted Shiprocket password for server-side use only. */
    public function shiprocketPassword(): ?string
    {
        $value = $this->raw('shipping')['shiprocketPassword'] ?? '';

        return $value === '' ? null : Crypt::decryptString($value);
    }

    public function heroConfig(): array
    {
        return $this->configWithTimestamp(self::HERO);
    }

    public function dealsConfig(): array
    {
        return $this->configWithTimestamp(self::DEALS);
    }

    /** PUT semantics: replace the whole config object. */
    public function replaceConfig(string $section, array $data): array
    {
        unset($data['updatedAt']);
        $this->store($section, array_replace(self::defaults($section), $data));

        return $this->configWithTimestamp($section);
    }

    private function configWithTimestamp(string $section): array
    {
        $row = Setting::query()->find($section);
        $data = array_replace(self::defaults($section), $row?->data ?? []);
        unset($data['updatedAt']);
        $data['updatedAt'] = $row?->updated_at
            ? $row->updated_at->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z')
            : null;

        return $data;
    }

    private function store(string $section, array $data): void
    {
        Setting::query()->updateOrCreate(['section' => $section], ['data' => $data, 'updated_at' => now()]);
    }
}
