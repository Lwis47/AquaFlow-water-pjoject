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

    /**
     * Plumbing has no published price in the project brief.  The quote records
     * the site's agreed unit rates so the calculation is transparent and can be
     * audited later instead of silently inventing a price.
     */

    public static function calculate(array $input): array
    {
        $category = self::choice($input, 'category', array_keys(self::FEES));
        $drilling = self::choice($input, 'drilling_type', array_keys(self::DRILLING));
        $pump = self::choice($input, 'pump_type', array_keys(self::PUMPS));
        $depth = self::number($input['depth_metres'] ?? 0, 'depth_metres');
        $height = self::number($input['tank_height_metres'] ?? 0, 'tank_height_metres');
        $pipeLength = self::number($input['pipe_length_metres'] ?? 0, 'pipe_length_metres');
        $outlets = self::number($input['outlets'] ?? 0, 'outlets');
        $pipeRate = self::number($input['pipe_rate_per_metre'] ?? 0, 'pipe_rate_per_metre');
        $outletRate = self::number($input['outlet_rate'] ?? 0, 'outlet_rate');
        $metres = max($depth, $height);
        $depthRate = self::depthRate($metres);
        $fees = self::FEES[$category];
        $depthFee = (int) round(($depth + $height) * $depthRate);
        $plumbingFee = (int) round(($pipeLength * $pipeRate) + ($outlets * $outletRate));
        $subtotal = $fees['survey'] + $fees['authority'] + self::DRILLING[$drilling];
        $subtotal += self::PUMPS[$pump] + $depthFee + $plumbingFee;
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
            'drilling_fee' => self::DRILLING[$drilling],
            'pump_fee' => self::PUMPS[$pump],
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

    private static function number(mixed $value, string $key): float
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException($key . ' must be a positive number.');
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
