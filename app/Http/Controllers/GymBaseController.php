<?php

namespace App\Http\Controllers;

use App\GymMember;
use App\Utils\Gym;
use App\Utils\GymException;
use App\Utils\HotelException;

/**
 * PosInnova gym: what every gym page shares. The module must be switched on for the business
 * (Settings › Modules › Gym); each action names the permission it needs (Admins have all).
 */
abstract class GymBaseController extends Controller
{
    protected function biz()
    {
        return (int) request()->session()->get('user.business_id');
    }

    protected function guard(...$perms)
    {
        if (! Gym::enabled($this->biz())) {
            abort(404);
        }
        foreach ($perms as $p) {
            if (auth()->user()->can($p)) {
                return;
            }
        }
        abort(403, __('messages.unauthorized_action'));
    }

    public static function can($perm)
    {
        return auth()->check() && auth()->user()->can($perm);
    }

    protected function member($id)
    {
        return GymMember::where('business_id', $this->biz())->with('contact')->findOrFail($id);
    }

    protected function attempt(callable $fn, $success, $back = null)
    {
        try {
            $r = $fn();
        } catch (GymException|HotelException $e) {
            return redirect()->back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        }
        $to = is_callable($back) ? $back($r) : redirect()->back();

        return $to->with('status', ['success' => 1, 'msg' => is_callable($success) ? $success($r) : $success]);
    }
}
