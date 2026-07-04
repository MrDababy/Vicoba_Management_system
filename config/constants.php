<?php
/**
 * Application Constants
 * 
 * This file defines global constants used throughout the application.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

// User Roles
define('ROLE_ADMIN', 'Admin');
define('ROLE_TREASURER', 'Treasurer');
define('ROLE_SECRETARY', 'Secretary');
define('ROLE_MEMBER', 'Member');

// User Statuses
define('USER_STATUS_ACTIVE', 'Active');
define('USER_STATUS_INACTIVE', 'Inactive');
define('USER_STATUS_SUSPENDED', 'Suspended');

// Member Statuses
define('MEMBER_STATUS_ACTIVE', 'Active');
define('MEMBER_STATUS_INACTIVE', 'Inactive');
define('MEMBER_STATUS_SUSPENDED', 'Suspended');
define('MEMBER_STATUS_DEFAULTED', 'Defaulted');

// Loan Statuses
define('LOAN_STATUS_PENDING', 'Pending');
define('LOAN_STATUS_APPROVED', 'Approved');
define('LOAN_STATUS_REJECTED', 'Rejected');
define('LOAN_STATUS_ACTIVE', 'Active');
define('LOAN_STATUS_COMPLETED', 'Completed');
define('LOAN_STATUS_DEFAULTED', 'Defaulted');

// Loan Installment Statuses
define('INSTALLMENT_STATUS_PENDING', 'Pending');
define('INSTALLMENT_STATUS_PAID', 'Paid');
define('INSTALLMENT_STATUS_PARTIALLY_PAID', 'Partially_Paid');
define('INSTALLMENT_STATUS_OVERDUE', 'Overdue');

// Fine Statuses
define('FINE_STATUS_PENDING', 'Pending');
define('FINE_STATUS_PAID', 'Paid');
define('FINE_STATUS_PARTIALLY_PAID', 'Partially_Paid');
define('FINE_STATUS_WAIVED', 'Waived');

// Dividend Statuses
define('DIVIDEND_STATUS_DECLARED', 'Declared');
define('DIVIDEND_STATUS_PAID', 'Paid');
define('DIVIDEND_STATUS_PARTIALLY_PAID', 'Partially_Paid');

// Meeting Statuses
define('MEETING_STATUS_SCHEDULED', 'Scheduled');
define('MEETING_STATUS_HELD', 'Held');
define('MEETING_STATUS_CANCELLED', 'Cancelled');

// Attendance Statuses
define('ATTENDANCE_PRESENT', 'Present');
define('ATTENDANCE_ABSENT', 'Absent');
define('ATTENDANCE_EXCUSED', 'Excused');
define('ATTENDANCE_LATE', 'Late');

// Transaction Types
define('TRANSACTION_DEPOSIT', 'Deposit');
define('TRANSACTION_WITHDRAWAL', 'Withdrawal');

// Payment Methods
define('PAYMENT_CASH', 'Cash');
define('PAYMENT_BANK_TRANSFER', 'Bank Transfer');
define('PAYMENT_MOBILE_MONEY', 'Mobile Money');
define('PAYMENT_CHEQUE', 'Cheque');

// Activity Actions
define('ACTION_CREATE', 'CREATE');
define('ACTION_READ', 'READ');
define('ACTION_UPDATE', 'UPDATE');
define('ACTION_DELETE', 'DELETE');
define('ACTION_LOGIN', 'LOGIN');
define('ACTION_LOGOUT', 'LOGOUT');
define('ACTION_APPROVE', 'APPROVE');
define('ACTION_REJECT', 'REJECT');

// Error Messages
define('ERROR_NOT_FOUND', 'The requested resource was not found.');
define('ERROR_UNAUTHORIZED', 'You are not authorized to perform this action.');
define('ERROR_INVALID_INPUT', 'Invalid input provided.');
define('ERROR_DATABASE', 'A database error occurred.');
define('ERROR_SECURITY', 'A security violation was detected.');

// Success Messages
define('SUCCESS_CREATED', 'Record created successfully.');
define('SUCCESS_UPDATED', 'Record updated successfully.');
define('SUCCESS_DELETED', 'Record deleted successfully.');
define('SUCCESS_SAVED', 'Record saved successfully.');
?>