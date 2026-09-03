<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\FaqRequest;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin FAQs (guide §13.26, §14.13).
 */
class FaqController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(FaqResource::collection($this->ordered()));
    }

    public function store(FaqRequest $request): JsonResponse
    {
        $data = $request->validated();

        $faq = DB::transaction(function () use ($data) {
            $attributes = $this->attributes($data);
            if (! array_key_exists('sortOrder', $data)) {
                $attributes['sort_order'] = (int) Faq::query()->max('sort_order') + 1;
            }
            $faq = Faq::create($attributes);
            $faq->products()->sync($data['productIds'] ?? []);

            return $faq;
        });

        return $this->respond(FaqResource::make($faq->load('products')), 201);
    }

    public function update(FaqRequest $request, int $id): JsonResponse
    {
        $faq = Faq::query()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($faq, $data) {
            $faq->fill($this->attributes($data))->save();
            if (array_key_exists('productIds', $data)) {
                $faq->products()->sync($data['productIds'] ?? []);
            }
        });

        return $this->respond(FaqResource::make($faq->fresh('products')));
    }

    public function destroy(int $id): JsonResponse
    {
        Faq::query()->findOrFail($id)->delete();

        return $this->respond(null);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $ids = array_map('intval', $request->validated('order'));
        $known = Faq::query()->whereIn('id', $ids)->pluck('id')->all();
        $unknown = array_diff($ids, $known);
        if ($unknown) {
            throw ValidationException::withMessages(['order' => ['Unknown FAQ id(s): '.implode(', ', $unknown)]]);
        }

        DB::transaction(function () use ($ids) {
            $position = 0;
            foreach ($ids as $id) {
                Faq::query()->whereKey($id)->update(['sort_order' => $position++]);
            }
            foreach ($this->ordered()->whereNotIn('id', $ids) as $faq) {
                $faq->forceFill(['sort_order' => $position++])->save();
            }
        });

        return $this->respond(FaqResource::collection($this->ordered()));
    }

    private function ordered()
    {
        return Faq::query()->with('products')->orderBy('sort_order')->orderBy('id')->get();
    }

    private function attributes(array $data): array
    {
        $attributes = [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'placements' => array_values(array_unique($data['placements'])),
            'is_active' => (bool) ($data['isActive'] ?? true),
        ];
        if (array_key_exists('sortOrder', $data)) {
            $attributes['sort_order'] = (int) $data['sortOrder'];
        }

        return $attributes;
    }
}
