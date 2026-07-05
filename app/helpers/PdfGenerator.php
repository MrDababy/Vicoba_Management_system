<?php
/**
 * PDF Generator Helper
 * 
 * Generates PDF reports using TCPDF library.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

class PdfGenerator
{
    /**
     * @var \TCPDF PDF instance
     */
    private \TCPDF $pdf;

    /**
     * @var array Page settings
     */
    private array $settings = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator(APP_NAME);
        $this->pdf->SetAuthor(APP_NAME);
        $this->pdf->SetTitle('Report');
        $this->pdf->SetSubject('VICOBA Report');
        
        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        
        // Set margins
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);
        
        // Add first page
        $this->pdf->AddPage();
    }

    /**
     * Generate member report PDF
     * 
     * @param array $data Report data
     * @param array $filters Report filters
     * @return string PDF content
     */
    public function generateMemberReport(array $data, array $filters): string
    {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, 'Member Report', 0, 1, 'C');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Filters summary
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 5, 'Filters:', 0, 1);
        $this->pdf->SetFont('helvetica', '', 9);
        
        $filterText = [];
        if (!empty($filters['status'])) $filterText[] = 'Status: ' . $filters['status'];
        if (!empty($filters['gender'])) $filterText[] = 'Gender: ' . $filters['gender'];
        if (!empty($filters['from_date'])) $filterText[] = 'From: ' . $filters['from_date'];
        if (!empty($filters['to_date'])) $filterText[] = 'To: ' . $filters['to_date'];
        
        if (!empty($filterText)) {
            $this->pdf->Cell(0, 5, implode(' | ', $filterText), 0, 1);
        } else {
            $this->pdf->Cell(0, 5, 'All Members', 0, 1);
        }
        $this->pdf->Ln(5);
        
        // Table header
        $this->pdf->SetFont('helvetica', 'B', 9);
        $header = ['#', 'Member No', 'Name', 'Gender', 'Phone', 'Status', 'Savings', 'Loans'];
        $widths = [8, 25, 50, 20, 30, 20, 25, 20];
        
        foreach ($header as $i => $col) {
            $this->pdf->Cell($widths[$i], 7, $col, 1, 0, 'C');
        }
        $this->pdf->Ln();
        
        // Table data
        $this->pdf->SetFont('helvetica', '', 8);
        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            $this->pdf->Cell($widths[0], 6, $rowCount, 1, 0, 'C');
            $this->pdf->Cell($widths[1], 6, $row['member_no'], 1, 0, 'L');
            $this->pdf->Cell($widths[2], 6, substr($row['full_name'], 0, 25), 1, 0, 'L');
            $this->pdf->Cell($widths[3], 6, $row['gender'], 1, 0, 'C');
            $this->pdf->Cell($widths[4], 6, $row['phone'], 1, 0, 'L');
            $this->pdf->Cell($widths[5], 6, $row['status'], 1, 0, 'C');
            $this->pdf->Cell($widths[6], 6, number_format($row['total_deposits'] ?? 0, 0), 1, 0, 'R');
            $this->pdf->Cell($widths[7], 6, $row['total_loans'] ?? 0, 1, 0, 'C');
            $this->pdf->Ln();
            
            if ($this->pdf->getY() > 250) {
                $this->pdf->AddPage();
            }
        }
        
        // Summary
        $this->pdf->Ln(5);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 5, 'Summary:', 0, 1);
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->Cell(0, 5, 'Total Members: ' . count($data), 0, 1);
        
        // Gender breakdown
        $male = 0;
        $female = 0;
        foreach ($data as $row) {
            if ($row['gender'] === 'Male') $male++;
            if ($row['gender'] === 'Female') $female++;
        }
        $this->pdf->Cell(0, 5, 'Gender: Male ' . $male . ', Female ' . $female, 0, 1);
        
        return $this->pdf->Output('', 'S');
    }

    /**
     * Generate financial summary PDF
     * 
     * @param array $summary Financial summary data
     * @param int $year Year
     * @return string PDF content
     */
    public function generateFinancialSummary(array $summary, int $year): string
    {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, 'Financial Summary - ' . $year, 0, 1, 'C');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // Income
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 8, 'INCOME', 0, 1, 'C');
        $this->pdf->SetFont('helvetica', '', 10);
        
        $this->pdf->Cell(80, 6, 'Savings Deposits:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['income']['savings'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->Cell(80, 6, 'Loan Interest:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['income']['loan_interest'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->Cell(80, 6, 'Fine Income:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['income']['fines'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(80, 6, 'Total Income:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['income']['total'], 2) . ' TSh', 0, 1, 'R');
        $this->pdf->Ln(10);
        
        // Expenses
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 8, 'EXPENSES', 0, 1, 'C');
        $this->pdf->SetFont('helvetica', '', 10);
        
        $this->pdf->Cell(80, 6, 'Loans Disbursed:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['expenses']['loans_disbursed'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->Cell(80, 6, 'Dividends Paid:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['expenses']['dividends_paid'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(80, 6, 'Total Expenses:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['expenses']['total'], 2) . ' TSh', 0, 1, 'R');
        $this->pdf->Ln(10);
        
        // Net Income
        $this->pdf->SetFont('helvetica', 'B', 12);
        $netColor = $summary['net_income'] >= 0 ? ' (Profit)' : ' (Loss)';
        $this->pdf->Cell(0, 8, 'NET INCOME' . $netColor, 0, 1, 'C');
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 8, number_format($summary['net_income'], 2) . ' TSh', 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // Additional metrics
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(0, 8, 'Additional Metrics', 0, 1);
        $this->pdf->SetFont('helvetica', '', 10);
        
        $this->pdf->Cell(80, 6, 'Total Savings Balance:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['total_savings_balance'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->Cell(80, 6, 'Outstanding Loans:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['total_outstanding_loans'], 2) . ' TSh', 0, 1, 'R');
        
        $this->pdf->Cell(80, 6, 'Outstanding Fines:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, number_format($summary['total_outstanding_fines'], 2) . ' TSh', 0, 1, 'R');
        
        return $this->pdf->Output('', 'S');
    }

    /**
     * Generate generic report PDF
     * 
     * @param array $data Report data
     * @param string $title Report title
     * @param array $columns Column definitions
     * @return string PDF content
     */
    public function generateGenericReport(array $data, string $title, array $columns): string
    {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, $title, 0, 1, 'C');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Table header
        $this->pdf->SetFont('helvetica', 'B', 9);
        $headers = array_column($columns, 'label');
        $widths = array_column($columns, 'width');
        
        foreach ($headers as $i => $col) {
            $this->pdf->Cell($widths[$i], 7, $col, 1, 0, 'C');
        }
        $this->pdf->Ln();
        
        // Table data
        $this->pdf->SetFont('helvetica', '', 8);
        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            foreach ($columns as $i => $col) {
                $value = $row[$col['field']] ?? '';
                if (isset($col['format']) && $col['format'] === 'currency') {
                    $value = number_format($value, 2);
                } elseif (isset($col['format']) && $col['format'] === 'date') {
                    $value = date('Y-m-d', strtotime($value));
                }
                $this->pdf->Cell($widths[$i], 6, substr($value, 0, 20), 1, 0, $col['align'] ?? 'L');
            }
            $this->pdf->Ln();
            
            if ($this->pdf->getY() > 250) {
                $this->pdf->AddPage();
                // Repeat header
                foreach ($headers as $i => $col) {
                    $this->pdf->Cell($widths[$i], 7, $col, 1, 0, 'C');
                }
                $this->pdf->Ln();
            }
        }
        
        // Summary
        $this->pdf->Ln(5);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 5, 'Total Records: ' . count($data), 0, 1);
        
        return $this->pdf->Output('', 'S');
    }
}