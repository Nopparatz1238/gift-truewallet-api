<?php

namespace Nopparat\VoucherAPI;

use InvalidArgumentException;
use RuntimeException;
use JsonException;

class Voucher
{
    private string $phoneNumber;
    private string $voucherHash;

    private const API_URL = 'https://gift.truemoney.com/campaign/vouchers/';

    public function __construct(string $phoneNumber, string $voucherHash)
    {
        if (!$this->isValidPhone($phoneNumber)) {
            throw new InvalidArgumentException('Invalid phone number format');
        }

        if (strlen($voucherHash) < 20) {
            throw new InvalidArgumentException('Invalid voucher hash');
        }

        $this->phoneNumber = $phoneNumber;
        $this->voucherHash = $voucherHash;
    }

    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^0[0-9]{9}$/', $phone) === 1;
    }

    private function sendRequest(): object
    {
        $url = self::API_URL . $this->voucherHash . '/redeem';

        $payload = json_encode([
            'mobile' => $this->phoneNumber,
            'voucher_hash' => $this->voucherHash,
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Origin: https://gift.truemoney.com',
                'Referer: https://gift.truemoney.com/campaign/?v=' . $this->voucherHash,
                'User-Agent: TrueWalletAPI/1.1 (+PHP cURL)'
            ],
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $curlErr = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('cURL error: ' . $curlErr);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            $snippet = substr($response, 0, 512);
            throw new RuntimeException("Unexpected HTTP status: $httpCode; response snippet: " . $snippet);
        }

        try {
            $decoded = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON response: ' . $e->getMessage());
        }

        return $decoded;
    }

    public function redeem(): array
    {
        try {
            $result = $this->sendRequest();

            $code = $result->status?->code ?? 'UNKNOWN';
            $data = $result->data ?? null;

            return match ($code) {
                'SUCCESS' => [
                    'status' => 'success',
                    'code' => $code,
                    'message' => 'Transaction successful',
                    'data' => [
                        'amount' => $data?->voucher?->redeemed_amount_baht ?? null,
                        'owner'  => $data?->owner_profile?->full_name ?? null,
                    ],
                ],
                'VOUCHER_OUT_OF_STOCK' => $this->errorResponse('Voucher out of stock', $code),
                'VOUCHER_NOT_FOUND'    => $this->errorResponse('Voucher not found', $code),
                'VOUCHER_EXPIRED'      => $this->errorResponse('Voucher expired', $code),
                default => $this->errorResponse('Unknown response or invalid link', $code),
            };
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 'EXCEPTION');
        }
    }

    private function errorResponse(string $message, string $code): array
    {
        return [
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ];
    }
}

?>