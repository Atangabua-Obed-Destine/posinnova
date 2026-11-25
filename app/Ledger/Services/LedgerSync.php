<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\TransactionMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the ledger in step with the POS documents.
 *
 * For a document (or payment) it asks PostingRules what entry it should have
 * now, compares that with what it posted last time (a fingerprint of the
 * lines), and when they differ reverses the old entry and posts the new one,
 * both in one database transaction. A deleted document gets its entry
 * reversed. paxhitestbed's TransactionAutoMapService::remap, done the same
 * way for every POS document.
 *
 * A sale never fails because of the books: if posting is refused (no fiscal
 * year, a mapping missing) the mapping is marked failed with the reason,
 * Ledger health lists it, and the sync tries again (every few minutes from
 * the scheduler, and whenever the document changes).
 */
class LedgerSync
{
    private static $queue = [];

    private static $registered = false;

    /** Ask for a document to be synced at the end of the request (after its database work is committed). */
    public static function queue(string $kind, int $id, int $businessId): void
    {
        if (! Ledger::enabledFor($businessId)) {
            return;
        }
        self::$queue[$businessId][$kind.':'.$id] = [$kind, $id];
        if (! self::$registered) {
            self::$registered = true;
            app()->terminating(function () {
                self::flush();
            });
        }
    }

    /** Sync everything queued. Tests and commands call it directly. */
    public static function flush(): array
    {
        $done = [];
        while (! empty(self::$queue)) {
            $queue = self::$queue;
            self::$queue = [];
            foreach ($queue as $businessId => $items) {
                if (! Ledger::isSetUp($businessId)) {
                    continue;
                }
                Ledger::forBusiness($businessId, function () use ($items, &$done) {
                    $sync = new self();
                    foreach ($items as [$kind, $id]) {
                        try {
                            $done[$kind.':'.$id] = $kind === 'payment' ? $sync->payment($id)
                                : ($kind === 'account_transaction' ? $sync->accountTransaction($id) : $sync->transaction($id));
                        } catch (\Throwable $e) {
                            \Log::error('Ledger sync '.$kind.' '.$id.': '.$e->getMessage());
                            $done[$kind.':'.$id] = 'error';
                        }
                    }
                });
            }
        }

        return $done;
    }

    // ------------------------------------------------------------------

    private $rules;

    private $postFrom;

    public function __construct()
    {
        $this->rules = new PostingRules();
        $this->postFrom = AccountingSetting::getValue('post_from_date');
    }

    /** @return string same|posted|reposted|reversed|none|failed|history */
    public function transaction(int $id): string
    {
        $t = DB::table('transactions')->where('id', $id)->first();
        if ($t && (int) $t->business_id !== Ledger::businessId()) {
            return 'none';
        }
        $map = $t ? TransactionMapping::where('transaction_type', $t->type)->where('transaction_id', $id)->first()
                  // a document's mapping: never a payment's or a Payment Accounts movement's (their ids are another series)
                  : TransactionMapping::whereNotIn('transaction_type', ['payment', 'account_transaction'])->where('transaction_id', $id)->first();
        if (! $t || ! in_array($t->type, PostingRules::TYPES)) {
            return $map ? $this->apply($map, null, 'doc', $id) : 'none';
        }
        if (! $map && $this->isHistory($t->transaction_date)) {
            return 'history';
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => $t->type, 'transaction_id' => $id]);
        $result = $this->apply($map, fn () => $this->rules->forTransaction($t), $t->type, $id);

        // its payments follow it (a draft's payment is an advance until the sale is final)
        foreach (DB::table('transaction_payments')->where('transaction_id', $id)->pluck('id') as $pid) {
            $this->payment((int) $pid);
        }

        return $result;
    }

    /** A Payment Accounts movement (transfer, deposit, opening balance). */
    public function accountTransaction(int $id): string
    {
        $a = DB::table('account_transactions as at')->join('accounts as ac', 'ac.id', '=', 'at.account_id')
            ->where('at.id', $id)->select('at.*', 'ac.business_id')->first();
        if ($a && (int) $a->business_id !== Ledger::businessId()) {
            return 'none';
        }
        $map = TransactionMapping::where('transaction_type', 'account_transaction')->where('transaction_id', $id)->first();
        if (! $a) {
            return $map ? $this->apply($map, null, 'account_transaction', $id) : 'none';
        }
        if (! $map && $this->isHistory($a->operation_date)) {
            return 'history';
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => 'account_transaction', 'transaction_id' => $id]);
        $result = $this->apply($map, fn () => $this->rules->forAccountTransaction($a), 'account_transaction', $id);
        // its partner follows (a deleted half changes who posts the pair)
        if ($a->transfer_transaction_id && $a->transfer_transaction_id != $id) {
            $pm = TransactionMapping::where('transaction_type', 'account_transaction')->where('transaction_id', $a->transfer_transaction_id)->exists();
            if ($pm || ! $this->isHistory($a->operation_date)) {
                $p = DB::table('account_transactions')->where('id', $a->transfer_transaction_id)->first();
                if ($p) {
                    $pmap = TransactionMapping::where('transaction_type', 'account_transaction')->where('transaction_id', $p->id)->first()
                        ?: new TransactionMapping(['transaction_type' => 'account_transaction', 'transaction_id' => $p->id]);
                    $pa = (object) array_merge((array) $p, ['business_id' => $a->business_id]);
                    $this->apply($pmap, fn () => $this->rules->forAccountTransaction($pa), 'account_transaction', (int) $p->id);
                }
            }
        }

        return $result;
    }

    public function payment(int $id): string
    {
        $p = DB::table('transaction_payments')->where('id', $id)->first();
        if ($p && (int) $p->business_id !== Ledger::businessId()) {
            return 'none';
        }
        $map = TransactionMapping::where('transaction_type', 'payment')->where('transaction_id', $id)->first();
        if (! $p) {
            return $map ? $this->apply($map, null, 'payment', $id) : 'none';
        }
        if (! $map && $this->isHistory($p->paid_on)) {
            return 'history';
        }
        $t = $p->transaction_id ? DB::table('transactions')->where('id', $p->transaction_id)->first() : null;
        $map = $map ?: new TransactionMapping(['transaction_type' => 'payment', 'transaction_id' => $id]);

        return $this->apply($map, fn () => $this->rules->forPayment($p, $t), 'payment', $id);
    }

    private function isHistory($date): bool
    {
        return $this->postFrom && $date && substr((string) $date, 0, 10) < $this->postFrom;
    }

    /**
     * @param  callable|null  $expected  returns the entry the source should have (or null)
     */
    private function apply(TransactionMapping $map, ?callable $expected, string $kind, int $id): string
    {
        $userId = auth()->check() ? auth()->id() : null;
        try {
            $want = $expected ? $expected() : null;
        } catch (\RuntimeException $e) {
            return $this->fail($map, $e->getMessage());
        }

        $fp = null;
        if ($want) {
            // What the document says, on its own date: a closed month moving the posting
            // date to today is not a change, or every later look at an old document (a
            // payment on an October invoice in November) would reverse and repost it.
            $fp = sha1(json_encode([$want['date'], $want['lines']]));
            $want['date'] = $this->postingDate($want['date'], $want);
            if ($map->exists && $map->status === 'posted' && $map->fingerprint === $fp) {
                return 'same';
            }
        } else {
            if (! $map->exists) {
                return 'none';
            }
            if ($map->status !== 'posted' || ! $map->journal_entry_id) {
                $map->forceFill(['status' => 'none', 'error' => null, 'fingerprint' => null])->save();

                return 'none';
            }
        }

        try {
            return DB::transaction(function () use ($map, $want, $userId, $kind, $id, $fp) {
                $had = $map->journal_entry_id ? JournalEntry::find($map->journal_entry_id) : null;
                $result = 'posted';
                if ($had && $had->is_posted && ! $had->is_reversed) {
                    if (! $map->exists) {
                        $map->save();
                    }
                    LedgerPoster::reverse($had, 'transaction_mapping', $map->id, $userId,
                        $want ? __('ledger.why_changed') : __('ledger.why_removed'));
                    $result = $want ? 'reposted' : 'reversed';
                }
                if ($want) {
                    $entry = LedgerPoster::post([
                        'entry_date' => $want['date'],
                        'journal_type' => $want['journal_type'],
                        'description' => $want['description'],
                        'reference_number' => $want['reference'],
                        'reference_type' => in_array($kind, ['payment', 'account_transaction']) ? $kind : 'transaction',
                        'reference_id' => $id,
                        'is_system_generated' => true,
                    ], $want['lines'], $userId);
                    $dr = collect($want['lines'])->firstWhere('debit', '>', 0);
                    $cr = collect($want['lines'])->firstWhere('credit', '>', 0);
                    $map->fill([
                        'journal_entry_id' => $entry->id,
                        'amount' => $entry->total_debit,
                        'transaction_date' => $want['date'],
                        'description' => $want['description'],
                        'debit_account_id' => $dr['account_id'] ?? null,
                        'credit_account_id' => $cr['account_id'] ?? null,
                        'fingerprint' => $fp,
                        'mapped_at' => now(),
                        'mapped_by' => $userId,
                        'status' => 'posted',
                        'error' => null,
                    ])->save();
                } else {
                    $map->fill(['journal_entry_id' => null, 'status' => 'none', 'fingerprint' => null, 'error' => null,
                        'mapped_at' => now(), 'mapped_by' => $userId])->save();
                }

                return $result;
            });
        } catch (\RuntimeException $e) {
            return $this->fail($map, $e->getMessage());
        }
    }

    private function fail(TransactionMapping $map, string $why): string
    {
        $map->fill(['status' => 'failed', 'error' => mb_substr($why, 0, 1000), 'fingerprint' => null])->save();

        return 'failed';
    }

    /**
     * The date an automatic entry goes in: the document's own date while its
     * month is open; once that month is closed, today (the figures of a closed
     * month have been reported, so the change is a new event). A calendar
     * year that does not exist yet is opened automatically, with its months,
     * when the business already keeps books in the year before.
     */
    private function postingDate(string $date, array &$want): string
    {
        $this->ensureYear($date);
        if (LedgerPoster::isOpenDate($date)) {
            return $date;
        }
        $year = FiscalYear::forDate($date);
        $period = FiscalYear::periodForDate($date);
        if (($year && $year->is_closed) || ($period && $period->is_closed)) {
            $today = Carbon::today()->toDateString();
            $this->ensureYear($today);
            $want['description'] = mb_substr($want['description'].' ('.__('ledger.dated', ['date' => Carbon::parse($date)->format('d/m/Y')]).')', 0, 250);

            return $today;
        }

        return $date; // no year: LedgerPoster refuses with the reason, and the mapping is marked failed
    }

    private function ensureYear(string $date): void
    {
        if (FiscalYear::forDate($date)) {
            return;
        }
        $y = (int) substr($date, 0, 4);
        $before = FiscalYear::whereYear('end_date', $y - 1)->exists();
        if (! $before) {
            return;
        }
        $fy = FiscalYear::create(['name' => (string) $y, 'start_date' => $y.'-01-01', 'end_date' => $y.'-12-31', 'is_active' => false]);
        $fy->generatePeriods(null);
    }

    // ------------------------------------------------------------------ sweep

    /**
     * Catch up: documents never posted, documents changed since the last
     * sweep, failed ones, and entries whose document was deleted.
     */
    public function sweep(): array
    {
        $bid = Ledger::businessId();
        $since = AccountingSetting::getValue('last_sweep_at');
        $since = $since ? Carbon::parse($since)->subMinutes(10) : null;
        $from = $this->postFrom ?: '1970-01-01';
        $stats = [];
        $count = function ($r) use (&$stats) {
            $stats[$r] = ($stats[$r] ?? 0) + 1;
        };
        $started = now();

        // documents: not mapped yet, or changed
        $q = DB::table('transactions as t')->where('t.business_id', $bid)->whereIn('t.type', PostingRules::TYPES)
            ->where('t.transaction_date', '>=', $from)
            ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                $j->on('m.transaction_id', '=', 't.id')->on('m.transaction_type', '=', 't.type')->where('m.business_id', $bid);
            })
            ->where(function ($w) use ($since) {
                $w->whereNull('m.id');
                if ($since) {
                    $w->orWhere('t.updated_at', '>=', $since);
                }
                $w->orWhere('m.status', 'failed');
            })->orderBy('t.id');
        foreach ($q->pluck('t.id') as $id) {
            $count($this->transaction($id));
        }

        $q = DB::table('transaction_payments as p')->where('p.business_id', $bid)->where('p.paid_on', '>=', $from)
            ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                $j->on('m.transaction_id', '=', 'p.id')->where('m.transaction_type', 'payment')->where('m.business_id', $bid);
            })
            ->where(function ($w) use ($since) {
                $w->whereNull('m.id');
                if ($since) {
                    $w->orWhere('p.updated_at', '>=', $since);
                }
                $w->orWhere('m.status', 'failed');
            })->orderBy('p.id');
        foreach ($q->pluck('p.id') as $id) {
            $count($this->payment($id));
        }

        // Payment Accounts movements (transfers, deposits, opening balances), not the cash side of payments
        $q = DB::table('account_transactions as at')->join('accounts as ac', 'ac.id', '=', 'at.account_id')
            ->where('ac.business_id', $bid)->whereNull('at.transaction_id')->whereNull('at.transaction_payment_id')
            ->where('at.operation_date', '>=', $from)
            ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                $j->on('m.transaction_id', '=', 'at.id')->where('m.transaction_type', 'account_transaction')->where('m.business_id', $bid);
            })
            ->where(function ($w) use ($since) {
                $w->whereNull('m.id');
                if ($since) {
                    $w->orWhere('at.updated_at', '>=', $since);
                }
                $w->orWhere('m.status', 'failed');
            })->orderBy('at.id');
        foreach ($q->pluck('at.id') as $id) {
            $count($this->accountTransaction((int) $id));
        }

        // entries whose document or payment is gone
        // (documents only: payments and Payment Accounts movements are numbered in their own tables)
        $gone = DB::table('transaction_mappings as m')->where('m.business_id', $bid)->where('m.status', 'posted')
            ->whereNotIn('m.transaction_type', ['payment', 'account_transaction'])
            ->leftJoin('transactions as t', 't.id', '=', 'm.transaction_id')->whereNull('t.id')->pluck('m.transaction_id');
        foreach ($gone as $id) {
            $count($this->transaction($id));
        }
        $gone = DB::table('transaction_mappings as m')->where('m.business_id', $bid)->where('m.status', 'posted')
            ->where('m.transaction_type', 'account_transaction')
            ->leftJoin('account_transactions as at', 'at.id', '=', 'm.transaction_id')->whereNull('at.id')->pluck('m.transaction_id');
        foreach ($gone as $id) {
            $count($this->accountTransaction((int) $id));
        }
        $gone = DB::table('transaction_mappings as m')->where('m.business_id', $bid)->where('m.status', 'posted')
            ->where('m.transaction_type', 'payment')
            ->leftJoin('transaction_payments as p', 'p.id', '=', 'm.transaction_id')->whereNull('p.id')->pluck('m.transaction_id');
        foreach ($gone as $id) {
            $count($this->payment($id));
        }

        AccountingSetting::setValue('last_sweep_at', $started->toDateTimeString());

        return $stats;
    }

    // ------------------------------------------------------------------ opening

    /**
     * Opening balances at go-live: what customers and suppliers owe (or have
     * paid in advance) from the documents before the books start, worked out
     * with the same posting rules, posted as one entry against the
     * opening-balance suspense account (4711). Payments after go-live on those
     * old invoices then clear these balances.
     */
    public function openingFromHistory(?int $userId = null): ?JournalEntry
    {
        if (! $this->postFrom || AccountingSetting::getValue('opening_entry_id')) {
            return null;
        }
        $bid = Ledger::businessId();
        $control = array_filter([
            DefaultAccount::id('customers'), DefaultAccount::id('suppliers'), DefaultAccount::id('customer_advances'),
            DefaultAccount::id('supplier_advances'),
        ]);
        $sum = []; // account|contact => net debit
        $add = function (?array $e) use (&$sum, $control) {
            if (! $e) {
                return;
            }
            foreach ($e['lines'] as $l) {
                if (in_array($l['account_id'], $control)) {
                    $k = $l['account_id'].'|'.($l['contact_id'] ?? '');
                    $sum[$k] = ($sum[$k] ?? 0) + $l['debit'] - $l['credit'];
                }
            }
        };

        $skipped = [];
        DB::table('transactions')->where('business_id', $bid)->whereIn('type', PostingRules::TYPES)
            ->where('transaction_date', '<', $this->postFrom)->orderBy('id')
            ->chunk(500, function ($rows) use ($add, &$skipped) {
                foreach ($rows as $t) {
                    try {
                        $add($this->rules->forTransaction($t));
                    } catch (\RuntimeException $e) {
                        $skipped[] = ['transaction', $t->id, $e->getMessage()];
                    }
                }
            });
        DB::table('transaction_payments')->where('business_id', $bid)->where('paid_on', '<', $this->postFrom)->orderBy('id')
            ->chunk(500, function ($rows) use ($add, &$skipped) {
                $tx = DB::table('transactions')->whereIn('id', $rows->pluck('transaction_id')->filter()->unique())->get()->keyBy('id');
                foreach ($rows as $p) {
                    try {
                        $add($this->rules->forPayment($p, $p->transaction_id ? ($tx[$p->transaction_id] ?? null) : null));
                    } catch (\RuntimeException $e) {
                        $skipped[] = ['payment', $p->id, $e->getMessage()];
                    }
                }
            });
        // Ledger health shows these: the opening balances leave them out
        AccountingSetting::setValue('opening_skipped', array_slice($skipped, 0, 200), 'json');
        AccountingSetting::setValue('opening_skipped_count', count($skipped), 'integer');

        $lines = [];
        $net = 0;
        foreach ($sum as $k => $v) {
            $v = round($v, 2);
            if (abs($v) < 0.01) {
                continue;
            }
            [$acc, $contact] = explode('|', $k);
            $lines[] = ['account_id' => (int) $acc, 'debit' => max(0, $v), 'credit' => max(0, -$v),
                'description' => __('ledger.l_opening_balance'), 'contact_id' => $contact !== '' ? (int) $contact : null];
            $net += $v;
        }
        if (empty($lines)) {
            AccountingSetting::setValue('opening_entry_id', 0);

            return null;
        }
        $offset = DefaultAccount::id('opening_balance');
        $net = round($net, 2);
        $lines[] = ['account_id' => $offset, 'debit' => max(0, -$net), 'credit' => max(0, $net),
            'description' => __('ledger.l_opening_offset')];

        $entry = LedgerPoster::post([
            'entry_date' => $this->postFrom,
            'journal_type' => 'opening',
            'description' => __('ledger.opening_entry_desc', ['date' => Carbon::parse($this->postFrom)->format('d/m/Y')]),
            'reference_type' => 'opening_balances',
            'reference_id' => $bid,
            'is_system_generated' => true,
        ], $lines, $userId);
        AccountingSetting::setValue('opening_entry_id', $entry->id);

        return $entry;
    }
}
