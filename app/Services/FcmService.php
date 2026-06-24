<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send a push notification to a specific device using Firebase Cloud Messaging.
     *
     * @param string $fcmToken  The target device's FCM registration token
     * @param string $title     Notification title
     * @param string $body      Notification body text
     * @param array  $data      Optional extra data payload
     * @return bool
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);

            Log::info('FCM notification sent successfully via Kreait SDK', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'title' => $title,
            ]);
            
            return true;

        } catch (\Exception $e) {
            Log::error('FCM notification exception: ' . $e->getMessage());
            return false;
        }
    }
}
