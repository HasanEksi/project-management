<?php

namespace App\Notifications;

use App\Http\Helpers\SmsSender;
use App\Models\Ticket;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private Ticket $ticket;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
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
        
        // SMS gönder sadece talep sorumlusuna ve telefon numarası varsa
        if ($this->ticket->responsible_id && $notifiable->id === $this->ticket->responsible_id && $notifiable->phone) {
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
            ->line(__('A new ticket has just been created.'))
            ->line('- ' . __('Ticket name:') . ' ' . $this->ticket->name)
            ->line('- ' . __('Project:') . ' ' . $this->ticket->project->name)
            ->line('- ' . __('Owner:') . ' ' . $this->ticket->owner->name)
            ->line('- ' . __('Responsible:') . ' ' . $this->ticket->responsible?->name ?? '-')
            ->line('- ' . __('Status:') . ' ' . $this->ticket->status->name)
            ->line('- ' . __('Type:') . ' ' . $this->ticket->type->name)
            ->line('- ' . __('Priority:') . ' ' . $this->ticket->priority->name)
            ->line(__('See more details of this ticket by clicking on the button below:'))
            ->action(__('View details'), route('filament.resources.tickets.share', $this->ticket->code));
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('New ticket created'))
            ->icon('heroicon-o-ticket')
            ->body(fn() => $this->ticket->name)
            ->actions([
                Action::make('view')
                    ->link()
                    ->icon('heroicon-s-eye')
                    ->url(fn() => route('filament.resources.tickets.share', $this->ticket->code)),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toSms($notifiable): array
    {
        $message = __('A new ticket has just been created.') . "\n";
        $message .= __('Ticket name:') . ' ' . $this->ticket->name . "\n";
        $message .= __('Project:') . ' ' . $this->ticket->project->name . "\n";
        $message .= __('Status:') . ' ' . $this->ticket->status->name . "\n";
        $message .= __('Priority:') . ' ' . $this->ticket->priority->name;

        return [
            'messageBody' => $message,
            'recipients' => [$notifiable->phone]
        ];
    }
}
