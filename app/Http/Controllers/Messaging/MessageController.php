<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, MessagingService $messaging): RedirectResponse
    {
        $messaging->sendMessage($conversation, $request->user(), $request->string('body'));

        return back();
    }
}
