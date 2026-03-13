<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
    ) {}

    // ── Applicant Endpoints ───────────────────────────────────

    /**
     * Create a new support ticket (applicant).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'reason' => 'required|string|in:general,appeal,tech,payment,status,docs,other',
            'message' => 'required|string|max:5000',
            'application_reference' => 'nullable|string',
        ]);

        $user = $request->user();

        // If an application reference is provided, try to link it
        $application = null;
        if (!empty($validated['application_reference'])) {
            $application = Application::where('reference_number', $validated['application_reference'])
                ->where('user_id', $user->id)
                ->first();
        }

        // Set priority based on reason
        $priority = match ($validated['reason']) {
            'appeal' => 'high',
            'payment' => 'high',
            'tech' => 'medium',
            default => 'medium',
        };

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'application_id' => $application?->id,
            'reference_number' => SupportTicket::generateReference(),
            'subject' => $validated['subject'],
            'reason' => $validated['reason'],
            'status' => 'open',
            'priority' => $priority,
        ]);

        // Create the initial message
        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_officer_reply' => false,
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'ticket' => $ticket->load('messages'),
        ], 201);
    }

    /**
     * List applicant's own support tickets.
     */
    public function myTickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages', 'application:id,reference_number,status'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return response()->json($tickets);
    }

    /**
     * View a single ticket (applicant — must own it).
     */
    public function showMine(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $ticket->load(['messages.user:id,first_name,last_name,role', 'application:id,reference_number,status']);

        // Mark officer messages as read
        $ticket->messages()->where('is_officer_reply', true)->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['ticket' => $ticket]);
    }

    /**
     * Applicant replies to their own ticket.
     */
    public function replyMine(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_officer_reply' => false,
        ]);

        // Reopen the ticket if it was closed/resolved
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        $ticket->touch();

        return response()->json([
            'message' => 'Reply sent successfully.',
            'reply' => $message->load('user:id,first_name,last_name,role'),
        ]);
    }

    // ── Officer Endpoints ─────────────────────────────────────

    /**
     * List all support tickets for officers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['user:id,first_name,last_name,email', 'application:id,reference_number,status', 'assignedOfficer:id,first_name,last_name'])
            ->withCount(['messages' => function ($q) {
                $q->where('is_officer_reply', false)->where('is_read', false);
            }]);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('reason') && $request->reason) {
            $query->where('reason', $request->reason);
        }

        $tickets = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($tickets);
    }

    /**
     * View a single ticket (officer).
     */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket->load([
            'user:id,first_name,last_name,email,phone',
            'application:id,reference_number,status,visa_type_id',
            'application.visaType:id,name',
            'messages.user:id,first_name,last_name,role',
            'assignedOfficer:id,first_name,last_name',
        ]);

        // Mark applicant messages as read
        $ticket->messages()->where('is_officer_reply', false)->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['ticket' => $ticket]);
    }

    /**
     * Officer replies to a ticket.
     */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_officer_reply' => true,
        ]);

        // Auto-assign officer if not already assigned
        if (!$ticket->assigned_officer_id) {
            $ticket->update(['assigned_officer_id' => $request->user()->id]);
        }

        // Move to in_progress if still open
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        $ticket->touch();

        return response()->json([
            'message' => 'Reply sent successfully.',
            'reply' => $message->load('user:id,first_name,last_name,role'),
        ]);
    }

    /**
     * Update ticket status (officer).
     */
    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'resolved_at' => in_array($validated['status'], ['resolved', 'closed']) ? now() : null,
        ]);

        return response()->json([
            'message' => 'Ticket status updated.',
            'ticket' => $ticket,
        ]);
    }

    /**
     * Officer overrides a denied application to approved (appeal action).
     */
    public function overrideToApproved(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        if (!$ticket->application_id) {
            return response()->json(['message' => 'This ticket is not linked to an application.'], 422);
        }

        $application = Application::find($ticket->application_id);
        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        if ($application->status !== 'denied') {
            return response()->json(['message' => 'Only denied applications can be overridden.'], 422);
        }

        try {
            // Step 1: Move denied → under_review
            $this->applicationService->changeStatus($application, 'under_review', 'Appeal accepted: ' . $validated['notes']);
            // Step 2: Move under_review → approved
            $this->applicationService->changeStatus($application->fresh(), 'approved', 'Approved via support appeal: ' . $validated['notes']);

            // Add officer message to the ticket
            SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'message' => "Application {$application->reference_number} has been approved via appeal. Notes: {$validated['notes']}",
                'is_officer_reply' => true,
            ]);

            $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

            return response()->json([
                'message' => 'Application has been approved via appeal.',
                'application' => $application->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get unread ticket count for officer badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = SupportTicket::whereHas('messages', function ($q) {
            $q->where('is_officer_reply', false)->where('is_read', false);
        })->count();

        return response()->json(['unread_count' => $count]);
    }
}
