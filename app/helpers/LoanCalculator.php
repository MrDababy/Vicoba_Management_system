<?php
/**
 * Loan Calculator Helper
 * 
 * Provides loan calculation functions including amortization,
 * interest calculation, and payment schedules.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class LoanCalculator
{
    /**
     * Calculate monthly payment using amortization
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $months Loan duration in months
     * @return float
     */
    public static function calculateMonthlyPayment(float $principal, float $annualRate, int $months): float
    {
        $monthlyRate = ($annualRate / 100) / 12;
        
        if ($monthlyRate == 0) {
            return $principal / $months;
        }
        
        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        return round($payment, 2);
    }

    /**
     * Calculate total interest over loan period
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $months Loan duration in months
     * @return float
     */
    public static function calculateTotalInterest(float $principal, float $annualRate, int $months): float
    {
        $monthlyPayment = self::calculateMonthlyPayment($principal, $annualRate, $months);
        $totalPayment = $monthlyPayment * $months;
        return round($totalPayment - $principal, 2);
    }

    /**
     * Calculate total repayable amount
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $months Loan duration in months
     * @return float
     */
    public static function calculateTotalRepayable(float $principal, float $annualRate, int $months): float
    {
        $monthlyPayment = self::calculateMonthlyPayment($principal, $annualRate, $months);
        return round($monthlyPayment * $months, 2);
    }

    /**
     * Generate amortization schedule
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $months Loan duration in months
     * @return array
     */
    public static function generateAmortizationSchedule(float $principal, float $annualRate, int $months): array
    {
        $monthlyRate = ($annualRate / 100) / 12;
        $monthlyPayment = self::calculateMonthlyPayment($principal, $annualRate, $months);
        $schedule = [];
        
        $balance = $principal;
        $totalInterest = 0;
        
        for ($i = 1; $i <= $months; $i++) {
            $interestAmount = $balance * $monthlyRate;
            $principalAmount = $monthlyPayment - $interestAmount;
            
            if ($i === $months) {
                $principalAmount = $balance;
                $monthlyPayment = $balance + $interestAmount;
            }
            
            $balance -= $principalAmount;
            $totalInterest += $interestAmount;
            
            $schedule[] = [
                'installment_no' => $i,
                'principal' => round($principalAmount, 2),
                'interest' => round($interestAmount, 2),
                'payment' => round($monthlyPayment, 2),
                'balance' => max(0, round($balance, 2))
            ];
        }
        
        return $schedule;
    }

    /**
     * Calculate loan affordability
     * 
     * @param float $income Monthly income
     * @param float $expenses Monthly expenses
     * @param float $loanAmount Requested loan amount
     * @param float $interestRate Interest rate
     * @param int $months Duration in months
     * @return array
     */
    public static function calculateAffordability(float $income, float $expenses, float $loanAmount, float $interestRate, int $months): array
    {
        $disposableIncome = $income - $expenses;
        $monthlyPayment = self::calculateMonthlyPayment($loanAmount, $interestRate, $months);
        $affordabilityRatio = $disposableIncome > 0 ? ($monthlyPayment / $disposableIncome) * 100 : 0;
        
        return [
            'disposable_income' => round($disposableIncome, 2),
            'monthly_payment' => $monthlyPayment,
            'affordability_ratio' => round($affordabilityRatio, 2),
            'is_affordable' => $affordabilityRatio <= 40, // 40% rule of thumb
            'recommendation' => self::getAffordabilityRecommendation($affordabilityRatio)
        ];
    }

    /**
     * Get affordability recommendation
     * 
     * @param float $ratio Affordability ratio
     * @return string
     */
    private static function getAffordabilityRecommendation(float $ratio): string
    {
        if ($ratio <= 30) {
            return 'Affordable - Loan is well within your budget.';
        } elseif ($ratio <= 40) {
            return 'Manageable - Loan is within budget but consider reducing amount.';
        } elseif ($ratio <= 50) {
            return 'Stretch - Loan may strain your budget. Consider reducing amount or extending term.';
        } else {
            return 'Not Recommended - Loan exceeds recommended budget limits.';
        }
    }

    /**
     * Calculate effective interest rate
     * 
     * @param float $principal Principal amount
     * @param float $totalPayment Total payment including interest
     * @param int $months Duration in months
     * @return float
     */
    public static function calculateEffectiveRate(float $principal, float $totalPayment, int $months): float
    {
        if ($principal <= 0 || $totalPayment <= $principal) {
            return 0;
        }
        
        $totalInterest = $totalPayment - $principal;
        $annualRate = ($totalInterest / $principal) / ($months / 12) * 100;
        return round($annualRate, 2);
    }

    /**
     * Calculate early repayment savings
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (%)
     * @param int $months Original duration
     * @param int $monthsPaid Months already paid
     * @return array
     */
    public static function calculateEarlyRepaymentSavings(float $principal, float $annualRate, int $months, int $monthsPaid): array
    {
        $monthlyPayment = self::calculateMonthlyPayment($principal, $annualRate, $months);
        $totalOriginal = $monthlyPayment * $months;
        
        // Calculate remaining balance
        $monthlyRate = ($annualRate / 100) / 12;
        $remainingBalance = $principal * (pow(1 + $monthlyRate, $months) - pow(1 + $monthlyRate, $monthsPaid)) / (pow(1 + $monthlyRate, $months) - 1);
        
        $remainingPayments = $monthlyPayment * ($months - $monthsPaid);
        $savings = round($remainingPayments - $remainingBalance, 2);
        
        return [
            'remaining_balance' => round($remainingBalance, 2),
            'remaining_payments' => round($remainingPayments, 2),
            'savings' => $savings,
            'savings_percentage' => $savings > 0 ? round(($savings / $remainingPayments) * 100, 2) : 0
        ];
    }
}