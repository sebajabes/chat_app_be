<?php

namespace App\Http\Controllers;

use App\Events\NewMessageSentEvent;
use App\Http\Requests\GetMessageRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;


class ChatMessageController extends Controller
{

    /**
     * Display a listing of messages.
     *
     * @param GetMessageRequest $request
     * @return JsonResponse
     */
    public function index(GetMessageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $chatId = $data['chat_id'];
        $currentPage = $data['page'];
        $pageSize = $data['page_size'] ?? 10;

        $messages = ChatMessage::where('chat_id', $chatId)
            ->with('user')
            ->latest('created_at')
            ->simplePaginate(
                $pageSize,
                ['*'],
                'page',
                $currentPage
            );

        return $this->success($messages->getCollection());
    }

    public function store(StoreMessageRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->user()->id;

        $chatMessage = ChatMessage::create($data);
        $chatMessage->load('user');

        if (!$chatMessage->user) {
            return $this->error('User data failed to load');
        }

        $this->sendNotificationToOther($chatMessage);

        // TODO Send broadcast event to pusher and send notification to onesignal services
        return $this->success($chatMessage, 'Message has been sent successfully');
    }


    /**
     * Send notification to other users in the chat
     *
     * @param ChatMessage $chatMessage
     */
    private function sendNotificationToOther(ChatMessage $chatMessage): void
    {
        $chatId = $chatMessage->chat_id;

        broadcast(new NewMessageSentEvent($chatMessage))->toOthers();

        $user = auth()->user();
        $userId = $user->id;

        $chat = Chat::where('id', $chatMessage->chat_id)
            ->with(['participants' => function ($query) use ($userId) {
                $query->where('user_id', '!=', $userId);
            }])
            ->first();

        if (count($chat->participants) > 0) {
            $otherUserId = $chat->participants[0]->user_id;
            $otherUser = User::where('id', $otherUserId)->first();

            $otherUser->sendNewNessageNotification([
                'messageData' => [
                    'senderName' => $user->username,
                    'message' => $chatMessage->message,
                    'chatId' => $chatMessage->chat_id,
                ]
            ]);
        }
    }
}
