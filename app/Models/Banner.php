<?php

namespace App\Models;

class Banner extends BaseModel
{
    public const BACKGROUND_TYPES = ['gradient', 'image', 'video'];

    public const IMAGE_POSITIONS = ['right center', 'center center', 'left center', 'center top', 'center bottom'];

    public const TEXT_ALIGNS = ['left', 'center', 'right'];

    protected $fillable = [
        'title', 'subtitle', 'eyebrow', 'cta', 'link', 'secondary_cta_label', 'secondary_cta_link', 'background_type',
        'gradient', 'image', 'image_position', 'video_url', 'video_poster', 'overlay_opacity', 'text_align',
        'duration_ms', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'overlay_opacity' => 'integer',
        'duration_ms' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
