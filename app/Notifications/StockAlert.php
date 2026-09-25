<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockAlert extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $product, public string $branch, public string $quantity, public int $branchId) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Stock abaixo do limite', 'message' => $this->product.' · '.$this->branch.' · saldo '.$this->quantity,
            'url' => '/admin/stock?alert=low&branch_id='.$this->branchId,
        ];
    }
}
