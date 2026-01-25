<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'https://app.woodfire.food');
        $dashboardUrl = $frontendUrl . '/dashboard';
        $companyName = config('app.name', 'Woodfire.food');

        return (new MailMessage)
            ->subject('Your Account Has Been Approved - Welcome to ' . $companyName)
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('Congratulations! Your account has been successfully approved.')
            ->line('You now have full access to the employee dashboard and can begin using all available features.')
            ->line('')
            ->line('**Login Details:**')
            ->line('Email: ' . $notifiable->email)
            ->action('Access Your Dashboard', $dashboardUrl)
            ->line('')
            ->line('**Next Steps:**')
            ->line('1. Log in to your employee dashboard using your email and password')
            ->line('2. Complete your profile information if not already done')
            ->line('3. Review onboarding materials and training modules')
            ->line('4. Check your work schedule')
            ->line('')
            ->line('If you have any questions or need assistance, please contact your manager or HR team.')
            ->salutation('Best regards,')
            ->from(config('mail.from.address'), config('mail.from.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
