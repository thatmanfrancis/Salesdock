<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantRegistration;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register', ['businessTypes' => self::businessTypes()]);
    }

    public static function businessTypes(): array
    {
        return ['Supermarket', 'Fashion', 'Electronics', 'Pharmacy', 'Restaurant', 'Grocery', 'Beauty', 'Hardware', 'General'];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ownerName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'businessName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'businessType' => ['required', 'string', Rule::in(self::businessTypes())],
            'city' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'rcNumber' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withInput()->withErrors(['email' => 'Email already registered']);
        }

        $existing = TenantRegistration::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing) {
            $live = Tenant::query()->whereRaw('lower(email) = ?', [$email])->exists();
            $canReuse = $existing->status === 'REJECTED' || (! $live && $existing->planSelected);
            if (! $canReuse) {
                return back()->withInput()->withErrors(['email' => 'Email already registered']);
            }
            $existing->delete();
        }

        $token = Str::random(64);
        TenantRegistration::query()->create([
            'ownerName' => $data['ownerName'],
            'email' => $email,
            'passwordHash' => Hash::make($data['password'], ['rounds' => 12]),
            'businessName' => $data['businessName'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'businessType' => $data['businessType'] ?? null,
            'city' => $data['city'] ?? null,
            'message' => $data['message'] ?? null,
            'tin' => $data['tin'] ?? null,
            'rcNumber' => $data['rcNumber'] ?? null,
            'website' => $data['website'] ?? null,
            'verifyToken' => $token,
            'verifyTokenExp' => now()->addDay(),
        ]);

        AuthMail::send($email, $data['ownerName'], 'Verify your email — SalesDock', [
            'preheader' => 'Confirm your email to finish the SalesDock application.',
            'heading' => 'Verify your email address',
            'kicker' => "You're almost there.",
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($data['ownerName']).'</strong>, please verify your email address to complete the SalesDock application for <strong style="color:#111827;">'.e($data['businessName']).'</strong>.',
                'The application stays on hold until this address is confirmed. Use the button below. Nothing else is required from you right now.',
            ],
            'url' => route('verify-email', ['token' => $token]),
            'label' => 'Verify Email Address',
            'stepsTitle' => 'What happens next',
            'steps' => [
                'Open the verification link. It expires in 24 hours.',
                'Our team reviews the business, usually within 24–48 hours.',
                'If it is approved, you receive another email with a 72-hour link to choose a plan.',
            ],
            'note' => 'If you did not apply for SalesDock, you can ignore this email.',
        ]);

        return redirect()->route('register')->with('sent', $email);
    }
}
