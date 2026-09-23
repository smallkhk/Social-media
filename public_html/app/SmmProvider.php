<?php
declare(strict_types=1);

/**
 * Client for the standard SMM panel "API v2" used by MoreThanPanel
 * (https://morethanpanel.com/api), Crescitaly and most other SMM panels:
 * every call is a POST to one URL with `key` + `action`.
 *
 * Every method returns the decoded JSON, or ['error' => '...'] on failure.
 */
final class SmmProvider
{
    public function __construct(private string $apiUrl, private string $apiKey)
    {
    }

    public static function fromRow(array $provider): self
    {
        return new self($provider['api_url'], $provider['api_key']);
    }

    public static function byId(int $providerId): ?self
    {
        $p = row('SELECT * FROM providers WHERE id = ?', [$providerId]);
        return $p ? self::fromRow($p) : null;
    }

    public function request(string $action, array $params = []): array
    {
        if ($this->apiKey === '') {
            return ['error' => 'API key not set for this provider'];
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params + ['key' => $this->apiKey, 'action' => $action]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NoraPanel/3.0)',
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['error' => 'Connection failed: ' . $err];
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            return ['error' => 'Invalid response from provider (HTTP ' . $code . ')'];
        }
        if (isset($data['error']) && !is_string($data['error'])) {
            $data['error'] = json_encode($data['error']);
        }
        return $data;
    }

    public function services(): array
    {
        return $this->request('services');
    }

    public function addOrder(string $serviceId, string $link, int $quantity): array
    {
        return $this->request('add', ['service' => $serviceId, 'link' => $link, 'quantity' => $quantity]);
    }

    public function status(string $orderId): array
    {
        return $this->request('status', ['order' => $orderId]);
    }

    /** @param string[] $orderIds up to 100 */
    public function multiStatus(array $orderIds): array
    {
        return $this->request('status', ['orders' => implode(',', $orderIds)]);
    }

    public function refill(string $orderId): array
    {
        return $this->request('refill', ['order' => $orderId]);
    }

    public function balance(): array
    {
        return $this->request('balance');
    }
}
