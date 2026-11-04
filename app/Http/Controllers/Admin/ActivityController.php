<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.activity', [
            'activities' => Activity::query()->with('user')->latest('id')->paginate(50),
        ]);
    }
}
