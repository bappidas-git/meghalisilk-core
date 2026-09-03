<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin leads (guide §13.25).
 */
class LeadController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(LeadResource::collection(Lead::query()->orderByDesc('created_at')->orderByDesc('id')->get()));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(LeadResource::make(Lead::query()->findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);

        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in($lead->allowedStatuses())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $attributes = [];
        if (array_key_exists('status', $data)) {
            $attributes['status'] = $data['status'];
        }
        if (array_key_exists('notes', $data)) {
            $attributes['notes'] = $data['notes'] ?? '';
        }
        $lead->fill($attributes)->save();

        return $this->respond(LeadResource::make($lead->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        Lead::query()->findOrFail($id)->delete();

        return $this->respond(null);
    }
}
