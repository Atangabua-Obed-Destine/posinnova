<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova gym module: plans and passes, members (a contact with a member number and card),
 * their memberships (a dated period, or a number of visits), and the visits counted at the door.
 * Every membership and day pass is an ordinary sale (App\Utils\Gym).
 */
return new class extends Migration
{
    private $permissions = ['gym.door', 'gym.members', 'gym.setup'];

    public function up(): void
    {
        Schema::create('gym_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->unique();
            $t->longText('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('gym_plans', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('name', 120);
            $t->string('kind', 10);                      // day | months | visits
            $t->unsignedSmallInteger('months')->default(0);
            $t->unsignedSmallInteger('visits')->default(0);
            $t->decimal('price', 22, 4);
            $t->unsignedInteger('variation_id')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(1);
            $t->timestamps();
        });

        Schema::create('gym_members', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('seq');
            $t->string('member_no', 30);
            $t->unsignedInteger('contact_id');
            $t->string('phone', 40)->nullable();
            $t->boolean('whatsapp')->default(1);
            $t->string('lang', 5)->default('fr');
            $t->text('note')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'seq']);
            $t->index(['business_id', 'contact_id']);
        });

        Schema::create('gym_memberships', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedBigInteger('member_id')->index();
            $t->unsignedBigInteger('plan_id');
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedSmallInteger('visits_left')->nullable();
            $t->decimal('amount', 22, 4)->default(0);
            $t->unsignedInteger('transaction_id')->nullable();
            $t->dateTime('reminded_before_at')->nullable();
            $t->dateTime('reminded_after_at')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('gym_visits', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('kind', 12);                      // member | day_pass | guest
            $t->unsignedBigInteger('member_id')->nullable();
            $t->unsignedBigInteger('membership_id')->nullable();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable();
            $t->unsignedInteger('transaction_id')->nullable();
            $t->string('name', 150)->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->dateTime('visited_at');
            $t->index(['business_id', 'visited_at']);
            $t->index('member_id');
        });

        if (Schema::hasTable('clinic_whatsapp_messages') && ! Schema::hasColumn('clinic_whatsapp_messages', 'gym_member_id')) {
            Schema::table('clinic_whatsapp_messages', function (Blueprint $t) {
                $t->unsignedBigInteger('gym_member_id')->nullable()->index();
            });
        }

        $now = now();
        foreach ($this->permissions as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app()['cache']->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinic_whatsapp_messages', 'gym_member_id')) {
            Schema::table('clinic_whatsapp_messages', function (Blueprint $t) {
                $t->dropColumn('gym_member_id');
            });
        }
        foreach (['gym_visits', 'gym_memberships', 'gym_members', 'gym_plans', 'gym_settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
