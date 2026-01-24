<?php

namespace App\Notifications;

use App\Helpers\SmsSender;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    private Ticket $ticket;
    private TicketActivity $activity;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->activity = $this->ticket->activities->last();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $channels = ['mail', 'database'];
        
        // SMS gönder sadece talep tamamlandığında talep sahibine ve telefon numarası varsa
        $isCompleted = $this->isTicketCompleted();
        if ($isCompleted && $notifiable->id === $this->ticket->owner_id && $notifiable->phone) {
            $channels[] = 'sms';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line(__('The status of ticket :ticket has been updated.', ['ticket' => $this->ticket->name]))
            ->line('- ' . __('Old status:') . ' ' . $this->activity->oldStatus->name)
            ->line('- ' . __('New status:') . ' ' . $this->activity->newStatus->name)
            ->line(__('See more details of this ticket by clicking on the button below:'))
            ->action(__('View details'), route('filament.resources.tickets.share', $this->ticket->code));
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('Ticket status updated'))
            ->icon('heroicon-o-ticket')
            ->body(
                fn() => __('Old status: :oldStatus - New status: :newStatus', [
                    'oldStatus' => $this->activity->oldStatus->name,
                    'newStatus' => $this->activity->newStatus->name,
                ])
            )
            ->actions([
                Action::make('view')
                    ->link()
                    ->icon('heroicon-s-eye')
                    ->url(fn() => route('filament.resources.tickets.share', $this->ticket->code)),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Check if ticket is completed based on status name
     *
     * @return bool
     */
    private function isTicketCompleted(): bool
    {
        $completedStatuses = ['Tamamlandı', 'Kapatıldı', 'Completed', 'Closed', 'Done'];
        return in_array($this->activity->newStatus->name, $completedStatuses);
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toSms($notifiable): array
    {
        $message = __('The status of ticket :ticket has been updated.', ['ticket' => $this->ticket->name]) . "\n";
        $message .= __('Old status:') . ' ' . $this->activity->oldStatus->name . "\n";
        $message .= __('New status:') . ' ' . $this->activity->newStatus->name . "\n";
        $message .= __('Project:') . ' ' . $this->ticket->project->name;

        return [
            'messageBody' => $message,
            'recipients' => [$notifiable->phone]
        ];
    }
}
