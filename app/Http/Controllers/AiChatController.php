<?php

namespace App\Http\Controllers;

use App\Ai\Agents\FinancialChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiChatController extends Controller
{
    /**
     * Send a chat message.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversationId' => 'nullable|string',
        ]);

        try {
            $result = FinancialChat::chat(
                message: $validated['message'],
                conversationId: $validated['conversationId'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'conversationId' => $result['conversationId'],
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
     * Clear a conversation.
     */
    public function clear(Request $request)
    {
        $conversationId = $request->input('conversationId');

        if ($conversationId) {
            DB::table('agent_conversations')
                ->where('conversation_id', $conversationId)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get conversation history.
     */
    public function history(Request $request)
    {
        $conversationId = $request->input('conversationId');

        if (! $conversationId) {
            return response()->json(['messages' => []]);
        }

        $messages = DB::table('agent_conversations')
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        return response()->json(['messages' => $messages]);
    }
}
