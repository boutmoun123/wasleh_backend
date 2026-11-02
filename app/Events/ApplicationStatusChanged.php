<?php
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class ApplicationStatusChanged {
    use Dispatchable, SerializesModels;
    public function __construct(public int $applicationId, public string $from, public string $to) {}
}
