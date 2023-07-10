<?php

namespace App\Events;

use App\Models\ReceiveOrder;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VerifiedROEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public User $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public ReceiveOrder $receiveOrder)
    {
        $this->user = auth()->user();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
