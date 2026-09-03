<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Validation\ValidationException;

/**
 * Store-credit wallet. The ledger (wallet_transactions) is the source of truth;
 * users.store_credit is a cache refreshed on every write (guide §11.19).
 */
class WalletService
{
    public function balance(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        $credits = (int) WalletTransaction::query()->where('user_id', $userId)->where('type', 'credit')->sum('amount');
        $debits = (int) WalletTransaction::query()->where('user_id', $userId)->where('type', 'debit')->sum('amount');

        return max(0, $credits - $debits);
    }

    public function credit(User $user, int $amount, ?string $reason, ?int $orderId = null, ?int $refundId = null): WalletTransaction
    {
        return $this->write($user, 'credit', $amount, $reason, $orderId, $refundId);
    }

    public function debit(User $user, int $amount, ?string $reason, ?int $orderId = null, ?int $refundId = null): WalletTransaction
    {
        if ($amount > $this->balance($user)) {
            throw ValidationException::withMessages(['storeCreditUsed' => ['Insufficient store credit.']]);
        }

        return $this->write($user, 'debit', $amount, $reason, $orderId, $refundId);
    }

    public function refreshCache(User $user): void
    {
        $user->forceFill(['store_credit' => $this->balance($user)])->save();
    }

    private function write(User $user, string $type, int $amount, ?string $reason, ?int $orderId, ?int $refundId): WalletTransaction
    {
        $before = $this->balance($user);
        $after = $type === 'credit' ? $before + $amount : max(0, $before - $amount);

        $transaction = WalletTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'order_id' => $orderId,
            'refund_id' => $refundId,
            'balance_before' => $before,
            'balance_after' => $after,
            'created_at' => now(),
        ]);

        $user->forceFill(['store_credit' => $after])->save();

        return $transaction;
    }
}
