<?php

namespace App\Http\Controllers;

use App\ClinicExamTemplate;
use App\Utils\ClinicExamTemplates;
use App\Utils\ClinicOptions;
use Illuminate\Http\Request;

/**
 * PosInnova clinic: Clinic setup > Examination forms. The clinic builds its own forms (ENT, eye,
 * gynaecology...) by dragging blocks - section titles, pictures to draw on, one-click answers, text,
 * numbers - and chooses the specialties that open each form in a consultation.
 */
class ClinicExamTemplateController extends Controller
{
    private function authorizeManage()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('clinic_settings.manage')) {
            abort(403, 'Unauthorized action.');
        }

        return request()->session()->get('user.business_id');
    }

    private function find($business_id, $id)
    {
        return ClinicExamTemplate::where('business_id', $business_id)->findOrFail($id);
    }

    public function index()
    {
        $business_id = $this->authorizeManage();
        ClinicExamTemplates::seed($business_id);
        $templates = ClinicExamTemplate::where('business_id', $business_id)->orderBy('sort_order')->orderBy('name')->get();

        return view('clinic.exam_templates.index', compact('templates'));
    }

    public function create()
    {
        $business_id = $this->authorizeManage();
        $template = new ClinicExamTemplate(['name' => '', 'specialties' => [], 'blocks' => [], 'is_active' => true]);

        return $this->builder($business_id, $template);
    }

    public function edit($id)
    {
        $business_id = $this->authorizeManage();

        return $this->builder($business_id, $this->find($business_id, $id));
    }

    private function builder($business_id, ClinicExamTemplate $template)
    {
        $specialties = collect(ClinicOptions::values($business_id, 'specialty'))
            ->merge((array) $template->specialties)->unique(fn ($s) => mb_strtolower($s))->values()->all();
        $library = [];
        foreach (ClinicExamTemplates::LIBRARY as $path => $label) {
            $library[] = ['path' => $path, 'url' => asset($path), 'label' => __($label), 'own' => false];
        }
        foreach (ClinicExamTemplates::uploadedImages($business_id) as $path) {
            $library[] = ['path' => $path, 'url' => asset($path), 'label' => basename($path), 'own' => true];
        }

        return view('clinic.exam_templates.edit', compact('template', 'specialties', 'library'));
    }

    /** The form's name, specialties and blocks, as sent by the builder. */
    private function input(Request $request, $business_id)
    {
        $request->validate(['name' => 'required|string|max:191']);
        $blocks = json_decode((string) $request->input('blocks'), true);
        $specialties = collect((array) $request->input('specialties', []))
            ->map(fn ($s) => ClinicOptions::clean($s))->filter()->unique(fn ($s) => mb_strtolower($s))->values()->all();

        return [
            'name' => ClinicOptions::clean($request->input('name')),
            'specialties' => $specialties,
            'blocks' => ClinicExamTemplates::normalizeBlocks($blocks, $business_id),
            'is_active' => $request->boolean('is_active') ? 1 : 0,
        ];
    }

    public function store(Request $request)
    {
        $business_id = $this->authorizeManage();
        $data = $this->input($request, $business_id);
        $max = (int) ClinicExamTemplate::where('business_id', $business_id)->max('sort_order');
        $t = ClinicExamTemplate::create($data + ['business_id' => $business_id, 'sort_order' => $max + 1, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return response()->json(['success' => true, 'msg' => __('clinic.ex_tpl_saved'),
            'redirect' => action([self::class, 'edit'], [$t->id])]);
    }

    public function update(Request $request, $id)
    {
        $business_id = $this->authorizeManage();
        $t = $this->find($business_id, $id);
        $t->fill($this->input($request, $business_id) + ['updated_by' => auth()->id()])->save();

        return response()->json(['success' => true, 'msg' => __('clinic.ex_tpl_saved'), 'blocks' => $t->blocks]);
    }

    public function destroy($id)
    {
        $business_id = $this->authorizeManage();
        $this->find($business_id, $id)->delete(); // consultations keep their own copy

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => __('clinic.ex_tpl_deleted')]);
    }

    public function duplicate($id)
    {
        $business_id = $this->authorizeManage();
        $t = $this->find($business_id, $id);
        $copy = $t->replicate(['created_at', 'updated_at']);
        $copy->name = mb_substr(__('clinic.ex_tpl_copy_of', ['name' => $t->name]), 0, 191);
        $copy->specialties = [];
        $copy->is_active = 0;
        $copy->sort_order = (int) ClinicExamTemplate::where('business_id', $business_id)->max('sort_order') + 1;
        $copy->created_by = auth()->id();
        $copy->save();

        return redirect()->action([self::class, 'edit'], [$copy->id])->with('status', ['success' => true, 'msg' => __('clinic.ex_tpl_duplicated')]);
    }

    public function toggle($id)
    {
        $business_id = $this->authorizeManage();
        $t = $this->find($business_id, $id);
        $t->is_active = ! $t->is_active;
        $t->save();

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => __('clinic.ex_tpl_saved')]);
    }

    public function readyMade(Request $request)
    {
        $business_id = $this->authorizeManage();
        $key = (string) $request->input('key');
        abort_unless(isset(ClinicExamTemplates::READY_MADE[$key]), 404);
        $t = ClinicExamTemplates::addReadyMade($business_id, $key, auth()->id());

        return redirect()->action([self::class, 'edit'], [$t->id])->with('status', ['success' => true, 'msg' => __('clinic.ex_tpl_added')]);
    }

    /**
     * A picture for the forms (an anatomy drawing...), kept as PNG: it is redrawn here, so only the
     * picture itself is kept.
     */
    public function uploadImage(Request $request)
    {
        $business_id = $this->authorizeManage();
        $request->validate(['image' => 'required|file|max:5120|mimes:png,jpg,jpeg,webp,gif'], ['image.mimes' => __('clinic.ex_img_bad')]);
        $file = $request->file('image');
        $im = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $im) {
            return response()->json(['success' => false, 'msg' => __('clinic.ex_img_bad')], 422);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $max = 1600;
        if ($w > $max || $h > $max) {
            $r = min($max / $w, $max / $h);
            $nw = max(1, (int) round($w * $r));
            $nh = max(1, (int) round($h * $r));
            $out = imagecreatetruecolor($nw, $nh);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im);
            $im = $out;
        } else {
            imagesavealpha($im, true);
        }
        $dir = ClinicExamTemplates::uploadDir($business_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))), '_') ?: 'picture';
        $name = substr($slug, 0, 40).'_'.bin2hex(random_bytes(4)).'.png';
        imagepng($im, $dir.DIRECTORY_SEPARATOR.$name, 9);
        imagedestroy($im);
        $path = 'uploads/clinic_diagrams/'.(int) $business_id.'/'.$name;

        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path), 'label' => $name]);
    }

    /** The form as the doctor will see it, from the builder's blocks (not saved). */
    public function preview(Request $request)
    {
        $business_id = $this->authorizeManage();
        $blocks = ClinicExamTemplates::normalizeBlocks(json_decode((string) $request->input('blocks'), true), $business_id);

        return view('consultation.exam.form', ['blocks' => $blocks, 'values' => [], 'exam' => null]);
    }
}
