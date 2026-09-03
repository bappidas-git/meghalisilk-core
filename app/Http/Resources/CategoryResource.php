<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CategoryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'image' => $this->image,
            'parentId' => $this->parent_id,
            'isActive' => (bool) $this->is_active,
            'sortOrder' => (int) $this->sort_order,
            'showInMainMenu' => (bool) $this->show_in_main_menu,
            'menuOrder' => (int) $this->menu_order,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
