<?php

namespace App\Notifications;

use App\Domain\AccountNotifications\Contracts\BusinessScopedNotification;
use App\Domain\AccountNotifications\Contracts\NotificationStream;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceAccessChangedNotification extends Notification implements BusinessScopedNotification, NotificationStream, ShouldQueueAfterCommit
{
    use BuildsBrandedMail;
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $businessPublicId,
        public readonly string $businessName,
        public readonly string $change,
        public readonly ?string $role = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $message, $action, $url] = match ($this->change) {
            'accepted' => ["You joined {$this->businessName}", "Your secure invitation was accepted and your workspace access is active{$this->roleText()}.", 'Open workspace', route('business.dashboard', $this->businessPublicId)],
            'updated' => ["Your access to {$this->businessName} changed", "An authorised manager updated your workspace role, modules or assigned locations{$this->roleText()}.", 'Review workspace', route('business.dashboard', $this->businessPublicId)],
            'revoked' => ["Your access to {$this->businessName} was removed", 'Your workspace access was removed. Active sessions and relevant access tokens were ended, while historical work remains attributable.', 'Sign in', route('login')],
            'restored' => ["Your access to {$this->businessName} was restored", "Your workspace access is active again{$this->roleText()}. You can sign in using your existing account.", 'Open workspace', route('business.dashboard', $this->businessPublicId)],
            default => ["Workspace access changed for {$this->businessName}", 'An authorised manager changed your workspace access.', 'Review workspace', route('business.dashboard', $this->businessPublicId)],
        };

        return $this->brandedMail(
            (new MailMessage)
                ->subject($subject.' — '.config('brand.product_name'))
                ->greeting('Hello '.$notifiable->name.',')
                ->line($message)
                ->action($action, $url)
                ->line('If you did not expect this change, contact your business owner or ClipperDesk support.'),
            'account',
            'workspace_access_'.$this->change,
        );
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    public function notificationStream(): string
    {
        return 'account';
    }

    private function roleText(): string
    {
        return $this->role ? ' as '.$this->role : '';
    }
}
