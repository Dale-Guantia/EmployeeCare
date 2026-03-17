<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketSystemNotification extends Notification
{
    use Queueable;

    protected string $title;
    protected string $message;
    protected ?string $url;
    protected array $extra;

    public function __construct(string $title, string $message, ?string $url = null, array $extra = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->extra = $extra;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ], $this->extra);
    }
}
