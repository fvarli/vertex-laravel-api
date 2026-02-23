<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly int $endpointId,
        private readonly string $event,
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);

        if (! $endpoint || ! $endpoint->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'payload' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            if ($response->successful()) {
                $endpoint->update([
                    'failure_count' => 0,
                    'last_triggered_at' => now(),
                ]);

                return;
            }

            $this->handleFailure($endpoint, "HTTP {$response->status()}");
        } catch (\Throwable $e) {
            $this->handleFailure($endpoint, $e->getMessage());
        }
    }

    private function handleFailure(WebhookEndpoint $endpoint, string $reason): void
    {
        $failures = $endpoint->failure_count + 1;

        $update = ['failure_count' => $failures, 'last_triggered_at' => now()];

        // Auto-disable after 10 consecutive failures
        if ($failures >= 10) {
            $update['is_active'] = false;
            Log::warning("Webhook endpoint disabled after {$failures} failures", [
                'endpoint_id' => $endpoint->id,
                'url' => $endpoint->url,
            ]);
        }

        $endpoint->update($update);

        Log::warning('Webhook delivery failed', [
            'endpoint_id' => $endpoint->id,
            'event' => $this->event,
            'reason' => $reason,
            'attempt' => $this->attempts(),
        ]);

        if ($this->attempts() < $this->tries) {
            throw new \RuntimeException("Webhook delivery failed: {$reason}");
        }
    }
}
