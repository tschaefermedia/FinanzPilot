<?php

namespace App\Http\Controllers;

use App\Services\AI\AiChatService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(
        private AiChatService $chatService,
    ) {}

    /**
     * Send a chat message.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->chatService->sendMessage(
                sessionId: $request->session()->getId(),
                userMessage: $validated['message'],
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'provider' => $result['provider'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Clear chat history.
     */
    public function clear(Request $request)
    {
        $this->chatService->clearHistory($request->session()->getId());

        return response()->json(['success' => true]);
    }

    /**
     * Get current chat history.
     */
    public function history(Request $request)
    {
        $history = $this->chatService->getHistory($request->session()->getId());

        return response()->json([
            'messages' => $history,
        ]);
    }
}
