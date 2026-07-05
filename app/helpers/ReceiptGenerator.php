<?php
/**
 * Receipt Generator Helper
 * 
 * Generates printable receipts for savings transactions.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class ReceiptGenerator
{
    /**
     * Generate receipt HTML
     * 
     * @param array $transaction Transaction data
     * @param array $company Company information
     * @return string
     */
    public static function generateReceipt(array $transaction, array $company): string
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Receipt</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    margin: 0;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .receipt {
                    max-width: 400px;
                    margin: 0 auto;
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .receipt-header {
                    text-align: center;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 20px;
                    margin-bottom: 20px;
                }
                .receipt-header h2 {
                    margin: 0;
                    color: #667eea;
                    font-size: 24px;
                }
                .receipt-header p {
                    margin: 5px 0;
                    color: #666;
                    font-size: 12px;
                }
                .receipt-details {
                    margin: 20px 0;
                }
                .receipt-details .row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .receipt-details .row:last-child {
                    border-bottom: none;
                }
                .receipt-details .label {
                    color: #666;
                    font-weight: normal;
                }
                .receipt-details .value {
                    font-weight: bold;
                }
                .receipt-details .value.amount {
                    font-size: 20px;
                    color: #667eea;
                }
                .receipt-details .value.deposit {
                    color: #48bb78;
                }
                .receipt-details .value.withdrawal {
                    color: #f56565;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 2px solid #667eea;
                    font-size: 12px;
                    color: #999;
                }
                .receipt-footer .thank-you {
                    font-size: 16px;
                    color: #667eea;
                    font-weight: bold;
                }
                .watermark {
                    text-align: center;
                    margin-top: 10px;
                    font-size: 10px;
                    color: #ccc;
                }
                @media print {
                    body { background: white; }
                    .receipt { box-shadow: none; }
                    .no-print { display: none; }
                }
                .no-print {
                    text-align: center;
                    margin-top: 20px;
                }
                .no-print button {
                    padding: 10px 30px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .no-print button:hover {
                    background: #5a67d8;
                }
            </style>
        </head>
        <body>
            <div class="receipt" id="receipt">
                <div class="receipt-header">
                    <h2>' . htmlspecialchars($company['company_name']) . '</h2>
                    <p>' . htmlspecialchars($company['company_address']) . '</p>
                    <p>Phone: ' . htmlspecialchars($company['company_phone']) . ' | Email: ' . htmlspecialchars($company['company_email']) . '</p>
                    <h3 style="margin-top: 10px;">SAVINGS RECEIPT</h3>
                </div>
                
                <div class="receipt-details">
                    <div class="row">
                        <span class="label">Receipt No:</span>
                        <span class="value">' . htmlspecialchars($transaction['receipt_no']) . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Date:</span>
                        <span class="value">' . date('F d, Y H:i', strtotime($transaction['transaction_date'])) . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Member:</span>
                        <span class="value">' . htmlspecialchars($transaction['member_name']) . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Member No:</span>
                        <span class="value">' . htmlspecialchars($transaction['member_no']) . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Transaction Type:</span>
                        <span class="value ' . strtolower($transaction['transaction_type']) . '">' . htmlspecialchars($transaction['transaction_type']) . '</span>
                    </div>
                    <div class="row">
                        <span class="label">Amount:</span>
                        <span class="value amount ' . strtolower($transaction['transaction_type']) . '">' . number_format($transaction['amount'], 2) . ' TSh</span>
                    </div>
                    <div class="row">
                        <span class="label">Balance After:</span>
                        <span class="value">' . number_format($transaction['balance_after'], 2) . ' TSh</span>
                    </div>
                    <div class="row">
                        <span class="label">Payment Mode:</span>
                        <span class="value">' . htmlspecialchars($transaction['transaction_mode']) . '</span>
                    </div>
                    ' . (!empty($transaction['reference_no']) ? '
                    <div class="row">
                        <span class="label">Reference No:</span>
                        <span class="value">' . htmlspecialchars($transaction['reference_no']) . '</span>
                    </div>
                    ' : '') . '
                    ' . (!empty($transaction['description']) ? '
                    <div class="row">
                        <span class="label">Description:</span>
                        <span class="value">' . htmlspecialchars($transaction['description']) . '</span>
                    </div>
                    ' : '') . '
                </div>
                
                <div class="receipt-footer">
                    <div class="thank-you">Thank you for saving with us!</div>
                    <p>This is a computer-generated receipt. No signature required.</p>
                    <div class="watermark">Generated: ' . date('Y-m-d H:i:s') . '</div>
                </div>
            </div>
            
            <div class="no-print">
                <button onclick="window.print()">🖨️ Print Receipt</button>
                <button onclick="window.close()" style="background: #718096;">Close</button>
            </div>
            
            <script>
                // Auto-print if opened in new window
                window.onload = function() {
                    if (window.opener) {
                        setTimeout(function() {
                            // Don't auto-print, let user decide
                        }, 1000);
                    }
                }
            </script>
        </body>
        </html>';
        
        return $html;
    }
}