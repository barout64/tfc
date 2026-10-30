<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AgroDealer;
use App\Models\AuditLog;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::with(['warehouse', 'agroDealer', 'transporter'])
                ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
                ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'roles' => Role::options(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', $this->formData(new User(['role' => Role::Viewer, 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create($this->validated($request));
        AuditLog::record('user.created', "Created user {$user->email} ({$user->role->value})", $user);

        return redirect()->route('users.index')->with('success', __('User created.'));
    }

    public function edit(User $user): View
    {
        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->with('error', __('You cannot deactivate your own account.'));
        }

        $user->update($data);
        AuditLog::record('user.updated', "Updated user {$user->email}", $user, collect($user->getChanges())->except(['password', 'updated_at'])->all());

        return redirect()->route('users.index')->with('success', __('User updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(Role::class)],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'agro_dealer_id' => ['required_if:role,agro_dealer', 'nullable', 'exists:agro_dealers,id'],
            'transporter_id' => ['required_if:role,transporter', 'nullable', 'exists:transporters,id'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active')];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::options(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
            'dealers' => AgroDealer::orderBy('business_name')->get(['id', 'business_name']),
            'transporters' => Transporter::orderBy('name')->get(['id', 'name']),
        ];
    }
}
