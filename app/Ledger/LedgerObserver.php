<?php

namespace App\Ledger;

use App\Ledger\Services\LedgerSync;

/**
 * Watches sales, purchases, expenses and payments (paxhitestbed's Fee /
 * Expense / Income observers). A save or delete queues the document; it is
 * posted once the request's own database work is done (LedgerSync), so a sale
 * never waits for, or fails because of, the books. Changes made without
 * Eloquent are caught by the scheduled `ledger:sync`.
 */
class LedgerObserver
{
    public function saved($model)
    {
        $this->queue($model);
    }

    public function deleted($model)
    {
        $this->queue($model);
    }

    private function queue($model): void
    {
        try {
            if (empty($model->business_id) || empty($model->id)) {
                return;
            }
            $kind = $model instanceof \App\TransactionPayment ? 'payment' : 'transaction';
            LedgerSync::queue($kind, (int) $model->id, (int) $model->business_id);
        } catch (\Throwable $e) {
            \Log::error('Ledger observer: '.$e->getMessage());
        }
    }
}
