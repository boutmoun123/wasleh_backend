<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class WaslaAlert extends Notification {
    public function __construct(private string $message, private string $kind, private ?int $referenceId=null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array {
        return ['message'=>$this->message,'type'=>$this->kind,'reference_id'=>$this->referenceId];
    }
}
