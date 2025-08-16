// AJAX Navigation System for Fast Page Loading
(function() {
    'use strict';
    
    // Configuration
    const config = {
        contentSelector: '#main-content',
        loaderSelector: '#page-loader',
        navLinksSelector: '.nav-link, .navbar-nav a',
        excludeSelectors: '.no-ajax, [target="_blank"], [download], .dropdown-toggle, [href="#"]',
        cacheExpiry: 300000, // 5 minutes
        enableCache: true
    };
    
    // Page cache
    const pageCache = new Map();
    
    // Create loader element
    function createLoader() {
        if (!document.querySelector(config.loaderSelector)) {
            const loader = document.createElement('div');
            loader.id = 'page-loader';
            loader.className = 'page-loader';
            loader./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>';
            loader.style.cssText = 'display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;';
            document.body.appendChild(loader);
        }
    }
    
    // Show/hide loader
    function showLoader() {
        const loader = document.querySelector(config.loaderSelector);
        if (loader) loader.style.display = 'block';
    }
    
    function hideLoader() {
        const loader = document.querySelector(config.loaderSelector);
        if (loader) loader.style.display = 'none';
    }
    
    // Load page via AJAX
    async function loadPage(url, pushState = true) {
        try {
            showLoader();
            
            // Check cache first
            if (config.enableCache && pageCache.has(url)) {
                const cached = pageCache.get(url);
                if (Date.now() - cached.timestamp < config.cacheExpiry) {
                    updateContent(cached.content, url, pushState);
                    hideLoader();
                    return;
                }
                pageCache.delete(url);
            }
            
            // Fetch page
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-AJAX-Request': 'true'
                }
            });
            
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            
            const html = await response.text();
            
            // Cache the content
            if (config.enableCache) {
                pageCache.set(url, {
                    content: html,
                    timestamp: Date.now()
                });
            }
            
            updateContent(html, url, pushState);
            
        } catch (error) {
            console.error('Error loading page:', error);
            // Fallback to normal navigation
            window.location.href = url;
        } finally {
            hideLoader();
        }
    }
    
    // Update page content
    function updateContent(html, url, pushState) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update main content
        const newContent = doc.querySelector(config.contentSelector);
        const currentContent = document.querySelector(config.contentSelector);
        
        if (newContent && currentContent) {
            // Fade out effect
            currentContent.style.opacity = '0';
            currentContent.style.transition = 'opacity 0.2s';
            
            setTimeout(() => {
                currentContent./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = newContent.innerHTML;
                currentContent.style.opacity = '1';
                
                // Reinitialize scripts and event listeners
                reinitializeScripts();
                attachAjaxLinks();
                
                // Update active nav items
                updateActiveNavItems(url);
                
                // Scroll to top
                window.scrollTo(0, 0);
            }, 200);
        }
        
        // Update page title
        const newTitle = doc.querySelector('title');
        if (newTitle) {
            document.title = newTitle.textContent;
        }
        
        // Update browser history
        if (pushState) {
            history.pushState({ url: url }, '', url);
        }
    }
    
    // Reinitialize page scripts
    function reinitializeScripts() {
        // Reinitialize Bootstrap components
        if (typeof bootstrap !== 'undefined') {
            // Tooltips
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
            
            // Popovers
            const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(el => new bootstrap.Popover(el));
        }
        
        // Reinitialize DataTables if present
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $('.datatable').DataTable();
        }
        
        // Trigger custom event for other scripts
        document.dispatchEvent(new Event('ajaxPageLoaded'));
    }
    
    // Update active navigation items
    function updateActiveNavItems(url) {
        // Remove all active classes
        document.querySelectorAll('.nav-link.active, .navbar-nav .active').forEach(el => {
            el.classList.remove('active');
        });
        
        // Add active class to current link
        const currentPath = new URL(url).pathname;
        document.querySelectorAll('a[href]').forEach(link => {
            const linkPath = new URL(link.href).pathname;
            if (linkPath === currentPath) {
                link.classList.add('active');
                // Also activate parent menu items
                let parent = link.closest('.nav-item, .dropdown');
                if (parent) {
                    parent.classList.add('active');
                }
            }
        });
    }
    
    // Attach AJAX handlers to links
    function attachAjaxLinks() {
        const links = document.querySelectorAll(config.navLinksSelector);
        
        links.forEach(link => {
            // Skip excluded links
            if (link.matches(config.excludeSelectors)) return;
            
            // Skip external links
            const linkUrl = new URL(link.href, window.location.origin);
            if (linkUrl.origin !== window.location.origin) return;
            
            // Remove existing listeners
            link.removeEventListener('click', handleLinkClick);
            
            // Add AJAX handler
            link.addEventListener('click', handleLinkClick);
            link.classList.add('ajax-enabled');
        });
    }
    
    // Handle link clicks
    function handleLinkClick(e) {
        e.preventDefault();
        const url = this.href;
        
        // Don't reload if already on the same page
        if (url === window.location.href) return;
        
        loadPage(url);
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.url) {
            loadPage(e.state.url, false);
        }
    });
    
    // Prefetch pages on hover
    function prefetchPage(url) {
        if (!config.enableCache || pageCache.has(url)) return;
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-AJAX-Request': 'true'
            }
        }).then(response => response.text()).then(html => {
            pageCache.set(url, {
                content: html,
                timestamp: Date.now()
            });
        }).catch(() => {});
    }
    
    // Add prefetch on hover
    document.addEventListener('mouseover', function(e) {
        const link = e.target.closest('a');
        if (link && link.classList.contains('ajax-enabled')) {
            prefetchPage(link.href);
        }
    });
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        createLoader();
        attachAjaxLinks();
        
        // Set initial state
        history.replaceState({ url: window.location.href }, '', window.location.href);
    }
    
    // Public API
    window.AjaxNav = {
        loadPage: loadPage,
        clearCache: () => pageCache.clear(),
        prefetch: prefetchPage
    };
})();