<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\ContactLeadRequest;
use App\Http\Requests\Storefront\NewsletterLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

/**
 * Public lead capture (guide §13.13).
 */
class LeadController extends ApiController
{
    public function contact(ContactLeadRequest $request): JsonResponse
    {
        $data = $request->validated();

        $lead = Lead::create([
            'type' => 'contact',
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'order_number' => $data['orderNumber'] ?? null,
            'category' => $data['category'] ?? 'general',
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'new',
            'notes' => '',
        ]);

        return $this->respond(LeadResource::make($lead), 201);
    }

    /** Duplicate subscriptions return the same success body (never reveal existing subscribers). */
    public function newsletter(NewsletterLeadRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim($request->validated('email')));

        $lead = Lead::query()->firstOrCreate(
            ['type' => 'newsletter', 'email' => $email],
            ['status' => 'subscribed', 'notes' => '']
        );

        if ($lead->status === 'unsubscribed') {
            $lead->forceFill(['status' => 'subscribed'])->save();
        }

        return $this->respond(LeadResource::make($lead), 201);
    }
}
