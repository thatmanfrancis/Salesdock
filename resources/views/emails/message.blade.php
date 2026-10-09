<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        a { color: #15803d; }
        @media only screen and (max-width: 620px) {
            .sd-shell { width: 100% !important; }
            .sd-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sd-hero h1 { font-size: 24px !important; line-height: 32px !important; }
            .sd-button a { display: block !important; text-align: center !important; }
            .sd-row td { display: block !important; width: 100% !important; text-align: left !important; }
            .sd-row td.sd-value { padding-top: 0 !important; }
        }
    </style>
</head>
@php
    $font = "'Space Grotesk','Segoe UI',Helvetica,Arial,sans-serif";
    $signoff = $signoff ?? 'The SalesDock Team';
@endphp
<body style="margin:0;padding:0;background:#eef2ef;font-family:{{ $font }};">
    <div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2ef;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="sd-shell" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">
                    {{-- Logo --}}
                    <tr>
                        <td style="padding:0 0 20px;">
                            <img src="cid:salesdock-logo" alt="SalesDock" width="150" style="display:block;width:150px;max-width:150px;height:auto;border:0;outline:none;text-decoration:none;">
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff;border:1px solid #dfe5e1;border-radius:6px;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                {{-- Hero --}}
                                <tr>
                                    <td class="sd-hero sd-pad" bgcolor="#15803d" style="background:#15803d;padding:36px 40px 32px;border-radius:6px 6px 0 0;">
                                        @if (! empty($kicker))
                                            <p style="margin:0 0 8px;font-family:{{ $font }};font-size:15px;line-height:22px;font-weight:500;color:#bbf7d0;">{{ $kicker }}</p>
                                        @endif
                                        <h1 style="margin:0;font-family:{{ $font }};font-size:28px;line-height:36px;font-weight:700;letter-spacing:-0.01em;color:#ffffff;">{{ $heading }}</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td bgcolor="#22c55e" style="background:#22c55e;height:4px;line-height:4px;font-size:0;">&nbsp;</td>
                                </tr>

                                {{-- Body --}}
                                <tr>
                                    <td class="sd-pad" style="padding:32px 40px 8px;">
                                        @foreach ($paragraphs as $paragraph)
                                            <p style="margin:0 0 18px;font-family:{{ $font }};font-size:15px;line-height:25px;color:#374151;">{!! $paragraph !!}</p>
                                        @endforeach
                                    </td>
                                </tr>

                                @if (! empty($url))
                                    <tr>
                                        <td class="sd-pad" style="padding:6px 40px 26px;">
                                            <table role="presentation" class="sd-button" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td bgcolor="#16a34a" style="background:#16a34a;border-radius:4px;">
                                                        <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 28px;font-family:{{ $font }};font-size:15px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:4px;border:1px solid #15803d;">{{ $label ?: 'Continue' }}</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($summary))
                                    <tr>
                                        <td class="sd-pad" style="padding:6px 40px 26px;">
                                            <p style="margin:0 0 10px;font-family:{{ $font }};font-size:12px;line-height:16px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ $summaryTitle ?: 'Details' }}</p>
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:2px solid #111827;">
                                                @foreach ($summary as $label => $value)
                                                    <tr class="sd-row">
                                                        <td width="45%" valign="top" style="padding:12px 12px 12px 0;border-bottom:1px solid #e5e7eb;font-family:{{ $font }};font-size:14px;line-height:20px;color:#6b7280;">{{ $label }}</td>
                                                        <td class="sd-value" width="55%" valign="top" align="right" style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-family:{{ $font }};font-size:14px;line-height:20px;font-weight:600;color:#111827;text-align:right;">{{ $value }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($steps))
                                    <tr>
                                        <td class="sd-pad" style="padding:6px 40px 26px;">
                                            <p style="margin:0 0 14px;font-family:{{ $font }};font-size:12px;line-height:16px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ $stepsTitle ?: 'What happens next' }}</p>
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                @foreach ($steps as $step)
                                                    <tr>
                                                        <td width="40" valign="top" style="padding:0 0 14px;">
                                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                                <tr>
                                                                    <td width="26" height="26" align="center" valign="middle" bgcolor="#dcfce7" style="width:26px;height:26px;background:#dcfce7;border-radius:4px;font-family:{{ $font }};font-size:13px;line-height:26px;font-weight:700;color:#15803d;">{{ $loop->iteration }}</td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td valign="top" style="padding:3px 0 14px;font-family:{{ $font }};font-size:14px;line-height:21px;color:#374151;">{{ $step }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($url))
                                    <tr>
                                        <td class="sd-pad" style="padding:0 40px 24px;">
                                            <p style="margin:0;font-family:{{ $font }};font-size:13px;line-height:20px;color:#6b7280;">Button not working? Copy and paste this link into your browser:</p>
                                            <p style="margin:4px 0 0;font-family:{{ $font }};font-size:13px;line-height:20px;word-break:break-all;"><a href="{{ $url }}" target="_blank" style="color:#15803d;text-decoration:underline;">{{ $url }}</a></p>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($note))
                                    <tr>
                                        <td class="sd-pad" style="padding:0 40px 24px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td bgcolor="#f6f8f7" style="background:#f6f8f7;border-left:3px solid #22c55e;padding:14px 16px;font-family:{{ $font }};font-size:13px;line-height:20px;color:#4b5563;">{{ $note }}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Sign-off --}}
                                <tr>
                                    <td class="sd-pad" style="padding:8px 40px 36px;">
                                        <p style="margin:0;font-family:{{ $font }};font-size:15px;line-height:24px;color:#374151;">Cheers,<br><strong style="color:#111827;">{{ $signoff }}</strong></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="sd-pad" style="padding:28px 8px 0;">
                            <p style="margin:0 0 10px;font-family:{{ $font }};font-size:13px;line-height:20px;font-weight:700;color:#111827;">SalesDock — POS, inventory and online store for Nigerian retailers.</p>
                            <p style="margin:0 0 14px;font-family:{{ $font }};font-size:12px;line-height:19px;color:#6b7280;">
                                <a href="{{ url('/') }}" target="_blank" style="color:#15803d;text-decoration:none;font-weight:600;">Website</a>
                                &nbsp;&nbsp;·&nbsp;&nbsp;
                                <a href="{{ url('/login') }}" target="_blank" style="color:#15803d;text-decoration:none;font-weight:600;">Sign in</a>
                                &nbsp;&nbsp;·&nbsp;&nbsp;
                                <a href="{{ url('/help') }}" target="_blank" style="color:#15803d;text-decoration:none;font-weight:600;">Help &amp; support</a>
                                &nbsp;&nbsp;·&nbsp;&nbsp;
                                <a href="{{ url('/privacy') }}" target="_blank" style="color:#15803d;text-decoration:none;font-weight:600;">Privacy</a>
                                &nbsp;&nbsp;·&nbsp;&nbsp;
                                <a href="{{ url('/terms') }}" target="_blank" style="color:#15803d;text-decoration:none;font-weight:600;">Terms</a>
                            </p>
                            <p style="margin:0 0 6px;font-family:{{ $font }};font-size:12px;line-height:19px;color:#9ca3af;">This email was sent to <span style="color:#6b7280;">{{ $email }}</span>. SalesDock will never ask for your password or PIN by email, phone or chat.</p>
                            <p style="margin:0;font-family:{{ $font }};font-size:12px;line-height:19px;color:#9ca3af;">© {{ now()->year }} SalesDock. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
