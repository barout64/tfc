<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\Systems;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(Systems $systems): View
    {
        return view('admin.activity', [
            'activities' => Activity::query()->with('user')->latest('id')->paginate(50),
            'systems' => $systems,
        ]);
    }
}
