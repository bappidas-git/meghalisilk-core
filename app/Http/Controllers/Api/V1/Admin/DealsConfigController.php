<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\DealsConfigRequest;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;

/**
 * Special-offers configuration singleton (guide §13.26, §14.10). PUT replaces the object.
 */
class DealsConfigController extends ApiController
{
    public function show(SettingsRepository $settings): JsonResponse
    {
        return $this->respond($settings->dealsConfig());
    }

    public function update(DealsConfigRequest $request, SettingsRepository $settings): JsonResponse
    {
        $data = $request->validated();

        $productIds = Product::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $couponIds = Coupon::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $config = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'hero' => [
                'tag' => $data['hero']['tag'] ?? '',
                'title' => $data['hero']['title'],
                'subtitle' => $data['hero']['subtitle'] ?? '',
            ],
            'timer' => [
                'enabled' => (bool) ($data['timer']['enabled'] ?? false),
                'endAt' => $data['timer']['endAt'] ?? '',
                'onExpiry' => $data['timer']['onExpiry'] ?? 'endOfDay',
            ],
            'featuredCouponIds' => $this->known($data['featuredCouponIds'] ?? [], $couponIds),
            'dealOfTheDayIds' => $this->known($data['dealOfTheDayIds'] ?? [], $productIds),
            'featuredProductIds' => $this->known($data['featuredProductIds'] ?? [], $productIds),
        ];

        return $this->respond($settings->replaceConfig(SettingsRepository::DEALS, $config));
    }

    /** Drop ids that do not reference an existing row, keeping order and uniqueness. */
    private function known(array $ids, array $existing): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->unique()->filter(fn ($id) => in_array($id, $existing, true))->values()->all();
    }
}
