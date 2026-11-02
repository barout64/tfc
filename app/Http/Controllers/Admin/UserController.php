<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Services\Systems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** The TFC administrator: people, their portal login, and which account they use in each system. */
class UserController extends Controller
{
    public function __construct(private Systems $systems) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.users.index', [
            'users' => User::query()->with('links')
                ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->orderByDesc('is_active')->orderBy('name')->paginate(30)->withQueryString(),
            'systems' => $this->systems->all(),
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
        return $this->form($user->load('links'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->save($request, $user);
        Activity::record('changed_person', null, $user->name);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} saved.");
    }

    private function form(User $user): View
    {
        // Only systems an administrator assigns by hand need a picker; the rest are automatic.
        $assigned = collect($this->systems->all())->reject(fn ($s, $key) => $this->systems->defaultAccess($key));

        return view('admin.users.form', [
            'user' => $user,
            'systems' => $this->systems->all(),
            'assignedSystems' => $assigned,
            'lists' => $assigned->map(fn ($s, $key) => $this->systems->usersOrError($key)),
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
            'links' => ['array'],
            'links.*' => ['nullable', 'string', 'max:100'],
        ]);

        // The administrator cannot lock themself out
        if ($user->is($request->user()) && (! $request->boolean('is_admin') || ! $request->boolean('is_active'))) {
            throw ValidationException::withMessages(['is_admin' => 'You cannot remove your own administrator access or switch yourself off.']);
        }

        // Each chosen account must exist in that system (an unreadable list keeps what was there). Systems
        // with default_access are not in this picker at all - every active person gets those automatically.
        $links = [];
        foreach ($this->systems->all() as $key => $s) {
            if ($this->systems->defaultAccess($key)) {
                continue;
            }
            $chosen = (string) ($data['links'][$key] ?? '');
            if ($chosen === '') {
                continue;
            }
            [$people, $error] = $this->systems->usersOrError($key);
            if ($error) {
                $old = $user->exists ? $user->links()->where('system', $key)->first() : null;
                if ($old && $old->remote_id === $chosen) {
                    $links[$key] = [$chosen, $old->remote_name];

                    continue;
                }
                throw ValidationException::withMessages(["links.{$key}" => $error]);
            }
            if (! $people->has($chosen)) {
                throw ValidationException::withMessages(["links.{$key}" => "That account is not in {$s['name']}."]);
            }
            $links[$key] = [$chosen, $people[$chosen]['name']];
        }

        DB::transaction(function () use ($user, $data, $request, $links) {
            $user->fill([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'is_admin' => $request->boolean('is_admin'),
                'is_active' => $request->boolean('is_active'),
                'is_support' => $request->boolean('is_support'),
            ]);
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $user->save();

            // Assigned systems: exactly what the administrator chose just now.
            foreach ($this->systems->all() as $key => $s) {
                if ($this->systems->defaultAccess($key)) {
                    continue;
                }
                if (array_key_exists($key, $links)) {
                    [$id, $name] = $links[$key];
                    $user->links()->updateOrCreate(['system' => $key], ['remote_id' => $id, 'remote_name' => $name]);
                } else {
                    $user->links()->where('system', $key)->delete();
                }
            }

            // Default-access systems: give them an account there the first time, if they don't have one yet.
            if ($user->is_active) {
                foreach ($this->systems->all() as $key => $s) {
                    if (! $this->systems->defaultAccess($key) || $user->links()->where('system', $key)->exists()) {
                        continue;
                    }
                    try {
                        $remoteId = $this->systems->provision($key, $user->name, $user->email);
                        $user->links()->updateOrCreate(['system' => $key], ['remote_id' => $remoteId, 'remote_name' => $user->name]);
                    } catch (Throwable $e) {
                        // Don't block saving this person over one system being briefly unreachable -
                        // they'll be given access automatically next time they're saved.
                        report($e);
                    }
                }
            }

            // Switched off: signed out of the portal everywhere
            if (! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
    }
}
