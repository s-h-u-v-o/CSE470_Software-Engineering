<?php

namespace App\Http\Controllers;

use App\Exceptions\TicketWorkflowException;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaEngine;
use App\Services\TicketService;
use App\Support\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F16/F18 HTTP layer: validates request shape and delegates to services.
 * All workflow and authorization rules live in TicketService / SlaEngine.
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets, private SlaEngine $sla)
    {
    }

    public function index()
    {
        $tickets = Ticket::with(['asset', 'assignee'])->latest('id')->get();
        $sla = $tickets->mapWithKeys(fn (Ticket $t) => [$t->id => $this->sla->evaluate($t)]);
        $assets = Asset::where('lifecycle_state', '!=', 'retired')->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('tickets.index', compact('tickets', 'sla', 'assets', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'description' => 'required|string|max:2000',
            'severity' => ['nullable', Rule::in(TicketService::SEVERITIES)],
        ]);

        return $this->attempt(function () use ($request, $data) {
            $ticket = $this->tickets->report(Asset::findOrFail($data['asset_id']), $this->actor($request), $data['description'], $data['severity'] ?? null);

            return redirect()->route('tickets.show', $ticket)->with('success', "Ticket #{$ticket->id} reported.");
        });
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['asset', 'reporter', 'assignee', 'events.actor', 'slaPauses.pausedBy', 'slaPauses.resumedBy']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'sla' => $this->sla->evaluate($ticket),
            'blocks' => $this->tickets->blocksReadiness($ticket),
            'users' => User::orderBy('name')->get(),
            'engineers' => User::where('role', Role::ENGINEER)->orderBy('name')->get(),
            'pauseReasons' => SlaEngine::PAUSE_REASONS,
        ]);
    }

    public function triage(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['severity' => ['required', Rule::in(TicketService::SEVERITIES)], 'note' => 'nullable|string|max:2000']);

        return $this->attempt(fn () => $this->tickets->triage($ticket, $this->actor($request), $data['severity'], $data['note'] ?? null), 'Ticket triaged.');
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['engineer_id' => 'required|integer|exists:users,id']);

        return $this->attempt(fn () => $this->tickets->assign($ticket, $this->actor($request), User::findOrFail($data['engineer_id'])), 'Engineer assigned.');
    }

    public function transition(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'action' => 'required|in:await_parts,await_vendor,resume_work,repaired,verify_pass,verify_fail,close',
            'reason' => 'nullable|string|max:2000',
        ]);
        $reason = $data['reason'] ?? null;

        return $this->attempt(function () use ($request, $ticket, $data, $reason) {
            $actor = $this->actor($request);
            match ($data['action']) {
                'await_parts' => $this->tickets->awaitParts($ticket, $actor, (string) $reason),
                'await_vendor' => $this->tickets->awaitVendor($ticket, $actor, (string) $reason),
                'resume_work' => $this->tickets->resumeWork($ticket, $actor, $reason),
                'repaired' => $this->tickets->markRepaired($ticket, $actor, $reason),
                'verify_pass' => $this->tickets->verify($ticket, $actor, true, $reason),
                'verify_fail' => $this->tickets->verify($ticket, $actor, false, $reason),
                'close' => $this->tickets->close($ticket, $actor, $reason),
            };
        }, 'Ticket updated.');
    }

    public function reclassify(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['severity' => ['required', Rule::in(TicketService::SEVERITIES)], 'reason' => 'required|string|max:2000']);

        return $this->attempt(fn () => $this->tickets->reclassify($ticket, $this->actor($request), $data['severity'], $data['reason']), 'Severity reclassified.');
    }

    public function pause(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['reason_code' => ['required', Rule::in(array_keys(SlaEngine::PAUSE_REASONS))], 'note' => 'nullable|string|max:2000']);

        return $this->attempt(fn () => $this->sla->pause($ticket, $this->actor($request), $data['reason_code'], $data['note'] ?? null), 'SLA clock paused.');
    }

    public function resume(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        return $this->attempt(fn () => $this->sla->resume($ticket, $this->actor($request), $data['note'] ?? null), 'SLA clock resumed.');
    }

    /**
     * Logged-in user when authentication exists. Until login is built, local
     * development may pick an "acting as" user; this is refused elsewhere.
     */
    private function actor(Request $request): User
    {
        if ($request->user()) {
            return $request->user();
        }
        abort_unless(app()->environment('local', 'testing'), 403, 'Authentication required.');

        return User::findOrFail($request->validate(['acting_user_id' => 'required|integer|exists:users,id'])['acting_user_id']);
    }

    private function attempt(callable $action, ?string $success = null)
    {
        try {
            $result = $action();

            return $result instanceof \Symfony\Component\HttpFoundation\Response ? $result : back()->with('success', $success);
        } catch (TicketWorkflowException|AuthorizationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
