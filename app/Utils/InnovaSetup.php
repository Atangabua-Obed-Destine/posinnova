<?php

namespace App\Utils;

use App\Business;
use App\Currency;
use App\System;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * I-NNOVA POS installation: the fixed superadmin in its business "I-NNOVA",
 * the package, the one-time link that creates the client's business, and
 * that business itself. Used by php artisan innova:install / innova:superadmin
 * and by the /setup page. See INSTALL.md.
 */
class InnovaSetup
{
    private const TOKEN_KEY = 'innova_setup_token';

    private const TOKEN_DAYS = 7;

    /** The superadmin password hash shipped with this copy of the code, or null when it was never set */
    public static function passwordHash(): ?string
    {
        $hash = (string) config('innova.superadmin.password_hash');

        return preg_match('/^\$2[aby]\$\d\d\$[.\/A-Za-z0-9]{53}$/', $hash) ? $hash : null;
    }

    public static function username(): string
    {
        return strtolower((string) config('innova.superadmin.username'));
    }

    /** A client business exists: the installation is finished and belongs to a running shop */
    public static function hasClientBusiness(): bool
    {
        if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
            return false;
        }

        $ours = self::superadminBusinessId();

        return Business::when($ours, fn ($q) => $q->where('id', '!=', $ours))->exists();
    }

    public static function superadminBusinessId(): ?int
    {
        $user = User::where('username', self::username())->first();

        return $user ? (int) $user->business_id : null;
    }

    /**
     * Creates the superadmin and its business "I-NNOVA" if missing; otherwise brings its password
     * back to the one shipped with the code (the same on every installation).
     *
     * @return array{user: User, created: bool, password_changed: bool}
     */
    public static function ensureSuperadmin(): array
    {
        $hash = self::passwordHash();
        if ($hash === null) {
            throw new \RuntimeException('The superadmin password is not set in this copy of the code (config/innova.php). Run php artisan innova:superadmin-password on the I-NNOVA computer, commit and push, then update this copy.');
        }

        $cfg = config('innova.superadmin');
        $user = User::where('username', self::username())->first();

        if ($user) {
            $changed = ! hash_equals((string) $user->password, $hash);
            // Straight to the column: the hash is already a hash
            DB::table('users')->where('id', $user->id)->update(['password' => $hash, 'status' => 'active', 'allow_login' => 1, 'deleted_at' => null]);

            return ['user' => $user->fresh(), 'created' => false, 'password_changed' => $changed];
        }

        $user = DB::transaction(function () use ($cfg, $hash) {
            $user = User::create_user([
                'surname' => null,
                'first_name' => $cfg['first_name'],
                'last_name' => $cfg['last_name'],
                'username' => self::username(),
                'email' => null,
                'password' => Str::random(40),
                'language' => config('innova.defaults.language', 'en'),
            ]);
            DB::table('users')->where('id', $user->id)->update(['password' => $hash]);

            self::createBusinessFor($user, [
                'name' => $cfg['business_name'],
                'currency_id' => self::defaultCurrencyId(),
                'time_zone' => config('innova.defaults.time_zone'),
                'country' => config('innova.defaults.country'),
                'city' => '-',
            ]);

            return $user->fresh();
        });

        return ['user' => $user, 'created' => true, 'password_changed' => true];
    }

    /** The I-NNOVA package (all features, free), when the Superadmin module is there */
    public static function ensurePackage(): ?object
    {
        if (! self::superadminInstalled()) {
            return null;
        }

        $cfg = config('innova.package');
        $package = \Modules\Superadmin\Entities\Package::where('name', $cfg['name'])->first();
        if ($package) {
            return $package;
        }

        $permissions = [];
        foreach ((new ModuleUtil())->getModuleData('superadmin_package') as $module_permissions) {
            foreach ((array) $module_permissions as $p) {
                if (! empty($p['name'])) {
                    $permissions[$p['name']] = '1';
                }
            }
        }

        return \Modules\Superadmin\Entities\Package::create([
            'name' => $cfg['name'],
            'description' => $cfg['description'],
            'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
            'interval' => 'years',
            'interval_count' => (int) $cfg['years'],
            'trial_days' => 0,
            'price' => 0,
            'custom_permissions' => $permissions,
            'created_by' => optional(User::where('username', self::username())->first())->id ?? 1,
            'sort_order' => 1,
            'is_active' => 1,
            'is_private' => 1,
            'is_one_time' => 0,
            'enable_custom_link' => 0,
            'mark_package_as_popular' => 0,
        ]);
    }

    /**
     * The client's business and its owner, subscribed to the I-NNOVA package until $until.
     *
     * @param  array  $business  name, currency_id, time_zone, country, city
     * @param  array  $owner  first_name, last_name, username, email, password, language
     */
    public static function createClientBusiness(array $business, array $owner, ?Carbon $until = null): Business
    {
        return DB::transaction(function () use ($business, $owner, $until) {
            $user = User::create_user([
                'surname' => null,
                'first_name' => $owner['first_name'],
                'last_name' => $owner['last_name'] ?? null,
                'username' => $owner['username'],
                'email' => $owner['email'] ?? null,
                'password' => $owner['password'],
                'language' => $owner['language'] ?? 'en',
            ]);

            $biz = self::createBusinessFor($user, $business);

            $package = self::ensurePackage();
            if ($package) {
                $start = Carbon::today();
                $end = $until ?: $start->copy()->addYears((int) config('innova.package.years', 7));
                $details = ['location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0, 'name' => $package->name];
                foreach ((array) $package->custom_permissions as $k => $v) {
                    $details[$k] = $v;
                }
                \Modules\Superadmin\Entities\Subscription::create([
                    'business_id' => $biz->id,
                    'package_id' => $package->id,
                    'start_date' => $start->toDateString(),
                    'trial_end_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'package_price' => 0,
                    'original_price' => 0,
                    'package_details' => $details,
                    'created_id' => optional(User::where('username', self::username())->first())->id ?? $user->id,
                    'paid_via' => 'offline',
                    'payment_transaction_id' => 'I-NNOVA',
                    'status' => 'approved',
                ]);
            }

            return $biz;
        });
    }

    private static function createBusinessFor(User $user, array $data): Business
    {
        $util = new BusinessUtil();

        $business = $util->createNewBusiness([
            'name' => $data['name'],
            'currency_id' => $data['currency_id'],
            'time_zone' => $data['time_zone'],
            'start_date' => Carbon::today()->toDateString(),
            'fy_start_month' => 1,
            'accounting_method' => 'fifo',
            'owner_id' => $user->id,
            'enabled_modules' => ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'],
        ]);

        $user->business_id = $business->id;
        $user->save();

        $util->newBusinessDefaultResources($business->id, $user->id);
        $location = $util->addLocation($business->id, [
            'name' => $data['name'],
            'country' => $data['country'],
            'state' => $data['city'],
            'city' => $data['city'],
        ]);
        Permission::firstOrCreate(['name' => 'location.'.$location->id, 'guard_name' => 'web']);

        if (config('app.env') != 'demo') {
            (new ModuleUtil())->getModuleData('after_business_created', ['business' => $business]);
        }

        return $business;
    }

    public static function defaultCurrencyId(): int
    {
        return (int) (Currency::where('code', config('innova.defaults.currency_code', 'XAF'))->value('id')
            ?: Currency::orderBy('id')->value('id'));
    }

    private static function superadminInstalled(): bool
    {
        return Schema::hasTable('packages') && (new ModuleUtil())->isSuperadminInstalled();
    }

    /** A new one-time link token (the old one stops working) */
    public static function issueToken(): string
    {
        $token = Str::random(40);
        $value = json_encode(['hash' => hash('sha256', $token), 'until' => now()->addDays(self::TOKEN_DAYS)->timestamp]);

        if (System::getProperty(self::TOKEN_KEY) === null) {
            System::addProperty(self::TOKEN_KEY, $value);
        } else {
            System::setProperty(self::TOKEN_KEY, $value);
        }
        System::flushPropertyCache();

        return $token;
    }

    public static function tokenIsValid(?string $token): bool
    {
        if (empty($token) || ! Schema::hasTable('system')) {
            return false;
        }
        System::flushPropertyCache();
        $saved = json_decode((string) System::getProperty(self::TOKEN_KEY), true);

        return is_array($saved)
            && ($saved['until'] ?? 0) >= now()->timestamp
            && hash_equals((string) ($saved['hash'] ?? ''), hash('sha256', $token));
    }

    public static function forgetToken(): void
    {
        System::removeProperty(self::TOKEN_KEY);
        System::flushPropertyCache();
    }
}
