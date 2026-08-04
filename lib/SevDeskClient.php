<?php

declare(strict_types=1);

final class SevDeskClient
{
    public function __construct(private readonly string $baseUrl, private readonly string $token) {}

    public function configured(): bool { return trim($this->baseUrl) !== '' && trim($this->token) !== ''; }

    /** @return array<string,mixed> */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->configured()) throw new RuntimeException('Für diesen Mandanten ist keine SevDesk-Verbindung hinterlegt.');
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('SevDesk-Verbindung konnte nicht geöffnet werden.');
        $headers = ['Authorization: ' . $this->token, 'Accept: application/json', 'Content-Type: application/json'];
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($payload !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        if ($body === false || $error !== '') throw new RuntimeException('SevDesk-Netzwerkfehler: ' . ($error ?: 'unbekannt'));
        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) throw new RuntimeException('SevDesk antwortet mit HTTP ' . $status . ': ' . mb_substr((string) $body, 0, 500));
        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    /** Erstellt einen Entwurf; Positionen werden als Prüfungen/Regiezeit dokumentiert. */
    public function createDraftInvoice(string $customerId, string $invoiceNumber, string $date, array $items, float $inspectionRate = 0, float $regieRate = 0): array
    {
        $positions = [];
        $positionNumber = 0;
        foreach ($items as $item) {
            $positions[] = ['id' => null, 'objectName' => 'InvoicePos', 'mapAll' => true, 'quantity' => 1, 'price' => $inspectionRate, 'name' => (string) ($item['description'] ?? 'Prüfung'), 'unity' => ['id' => 1, 'objectName' => 'Unity'], 'positionNumber' => $positionNumber++, 'text' => (string) ($item['details'] ?? ''), 'taxRate' => 0];
            if ((int) ($item['regie_minutes'] ?? 0) > 0) $positions[] = ['id' => null, 'objectName' => 'InvoicePos', 'mapAll' => true, 'quantity' => (int) $item['regie_minutes'], 'price' => $regieRate, 'name' => 'Regiezeit zur Prüfung', 'unity' => ['id' => 1, 'objectName' => 'Unity'], 'positionNumber' => $positionNumber++, 'taxRate' => 0];
        }
        return $this->request('POST', '/Invoice/Factory/saveInvoice', ['invoice' => ['id' => null, 'objectName' => 'Invoice', 'mapAll' => true, 'invoiceNumber' => $invoiceNumber, 'contact' => ['id' => (int) $customerId, 'objectName' => 'Contact'], 'invoiceDate' => date('d.m.Y', strtotime($date)), 'status' => 100, 'invoiceType' => 'RE', 'currency' => 'EUR', 'showNet' => 1, 'taxRate' => 0, 'mapAll' => true], 'invoicePosSave' => $positions, 'invoicePosDelete' => null, 'discountSave' => [], 'discountDelete' => null]);
    }
}
