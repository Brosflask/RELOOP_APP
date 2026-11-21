<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(ComplaintStatus::class)]]);
        $status = $filters['status'] ?? null;

        return Inertia::render('Admin/Complaints/Index', [
            'complaints' => Complaint::query()
                ->with(['complainant:id,name', 'againstUser:id,name', 'order:id,code,status'])
                ->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', [ComplaintStatus::Open, ComplaintStatus::UnderReview]))
                ->oldest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Complaint $c) => [
                    'id' => $c->id,
                    'subject' => $c->subject,
                    'category' => $c->category->value,
                    'status' => $c->status->value,
                    'complainant' => $c->complainant->name,
                    'against' => $c->againstUser?->name,
                    'order' => $c->order ? ['id' => $c->order->id, 'code' => $c->order->code, 'status' => $c->order->status->value] : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ]),
            'filters' => ['status' => $status],
            'statuses' => array_map(fn ($s) => $s->value, ComplaintStatus::cases()),
        ]);
    }

    public function show(Complaint $complaint): Response
    {
        $complaint->load(['complainant:id,name', 'againstUser:id,name', 'order:id,code,status', 'resolver:id,name']);

        return Inertia::render('Admin/Complaints/Show', [
            'complaint' => [
                'id' => $complaint->id,
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'category' => $complaint->category->value,
                'status' => $complaint->status->value,
                'resolution' => $complaint->resolution,
                'complainant' => $complaint->complainant->only('id', 'name'),
                'against' => $complaint->againstUser?->only('id', 'name'),
                'order' => $complaint->order ? ['id' => $complaint->order->id, 'code' => $complaint->order->code, 'status' => $complaint->order->status->value] : null,
                'resolver' => $complaint->resolver?->name,
                'resolved_at' => $complaint->resolved_at?->toIso8601String(),
                'created_at' => $complaint->created_at->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'resolution' => [Rule::requiredIf(in_array($request->input('status'), ['resolved', 'rejected'], true)), 'nullable', 'string', 'max:5000'],
        ]);

        $final = in_array($data['status'], [ComplaintStatus::Resolved->value, ComplaintStatus::Rejected->value], true);

        $complaint->forceFill([
            'status' => $data['status'],
            'resolution' => $data['resolution'] ?? $complaint->resolution,
            'resolved_by' => $final ? $request->user()->id : null,
            'resolved_at' => $final ? now() : null,
        ])->save();

        return back()->with('success', 'Complaint updated.');
    }
}
