// Navigation Performance Improvements
(function() {
    'use strict';

    // Enhanced navigation with loading states
    function enhanceNavigation() {
        // Add loading indicator to nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            if (!link.matches('.dropdown-toggle, [href="#"], .no-ajax')) {
                link.addEventListener('click', function(e) {
                    // Add loading class
                    this.classList.add('loading');
                    
                    // Create loading spinner
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm me-2';
                    spinner.style.display = 'inline-block';
                    
                    // Insert spinner before text
                    this.insertBefore(spinner, this.firstChild);
                    
                    // Remove loading state after delay
                    setTimeout(() => {
                        this.classList.remove('loading');
                        if (spinner.parentNode) {
                            spinner.remove();
                        }
                    }, 2000);
                });
            }
        });
    }

    // Smooth scrolling for internal links
    function addSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Keyboard navigation support
    function addKeyboardSupport() {
        let focusedIndex = -1;
        const navLinks = Array.from(document.querySelectorAll('.nav-link'));

        document.addEventListener('keydown', function(e) {
            // Alt + Number keys for quick navigation
            if (e.altKey && e.key >= '1' && e.key <= '9') {
                e.preventDefault();
                const index = parseInt(e.key) - 1;
                if (navLinks[index]) {
                    navLinks[index].click();
                }
            }

            // Arrow keys for nav menu navigation
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (document.activeElement.classList.contains('nav-link')) {
                    e.preventDefault();
                    
                    if (e.key === 'ArrowDown') {
                        focusedIndex = (focusedIndex + 1) % navLinks.length;
                    } else {
                        focusedIndex = focusedIndex <= 0 ? navLinks.length - 1 : focusedIndex - 1;
                    }
                    
                    navLinks[focusedIndex].focus();
                }
            }
        });
    }

    // Progress indicator for page loads
    function addProgressIndicator() {
        const progressBar = document.createElement('div');
        progressBar.className = 'navigation-progress';
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #0056b3);
            z-index: 10000;
            transition: width 0.3s ease;
            display: none;
        `;
        document.body.appendChild(progressBar);

        // Show progress on AJAX requests
        document.addEventListener('ajaxStart', function() {
            progressBar.style.display = 'block';
            progressBar.style.width = '30%';
        });

        document.addEventListener('ajaxProgress', function() {
            progressBar.style.width = '70%';
        });

        document.addEventListener('ajaxComplete', function() {
            progressBar.style.width = '100%';
            setTimeout(() => {
                progressBar.style.display = 'none';
                progressBar.style.width = '0%';
            }, 300);
        });
    }

    // Breadcrumb navigation
    function updateBreadcrumbs() {
        const breadcrumbContainer = document.querySelector('.breadcrumb');
        if (!breadcrumbContainer) return;

        const currentPath = window.location.pathname;
        const pathSegments = currentPath.split('/').filter(segment => segment);
        
        breadcrumbContainer./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '';
        
        // Add home
        const homeItem = document.createElement('li');
        homeItem.className = 'breadcrumb-item';
        homeItem./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '<a href="/">الرئيسية</a>';
        breadcrumbContainer.appendChild(homeItem);

        // Add path segments
        pathSegments.forEach((segment, index) => {
            const item = document.createElement('li');
            item.className = index === pathSegments.length - 1 ? 'breadcrumb-item active' : 'breadcrumb-item';
            
            const fullPath = '/' + pathSegments.slice(0, index + 1).join('/');
            const segmentName = getSegmentName(segment);
            
            if (index === pathSegments.length - 1) {
                item.textContent = segmentName;
            } else {
                item./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = `<a href="${fullPath}">${segmentName}</a>`;
            }
            
            breadcrumbContainer.appendChild(item);
        });
    }

    function getSegmentName(segment) {
        const segmentNames = {
            'dashboard': 'لوحة التحكم',
            'clients': 'العملاء',
            'transactions': 'المعاملات',
            'loadings': 'الشحنات',
            'cashbox': 'الخزينة',
            'reports': 'التقارير',
            'settings': 'الإعدادات',
            'create': 'إنشاء جديد',
            'edit': 'تعديل'
        };
        
        return segmentNames[segment] || segment;
    }

    // Active menu highlighting
    function updateActiveMenu() {
        const currentPath = window.location.pathname;
        
        // Remove all active classes
        document.querySelectorAll('.nav-link.active').forEach(link => {
            link.classList.remove('active');
        });

        // Find and activate current menu item
        document.querySelectorAll('.nav-link').forEach(link => {
            const linkPath = new URL(link.href, window.location.origin).pathname;
            
            if (linkPath === currentPath || 
                (currentPath.startsWith(linkPath) && linkPath !== '/')) {
                link.classList.add('active');
                
                // Also activate parent dropdown
                const dropdown = link.closest('.dropdown');
                if (dropdown) {
                    const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
                    if (dropdownToggle) {
                        dropdownToggle.classList.add('active');
                    }
                }
            }
        });
    }

    // Initialize all enhancements
    function init() {
        enhanceNavigation();
        addSmoothScrolling();
        addKeyboardSupport();
        addProgressIndicator();
        updateActiveMenu();
        
        // Update on page changes
        window.addEventListener('popstate', updateActiveMenu);
        document.addEventListener('ajaxPageLoaded', updateActiveMenu);
        document.addEventListener('ajaxPageLoaded', updateBreadcrumbs);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    window.NavigationEnhancements = {
        updateActiveMenu: updateActiveMenu,
        updateBreadcrumbs: updateBreadcrumbs
    };

})();