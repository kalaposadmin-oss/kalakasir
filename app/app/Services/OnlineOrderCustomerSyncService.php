<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OnlineOrder;

class OnlineOrderCustomerSyncService
{
    public function syncForOrder(OnlineOrder $order): ?Customer
    {
        $customer = $this->syncFromPayload(
            tenantId: (string) $order->tenant_id,
            name: $order->customer_name,
            phone: $order->customer_phone,
            address: $order->address
        );

        if ($customer && (string) ($order->customer_id ?? '') !== (string) $customer->id) {
            $order->forceFill(['customer_id' => $customer->id])->save();
        }

        return $customer;
    }

    public function syncFromPayload(string $tenantId, ?string $name, ?string $phone, ?string $address = null): ?Customer
    {
        $name = trim((string) $name);
        $phone = $this->normalizePhone($phone);
        $address = trim((string) $address);

        if ($tenantId === '' || ($name === '' && $phone === '')) {
            return null;
        }

        $customer = $this->findExistingCustomer($tenantId, $name, $phone);

        if (! $customer) {
            return Customer::query()->create([
                'tenant_id' => $tenantId,
                'name' => $name !== '' ? $name : $phone,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
                'created_at' => now('UTC'),
            ]);
        }

        $changes = [];

        if ($name !== '' && $customer->name !== $name) {
            $changes['name'] = $name;
        }

        if ($phone !== '' && $customer->phone !== $phone) {
            $changes['phone'] = $phone;
        }

        if ($address !== '' && $customer->address !== $address) {
            $changes['address'] = $address;
        }

        if ($changes !== []) {
            $customer->forceFill($changes)->save();
        }

        return $customer->refresh();
    }

    private function findExistingCustomer(string $tenantId, string $name, string $phone): ?Customer
    {
        if ($phone !== '') {
            $phoneColumn = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '.', ''), '(', ''), ')', ''), '+', '')";

            foreach ($this->phoneComparisonCandidates($phone) as $candidatePhone) {
                $customer = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->whereRaw($phoneColumn.' = ?', [$candidatePhone])
                    ->first();

                if ($customer) {
                    return $customer;
                }
            }

            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('phone', $phone)
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        if ($name === '') {
            return null;
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('lower(name) = ?', [strtolower($name)])
            ->when($phone !== '', fn ($query) => $query->where(function ($nested) use ($phone) {
                $nested->whereNull('phone')
                    ->orWhere('phone', '')
                    ->orWhere('phone', $phone);
            }))
            ->first();
    }

    private function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', trim((string) $phone)) ?: '';
    }

    /**
     * @return array<int, string>
     */
    private function phoneComparisonCandidates(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];

        if (str_starts_with($normalized, '62') && strlen($normalized) > 2) {
            $candidates[] = '0'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '0') && strlen($normalized) > 1) {
            $candidates[] = '62'.substr($normalized, 1);
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
