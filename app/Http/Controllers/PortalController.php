<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\Systems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PortalController extends Controller
{
    public function __construct(private Systems $systems) {}

    /** Every TFC system; the ones this person may open are unlocked. */
    public function index(Request $request): View
    {
        $user = $request->user()->load('links');

        return view('home', [
            // Only what the page shows: never the system's signing key
            'systems' => collect($this->systems->all())->map(fn ($s, $id) => [
                'id' => $id, 'name' => $s['name'], 'about' => $s['about'], 'link' => $user->linkTo($id),
            ]),
        ]);
    }

    /** Into the system, already signed in as their account there. */
    public function open(Request $request, string $system): RedirectResponse
    {
        abort_unless($this->systems->has($system), 404);
        $link = $request->user()->links()->where('system', $system)->first();
        if (! $link) {
            return redirect()->route('home')->with('error', "You do not have access to {$this->systems->name($system)}. Ask the TFC administrator.");
        }

        try {
            $url = $this->systems->signInLink($system, $link->remote_id);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('home')->with('error', "{$this->systems->name($system)} cannot be opened from the portal right now.");
        }
        Activity::record('opened', $system, $link->remote_name);

        return redirect()->away($url);
    }
}
