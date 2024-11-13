<?php

namespace App\Notifications\Api\V1;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;

class CustomVerifyEmail extends VerifyEmailNotification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Generate the custom verification URL
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->greeting('Hello!')
            ->line('You are receiving this email because you registered on our platform.')
            ->line('To complete your registration, please verify your email address by clicking the button below:')
            ->action('Verify Email', $verificationUrl)
            ->line('If you did not create an account, no further action is required.')
            ->line('If you need assistance, please contact our support team.')
            ->line($notifiable->getKey())
            ->line(sha1($notifiable->getEmailForVerification()));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function verificationUrl($notifiable)
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        if (!$id || !$hash) {
            throw new Exception('Invalid parameters for verification URL.');
        }

        $frontendUrl = $this->getFrontendUrl();
        $routeParams = [
            'id' => $id,
            'hash' => $hash,
        ];

        return $frontendUrl . '/email/verify?' . http_build_query($routeParams);
    }

    private function getFrontendUrl(): string
    {
        // Generate a custom url (frontend url or API-based endpoint)

        // return config('app.frontend_url');
        return 'http://127.0.0.1:8000/api/v1/email/verify';
    }
}
