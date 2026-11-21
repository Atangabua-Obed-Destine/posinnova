<?php

namespace App\Http\Controllers;

use App\HotelReservation;
use App\Utils\Hotel;
use App\Utils\HotelException;
use App\Utils\TransactionUtil;

/**
 * PosInnova hotel: what every hotel page shares. The module must be switched on for
 * the business (Settings › Modules › Hotel); each action names the permission it needs
 * (Admins have them all).
 */
abstract class HotelBaseController extends Controller
{
    protected $tu;

    public function __construct(TransactionUtil $tu)
    {
        $this->tu = $tu;
    }

    protected function biz()
    {
        return (int) request()->session()->get('user.business_id');
    }

    /** 404 when the module is off, 403 without one of the permissions. */
    protected function guard(...$perms)
    {
        if (! Hotel::enabled($this->biz())) {
            abort(404);
        }
        $u = auth()->user();
        foreach ($perms as $p) {
            if ($u->can($p)) {
                return;
            }
        }
        abort(403, __('messages.unauthorized_action'));
    }

    public static function can($perm)
    {
        return auth()->check() && auth()->user()->can($perm);
    }

    protected function reservation($id)
    {
        return HotelReservation::where('business_id', $this->biz())->with(['contact', 'roomType', 'room'])->findOrFail($id);
    }

    /** Runs a hotel action and comes back with its message, or the rule that stopped it. */
    protected function attempt(callable $fn, $success_msg, $back = null)
    {
        $back = $back ?: redirect()->back();
        try {
            $result = $fn();
        } catch (HotelException $e) {
            return $back->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $back->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        } catch (\Exception $e) {
            \Log::emergency('Hotel: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return $back->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        return $back->with('status', ['success' => 1, 'msg' => is_callable($success_msg) ? $success_msg($result) : $success_msg]);
    }

    protected function money($v, $symbol = true)
    {
        return $this->tu->num_f($v, $symbol);
    }
}
