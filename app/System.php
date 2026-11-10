<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class System extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * PosInnova: per-request memo for the `system` settings table.
     *
     * The POS create screen asked this table 61 times per page load for only 3
     * distinct rows - the `layouts.*` view composer and ModuleUtil::getModuleData()
     * each re-read the same module-version rows on every partial rendered. The
     * values cannot change while a single page is being rendered, so they are
     * read once and reused.
     *
     * Shape: [ key => ['exists' => bool, 'value' => mixed] ]
     *
     * 'exists' is tracked separately from 'value' because a row may legitimately
     * hold a NULL value, and getProperties() must keep omitting absent keys
     * exactly as pluck() did.
     *
     * Lifetime is one PHP process. QUEUE_CONNECTION is `sync`, so no worker
     * outlives a request; every write path below flushes anyway.
     *
     * @var array
     */
    protected static $property_cache = [];

    /**
     * Drop the memo. Call this after writing to the `system` table by any route
     * that bypasses the helpers below (e.g. a raw query-builder update).
     *
     * @return void
     */
    public static function flushPropertyCache()
    {
        static::$property_cache = [];
    }

    /**
     * Catch writes that go through the model but not through the helpers below,
     * such as System::updateOrCreate() in the Superadmin settings controller.
     *
     * Note: mass update/delete via the query builder does NOT fire these, which
     * is why setProperty()/removeProperty() flush explicitly.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saved(function () {
            static::flushPropertyCache();
        });

        static::deleted(function () {
            static::flushPropertyCache();
        });
    }

    /**
     * Read the given keys that are not memoized yet, in a single query, and
     * record both present and absent keys so each key is only ever asked once.
     *
     * @param  array  $keys
     * @return void
     */
    protected static function primePropertyCache(array $keys)
    {
        $missing = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, static::$property_cache)) {
                $missing[$key] = true;
            }
        }

        if (empty($missing)) {
            return;
        }

        $missing = array_keys($missing);

        $rows = DB::table('system')
            ->select('key', 'value')
            ->whereIn('key', $missing)
            ->get();

        // Seed every requested key as absent, then overwrite the ones found.
        // Absent keys are cached too, so a missing row is not re-queried.
        foreach ($missing as $key) {
            static::$property_cache[$key] = ['exists' => false, 'value' => null];
        }

        foreach ($rows as $row) {
            static::$property_cache[$row->key] = ['exists' => true, 'value' => $row->value];
        }
    }

    /**
     * Return the value of the property
     *
     * @param  $key  string
     * @return mixed
     */
    public static function getProperty($key)
    {
        static::primePropertyCache([$key]);

        $cached = static::$property_cache[$key];

        // Matches the original isset($row->value) test: a missing row and a row
        // holding NULL both return null.
        return $cached['exists'] ? $cached['value'] : null;
    }

    /**
     * Return the value of the multiple properties
     *
     * @param  $keys  array
     * @return mixed
     */
    public static function getProperties($keys, $pluck = false)
    {
        if ($pluck == true) {
            static::primePropertyCache((array) $keys);

            $values = [];
            foreach ((array) $keys as $key) {
                if (static::$property_cache[$key]['exists']) {
                    $values[$key] = static::$property_cache[$key]['value'];
                }
            }

            // A FRESH collection every call. Callers mutate what they get back -
            // AppServiceProvider appends module JS/CSS onto the returned
            // collection - so handing out a shared instance would let those
            // appends pile up across the ~10 `layouts.*` composer runs.
            return collect($values);
        }

        // Uncached: returns whole rows (id included) and has no callers today.
        return System::whereIn('key', $keys)
            ->get()
            ->toArray();
    }

    /**
     * Return the system default currency details
     *
     * @param  void
     * @return object
     */
    public static function getCurrency()
    {
        $c_id = System::where('key', 'app_currency_id')
                ->first()
                ->value;

        $currency = Currency::find($c_id);

        return $currency;
    }

    /**
     * Set the property
     *
     * @param  $key
     * @param  $value
     * @return void
     */
    public static function setProperty($key, $value)
    {
        System::where('key', $key)
            ->update(['value' => $value]);

        static::flushPropertyCache();
    }

    /**
     * Remove the specified property
     *
     * @param  $key
     * @return void
     */
    public static function removeProperty($key)
    {
        System::where('key', $key)
            ->delete();

        static::flushPropertyCache();
    }

    /**
     * Add a new property, if exist update the value
     *
     * @param  $key
     * @param  $value
     * @return void
     */
    public static function addProperty($key, $value)
    {
        System::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        static::flushPropertyCache();
    }
}
