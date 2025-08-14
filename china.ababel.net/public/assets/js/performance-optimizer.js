// تحسينات الأداء المضافة
const DOMCache = new Map();
function cachedQuerySelector(selector) {
    if (!DOMCache.has(selector)) {
        DOMCache.set(selector, document.querySelector(selector));
    }
    return DOMCache.get(selector);
}
// Performance Optimization Module
(function() {
    'use strict';
    
    // Debounce function for event handlers
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Throttle function for scroll/resize events
    function throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // Request idle callback polyfill
    window.requestIdleCallback = window.requestIdleCallback || function(cb) {
        const start = Date.now();
        return setTimeout(function() {
            cb({
                didTimeout: false,
                timeRemaining: function() {
                    return Math.max(0, 50 - (Date.now() - start));
                }
            });
        }, 1);
    };
    
    // Optimize table rendering
    function optimizeTables() {
        const tables = document.querySelectorAll('table.table');
        
        tables.forEach(table => {
            // Add virtual scrolling for large tables
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 100) {
                addVirtualScrolling(table);
            }
            
            // Add search functionality
            if (!table.dataset.searchAdded) {
                addTableSearch(table);
                table.dataset.searchAdded = 'true';
            }
            
            // Add sorting functionality
            if (!table.dataset.sortAdded) {
                addTableSorting(table);
                table.dataset.sortAdded = 'true';
            }
        });
    }
    
    // Virtual scrolling for large tables
    function addVirtualScrolling(table) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const rowHeight = 40; // Approximate row height
        const visibleRows = 20; // Number of visible rows
        
        // Create scroll container
        const container = document.createElement('div');
        container.className = 'virtual-scroll-container';
        container.style.height = (visibleRows * rowHeight) + 'px';
        container.style.overflowY = 'auto';
        container.style.position = 'relative';
        
        // Create viewport
        const viewport = document.createElement('div');
        viewport.style.height = (rows.length * rowHeight) + 'px';
        
        table.parentNode.insertBefore(container, table);
        container.appendChild(table);
        container.appendChild(viewport);
        
        let startIndex = 0;
        
        function renderVisibleRows() {
            const endIndex = Math.min(startIndex + visibleRows, rows.length);
            
            // Clear tbody
            tbody./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '';
            
            // Add visible rows
            for (let i = startIndex; i < endIndex; i++) {
                tbody.appendChild(rows[i]);
            }
            
            // Update table position
            table.style.transform = `translateY(${startIndex * rowHeight}px)`;
        }
        
        container.addEventListener('scroll', throttle(() => {
            startIndex = Math.floor(container.scrollTop / rowHeight);
            renderVisibleRows();
        }, 16));
        
        renderVisibleRows();
    }
    
    // Add search to tables
    function addTableSearch(table) {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search mb-3';
        searchContainer./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = `
            <input type="text" class="form-control" placeholder="Search in table..." />
        `;
        
        table.parentNode.insertBefore(searchContainer, table);
        
        const searchInput = searchContainer.querySelector('input');
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        
        searchInput.addEventListener('input', debounce(function() {
            const searchTerm = this.value.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }, 300));
    }
    
    // Add sorting to tables
    function addTableSorting(table) {
        const headers = table.querySelectorAll('thead th');
        const tbody = table.querySelector('tbody');
        
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            
            let sortDirection = 0; // 0: none, 1: asc, -1: desc
            
            header.addEventListener('click', function() {
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                // Toggle sort direction
                sortDirection = sortDirection === 1 ? -1 : 1;
                
                // Update header indicators
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                this.classList.add(sortDirection === 1 ? 'sort-asc' : 'sort-desc');
                
                // Sort rows
                rows.sort((a, b) => {
                    const aValue = a.cells[index].textContent.trim();
                    const bValue = b.cells[index].textContent.trim();
                    
                    // Try to parse as number
                    const aNum = parseFloat(aValue);
                    const bNum = parseFloat(bValue);
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return (aNum - bNum) * sortDirection;
                    }
                    
                    // Sort as string
                    return aValue.localeCompare(bValue) * sortDirection;
                });
                
                // Reorder rows
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    }
    
    // Optimize forms
    function optimizeForms() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            // Add input validation feedback
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                // Debounce validation
                input.addEventListener('input', debounce(() => {
                    if (input.checkValidity()) {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    } else {
                        input.classList.remove('is-valid');
                        input.classList.add('is-invalid');
                    }
                }, 500));
            });
            
            // Prevent double submission
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return false;
                }
                
                form.dataset.submitting = 'true';
                
                // Show loading state
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                }
                
                // Reset after 10 seconds (timeout)
                setTimeout(() => {
                    form.dataset.submitting = 'false';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = submitBtn.dataset.originalText || 'Submit';
                    }
                }, 10000);
            });
        });
    }
    
    // Preload critical resources
    function preloadResources() {
        const criticalResources = [
            '/assets/css/style.css',
            '/assets/js/main.js',
            '/assets/images/logo.png'
        ];
        
        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = resource;
            
            if (resource.endsWith('.css')) {
                link.as = 'style';
            } else if (resource.endsWith('.js')) {
                link.as = 'script';
            } else if (resource.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                link.as = 'image';
            }
            
            document.head.appendChild(link);
        });
    }
    
    // Memory management
    function cleanupMemory() {
        // Clear old cache entries
        if (window.AjaxNav) {
            const cacheSize = localStorage.length;
            if (cacheSize > 50) {
                // Clear oldest entries
                const keys = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith('cache_')) {
                        keys.push(key);
                    }
                }
                
                // Remove oldest half
                keys.slice(0, Math.floor(keys.length / 2)).forEach(key => {
                    localStorage.removeItem(key);
                });
            }
        }
        
        // Remove detached DOM nodes
        const detachedNodes = document.querySelectorAll('.to-be-removed');
        detachedNodes.forEach(node => node.remove());
    }
    
    // Performance monitoring
    function monitorPerformance() {
        if ('PerformanceObserver' in window) {
            // Monitor long tasks
            try {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.duration > 50) {
                            console.warn('Long task detected:', entry);
                        }
                    }
                });
                observer.observe({ entryTypes: ['longtask'] });
            } catch (e) {
                // Longtask observer not supported
            }
            
            // Monitor resource timing
            const resourceObserver = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.duration > 1000) {
                        console.warn('Slow resource:', entry.name, entry.duration + 'ms');
                    }
                }
            });
            resourceObserver.observe({ entryTypes: ['resource'] });
        }
    }
    
    // Initialize optimizations
    function init() {
        // Run optimizations
        requestIdleCallback(() => {
            optimizeTables();
            optimizeForms();
            preloadResources();
            monitorPerformance();
        });
        
        // Periodic cleanup
        setInterval(() => {
            requestIdleCallback(cleanupMemory);
        }, 60000); // Every minute
    }
    
    // Listen for page changes
    document.addEventListener('ajaxPageLoaded', () => {
        requestIdleCallback(() => {
            optimizeTables();
            optimizeForms();
        });
    });
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Public API
    window.PerformanceOptimizer = {
        debounce: debounce,
        throttle: throttle,
        optimizeTables: optimizeTables,
        optimizeForms: optimizeForms,
        cleanupMemory: cleanupMemory
    };
})();