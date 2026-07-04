<?php
/**
 * Chart Helper
 * 
 * Provides helper functions for chart data formatting and generation.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class ChartHelper
{
    /**
     * Format data for Chart.js
     * 
     * @param array $data Data array
     * @param string $type Chart type
     * @return array
     */
    public static function formatForChart(array $data, string $type = 'bar'): array
    {
        $labels = array_keys($data);
        $values = array_values($data);
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => self::getChartLabel($type),
                    'data' => $values,
                    'backgroundColor' => self::getColors($type, count($values)),
                    'borderColor' => self::getBorderColors($type, count($values)),
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    /**
     * Get chart label
     * 
     * @param string $type Chart type
     * @return string
     */
    private static function getChartLabel(string $type): string
    {
        $labels = [
            'savings' => 'Savings',
            'loans' => 'Loans',
            'repayments' => 'Repayments',
            'income' => 'Income',
            'growth' => 'Member Growth',
            'loan_status' => 'Loan Status'
        ];
        
        return $labels[$type] ?? 'Data';
    }

    /**
     * Get chart colors
     * 
     * @param string $type Chart type
     * @param int $count Number of colors needed
     * @return array
     */
    private static function getColors(string $type, int $count): array
    {
        $colors = [
            'savings' => 'rgba(54, 162, 235, 0.6)',
            'loans' => 'rgba(255, 99, 132, 0.6)',
            'repayments' => 'rgba(75, 192, 192, 0.6)',
            'income' => 'rgba(153, 102, 255, 0.6)',
            'growth' => 'rgba(255, 159, 64, 0.6)',
            'loan_status' => [
                'Pending' => 'rgba(255, 206, 86, 0.8)',
                'Approved' => 'rgba(54, 162, 235, 0.8)',
                'Rejected' => 'rgba(255, 99, 132, 0.8)',
                'Active' => 'rgba(75, 192, 192, 0.8)',
                'Completed' => 'rgba(153, 102, 255, 0.8)',
                'Defaulted' => 'rgba(255, 159, 64, 0.8)'
            ]
        ];
        
        if ($type === 'loan_status') {
            return $colors['loan_status'];
        }
        
        return array_fill(0, $count, $colors[$type] ?? 'rgba(0, 0, 0, 0.6)');
    }

    /**
     * Get border colors
     * 
     * @param string $type Chart type
     * @param int $count Number of colors needed
     * @return array
     */
    private static function getBorderColors(string $type, int $count): array
    {
        $colors = [
            'savings' => 'rgba(54, 162, 235, 1)',
            'loans' => 'rgba(255, 99, 132, 1)',
            'repayments' => 'rgba(75, 192, 192, 1)',
            'income' => 'rgba(153, 102, 255, 1)',
            'growth' => 'rgba(255, 159, 64, 1)',
            'loan_status' => [
                'Pending' => 'rgba(255, 206, 86, 1)',
                'Approved' => 'rgba(54, 162, 235, 1)',
                'Rejected' => 'rgba(255, 99, 132, 1)',
                'Active' => 'rgba(75, 192, 192, 1)',
                'Completed' => 'rgba(153, 102, 255, 1)',
                'Defaulted' => 'rgba(255, 159, 64, 1)'
            ]
        ];
        
        if ($type === 'loan_status') {
            return $colors['loan_status'];
        }
        
        return array_fill(0, $count, $colors[$type] ?? 'rgba(0, 0, 0, 1)');
    }

    /**
     * Get gradient colors for charts
     * 
     * @param string $color Base color
     * @param int $steps Number of steps
     * @return array
     */
    public static function getGradientColors(string $color, int $steps): array
    {
        // Parse color
        preg_match('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $color, $matches);
        
        if (count($matches) < 5) {
            return array_fill(0, $steps, $color);
        }
        
        $r = (int)$matches[1];
        $g = (int)$matches[2];
        $b = (int)$matches[3];
        $a = (float)$matches[4];
        
        $colors = [];
        for ($i = 0; $i < $steps; $i++) {
            $opacity = $a * (1 - ($i / ($steps * 2)));
            $colors[] = "rgba($r, $g, $b, $opacity)";
        }
        
        return $colors;
    }

    /**
     * Format currency for chart tooltips
     * 
     * @param float $value Currency value
     * @return string
     */
    public static function formatCurrency(float $value): string
    {
        return number_format($value, 2) . ' TSh';
    }

    /**
     * Get month labels for charts
     * 
     * @param int $months Number of months
     * @return array
     */
    public static function getMonthLabels(int $months = 12): array
    {
        $labels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = date('M Y', strtotime("-$i months"));
        }
        return $labels;
    }
}