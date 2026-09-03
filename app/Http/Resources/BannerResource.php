<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class BannerResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?? '',
            'eyebrow' => $this->eyebrow ?? '',
            'cta' => $this->cta ?? '',
            'link' => $this->link,
            'secondaryCtaLabel' => $this->secondary_cta_label ?? '',
            'secondaryCtaLink' => $this->secondary_cta_link ?? '',
            'backgroundType' => $this->background_type,
            'gradient' => $this->gradient ?? '',
            'image' => $this->image ?? '',
            'imagePosition' => $this->image_position,
            'videoUrl' => $this->video_url ?? '',
            'videoPoster' => $this->video_poster ?? '',
            'overlayOpacity' => $this->overlay_opacity,
            'textAlign' => $this->text_align,
            'durationMs' => (int) $this->duration_ms,
            'isActive' => (bool) $this->is_active,
            'sortOrder' => (int) $this->sort_order,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
