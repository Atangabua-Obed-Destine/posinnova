<?php

namespace App\Http\Controllers;

use App\PressingOrder;
use App\Utils\Pressing;
use App\Utils\PressingException;

/**
 * PosInnova pressing: what every pressing page shares. The module must be switched on for the
 * business (Settings › Modules › Pressing); each action names the permission it needs (Admins have all).
 */
abstract class PressingBaseController extends Controller
{
    protected function biz()
    {
        return (int) request()->session()->get('user.business_id');
    }

    /** 404 when the module is off, 403 without one of the permissions. */
    protected function guard(...$perms)
    {
        if (! Pressing::enabled($this->biz())) {
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

    protected function order($id)
    {
        return PressingOrder::where('business_id', $this->biz())->with(['contact', 'pieces.item', 'pieces.service', 'events.user', 'problems.piece.item', 'reservation.room'])->findOrFail($id);
    }

    /** Runs an action and comes back with its message, or the rule that stopped it. */
    protected function attempt(callable $fn, $success, $back = null)
    {
        try {
            $r = $fn();
        } catch (PressingException $e) {
            return redirect()->back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        }
        $to = is_callable($back) ? $back($r) : redirect()->back();

        return $to->with('status', ['success' => 1, 'msg' => is_callable($success) ? $success($r) : $success]);
    }
}
