<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketCategory;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $tickets = $request->user()
            ->supportTickets()
            ->with('assignee:id,name')
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->getKey(),
                'subject' => $ticket->subject,
                'category' => [
                    'value' => $ticket->category->value,
                    'label' => $ticket->category->label(),
                ],
                'status' => [
                    'value' => $ticket->status->value,
                    'label' => $ticket->status->label(),
                ],
                'assignee_name' => $ticket->assignee?->name,
                'last_message_at' => $ticket->last_message_at->toIso8601String(),
            ]);

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'categories' => collect(SupportTicketCategory::cases())
                ->map(fn (SupportTicketCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ]),
            'status' => $request->session()->get('status'),
        ]);
    }
}
