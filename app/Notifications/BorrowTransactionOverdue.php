<?php

namespace App\Notifications;

use App\Models\BorrowTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a borrow transaction becomes overdue
 * 
 * This notification is queued for performance and sent via email
 * to alert students that their borrowed academic paper is overdue
 * and needs to be returned immediately.
 */
class BorrowTransactionOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public BorrowTransaction $transaction
    ) {
        // Queue configuration
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $transaction = $this->transaction;
        $paper = $transaction->inventory->academicPaper;
        $overdueDuration = $transaction->overdue_duration;

        $borrowDate = $transaction->time_in->format('F j, Y \a\t g:i A');
        $dueDate = $transaction->expires_at->format('F j, Y \a\t g:i A');

        return (new MailMessage)
            ->error()
            ->subject('⚠️ Overdue: Library Material Requires Immediate Return')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('This is an urgent notice that your borrowed library material is now **overdue**.')
            ->line('**Material Details:**')
            ->line('📚 **Title:** ' . $paper->title)
            ->line('🏷️ **Type:** ' . $paper->paper_type)
            ->line('📖 **Copy Number:** ' . $transaction->inventory->copy_number)
            ->line('🆔 **Catalog Code:** ' . $paper->catalog_code)
            ->line('')
            ->line('**Transaction Details:**')
            ->line('📅 **Borrowed On:** ' . $borrowDate)
            ->line('⏰ **Was Due:** ' . $dueDate)
            ->line('⚠️ **Overdue By:** ' . $overdueDuration)
            ->line('')
            ->line('**Important Notice:**')
            ->line('• Please return this material to the library **immediately**')
            ->line('• Late returns may affect your library privileges')
            ->line('• Continued delays may result in additional penalties')
            ->line('• Your credit score may be impacted by overdue materials')
            ->line('')
            ->line('**Return Instructions:**')
            ->line('1. Visit the PLV Library during operating hours')
            ->line('2. Present the material to the librarian at the counter')
            ->line('3. Your QR code will be scanned to process the return')
            ->line('')
            ->action('View Transaction Details', url('/student/transactions'))
            ->line('If you have already returned this material, please disregard this notice or contact the library.')
            ->line('')
            ->line('Thank you for your cooperation.')
            ->salutation('PLV eLibrary System');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'academic_paper_id' => $this->transaction->academic_paper_id,
            'inventory_id' => $this->transaction->inventory_id,
            'paper_title' => $this->transaction->inventory->academicPaper->title,
            'due_date' => $this->transaction->expires_at->toDateTimeString(),
            'overdue_duration' => $this->transaction->overdue_duration,
            'type' => 'overdue_transaction',
        ];
    }
}
