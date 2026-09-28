@extends('layouts.app')

@section('title', 'Profile | SalesDock')

@section('content')
    <div class="page">
        <div class="settings">
            <nav class="settings-nav" aria-label="Profile">
                @foreach ($sections as $key => $label)
                    <a href="{{ route('profile', ['section' => $key]) }}" @class(['on' => $section === $key]) @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="settings-main">
                @if ($section === 'profile')
                    <section class="card profile-card">
                        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="profile">
                            <div class="profile-layout">
                                <div class="profile-id">
                                    @if ($member->avatarUrl)
                                        <img src="{{ $member->avatarUrl }}" alt="">
                                    @else
                                        <span class="face">{{ $initials }}</span>
                                    @endif
                                    <strong>{{ $member->name }}</strong>
                                    <em>{{ $member->email }}</em>
                                    <div class="pills">
                                        <span class="pill">{{ $member->role?->name ?: '—' }}</span>
                                        @if ($member->branch?->name)
                                            <span class="pill">{{ $member->branch->name }}</span>
                                        @endif
                                    </div>
                                    <div class="csv-file" data-file>
                                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-file-input>
                                        <button type="button" class="tool" data-file-open>Change photo</button>
                                    </div>
                                </div>
                                <div class="profile-fields">
                                    <label>
                                        <span>Name <span class="req">*</span></span>
                                        <input name="name" value="{{ old('section') === 'profile' ? old('name') : $member->name }}" required maxlength="255">
                                    </label>
                                    <label>
                                        <span>Phone</span>
                                        <input name="phone" value="{{ old('section') === 'profile' ? old('phone') : $member->phone }}" placeholder="0803 000 0000" maxlength="50">
                                    </label>
                                    <label>
                                        <span>Email</span>
                                        <input type="email" value="{{ $member->email }}" readonly>
                                    </label>
                                    <div class="ui-modal-actions">
                                        <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'password')
                    <section class="card profile-narrow">
                        <form method="post" action="{{ route('profile.update') }}" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="password">
                            <label>
                                <span>Current password <span class="req">*</span></span>
                                <input type="password" name="current" required autocomplete="current-password">
                            </label>
                            <label>
                                <span>New password <span class="req">*</span></span>
                                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                            </label>
                            <label>
                                <span>Confirm new password <span class="req">*</span></span>
                                <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
                            </label>
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Changing…"><span data-label>Change password</span></button>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'pin')
                    <section class="card profile-narrow">
                        <form method="post" action="{{ route('profile.update') }}" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="pin">
                            <label>
                                <span>New PIN <span class="req">*</span></span>
                                <input name="pin" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" required autocomplete="off" placeholder="4–6 digits">
                            </label>
                            <label>
                                <span>Confirm PIN <span class="req">*</span></span>
                                <input name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4,6}" minlength="4" maxlength="6" required autocomplete="off" placeholder="4–6 digits">
                            </label>
                            <label>
                                <span>Current password <span class="req">*</span></span>
                                <input type="password" name="current" required autocomplete="current-password">
                            </label>
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Saving…"><span data-label>Set PIN</span></button>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'two-factor')
                    <section class="card profile-narrow">
                        <div class="profile-status">
                            <strong>Two-factor</strong>
                            <span class="badge {{ $member->twoFaEnabled ? 'active' : 'cancelled' }}">{{ $member->twoFaEnabled ? 'On' : 'Off' }}</span>
                        </div>
                        @if ($member->twoFaEnabled)
                            <form method="post" action="{{ route('profile.update') }}" data-busy>
                                @csrf
                                <input type="hidden" name="section" value="two-factor">
                                <input type="hidden" name="intent" value="disable">
                                <label>
                                    <span>Password <span class="req">*</span></span>
                                    <input type="password" name="current" required autocomplete="current-password">
                                </label>
                                <div class="ui-modal-actions">
                                    <button type="submit" class="danger" data-loading="Turning off…"><span data-label>Turn off</span></button>
                                </div>
                            </form>
                        @elseif ($pendingSecret)
                            <div class="qr">{!! $qr !!}</div>
                            <p class="secret">{{ $pendingSecret }}</p>
                            <form method="post" action="{{ route('profile.update') }}" data-busy>
                                @csrf
                                <input type="hidden" name="section" value="two-factor">
                                <input type="hidden" name="intent" value="confirm">
                                <label>
                                    <span>6-digit code <span class="req">*</span></span>
                                    <input name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code">
                                </label>
                                <div class="ui-modal-actions">
                                    <button type="submit" data-loading="Checking…"><span data-label>Turn on</span></button>
                                </div>
                            </form>
                        @else
                            <form method="post" action="{{ route('profile.update') }}">
                                @csrf
                                <input type="hidden" name="section" value="two-factor">
                                <input type="hidden" name="intent" value="start">
                                <button type="submit">Turn on</button>
                            </form>
                        @endif
                    </section>
                @else
                    <section class="card orders">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Module</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activity as $row)
                                    <tr>
                                        <td>{{ $row->timestamp?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</td>
                                        <td><span class="pill">{{ $modules[$row->module] ?? \Illuminate\Support\Str::headline((string) $row->module) }}</span></td>
                                        <td>{{ $row->description }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="empty" colspan="3">No activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @endif
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-file]').forEach((box) => {
            const input = box.querySelector('[data-file-input]');
            const name = box.querySelector('[data-file-name]');
            const hint = name.textContent;
            box.querySelector('[data-file-open]').addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                name.textContent = file ? file.name : hint;
            });
        });
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
