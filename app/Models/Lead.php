<?php

namespace App\Models;

class Lead extends BaseModel
{
    public const CATEGORIES = ['general', 'order', 'payment', 'shipping', 'returns', 'product'];

    public const CONTACT_STATUSES = ['new', 'contacted', 'resolved', 'spam'];

    public const NEWSLETTER_STATUSES = ['subscribed', 'unsubscribed'];

    protected $fillable = ['type', 'name', 'email', 'phone', 'order_number', 'category', 'subject', 'message', 'status', 'notes'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function allowedStatuses(): array
    {
        return $this->type === 'newsletter' ? self::NEWSLETTER_STATUSES : self::CONTACT_STATUSES;
    }
}
