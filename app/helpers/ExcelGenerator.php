<?php
/**
 * Excel Generator Helper
 * 
 * Generates Excel reports using simple HTML table format
 * that Excel can open.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class ExcelGenerator
{
    /**
     * Generate Excel file content
     * 
     * @param array $data Report data
     * @param string $title Report title
     * @param array $columns Column definitions
     * @return string Excel content
     */
    public static function generate(array $data, string $title, array $columns): string
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                       xmlns:x="urn:schemas-microsoft-com:office:excel" 
                       xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8">';
        $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . $title . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        $html .= '<style>
                    table { border-collapse: collapse; width: 100%; }
                    th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; }
                    td { padding: 6px; border: 1px solid #ccc; }
                    .header { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
                    .subheader { font-size: 10px; text-align: center; margin-bottom: 15px; color: #666; }
                  </style>';
        $html .= '</head><body>';
        
        // Header
        $html .= '<div class="header">' . APP_NAME . ' - ' . $title . '</div>';
        $html .= '<div class="subheader">Generated: ' . date('Y-m-d H:i:s') . '</div>';
        
        // Table
        $html .= '<table>';
        
        // Headers
        $html .= '<tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . htmlspecialchars($col['label']) . '</th>';
        }
        $html .= '</tr>';
        
        // Data
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $row[$col['field']] ?? '';
                if (isset($col['format']) && $col['format'] === 'currency') {
                    $value = number_format($value, 2);
                } elseif (isset($col['format']) && $col['format'] === 'date') {
                    $value = date('Y-m-d', strtotime($value));
                }
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        // Summary
        $html .= '<br>';
        $html .= '<div style="font-weight: bold;">Total Records: ' . count($data) . '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Generate financial summary Excel
     * 
     * @param array $summary Financial summary data
     * @param int $year Year
     * @return string Excel content
     */
    public static function generateFinancialSummary(array $summary, int $year): string
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                       xmlns:x="urn:schemas-microsoft-com:office:excel" 
                       xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8">';
        $html .= '<style>
                    body { font-family: Arial, sans-serif; }
                    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                    th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; }
                    td { padding: 6px; border: 1px solid #ccc; }
                    .header { font-size: 16px; font-weight: bold; text-align: center; }
                    .section-title { font-size: 14px; font-weight: bold; margin-top: 15px; background-color: #e9ecef; padding: 5px; }
                    .total { font-weight: bold; background-color: #f8f9fa; }
                    .profit { color: green; }
                    .loss { color: red; }
                  </style>';
        $html .= '</head><body>';
        
        // Header
        $html .= '<div class="header">' . APP_NAME . ' - Financial Summary ' . $year . '</div>';
        $html .= '<div style="text-align:center; font-size:10px; color:#666;">Generated: ' . date('Y-m-d H:i:s') . '</div>';
        
        // Income
        $html .= '<div class="section-title">INCOME</div>';
        $html .= '<table>';
        $html .= '<tr><th>Source</th><th align="right">Amount (TSh)</th></tr>';
        $html .= '<tr><td>Savings Deposits</td><td align="right">' . number_format($summary['income']['savings'], 2) . '</td></tr>';
        $html .= '<tr><td>Loan Interest</td><td align="right">' . number_format($summary['income']['loan_interest'], 2) . '</td></tr>';
        $html .= '<tr><td>Fine Income</td><td align="right">' . number_format($summary['income']['fines'], 2) . '</td></tr>';
        $html .= '<tr class="total"><td><strong>Total Income</strong></td><td align="right"><strong>' . number_format($summary['income']['total'], 2) . '</strong></td></tr>';
        $html .= '</table>';
        
        // Expenses
        $html .= '<div class="section-title">EXPENSES</div>';
        $html .= '<table>';
        $html .= '<tr><th>Category</th><th align="right">Amount (TSh)</th></tr>';
        $html .= '<tr><td>Loans Disbursed</td><td align="right">' . number_format($summary['expenses']['loans_disbursed'], 2) . '</td></tr>';
        $html .= '<tr><td>Dividends Paid</td><td align="right">' . number_format($summary['expenses']['dividends_paid'], 2) . '</td></tr>';
        $html .= '<tr class="total"><td><strong>Total Expenses</strong></td><td align="right"><strong>' . number_format($summary['expenses']['total'], 2) . '</strong></td></tr>';
        $html .= '</table>';
        
        // Net Income
        $netClass = $summary['net_income'] >= 0 ? 'profit' : 'loss';
        $html .= '<div class="section-title">NET INCOME</div>';
        $html .= '<table>';
        $html .= '<tr><td style="font-size:14px; font-weight:bold; text-align:center;">';
        $html .= '<span class="' . $netClass . '">' . number_format($summary['net_income'], 2) . ' TSh</span>';
        $html .= '</td></tr>';
        $html .= '</table>';
        
        // Additional Metrics
        $html .= '<div class="section-title">Additional Metrics</div>';
        $html .= '<table>';
        $html .= '<tr><td><strong>Total Savings Balance</strong></td><td align="right">' . number_format($summary['total_savings_balance'], 2) . ' TSh</td></tr>';
        $html .= '<tr><td><strong>Outstanding Loans</strong></td><td align="right">' . number_format($summary['total_outstanding_loans'], 2) . ' TSh</td></tr>';
        $html .= '<tr><td><strong>Outstanding Fines</strong></td><td align="right">' . number_format($summary['total_outstanding_fines'], 2) . ' TSh</td></tr>';
        $html .= '</table>';
        
        $html .= '</body></html>';
        
        return $html;
    }
}