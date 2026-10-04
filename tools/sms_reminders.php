<?php

declare(strict_types=1);

/**
 * Run every few minutes from a real cron (`* * * * * php tools/sms_reminders.php`).
 * Sends the 24h/2h "یادآور" reminders for booked appointments (C01).
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Domain\Messaging\QueueNotificationService;

$sent = (new QueueNotificationService())->sendUpcomingReminders();
echo "Sent {$sent} reminder(s)\n";
