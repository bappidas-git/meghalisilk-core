<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\HeroConfigRequest;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;

/**
 * Hero section configuration singleton (guide §14.11). PUT replaces the object.
 */
class HeroConfigController extends ApiController
{
    public function show(SettingsRepository $settings): JsonResponse
    {
        return $this->respond($settings->heroConfig());
    }

    public function update(HeroConfigRequest $request, SettingsRepository $settings): JsonResponse
    {
        $data = $request->validated();

        $config = [
            'enabled' => (bool) ($data['enabled'] ?? true),
            'autoplay' => (bool) ($data['autoplay'] ?? true),
            'intervalMs' => (int) $data['intervalMs'],
            'transition' => $data['transition'],
            'pauseOnHover' => (bool) ($data['pauseOnHover'] ?? true),
            'showControls' => (bool) ($data['showControls'] ?? true),
            'showCounter' => (bool) ($data['showCounter'] ?? false),
            'showProgress' => (bool) ($data['showProgress'] ?? true),
            'showArrows' => (bool) ($data['showArrows'] ?? true),
            'overlayOpacity' => (int) $data['overlayOpacity'],
            'heights' => [],
            'secondaryCta' => [
                'enabled' => (bool) ($data['secondaryCta']['enabled'] ?? false),
                'label' => $data['secondaryCta']['label'] ?? '',
                'link' => $data['secondaryCta']['link'] ?? '',
            ],
            'openers' => [
                'enabled' => (bool) ($data['openers']['enabled'] ?? true),
                'label' => $data['openers']['label'] ?? '',
                'limit' => (int) ($data['openers']['limit'] ?? 8),
            ],
        ];
        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            $config['heights'][$device] = [
                'min' => (int) $data['heights'][$device]['min'],
                'vh' => (int) $data['heights'][$device]['vh'],
                'max' => (int) $data['heights'][$device]['max'],
            ];
        }

        return $this->respond($settings->replaceConfig(SettingsRepository::HERO, $config));
    }
}
