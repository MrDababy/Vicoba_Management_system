<?php
/**
 * Member Controller
 * 
 * Handles all member management operations including CRUD,
 * search, pagination, and profile picture upload.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\Member;
use App\Helpers\FileUpload;
use App\Helpers\ActivityLogger;
use App\Exceptions\ValidationException;

class MemberController extends BaseController
{
    /**
     * @var Member Member model instance
     */
    private Member $memberModel;

    /**
     * @var FileUpload File upload helper
     */
    private FileUpload $fileUpload;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->memberModel = new Member();
        $this->fileUpload = new FileUpload();
        
        // Require authentication for all member operations
        $this->requireAuth();
        
        // Only admins, treasurers, and secretaries can manage members
        $this->requireRole(['Admin', 'Treasurer', 'Secretary']);
    }

    /**
     * List all members with search and pagination
     * 
     * @return void
     */
    public function index(): void
    {
        // Get page number
        $page = (int)$this->input('page', 1);
        $page = max(1, $page);
        
        // Get per page
        $perPage = (int)$this->input('per_page', 20);
        $perPage = min(100, max(5, $perPage));
        
        // Build filters
        $filters = [
            'search' => $this->input('search'),
            'status' => $this->input('status'),
            'gender' => $this->input('gender'),
            'joining_date_from' => $this->input('joining_date_from'),
            'joining_date_to' => $this->input('joining_date_to')
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get members
        $result = $this->memberModel->getPaginated($page, $perPage, $filters);
        
        // Get statistics for dashboard
        $stats = [
            'total' => $result['total'],
            'active' => $this->memberModel->getActiveCount(),
            'gender' => $this->memberModel->getGenderStats()
        ];
        
        $data = [
            'title' => 'Members - ' . APP_NAME,
            'members' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'stats' => $stats,
            'per_page' => $perPage,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('members.index', $data, 'main');
    }

    /**
     * Show create member form
     * 
     * @return void
     */
    public function create(): void
    {
        // Check permissions
        if (!$this->auth->hasRole(['Admin', 'Treasurer', 'Secretary'])) {
            $this->session->flash('error', 'You do not have permission to add members.');
            $this->redirect('/members');
            return;
        }
        
        $data = [
            'title' => 'Add Member - ' . APP_NAME,
            'member' => null,
            'is_edit' => false,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('members.create', $data, 'main');
    }

    /**
     * Store a new member
     * 
     * @return void
     */
    public function store(): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer', 'Secretary'])) {
                throw new \Exception('You do not have permission to add members.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get form data
            $data = [
                'full_name' => $this->input('full_name'),
                'gender' => $this->input('gender'),
                'date_of_birth' => $this->input('date_of_birth'),
                'national_id' => $this->input('national_id'),
                'phone' => $this->input('phone'),
                'email' => $this->input('email'),
                'address' => $this->input('address'),
                'occupation' => $this->input('occupation'),
                'joining_date' => $this->input('joining_date'),
                'status' => $this->input('status', 'Active'),
                'created_by' => $this->getUserId()
            ];
            
            // Validate input
            $this->validateMemberData($data);
            
            // Handle profile picture upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->fileUpload->uploadProfilePicture($_FILES['profile_pic']);
                if ($uploadResult['success']) {
                    $data['profile_pic'] = $uploadResult['filename'];
                } else {
                    throw new \Exception($uploadResult['message']);
                }
            }
            
            // Create member
            $memberId = $this->memberModel->create($data);
            
            if ($memberId) {
                // Log activity
                ActivityLogger::log(
                    'CREATE',
                    'members',
                    $memberId,
                    "New member created: {$data['full_name']}",
                    null,
                    $data
                );
                
                $this->session->flash('success', 'Member added successfully!');
                $this->redirect('/members/' . $memberId);
            } else {
                throw new \Exception('Failed to create member. Please try again.');
            }
            
        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/members/create');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/members/create');
        }
    }

    /**
     * Show member details
     * 
     * @param int $id Member ID
     * @return void
     */
    public function view(int $id): void
    {
        $member = $this->memberModel->getMember($id);
        
        if (!$member) {
            $this->session->flash('error', 'Member not found.');
            $this->redirect('/members');
            return;
        }
        
        // Get member statistics
        $stats = $this->getMemberStats($id);
        
        $data = [
            'title' => 'Member Details - ' . APP_NAME,
            'member' => $member,
            'stats' => $stats,
            'can_edit' => $this->auth->hasRole(['Admin', 'Treasurer', 'Secretary']),
            'can_delete' => $this->auth->isAdmin(),
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('members.view', $data, 'main');
    }

    /**
     * Show edit member form
     * 
     * @param int $id Member ID
     * @return void
     */
    public function edit(int $id): void
    {
        // Check permissions
        if (!$this->auth->hasRole(['Admin', 'Treasurer', 'Secretary'])) {
            $this->session->flash('error', 'You do not have permission to edit members.');
            $this->redirect('/members');
            return;
        }
        
        $member = $this->memberModel->getMember($id);
        
        if (!$member) {
            $this->session->flash('error', 'Member not found.');
            $this->redirect('/members');
            return;
        }
        
        $data = [
            'title' => 'Edit Member - ' . APP_NAME,
            'member' => $member,
            'is_edit' => true,
            'csrf_token' => $this->csrfToken()
        ];
        
        $this->render('members.edit', $data, 'main');
    }

    /**
     * Update a member
     * 
     * @param int $id Member ID
     * @return void
     */
    public function update(int $id): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer', 'Secretary'])) {
                throw new \Exception('You do not have permission to edit members.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get current member data
            $currentMember = $this->memberModel->getMember($id);
            if (!$currentMember) {
                throw new \Exception('Member not found.');
            }
            
            // Get form data
            $data = [
                'full_name' => $this->input('full_name'),
                'gender' => $this->input('gender'),
                'date_of_birth' => $this->input('date_of_birth'),
                'national_id' => $this->input('national_id'),
                'phone' => $this->input('phone'),
                'email' => $this->input('email'),
                'address' => $this->input('address'),
                'occupation' => $this->input('occupation'),
                'joining_date' => $this->input('joining_date'),
                'status' => $this->input('status', 'Active')
            ];
            
            // Validate input
            $this->validateMemberData($data, true);
            
            // Handle profile picture upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->fileUpload->uploadProfilePicture($_FILES['profile_pic']);
                if ($uploadResult['success']) {
                    $data['profile_pic'] = $uploadResult['filename'];
                } else {
                    throw new \Exception($uploadResult['message']);
                }
            }
            
            // Get before data for logging
            $beforeData = $currentMember;
            
            // Update member
            if ($this->memberModel->update($id, $data)) {
                // Log activity
                ActivityLogger::log(
                    'UPDATE',
                    'members',
                    $id,
                    "Member updated: {$data['full_name']}",
                    $beforeData,
                    $data
                );
                
                $this->session->flash('success', 'Member updated successfully!');
                $this->redirect('/members/' . $id);
            } else {
                throw new \Exception('Failed to update member. Please try again.');
            }
            
        } catch (ValidationException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/members/' . $id . '/edit');
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/members/' . $id . '/edit');
        }
    }

    /**
     * Delete a member
     * 
     * @param int $id Member ID
     * @return void
     */
    public function delete(int $id): void
    {
        try {
            // Only admins can delete
            if (!$this->auth->isAdmin()) {
                throw new \Exception('Only administrators can delete members.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            // Get member data for logging
            $member = $this->memberModel->getMember($id);
            if (!$member) {
                throw new \Exception('Member not found.');
            }
            
            // Delete member
            if ($this->memberModel->delete($id)) {
                // Log activity
                ActivityLogger::log(
                    'DELETE',
                    'members',
                    $id,
                    "Member deleted: {$member['full_name']}",
                    $member
                );
                
                $this->session->flash('success', 'Member deleted successfully!');
            } else {
                throw new \Exception('Failed to delete member. Please try again.');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
        }
        
        $this->redirect('/members');
    }

    /**
     * Deactivate a member
     * 
     * @param int $id Member ID
     * @return void
     */
    public function deactivate(int $id): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer', 'Secretary'])) {
                throw new \Exception('You do not have permission to deactivate members.');
            }
            
            // Verify CSRF token
            if (!$this->verifyCsrfToken($this->input('csrf_token'))) {
                throw new \Exception('Invalid security token. Please try again.');
            }
            
            $member = $this->memberModel->getMember($id);
            if (!$member) {
                throw new \Exception('Member not found.');
            }
            
            $reason = $this->input('reason', 'No reason provided');
            
            if ($this->memberModel->deactivate($id, $reason)) {
                $this->session->flash('success', 'Member deactivated successfully!');
            } else {
                throw new \Exception('Failed to deactivate member.');
            }
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
        }
        
        $this->redirect('/members');
    }

    /**
     * Export members to CSV
     * 
     * @return void
     */
    public function export(): void
    {
        try {
            // Check permissions
            if (!$this->auth->hasRole(['Admin', 'Treasurer'])) {
                throw new \Exception('You do not have permission to export members.');
            }
            
            // Get filters
            $filters = [
                'status' => $this->input('status'),
                'gender' => $this->input('gender')
            ];
            $filters = array_filter($filters);
            
            // Get all members
            $result = $this->memberModel->getPaginated(1, 9999, $filters);
            $members = $result['data'];
            
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="members_' . date('Y-m-d') . '.csv"');
            
            // Create output stream
            $output = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Write headers
            fputcsv($output, [
                'Member No',
                'Full Name',
                'Gender',
                'Date of Birth',
                'National ID',
                'Phone',
                'Email',
                'Address',
                'Occupation',
                'Joining Date',
                'Status'
            ]);
            
            // Write data
            foreach ($members as $member) {
                fputcsv($output, [
                    $member['member_no'],
                    $member['full_name'],
                    $member['gender'],
                    $member['date_of_birth'],
                    $member['national_id'],
                    $member['phone'],
                    $member['email'],
                    $member['address'],
                    $member['occupation'],
                    $member['joining_date'],
                    $member['status']
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/members');
        }
    }

    /**
     * Get member statistics
     * 
     * @param int $id Member ID
     * @return array
     */
    private function getMemberStats(int $id): array
    {
        $stats = [];
        
        // Get savings total
        $sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END) as total_deposits,
                    SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END) as total_withdrawals
                FROM savings 
                WHERE member_id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $savings = $stmt->fetch();
        $stats['savings'] = [
            'total_deposits' => (float)($savings['total_deposits'] ?? 0),
            'total_withdrawals' => (float)($savings['total_withdrawals'] ?? 0),
            'balance' => (float)($savings['total_deposits'] ?? 0) - (float)($savings['total_withdrawals'] ?? 0)
        ];
        
        // Get loans count
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                FROM loans 
                WHERE member_id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $loans = $stmt->fetch();
        $stats['loans'] = [
            'total' => (int)($loans['total'] ?? 0),
            'active' => (int)($loans['active'] ?? 0),
            'pending' => (int)($loans['pending'] ?? 0),
            'completed' => (int)($loans['completed'] ?? 0)
        ];
        
        // Get total loan amount
        $sql = "SELECT SUM(amount) as total_amount FROM loans WHERE member_id = ? AND status NOT IN ('Rejected', 'Defaulted')";
        $stmt = $this->db->query($sql, [$id]);
        $amount = $stmt->fetch();
        $stats['loans']['total_amount'] = (float)($amount['total_amount'] ?? 0);
        
        // Get fines
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Pending' OR status = 'Partially_Paid' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid
                FROM fines 
                WHERE member_id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $fines = $stmt->fetch();
        $stats['fines'] = [
            'total' => (int)($fines['total'] ?? 0),
            'pending' => (int)($fines['pending'] ?? 0),
            'paid' => (int)($fines['paid'] ?? 0)
        ];
        
        // Get fine amount
        $sql = "SELECT SUM(amount) as total_amount FROM fines WHERE member_id = ? AND status IN ('Pending', 'Partially_Paid')";
        $stmt = $this->db->query($sql, [$id]);
        $fineAmount = $stmt->fetch();
        $stats['fines']['pending_amount'] = (float)($fineAmount['total_amount'] ?? 0);
        
        // Get dividends
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(interest_earned) as total_earned,
                    SUM(CASE WHEN status = 'Paid' THEN interest_earned ELSE 0 END) as paid_earned
                FROM dividends 
                WHERE member_id = ?";
        $stmt = $this->db->query($sql, [$id]);
        $dividends = $stmt->fetch();
        $stats['dividends'] = [
            'total' => (int)($dividends['total'] ?? 0),
            'total_earned' => (float)($dividends['total_earned'] ?? 0),
            'paid_earned' => (float)($dividends['paid_earned'] ?? 0)
        ];
        
        return $stats;
    }

    /**
     * Validate member data
     * 
     * @param array $data Member data
     * @param bool $isUpdate Whether this is an update
     * @throws ValidationException
     */
    private function validateMemberData(array $data, bool $isUpdate = false): void
    {
        $errors = [];
        
        // Full name
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required.';
        } elseif (strlen($data['full_name']) < 3) {
            $errors['full_name'] = 'Full name must be at least 3 characters.';
        } elseif (strlen($data['full_name']) > 100) {
            $errors['full_name'] = 'Full name must not exceed 100 characters.';
        }
        
        // Gender
        if (empty($data['gender'])) {
            $errors['gender'] = 'Gender is required.';
        } elseif (!in_array($data['gender'], ['Male', 'Female', 'Other'])) {
            $errors['gender'] = 'Invalid gender selection.';
        }
        
        // Date of birth
        if (empty($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Date of birth is required.';
        } else {
            $dob = strtotime($data['date_of_birth']);
            if ($dob === false) {
                $errors['date_of_birth'] = 'Invalid date format.';
            } elseif ($dob > time()) {
                $errors['date_of_birth'] = 'Date of birth cannot be in the future.';
            }
        }
        
        // National ID
        if (empty($data['national_id'])) {
            $errors['national_id'] = 'National ID is required.';
        } elseif (!preg_match('/^\d{12,14}$/', $data['national_id'])) {
            $errors['national_id'] = 'National ID must be 12-14 digits.';
        }
        
        // Phone
        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone number is required.';
        } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format. Use 07XXXXXXXX or +2557XXXXXXXX.';
        }
        
        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }
        
        // Joining date
        if (empty($data['joining_date'])) {
            $errors['joining_date'] = 'Joining date is required.';
        } else {
            $joinDate = strtotime($data['joining_date']);
            if ($joinDate === false) {
                $errors['joining_date'] = 'Invalid date format.';
            }
        }
        
        // Status
        if (!empty($data['status']) && !in_array($data['status'], ['Active', 'Inactive', 'Suspended', 'Defaulted'])) {
            $errors['status'] = 'Invalid status selection.';
        }
        
        // Throw exception if there are errors
        if (!empty($errors)) {
            $firstError = reset($errors);
            throw new ValidationException($firstError, $errors);
        }
    }
}