<?php
/**
 * Dividend Calculator Helper
 * 
 * Provides functions for calculating dividend distributions
 * based on member savings and total profit.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class DividendCalculator
{
    /**
     * Calculate individual member dividend share
     * 
     * @param float $totalProfit Total annual profit
     * @param float $totalSavings Total member savings
     * @param float $memberSavings Individual member savings
     * @param float $interestPercentage Percentage of profit to distribute
     * @return float
     */
    public static function calculateIndividualShare(
        float $totalProfit,
        float $totalSavings,
        float $memberSavings,
        float $interestPercentage
    ): float {
        if ($totalSavings <= 0 || $totalProfit <= 0) {
            return 0;
        }
        
        // Calculate amount to distribute
        $distributableAmount = $totalProfit * ($interestPercentage / 100);
        
        // Calculate member's proportion
        $proportion = $memberSavings / $totalSavings;
        
        // Calculate individual share
        $share = $distributableAmount * $proportion;
        
        return round($share, 2);
    }

    /**
     * Calculate total distributable amount
     * 
     * @param float $totalProfit Total annual profit
     * @param float $interestPercentage Percentage to distribute
     * @return float
     */
    public static function calculateDistributableAmount(
        float $totalProfit,
        float $interestPercentage
    ): float {
        return round($totalProfit * ($interestPercentage / 100), 2);
    }

    /**
     * Calculate percentage of total savings
     * 
     * @param float $memberSavings Individual member savings
     * @param float $totalSavings Total member savings
     * @return float
     */
    public static function calculateSavingsPercentage(
        float $memberSavings,
        float $totalSavings
    ): float {
        if ($totalSavings <= 0) {
            return 0;
        }
        return round(($memberSavings / $totalSavings) * 100, 2);
    }

    /**
     * Calculate average member share
     * 
     * @param float $totalProfit Total annual profit
     * @param int $memberCount Number of members
     * @param float $interestPercentage Percentage to distribute
     * @return float
     */
    public static function calculateAverageShare(
        float $totalProfit,
        int $memberCount,
        float $interestPercentage
    ): float {
        if ($memberCount <= 0) {
            return 0;
        }
        
        $distributableAmount = $totalProfit * ($interestPercentage / 100);
        return round($distributableAmount / $memberCount, 2);
    }

    /**
     * Calculate dividend yield
     * 
     * @param float $dividend Individual dividend
     * @param float $savings Individual savings
     * @return float
     */
    public static function calculateDividendYield(
        float $dividend,
        float $savings
    ): float {
        if ($savings <= 0) {
            return 0;
        }
        return round(($dividend / $savings) * 100, 2);
    }

    /**
     * Calculate top member savings contribution
     * 
     * @param array $members Member data with savings
     * @return array
     */
    public static function calculateTopContributors(array $members, int $limit = 5): array
    {
        usort($members, function($a, $b) {
            return $b['total_savings'] <=> $a['total_savings'];
        });
        
        return array_slice($members, 0, $limit);
    }

    /**
     * Calculate distribution summary
     * 
     * @param float $totalProfit Total annual profit
     * @param float $interestPercentage Percentage to distribute
     * @param int $memberCount Number of members
     * @param float $totalSavings Total member savings
     * @return array
     */
    public static function calculateDistributionSummary(
        float $totalProfit,
        float $interestPercentage,
        int $memberCount,
        float $totalSavings
    ): array {
        $distributableAmount = self::calculateDistributableAmount($totalProfit, $interestPercentage);
        $averageShare = self::calculateAverageShare($totalProfit, $memberCount, $interestPercentage);
        
        return [
            'total_profit' => $totalProfit,
            'interest_percentage' => $interestPercentage,
            'member_count' => $memberCount,
            'total_savings' => $totalSavings,
            'distributable_amount' => $distributableAmount,
            'average_share' => $averageShare,
            'retained_earnings' => $totalProfit - $distributableAmount
        ];
    }

    /**
     * Calculate multiple scenarios for what-if analysis
     * 
     * @param float $totalProfit Total annual profit
     * @param float $totalSavings Total member savings
     * @param array $scenarios Percentage scenarios
     * @return array
     */
    public static function calculateScenarios(
        float $totalProfit,
        float $totalSavings,
        array $scenarios = [50, 60, 70, 80, 90]
    ): array {
        $results = [];
        
        foreach ($scenarios as $percentage) {
            $distributableAmount = self::calculateDistributableAmount($totalProfit, $percentage);
            
            $results[] = [
                'percentage' => $percentage,
                'distributable_amount' => $distributableAmount,
                'retained_earnings' => $totalProfit - $distributableAmount,
                'rate' => $totalSavings > 0 ? round(($distributableAmount / $totalSavings) * 100, 2) : 0
            ];
        }
        
        return $results;
    }

    /**
     * Format dividend for display
     * 
     * @param float $amount Dividend amount
     * @return string
     */
    public static function formatDividend(float $amount): string
    {
        return number_format($amount, 2) . ' TSh';
    }

    /**
     * Get dividend status badge class
     * 
     * @param string $status Dividend status
     * @return string
     */
    public static function getStatusBadgeClass(string $status): string
    {
        $classes = [
            'Declared' => 'warning',
            'Partially_Paid' => 'info',
            'Paid' => 'success'
        ];
        
        return $classes[$status] ?? 'secondary';
    }
}