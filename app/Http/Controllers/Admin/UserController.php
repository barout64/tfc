<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Station;
use App\Models\User;
use App\Support\MeetingUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The TFC administrator: people, their sign-in, and what they do in Meetings, Protocol and Daily Report. */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.users.index', [
            'users' => User::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->orderByDesc('is_active')->orderBy('name')->paginate(30)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = new User;
        $this->save($request, $user);
        Activity::record('added_person', null, $user->name);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} added.");
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->save($request, $user);
        Activity::record('changed_person', null, $user->name);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} saved.");
    }

    private function form(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'departments' => Department::query()->orderBy('name')->get(),
            'stations' => Station::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function save(Request $request, User $user): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'password' => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_admin' => ['boolean'],
            'is_active' => ['boolean'],
            'is_support' => ['boolean'],
            'station_id' => ['nullable', 'integer', Rule::exists('stations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'job_title' => ['nullable', 'string', 'max:120'],
            'meeting_role' => ['nullable', Rule::in(array_keys(MeetingUi::ROLES))],
            'protocol_role' => ['nullable', Rule::in(['admin', 'protocol', 'head', 'secretary', 'employee'])],
        ]);

        // The administrator cannot lock themself out
        if ($user->is($request->user()) && (! $request->boolean('is_admin') || ! $request->boolean('is_active'))) {
            throw ValidationException::withMessages(['is_admin' => 'You cannot remove your own administrator access or switch yourself off.']);
        }

        DB::transaction(function () use ($user, $data, $request) {
            $user->fill([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'is_admin' => $request->boolean('is_admin'),
                'is_active' => $request->boolean('is_active'),
                'is_support' => $request->boolean('is_support'),
                'station_id' => $data['station_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'meeting_role' => $data['meeting_role'] ?? $user->meeting_role ?? 'member',
                'protocol_role' => $data['protocol_role'] ?? $user->protocol_role ?? 'employee',
            ]);
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $user->save();

            // Switched off: signed out of the portal everywhere
            if (! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
    }
}
