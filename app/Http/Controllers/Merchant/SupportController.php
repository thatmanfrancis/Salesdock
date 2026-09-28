<?php

namespace App\Http\Controllers\Merchant;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends MerchantController
{
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $status   = (string) $request->query('status', '');
        if (! in_array($status, ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'], true)) {
            $status = '';
        }

        $query = SupportTicket::query()
            ->where('tenantId', $tenantId)
            ->with('user')
            ->latest('createdAt');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = 15;
        $total   = (clone $query)->count();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min($pages, max(1, (int) $request->query('page', 1)));

        return view('merchant.support', [
            'tickets'  => $query->forPage($page, $perPage)->get(),
            'status'   => $status,
            'page'     => $page,
            'pages'    => $pages,
            'filtered' => $status !== '',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:LOW,NORMAL,HIGH,URGENT'],
        ]);

        SupportTicket::query()->create([
            'tenantId' => $this->tenantId(),
            'userId'   => $this->user()->id,
            'subject'  => trim($data['subject']),
            'message'  => trim($data['message']),
            'priority' => $data['priority'] ?? 'NORMAL',
            'status'   => 'OPEN',
        ]);

        return back()->with('status', 'Ticket submitted. We\'ll be in touch.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->tenantId === $this->tenantId(), 404);
        $ticket->load(['replies.user']);

        return view('merchant.support-ticket', [
            'ticket'  => $ticket,
            'replies' => $ticket->replies,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->tenantId === $this->tenantId(), 404);
        abort_unless($ticket->isOpen(), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SupportReply::query()->create([
            'ticketId' => $ticket->id,
            'userId'   => $this->user()->id,
            'isAdmin'  => false,
            'message'  => trim($data['message']),
        ]);

        $ticket->touch();

        return back()->with('status', 'Reply sent.');
    }

    public function close(SupportTicket $ticket)
    {
        abort_unless($ticket->tenantId === $this->tenantId(), 404);
        $ticket->forceFill(['status' => 'CLOSED'])->save();

        return back()->with('status', 'Ticket closed.');
    }

    /**
     * Return a count of replies the merchant hasn't seen yet
     * (admin replies since the last time they visited the ticket page).
     * Used by the float button badge poll.
     */
    public function unread(Request $request)
    {
        // SUPER_ADMIN has no tenantId — return zero safely
        $user = $this->user();
        if (! $user->tenantId) {
            return response()->json(['count' => 0]);
        }

        $tenantId = $this->tenantId();

        $count = SupportTicket::query()
            ->where('tenantId', $tenantId)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->whereHas('replies', fn ($q) => $q->where('isAdmin', true))
            ->count();

        return response()->json(['count' => $count]);
    }

    public function stream(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->tenantId === $this->tenantId(), 404);

        // Only accept actual EventSource connections — redirect everything else
        if (! str_contains($request->header('Accept', ''), 'text/event-stream')) {
            return redirect()->route('support.show', $ticket);
        }

        $lastId = (string) $request->query('lastId', '');

        return new StreamedResponse(function () use ($ticket, $lastId) {
            // Remove PHP's execution time limit for this long-lived request
            set_time_limit(0);
            ignore_user_abort(true);

            if (ob_get_level()) {
                ob_end_flush();
            }

            $start   = time();
            $current = $lastId;

            // Send a keep-alive comment immediately so the browser knows we connected
            echo ": connected\n\n";
            flush();

            while (true) {
                // If the client disconnected, stop
                if (connection_aborted()) {
                    break;
                }

                $ticket->refresh();

                $query = SupportReply::query()
                    ->where('ticketId', $ticket->id)
                    ->where('isAdmin', true)
                    ->orderBy('createdAt');

                if ($current !== '') {
                    $query->where('id', '>', $current);
                }

                foreach ($query->get() as $reply) {
                    $payload = json_encode([
                        'id'        => $reply->id,
                        'message'   => $reply->message,
                        'createdAt' => $reply->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i'),
                    ]);
                    echo "id: {$reply->id}\n";
                    echo "data: {$payload}\n\n";
                    $current = $reply->id;
                    flush();
                }

                if (! $ticket->isOpen()) {
                    echo "event: closed\ndata: {}\n\n";
                    flush();
                    break;
                }

                // After 50 s tell the client to reconnect cleanly
                if (time() - $start >= 50) {
                    echo "event: reconnect\ndata: " . json_encode(['lastId' => $current]) . "\n\n";
                    flush();
                    break;
                }

                // Send a heartbeat comment every cycle so proxies don't close idle connections
                echo ": ping\n\n";
                flush();

                sleep(3);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
