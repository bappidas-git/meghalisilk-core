<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id ?? $this->route('id');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($categoryId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'parentId' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'showInMainMenu' => ['nullable', 'boolean'],
            'menuOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['slug.unique' => 'A category with this slug already exists.'];
    }
}
