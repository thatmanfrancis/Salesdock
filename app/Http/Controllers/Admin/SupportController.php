<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'OPEN');
        if (! in_array($status, ['', 'OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'], true)) {
            $status = 'OPEN';
        }

        $query = SupportTicket::query()
            ->with(['user', 'tenant'])
            ->latest('createdAt');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = 20;
        $total   = (clone $query)->count();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min($pages, max(1, (int) $request->query('page', 1)));

        $counts = SupportTicket::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('platform.support', [
            'tickets'  => $query->forPage($page, $perPage)->get(),
            'status'   => $status,
            'page'     => $page,
            'pages'    => $pages,
            'filtered' => $status !== '',
            'counts'   => $counts,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'tenant', 'replies.user']);

        return view('platform.support-ticket', [
            'ticket'  => $ticket,
            'replies' => $ticket->replies,
        ]);
    }

    public function claim(SupportTicket $ticket)
    {
        abort_unless(in_array($ticket->status, ['OPEN'], true), 422);
        $ticket->forceFill([
            'status'    => 'IN_PROGRESS',
            'claimedBy' => Auth::id(),
        ])->save();

        return back()->with('status', 'Ticket claimed.');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->isOpen(), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'status'  => ['nullable', 'in:IN_PROGRESS,RESOLVED,CLOSED'],
        ]);

        SupportReply::query()->create([
            'ticketId' => $ticket->id,
            'userId'   => Auth::id(),
            'isAdmin'  => true,
            'message'  => trim($data['message']),
        ]);

        if (! empty($data['status'])) {
            $ticket->forceFill([
                'status'     => $data['status'],
                'resolvedAt' => $data['status'] === 'RESOLVED' ? now() : $ticket->resolvedAt,
            ])->save();
        } else {
            $ticket->touch();
        }

        // In-app notification (shows in the bell for all non-cashier users on this tenant)
        Notification::query()->create([
            'tenantId'  => $ticket->tenantId,
            'type'      => 'SYSTEM',
            'title'     => 'Support reply',
            'message'   => 'New reply on your ticket: '.$ticket->subject,
            'entityId'  => $ticket->id,
            'isRead'    => false,
            'createdAt' => now(),
        ]);

        // Email notification
        $user = User::query()->find($ticket->userId);
        if ($user?->email) {
            $replyText = trim($data['message']);
            AuthMail::send($user->email, $user->name, 'Support reply: '.$ticket->subject, [
                'preheader'  => 'You have a new reply on your support ticket.',
                'heading'    => 'New reply on your ticket',
                'kicker'     => $ticket->subject,
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($user->name).'</strong>, our support team has replied to your ticket.',
                    e($replyText),
                ],
                'url'        => route('support.show', $ticket),
                'label'      => 'View ticket',
                'note'       => 'You can reply directly from the SalesDock dashboard.',
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function resolve(SupportTicket $ticket)
    {
        $ticket->forceFill([
            'status'     => 'RESOLVED',
            'resolvedAt' => now(),
        ])->save();

        return back()->with('status', 'Ticket resolved.');
    }
}
