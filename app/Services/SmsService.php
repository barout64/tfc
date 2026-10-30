<?php

namespace App\Services;

use App\Models\Farmer;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    public function send(string $phone, string $message, string $category = 'alert', ?Farmer $farmer = null): SmsMessage
    {
        $sms = SmsMessage::create([
            'phone' => Farmer::normalisePhone($phone),
            'message' => $message,
            'direction' => 'out',
            'status' => 'queued',
            'category' => $category,
            'farmer_id' => $farmer?->id,
        ]);

        match (config('kilimo.sms.driver')) {
            'beem' => $this->sendViaBeem($sms),
            default => $sms->update(['status' => 'logged', 'sent_at' => now()]),
        };

        return $sms;
    }

    private function sendViaBeem(SmsMessage $sms): void
    {
        $config = config('kilimo.sms.beem');

        try {
            $response = Http::withBasicAuth($config['api_key'], $config['secret'])
                ->timeout(10)
                ->post($config['url'], [
                    'source_addr' => config('kilimo.sms.sender_id'),
                    'encoding' => 0,
                    'message' => $sms->message,
                    'recipients' => [['recipient_id' => $sms->id, 'dest_addr' => $sms->phone]],
                ]);

            $sms->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'provider_id' => $response->json('request_id'),
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('SMS sending failed', ['sms_id' => $sms->id, 'error' => $e->getMessage()]);
            $sms->update(['status' => 'failed']);
        }
    }
}
