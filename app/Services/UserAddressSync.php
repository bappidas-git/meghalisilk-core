<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PUT /auth/user { addresses: [...] } — whole-array replace with id reconciliation (guide §11.2).
 * Numeric ids that belong to the user are updated; anything else (client base-36 ids) is inserted;
 * rows missing from the array are deleted. Exactly one default is kept.
 */
class UserAddressSync
{
    public function sync(User $user, array $addresses): void
    {
        DB::transaction(function () use ($user, $addresses) {
            $existingIds = $user->addresses()->pluck('id')->all();
            $keep = [];
            $hasDefault = collect($addresses)->contains(fn ($a) => ! empty($a['isDefault']));

            foreach (array_values($addresses) as $index => $address) {
                $attributes = [
                    'label' => $address['label'] ?? 'Home',
                    'first_name' => $address['firstName'],
                    'last_name' => $address['lastName'],
                    'phone' => $address['phone'],
                    'address_line1' => $address['addressLine1'],
                    'address_line2' => $address['addressLine2'] ?? null,
                    'city' => $address['city'],
                    'state' => $address['state'],
                    'postal_code' => $address['postalCode'],
                    'country' => ($address['country'] ?? '') ?: 'India',
                    'is_default' => $hasDefault ? ! empty($address['isDefault']) : $index === 0,
                    'sort_order' => $index,
                ];

                $id = $address['id'] ?? null;
                if (is_numeric($id) && in_array((int) $id, $existingIds, true)) {
                    $user->addresses()->whereKey((int) $id)->update($attributes);
                    $keep[] = (int) $id;
                } else {
                    $keep[] = $user->addresses()->create($attributes)->id;
                }
            }

            $user->addresses()->whereNotIn('id', $keep ?: [0])->delete();

            // Enforce exactly one default when several were flagged.
            $defaults = $user->addresses()->where('is_default', true)->orderBy('sort_order')->pluck('id');
            if ($defaults->count() > 1) {
                $user->addresses()->whereIn('id', $defaults->slice(1)->all())->update(['is_default' => false]);
            }
        });
    }
}
