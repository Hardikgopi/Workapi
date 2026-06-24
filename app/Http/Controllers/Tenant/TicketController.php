<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Ticket;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    /**
     * List all tickets (with optional filters).
     */
    public function index(Request $request)
    {
        $query = Ticket::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Create a new ticket and use AI to auto-categorize it.
     */
    public function store(Request $request, \App\Services\AiService $aiService, FcmService $fcmService)
    {
        $user = $request->attributes->get('tenant_user');

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'nullable|in:low,medium,high,critical',
            'status'      => 'nullable|in:open,in_progress,resolved,closed',
            'category'    => 'nullable|string|max:100',
            'assigned_to' => 'nullable|integer',
            'attachment'  => 'nullable|file|max:10240',
        ]);

        // 🔥 AI FEATURE: Auto-predict priority & category upon creation automatically
        $analysis = $aiService->analyzeTicket($data['title'], $data['description'] ?? '');
        
        $data['priority'] = $analysis['priority'];
        $data['category'] = $analysis['category'];

        $data['raised_by'] = $user->id;

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('tickets', 'r2');
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $ticket = Ticket::create($data);

        // 🔔 Send push notification to assigned user
        if (!empty($data['assigned_to'])) {
            $this->notifyAssignedUser($fcmService, $data['assigned_to'], $ticket, $user->name);
        }

        return response()->json(['message' => 'Ticket created', 'ticket' => $ticket], 201);
    }

    /**
     * Show a single ticket.
     */
    public function show(int $id)
    {
        $ticket = Ticket::findOrFail($id);
        return response()->json($ticket);
    }

    /**
     * Update a ticket.
     */
    public function update(Request $request, int $id, FcmService $fcmService)
    {
        $ticket = Ticket::findOrFail($id);
        $user = $request->attributes->get('tenant_user');
        $previousAssignee = $ticket->assigned_to;

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'nullable|in:low,medium,high,critical',
            'status'      => 'nullable|in:open,in_progress,resolved,closed',
            'category'    => 'nullable|string|max:100',
            'assigned_to' => 'nullable|integer',
            'attachment'  => 'nullable|file|max:10240',
            'remove_attachment' => 'nullable|boolean',
        ]);

        // Auto-set resolved_at when status becomes resolved
        if (isset($data['status']) && $data['status'] === 'resolved' && !$ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        if ($request->hasFile('attachment')) {
            if (!empty($ticket->attachment_path)) {
                Storage::disk('r2')->delete($ticket->attachment_path);
            }

            $path = $request->file('attachment')->store('tickets', 'r2');
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $request->file('attachment')->getClientOriginalName();
        } elseif (($data['remove_attachment'] ?? false) && !empty($ticket->attachment_path)) {
            Storage::disk('r2')->delete($ticket->attachment_path);
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
        }

        unset($data['remove_attachment']);

        $ticket->update($data);

        // 🔔 Send push notification if assigned_to changed to a new user
        if (
            isset($data['assigned_to']) &&
            $data['assigned_to'] &&
            $data['assigned_to'] != $previousAssignee
        ) {
            $assignerName = $user ? $user->name : 'Someone';
            $this->notifyAssignedUser($fcmService, $data['assigned_to'], $ticket, $assignerName);
        }

        return response()->json(['message' => 'Ticket updated', 'ticket' => $ticket]);
    }

    /**
     * Delete a ticket.
     */
    public function destroy(int $id)
    {
        Ticket::findOrFail($id)->delete();
        return response()->json(['message' => 'Ticket deleted']);
    }

    /**
     * Send a push notification to the assigned user.
     */
    private function notifyAssignedUser(FcmService $fcmService, int $userId, Ticket $ticket, string $assignerName): void
    {
        $assignedUser = DB::connection('tenant')
            ->table('users')
            ->where('id', $userId)
            ->first();

        if ($assignedUser && !empty($assignedUser->fcm_token)) {
            $fcmService->sendNotification(
                $assignedUser->fcm_token,
                'Ticket Assigned to You',
                "{$assignerName} assigned you ticket #{$ticket->id}: {$ticket->title}",
                [
                    'type'      => 'ticket_assigned',
                    'ticket_id' => (string) $ticket->id,
                ]
            );
        }
    }
}
