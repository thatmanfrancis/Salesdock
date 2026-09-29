<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:'Space Grotesk','Segoe UI',Arial,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;">{{ $preheader }}</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:24px;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 40px 8px;text-align:center;">
                            <img src="cid:salesdock-logo" alt="SalesDock" width="200" style="display:block;margin:0 auto 20px;max-width:200px;height:auto;border:0;outline:none;text-decoration:none;">
                            <h1 style="margin:0 0 4px;font-size:24px;font-weight:800;color:#111827;letter-spacing:-0.02em;">{{ $heading }}</h1>
                            @if (!empty($kicker))
                                <p style="margin:0;font-size:14px;color:#6b7280;">{{ $kicker }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 32px;">
                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">{!! $paragraph !!}</p>
                            @endforeach

                            @if (!empty($summary))
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px;border:1px solid #e5e7eb;background:#f9fafb;margin:0 0 20px;">
                                    <tr>
                                        <td style="padding:16px 18px 4px;">
                                            <p style="margin:0 0 8px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;">{{ $summaryTitle ?: 'Details' }}</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 18px 16px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#374151;">
                                                @foreach ($summary as $label => $value)
                                                    <tr>
                                                        <td width="40%" style="padding:4px 0;color:#6b7280;">{{ $label }}</td>
                                                        <td width="60%" style="padding:4px 0;color:#111827;font-weight:500;">{{ $value }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (!empty($steps))
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px;border:1px solid #e5e7eb;margin:0 0 20px;">
                                    <tr>
                                        <td style="padding:16px 18px;">
                                            <p style="margin:0 0 8px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;">{{ $stepsTitle ?: 'What happens next' }}</p>
                                            <ol style="margin:0;padding:0 0 0 18px;font-size:13px;color:#4b5563;line-height:1.7;">
                                                @foreach ($steps as $step)
                                                    <li style="margin-bottom:4px;">{{ $step }}</li>
                                                @endforeach
                                            </ol>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (!empty($url))
                                <table cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                    <tr>
                                        <td align="center" bgcolor="#22c55e" style="border-radius:999px;">
                                            <a href="{{ $url }}" style="display:inline-block;padding:10px 22px;font-size:13px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">{{ $label ?: 'Continue' }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 12px;font-size:13px;color:#6b7280;line-height:1.6;word-break:break-all;">Or copy this link: <span style="color:#16a34a;">{{ $url }}</span></p>
                            @endif

                            @if (!empty($note))
                                <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">{{ $note }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 40px 28px;border-top:1px solid #e5e7eb;text-align:center;">
                            <p style="margin:0 0 4px;font-size:11px;color:#9ca3af;">© {{ now()->year }} SalesDock. All rights reserved.</p>
                            <p style="margin:0;font-size:11px;color:#9ca3af;">This email was sent to {{ $email }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
