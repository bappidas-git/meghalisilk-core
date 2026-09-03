<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin hero slides (guide §13.26, §14.12).
 */
class BannerController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(BannerResource::collection($this->ordered()));
    }

    public function store(BannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attributes = $this->attributes($data);
        if (! array_key_exists('sortOrder', $data)) {
            $attributes['sort_order'] = (int) Banner::query()->max('sort_order') + 1;
        }

        return $this->respond(BannerResource::make(Banner::create($attributes)), 201);
    }

    public function update(BannerRequest $request, int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $banner->fill($this->attributes($request->validated()))->save();

        return $this->respond(BannerResource::make($banner->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        Banner::query()->findOrFail($id)->delete();

        return $this->respond(null);
    }

    /** { order: [ids, top first] } → sortOrder = index; unlisted rows follow in their current order. */
    public function reorder(ReorderRequest $request): JsonResponse
    {
        $ids = array_map('intval', $request->validated('order'));
        $known = Banner::query()->whereIn('id', $ids)->pluck('id')->all();
        $unknown = array_diff($ids, $known);
        if ($unknown) {
            throw ValidationException::withMessages(['order' => ['Unknown banner id(s): '.implode(', ', $unknown)]]);
        }

        DB::transaction(function () use ($ids) {
            $position = 0;
            foreach ($ids as $id) {
                Banner::query()->whereKey($id)->update(['sort_order' => $position++]);
            }
            foreach ($this->ordered()->whereNotIn('id', $ids) as $banner) {
                $banner->forceFill(['sort_order' => $position++])->save();
            }
        });

        return $this->respond(BannerResource::collection($this->ordered()));
    }

    private function ordered()
    {
        return Banner::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    private function attributes(array $data): array
    {
        $map = [
            'title' => 'title', 'subtitle' => 'subtitle', 'eyebrow' => 'eyebrow', 'cta' => 'cta', 'link' => 'link',
            'secondaryCtaLabel' => 'secondary_cta_label', 'secondaryCtaLink' => 'secondary_cta_link',
            'backgroundType' => 'background_type', 'gradient' => 'gradient', 'image' => 'image',
            'imagePosition' => 'image_position', 'videoUrl' => 'video_url', 'videoPoster' => 'video_poster',
            'overlayOpacity' => 'overlay_opacity', 'textAlign' => 'text_align', 'durationMs' => 'duration_ms',
            'isActive' => 'is_active', 'sortOrder' => 'sort_order',
        ];
        $attributes = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $attributes[$column] = $data[$key];
            }
        }
        $attributes['image_position'] = $attributes['image_position'] ?? 'right center';
        $attributes['text_align'] = $attributes['text_align'] ?? 'left';
        $attributes['duration_ms'] = (int) ($attributes['duration_ms'] ?? 0);
        $attributes['is_active'] = (bool) ($attributes['is_active'] ?? true);

        return $attributes;
    }
}
