<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AwayCheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class WelcomeBackController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $prompt = $request->session()->get('away_prompt');
        if (! is_array($prompt)) {
            return redirect($this->user()->homePath());
        }

        return view('auth.welcome-back', [
            'days' => (int) ($prompt['days'] ?? 7),
            'name' => $this->user()->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prompt = $request->session()->get('away_prompt');
        $user = $this->user();
        if (! is_array($prompt)) {
            return redirect($user->homePath());
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = trim((string) ($data['reason'] ?? ''));

        AwayCheckIn::query()->create([
            'tenantId' => $user->tenantId,
            'userId' => $user->id,
            'daysAway' => (int) ($prompt['days'] ?? 7),
            'lastSeenAt' => isset($prompt['lastSeenAt']) ? Carbon::parse($prompt['lastSeenAt']) : null,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        $request->session()->forget('away_prompt');

        return redirect()->intended($user->homePath());
    }
}
