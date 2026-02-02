<?php

namespace Modules\NsManufacturing\Services;

trait ManufacturingHelper
{
    /**
     * Format a number using NexoPOS standard settings.
     *
     * @param float|int|string $value
     * @param int|null $precision
     * @return string
     */
    public function formatNumber($value, $precision = null): string
    {
        $precision = $precision !== null ? $precision : ns()->option->get('ns_currency_precision', 2);
        $decimalSeparator = ns()->option->get('ns_currency_decimal_separator', '.');
        $thousandSeparator = ns()->option->get('ns_currency_thousand_separator', ',');

        return number_format(
            (float) $value,
            $precision,
            $decimalSeparator,
            $thousandSeparator
        );
    }

    /**
     * Format a value as currency using NexoPOS standard settings.
     *
     * @param float|int|string $value
     * @return string
     */
    public function formatCurrency($value): string
    {
        return (string) ns()->currency->define($value);
    }

    /**
     * Format a quantity with specified decimal precision.
     *
     * @param float $quantity The quantity to format
     * @param int $decimals Number of decimal places (default: 4)
     * @return string Formatted quantity
     */
    public static function formatQuantity(float $quantity, int $decimals = 4): string
    {
        return number_format($quantity, $decimals);
    }

    /**
     * Format a cost value as currency.
     *
     * @param float $cost The cost to format
     * @return string Formatted cost
     */
    public static function formatCost(float $cost): string
    {
        return (string) ns()->currency->define($cost);
    }

    /**
     * Calculate waste amount based on quantity and waste percentage.
     *
     * @param float $quantity The original quantity
     * @param float $wastePercent The waste percentage (0-100)
     * @return float The waste amount
     */
    public static function calculateWaste(float $quantity, float $wastePercent): float
    {
        return $quantity * ($wastePercent / 100);
    }
}
