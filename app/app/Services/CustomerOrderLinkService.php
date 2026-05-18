<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CustomerOrderLinkService
{
    private const TOKEN_LENGTH = 20;
    private const TOKEN_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    /**
     * @return array{url: string, token: string, generated: bool, permanent: bool, rotated_at: ?string}
     */
    public function issue(Customer $customer, bool $regenerate = false): array
    {
        $token = $regenerate ? null : $this->existingToken($customer);
        $generated = false;

        if (! $token) {
            $token = $this->newUniqueToken($customer);
            $now = now('UTC');

            $customer->forceFill([
                'order_link_token_hash' => self::hashToken($token),
                'order_link_token_encrypted' => Crypt::encryptString($token),
                'order_link_token_created_at' => $customer->order_link_token_created_at ?: $now,
                'order_link_token_rotated_at' => $now,
            ])->save();

            $customer->refresh();
            $generated = true;
        }

        return [
            'url' => $this->urlForToken($customer, $token),
            'token' => $token,
            'generated' => $generated,
            'permanent' => true,
            'rotated_at' => $customer->order_link_token_rotated_at?->toIso8601String(),
        ];
    }

    public function findByToken(string $token): ?Customer
    {
        $token = $this->normalizeToken($token);

        if ($token === null) {
            return null;
        }

        return Customer::query()
            ->where('order_link_token_hash', self::hashToken($token))
            ->first();
    }

    public function findByCustomerLink(string $customerLink): ?Customer
    {
        $token = $this->tokenFromCustomerLink($customerLink);

        return $token ? $this->findByToken($token) : null;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function existingToken(Customer $customer): ?string
    {
        $encrypted = trim((string) ($customer->order_link_token_encrypted ?? ''));
        $hash = trim((string) ($customer->order_link_token_hash ?? ''));

        if ($encrypted === '' || $hash === '') {
            return null;
        }

        try {
            $token = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        $token = $this->normalizeToken($token);

        if (! $token || ! hash_equals($hash, self::hashToken($token))) {
            return null;
        }

        return $token;
    }

    private function newUniqueToken(Customer $customer): string
    {
        do {
            $token = $this->randomToken();
            $hash = self::hashToken($token);
        } while (
            Customer::query()
                ->where('order_link_token_hash', $hash)
                ->where('id', '!=', $customer->id)
                ->exists()
        );

        return $token;
    }

    private function normalizeToken(string $token): ?string
    {
        $token = trim($token);

        if (! preg_match('/\A[A-Za-z0-9]{20}\z/', $token)
            && ! preg_match('/\A[a-f0-9]{64}\z/i', $token)
        ) {
            return null;
        }

        return $token;
    }

    private function tokenFromCustomerLink(string $customerLink): ?string
    {
        $customerLink = trim($customerLink);

        if ($customerLink === '') {
            return null;
        }

        $parts = explode('-', $customerLink);

        return $this->normalizeToken((string) end($parts));
    }

    private function randomToken(): string
    {
        $token = '';
        $max = strlen(self::TOKEN_ALPHABET) - 1;

        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $token .= self::TOKEN_ALPHABET[random_int(0, $max)];
        }

        return $token;
    }

    private function urlForToken(Customer $customer, string $token): string
    {
        return URL::route('order.online.customer-short-start', [
            'customerLink' => $this->slugForCustomer($customer).'-'.$token,
        ]);
    }

    private function slugForCustomer(Customer $customer): string
    {
        $slug = Str::slug((string) $customer->name);

        return $slug !== '' ? Str::limit($slug, 32, '') : 'pelanggan';
    }
}
