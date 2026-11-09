<?php

declare(strict_types=1);

namespace AquaFlow;

use InvalidArgumentException;

final class QuoteCalculator
{
    private const TAX_RATE = 0.16;

    private const FEES = [
        'Domestic' => ['survey' => 7000, 'authority' => 10000],
        'Commercial' => ['survey' => 15000, 'authority' => 30000],
        'Industrial' => ['survey' => 20000, 'authority' => 50000],
    ];

    private const DRILLING = [
        'None' => 0,
        'Symmetric' => 130000,
        'Core' => 225000,
        'Geotechnical' => 335000,
    ];

    private const PUMPS = [
        'None' => 0,
        'Submersible' => 90000,
        'Solar' => 65000,
        'Hand' => 30000,
    ];

    public static function calculate(array $input, ?array $catalogue = null): array
    {
        $category = self::choice($input, 'category', array_keys(self::FEES));
        $drilling = self::choice($input, 'drilling_type', array_keys(self::DRILLING));
        $pump = self::choice($input, 'pump_type', array_keys(self::PUMPS));
        $drillingFee = self::servicePrice(match ($drilling) {
            'None' => '',
            'Symmetric' => 'symmetric',
            'Core' => 'core',
            'Geotechnical' => 'geo-technical',
        }, self::DRILLING[$drilling], $catalogue);
        $pumpFee = self::servicePrice(match ($pump) {
            'None' => '',
            'Submersible' => 'submersible',
            'Solar' => 'solar',
            'Hand' => 'hand',
        }, self::PUMPS[$pump], $catalogue);
        $depth = self::number($input['depth_metres'] ?? 0, 'depth_metres');
        $height = self::number($input['tank_height_metres'] ?? 0, 'tank_height_metres');
        $pipeLength = self::number($input['pipe_length_metres'] ?? 0, 'pipe_length_metres');
        $outlets = self::number($input['outlets'] ?? 0, 'outlets');
        if (floor($outlets) !== $outlets) {
            throw new InvalidArgumentException('outlets must be a non-negative integer.');
        }
        $pipeRate = self::number($input['pipe_rate_per_metre'] ?? 0, 'pipe_rate_per_metre');
        $outletRate = self::number($input['outlet_rate'] ?? 0, 'outlet_rate');
        $metres = max($depth, $height);
        $depthRate = self::depthRate($metres);
        $fees = self::FEES[$category];
        $depthFee = (int) round(($depth + $height) * $depthRate);
        $plumbingFee = (int) round(($pipeLength * $pipeRate) + ($outlets * $outletRate));
        $subtotal = $fees['survey'] + $fees['authority'] + $drillingFee;
        $subtotal += $pumpFee + $depthFee + $plumbingFee;
        $tax = (int) round($subtotal * self::TAX_RATE);

        return [
            'category' => $category,
            'drilling_type' => $drilling,
            'pump_type' => $pump,
            'depth_metres' => $depth,
            'tank_height_metres' => $height,
            'depth_rate' => $depthRate,
            'survey_fee' => $fees['survey'],
            'authority_fee' => $fees['authority'],
            'drilling_fee' => $drillingFee,
            'pump_fee' => $pumpFee,
            'depth_fee' => $depthFee,
            'pipe_type' => trim((string) ($input['pipe_type'] ?? '')),
            'pipe_diameter_inches' => self::number($input['pipe_diameter_inches'] ?? 0, 'pipe_diameter_inches'),
            'pipe_length_metres' => $pipeLength,
            'outlets' => $outlets,
            'pipe_rate_per_metre' => $pipeRate,
            'outlet_rate' => $outletRate,
            'plumbing_fee' => $plumbingFee,
            'subtotal' => $subtotal,
            'tax_rate' => self::TAX_RATE,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ];
    }

    private static function choice(array $input, string $key, array $allowed): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Invalid ' . str_replace('_', ' ', $key) . '.');
        }

        return $value;
    }

    private static function servicePrice(string $code, int $default, ?array $catalogue): int|float
    {
        if ($code === '' || $catalogue === null) {
            return $default;
        }

        foreach ($catalogue as $service) {
            if (($service['code'] ?? '') !== $code) {
                continue;
            }
            if ((int) ($service['active'] ?? 0) !== 1 || ($service['pricing_model'] ?? '') !== 'fixed') {
                throw new InvalidArgumentException('Selected service "' . $code . '" must be active with fixed pricing.');
            }

            return self::number($service['base_price'] ?? null, 'Service price');
        }

        throw new InvalidArgumentException('Selected service "' . $code . '" is unavailable.');
    }

    private static function number(mixed $value, string $key): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException($key . ' must be a finite non-negative number.');
        }

        return (float) $value;
    }

    private static function depthRate(float $metres): int
    {
        return match (true) {
            $metres <= 100 => 1000,
            $metres <= 200 => 1500,
            $metres <= 300 => 2000,
            default => 2500,
        };
    }
}
