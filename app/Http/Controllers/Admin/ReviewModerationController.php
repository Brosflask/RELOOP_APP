<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewModerationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => Review::query()
                ->with(['reviewer:id,name', 'reviewee:id,name', 'order:id,code'])
                ->when($request->boolean('low'), fn ($q) => $q->where('rating', '<=', 2))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Review $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'is_hidden' => $r->is_hidden,
                    'reviewer' => $r->reviewer->name,
                    'reviewee' => $r->reviewee->name,
                    'order' => $r->order->code,
                    'created_at' => $r->created_at->toIso8601String(),
                ]),
            'filters' => ['low' => $request->boolean('low')],
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['is_hidden' => ['required', 'boolean']]);
        $review->forceFill(['is_hidden' => $data['is_hidden']])->save();

        return back()->with('success', $data['is_hidden'] ? 'Review hidden.' : 'Review shown.');
    }
}
