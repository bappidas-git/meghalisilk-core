<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HeroConfigRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $rules = [
            'enabled' => ['nullable', 'boolean'],
            'autoplay' => ['nullable', 'boolean'],
            'intervalMs' => ['required', 'integer', 'min:1000', 'max:60000'],
            'transition' => ['required', Rule::in(['fade', 'slide', 'none'])],
            'pauseOnHover' => ['nullable', 'boolean'],
            'showControls' => ['nullable', 'boolean'],
            'showCounter' => ['nullable', 'boolean'],
            'showProgress' => ['nullable', 'boolean'],
            'showArrows' => ['nullable', 'boolean'],
            'overlayOpacity' => ['required', 'integer', 'min:0', 'max:100'],
            'heights' => ['required', 'array'],
            'secondaryCta' => ['nullable', 'array'],
            'secondaryCta.enabled' => ['nullable', 'boolean'],
            'secondaryCta.label' => ['nullable', 'string', 'max:100'],
            'secondaryCta.link' => ['nullable', 'string', 'max:500'],
            'openers' => ['nullable', 'array'],
            'openers.enabled' => ['nullable', 'boolean'],
            'openers.label' => ['nullable', 'string', 'max:100'],
            'openers.limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            $rules["heights.$device"] = ['required', 'array'];
            $rules["heights.$device.min"] = ['required', 'integer', 'min:200', 'max:1200'];
            $rules["heights.$device.vh"] = ['required', 'integer', 'min:20', 'max:100'];
            $rules["heights.$device.max"] = ['required', 'integer', 'min:200', 'max:1600'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach (['desktop', 'tablet', 'mobile'] as $device) {
                $min = (int) $this->input("heights.$device.min");
                $max = (int) $this->input("heights.$device.max");
                if ($max < $min) {
                    $v->errors()->add("heights.$device.max", "The $device maximum height must be at least the minimum.");
                }
            }
        });
    }
}
