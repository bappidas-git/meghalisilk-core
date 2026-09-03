<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Banner;
use Illuminate\Validation\Rule;

class BannerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'eyebrow' => ['nullable', 'string', 'max:150'],
            'cta' => ['nullable', 'string', 'max:100'],
            'link' => ['required', 'string', 'max:500'],
            'secondaryCtaLabel' => ['nullable', 'string', 'max:100'],
            'secondaryCtaLink' => ['nullable', 'string', 'max:500'],
            'backgroundType' => ['required', Rule::in(Banner::BACKGROUND_TYPES)],
            'gradient' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'imagePosition' => ['nullable', Rule::in(Banner::IMAGE_POSITIONS)],
            'videoUrl' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'videoPoster' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'overlayOpacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'textAlign' => ['nullable', Rule::in(Banner::TEXT_ALIGNS)],
            'durationMs' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $value = (int) $value;
                if ($value !== 0 && ($value < 1000 || $value > 60000)) {
                    $fail('The slide duration must be 0 (inherit) or between 1000 and 60000 ms.');
                }
            }],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer'],
        ];
    }
}
