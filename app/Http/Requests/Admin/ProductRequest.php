<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST/PUT /admin/products (guide §14.3, §16).
 */
class ProductRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($productId)->whereNull('deleted_at')],
            'sku' => ['nullable', 'string', 'max:100'],
            'shortDescription' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand' => ['nullable', 'string', 'max:150'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:1000', 'url:http,https'],
            'price' => ['required', 'integer', 'min:0'],
            'comparePrice' => ['nullable', 'integer', 'min:0'],
            'costPrice' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'lowStockThreshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['required', 'string', 'max:64'],
            'variants.*.name' => ['required', 'string', 'max:150'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.swatchHex' => ['nullable', 'string', 'max:9'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'featured' => ['nullable', 'boolean'],
            'trending' => ['nullable', 'boolean'],
            'hot' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string'],
            'relatedProductIds' => ['nullable', 'array'],
            'relatedProductIds.*' => ['integer'],
            'frequentlyBoughtTogetherIds' => ['nullable', 'array'],
            'frequentlyBoughtTogetherIds.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'A product with this slug already exists.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $price = (int) $this->input('price', 0);
            $variants = $this->input('variants', []);
            if ($price <= 0 && empty($variants)) {
                $v->errors()->add('price', 'Set a price or add at least one variant.');
            }
            $keys = array_map(fn ($variant) => (string) ($variant['id'] ?? ''), is_array($variants) ? $variants : []);
            if (count($keys) !== count(array_unique($keys))) {
                $v->errors()->add('variants', 'Variant ids must be unique.');
            }
        });
    }
}
