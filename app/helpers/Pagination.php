<?php
/**
 * Pagination Helper
 * 
 * Generates HTML pagination links with Bootstrap styling.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class Pagination
{
    /**
     * Generate pagination HTML
     * 
     * @param array $pagination Pagination data from model
     * @param string $baseUrl Base URL for pagination links
     * @param array $queryParams Additional query parameters
     * @return string
     */
    public static function render(array $pagination, string $baseUrl = '', array $queryParams = []): string
    {
        if ($pagination['total_pages'] <= 1) {
            return '';
        }

        $currentPage = $pagination['page'];
        $totalPages = $pagination['total_pages'];
        $perPage = $pagination['per_page'] ?? 20;

        // Build query string
        $queryParams['per_page'] = $perPage;
        $queryString = http_build_query($queryParams);

        $html = '<nav aria-label="Page navigation">';
        $html .= '<ul class="pagination justify-content-center flex-wrap">';

        // Previous button
        if ($currentPage > 1) {
            $html .= self::pageItem($baseUrl, $currentPage - 1, $queryString, 'Previous');
        } else {
            $html .= self::pageItemDisabled('Previous');
        }

        // Page numbers
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);

        if ($startPage > 1) {
            $html .= self::pageItem($baseUrl, 1, $queryString, '1');
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i === $currentPage) {
                $html .= self::pageItemActive($i);
            } else {
                $html .= self::pageItem($baseUrl, $i, $queryString, $i);
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= self::pageItem($baseUrl, $totalPages, $queryString, $totalPages);
        }

        // Next button
        if ($currentPage < $totalPages) {
            $html .= self::pageItem($baseUrl, $currentPage + 1, $queryString, 'Next');
        } else {
            $html .= self::pageItemDisabled('Next');
        }

        $html .= '</ul>';
        $html .= '<div class="text-center text-muted small">';
        $html .= 'Showing ' . number_format($pagination['from'] ?? 0) . ' to ' .
                 number_format($pagination['to'] ?? 0) . ' of ' .
                 number_format($pagination['total'] ?? 0) . ' entries';
        $html .= '</div>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Generate a page item
     * 
     * @param string $baseUrl Base URL
     * @param int $page Page number
     * @param string $queryString Query string
     * @param string $label Link label
     * @return string
     */
    private static function pageItem(string $baseUrl, int $page, string $queryString, string $label): string
    {
        $url = $baseUrl . '?page=' . $page;
        if (!empty($queryString)) {
            $url .= '&' . $queryString;
        }

        $label = htmlspecialchars($label);
        return '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($url) . '">' . $label . '</a></li>';
    }

    /**
     * Generate an active page item
     * 
     * @param int $page Page number
     * @return string
     */
    private static function pageItemActive(int $page): string
    {
        return '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
    }

    /**
     * Generate a disabled page item
     * 
     * @param string $label Link label
     * @return string
     */
    private static function pageItemDisabled(string $label): string
    {
        return '<li class="page-item disabled"><span class="page-link">' . htmlspecialchars($label) . '</span></li>';
    }

    /**
     * Generate pagination for AJAX
     * 
     * @param array $pagination Pagination data
     * @return array
     */
    public static function getAjaxData(array $pagination): array
    {
        return [
            'current_page' => $pagination['page'],
            'total_pages' => $pagination['total_pages'],
            'total_items' => $pagination['total'],
            'per_page' => $pagination['per_page'],
            'from' => $pagination['from'] ?? 0,
            'to' => $pagination['to'] ?? 0
        ];
    }
}