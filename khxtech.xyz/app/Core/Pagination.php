<?php
namespace App\Core;

class Pagination
{
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $totalPages;
    private $offset;
    private $range = 3; // Number of pages to show on each side of current page
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1)
    {
        $this->totalItems = (int) $totalItems;
        $this->itemsPerPage = (int) $itemsPerPage;
        $this->currentPage = (int) max(1, $currentPage);
        $this->totalPages = (int) ceil($this->totalItems / $this->itemsPerPage);
        $this->currentPage = min($this->currentPage, $this->totalPages);
        $this->offset = ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    public function getOffset()
    {
        return $this->offset;
    }
    
    public function getLimit()
    {
        return $this->itemsPerPage;
    }
    
    public function getCurrentPage()
    {
        return $this->currentPage;
    }
    
    public function getTotalPages()
    {
        return $this->totalPages;
    }
    
    public function getTotalItems()
    {
        return $this->totalItems;
    }
    
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }
    
    public function hasPages()
    {
        return $this->totalPages > 1;
    }
    
    public function hasPreviousPage()
    {
        return $this->currentPage > 1;
    }
    
    public function hasNextPage()
    {
        return $this->currentPage < $this->totalPages;
    }
    
    public function getPreviousPage()
    {
        return max(1, $this->currentPage - 1);
    }
    
    public function getNextPage()
    {
        return min($this->totalPages, $this->currentPage + 1);
    }
    
    public function getPageUrl($page, $baseUrl = '')
    {
        $params = $_GET;
        $params['page'] = $page;
        
        if (isset($params['per_page'])) {
            $params['per_page'] = $this->itemsPerPage;
        }
        
        $queryString = http_build_query($params);
        return $baseUrl . '?' . $queryString;
    }
    
    public function getPages()
    {
        $pages = [];
        
        // Always include first page
        if ($this->currentPage > ($this->range + 2)) {
            $pages[] = 1;
            $pages[] = '...';
        }
        
        // Calculate start and end of range
        $start = max(1, $this->currentPage - $this->range);
        $end = min($this->totalPages, $this->currentPage + $this->range);
        
        // Add pages in range
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        // Always include last page
        if ($this->currentPage < ($this->totalPages - $this->range - 1)) {
            $pages[] = '...';
            $pages[] = $this->totalPages;
        }
        
        return $pages;
    }
    
    public function renderBootstrap5($baseUrl = '', $ajaxEnabled = true)
    {
        if (!$this->hasPages()) {
            return '';
        }
        
        $ajaxClass = $ajaxEnabled ? 'ajax-pagination' : '';
        
        $html = '<nav aria-label="Page navigation">';
        $html .= '<ul class="pagination justify-content-center ' . $ajaxClass . '">';
        
        // Previous button
        if ($this->hasPreviousPage()) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->getPageUrl($this->getPreviousPage(), $baseUrl) . '" aria-label="Previous">';
            $html .= '<span aria-hidden="true">&laquo;</span>';
            $html .= '</a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&laquo;</span>';
            $html .= '</li>';
        }
        
        // Page numbers
        foreach ($this->getPages() as $page) {
            if ($page === '...') {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            } elseif ($page == $this->currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
            } else {
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="' . $this->getPageUrl($page, $baseUrl) . '">' . $page . '</a>';
                $html .= '</li>';
            }
        }
        
        // Next button
        if ($this->hasNextPage()) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->getPageUrl($this->getNextPage(), $baseUrl) . '" aria-label="Next">';
            $html .= '<span aria-hidden="true">&raquo;</span>';
            $html .= '</a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&raquo;</span>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        // Add info text
        $start = $this->offset + 1;
        $end = min($this->offset + $this->itemsPerPage, $this->totalItems);
        $html .= '<div class="text-center text-muted mt-2">';
        $html .= sprintf('Showing %d to %d of %d entries', $start, $end, $this->totalItems);
        $html .= '</div>';
        
        $html .= '</nav>';
        
        return $html;
    }
    
    public function toArray()
    {
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'total_items' => $this->totalItems,
            'items_per_page' => $this->itemsPerPage,
            'offset' => $this->offset,
            'has_previous' => $this->hasPreviousPage(),
            'has_next' => $this->hasNextPage(),
            'previous_page' => $this->getPreviousPage(),
            'next_page' => $this->getNextPage()
        ];
    }
}