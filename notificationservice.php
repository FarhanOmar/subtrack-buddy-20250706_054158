<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use Exception;

class NotificationService
{
    public function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject);
            });
            Log::info("Email sent to {$to} with subject '{$subject}'.");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to send email to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a WhatsApp message via Twilio.
     *
     * @param string $to E.164 format phone number (e.g., +1234567890).
     * @param string $message Message content.
     * @return bool True on success, false on failure.
     */
    public function sendWhatsApp(string $to, string $message): bool
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.whatsapp_from');

            if (empty($sid) || empty($token) || empty($from)) {
                throw new Exception('Twilio configuration missing.');
            }

            $client = new Client($sid, $token);
            $client->messages->create(
                'whatsapp:' . $to,
                [
                    'from' => 'whatsapp:' . $from,
                    'body' => $message,
                ]
            );
            Log::info("WhatsApp message sent to {$to}.");
            return true;
        } catch (RestException $e) {
            Log::error("Twilio REST error sending WhatsApp message to {$to}: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp message to {$to}: " . $e->getMessage());
            return false;
        }
    }
}