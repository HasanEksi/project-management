<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SmsSender
{
    private Client $client;

    private string $message;

    private array $recipients;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.organikhaberlesme.com',
            'headers' => [
                'X-Organik-Auth' => env('ORGANIK_AUTH'),
            ],
        ]);
    }

    public function getTitles(): array
    {
        try {
            return $this->getTitleListInternal();
        } catch (\Exception $e) {
            return ['result' => false];
        }
    }

    public function sendSingle(array $message): array
    {
        $this->message = $message['messageBody'];
        $this->recipients = $message['recipients'];

        return $this->send();
    }

    public function sendMultiple(array $message): array
    {
        $this->message = $message['messageBody'];
        $this->recipients = $message['recipients'];

        return $this->send();
    }

    private function send(): array
    {
        try {
            return $this->sendInternal();
        } catch (\Exception $e) {
            return [
                'result' => false,
                'error' => [
                    'code' => 500,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function sendInternal(): array
    {
        $config = $this->getSmsConfig();

        if (empty($this->recipients)) {
            throw new \Exception('Alıcılar boş olamaz.');
        }

        if (empty($this->message)) {
            throw new \Exception('Mesaj içeriği boş olamaz.');
        }

        $this->recipients = array_map(fn ($x) => str_replace(' ', '', $x), $this->recipients);

        $messageBody = [
            'header' => 1638,
            'message' => $this->message,
            'otp' => false,
            'recipients' => $this->recipients,
            'type' => 'sms',
            'validity' => 48,
        ];

        try {
            $response = $this->client->post('/sms/send', [
                'json' => $messageBody,
            ]);

            $buffer = $response->getBody()->getContents();

            return json_decode($buffer, true);
        } catch (RequestException $e) {
            return [
                'result' => false,
                'error' => [
                    'code' => 500,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function getTitleListInternal(): array
    {
        $response = $this->client->get('/sms/headers/get');
        $buffer = $response->getBody()->getContents();

        return json_decode($buffer, true);
    }

    private function getSmsConfig(): array
    {
        return [
            'commercial' => 'BIREYSEL',
            'date' => 'Hemen',
            'defaultTitleId' => 1638,
            'otp' => false,
            'type' => 'sms',
            'validity' => 48,
        ];
    }
}
