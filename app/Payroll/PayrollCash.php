<?php

namespace App\Payroll;

use App\AccountTransaction;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Services\LedgerPoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: money leaving a payment account for something other than a salary (a loan
 * given, a tax paid over), the way a salary payment does it:
 *  - the movement on the payment account (its own sub_type, so the ledger sync leaves it alone and
 *    the account book neither edits nor deletes it);
 *  - when the business keeps its books here, one entry through LedgerPoster:
 *      Dr $debitCode   (421 staff advances, 431 CNPS, 447 the State …)
 *      Cr the ledger account the payment account is linked to.
 */
class PayrollCash
{
    /** The open payment account the money leaves, of this business, or an error. */
    public static function account($business_id, $id)
    {
        $a = $id ? DB::table('accounts')->where('business_id', $business_id)->where('id', $id)->where('is_closed', 0)->whereNull('deleted_at')->first() : null;
        if (! $a) {
            throw new PayrollException(__('payroll.err_account'));
        }

        return $a;
    }

    public static function date($date)
    {
        $d = Carbon::parse($date ?: today())->startOfDay();
        if ($d->gt(today())) {
            throw new PayrollException(__('payroll.err_future_date'));
        }

        return $d;
    }

    public static function books($business_id)
    {
        return Ledger::enabledFor($business_id) && Ledger::isSetUp($business_id);
    }

    /**
     * Pay out: the movement, then the entry. Returns [account transaction id, journal entry id|null].
     * Call inside a DB transaction.
     */
    public static function out($business_id, $account, $amount, $subType, $reff, $note, Carbon $date, $debitCode, $description, $lineText, $refType, $refId, $actor_id)
    {
        $at = AccountTransaction::createAccountTransaction(['account_id' => $account->id, 'type' => 'debit', 'sub_type' => $subType,
            'amount' => $amount, 'operation_date' => $date->copy()->setTimeFrom(now())->toDateTimeString(),
            'created_by' => $actor_id, 'note' => mb_substr((string) $note, 0, 250) ?: null]);
        $at->reff_no = $reff;   // createAccountTransaction does not take it
        $at->save();

        $entryId = null;
        if (self::books($business_id)) {
            $entry = Ledger::forBusiness($business_id, function () use ($account, $amount, $date, $debitCode, $description, $lineText, $refType, $refId, $reff, $actor_id) {
                $debit = self::ledgerAccount($debitCode);
                $cash = $account->chart_of_account_id ? (int) $account->chart_of_account_id : null;
                if (! $cash) {
                    throw new PayrollException(__('payroll.err_account_not_linked', ['name' => $account->name]));
                }

                return LedgerPoster::post([
                    'entry_date' => $date->toDateString(), 'journal_type' => 'payroll', 'description' => $description,
                    'reference_type' => $refType, 'reference_id' => $refId, 'reference_number' => $reff, 'is_system_generated' => true,
                ], [
                    ['account_id' => $debit, 'debit' => round((float) $amount, 2), 'credit' => 0, 'description' => $lineText],
                    ['account_id' => $cash, 'debit' => 0, 'credit' => round((float) $amount, 2), 'description' => $lineText],
                ], $actor_id);
            });
            $entryId = $entry->id;
        }

        return [$at->id, $entryId];
    }

    /** Undo: the entry reversed (today), the movement removed. */
    public static function undo($business_id, $atId, $entryId, $refType, $refId, $why, $actor_id)
    {
        if ($entryId) {
            $entry = Ledger::forBusiness($business_id, fn () => JournalEntry::find($entryId));
            if ($entry && $entry->is_posted && ! $entry->is_reversed) {
                Ledger::forBusiness($business_id, fn () => LedgerPoster::reverse($entry, $refType, $refId, $actor_id, $why));
            }
        }
        if ($atId && ($at = AccountTransaction::find($atId))) {
            $at->delete();
        }
    }

    /** The ledger account of a code (or its 3-digit parent), inside Ledger::forBusiness. */
    public static function ledgerAccount($code)
    {
        $acc = fn ($c) => ChartOfAccount::where('account_code', $c)->where('is_active', 1)->value('id');
        $id = $acc($code) ?: $acc(substr((string) $code, 0, 3));
        if (! $id) {
            throw new PayrollException(__('payroll.err_no_account', ['code' => $code]));
        }

        return $id;
    }
}
