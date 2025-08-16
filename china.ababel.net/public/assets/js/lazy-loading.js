// Lazy Loading System for Images and Content
(function() {
    'use strict';
    
    // Configuration
    const config = {
        rootMargin: '50px 0px',
        threshold: 0.01,
        loadedClass: 'lazy-loaded',
        loadingClass: 'lazy-loading',
        errorClass: 'lazy-error'
    };
    
    // Check if IntersectionObserver is supported
    if (!('IntersectionObserver' in window)) {
        // Fallback: load all images immediately
        loadAllImages();
        return;
    }
    
    // Create intersection observer
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadImage(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, config);
    
    // Create intersection observer for content
    const contentObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadContent(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, config);
    
    // Load image
    function loadImage(img) {
        const src = img.dataset.src;
        const srcset = img.dataset.srcset;
        
        if (!src) return;
        
        // Add loading class
        img.classList.add(config.loadingClass);
        
        // Create new image to preload
        const newImg = new Image();
        
        newImg.onload = function() {
            img.src = src;
            if (srcset) {
                img.srcset = srcset;
            }
            img.classList.remove(config.loadingClass);
            img.classList.add(config.loadedClass);
            
            // Fade in effect
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                img.style.opacity = '1';
            }, 10);
        };
        
        newImg.onerror = function() {
            img.classList.remove(config.loadingClass);
            img.classList.add(config.errorClass);
            
            // Use placeholder image
            if (img.dataset.placeholder) {
                img.src = img.dataset.placeholder;
            }
        };
        
        newImg.src = src;
    }
    
    // Load content via AJAX
    async function loadContent(element) {
        const url = element.dataset.lazyLoad;
        
        if (!url) return;
        
        element.classList.add(config.loadingClass);
        
        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) throw new Error('Failed to load content');
            
            const content = await response.text();
            element./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = content;
            element.classList.remove(config.loadingClass);
            element.classList.add(config.loadedClass);
            
            // Trigger event for newly loaded content
            element.dispatchEvent(new Event('lazyloaded'));
            
        } catch (error) {
            console.error('Error loading content:', error);
            element.classList.remove(config.loadingClass);
            element.classList.add(config.errorClass);
            element./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = '<p>Failed to load content</p>';
        }
    }
    
    // Load all images (fallback)
    function loadAllImages() {
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            img.src = img.dataset.src;
            if (img.dataset.srcset) {
                img.srcset = img.dataset.srcset;
            }
        });
    }
    
    // Initialize lazy loading
    function init() {
        // Observe images
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => imageObserver.observe(img));
        
        // Observe content containers
        const containers = document.querySelectorAll('[data-lazy-load]');
        containers.forEach(container => contentObserver.observe(container));
        
        // Add loading placeholders
        addPlaceholders();
    }
    
    // Add loading placeholders
    function addPlaceholders() {
        const images = document.querySelectorAll('img[data-src]:not(.lazy-loaded)');
        images.forEach(img => {
            if (!img.src) {
                // Create SVG placeholder
                const width = img.width || 100;
                const height = img.height || 100;
                const placeholder = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${width} ${height}'%3E%3Crect width='${width}' height='${height}' fill='%23f0f0f0'/%3E%3C/svg%3E`;
                img.src = placeholder;
            }
        });
    }
    
    // Reinitialize for dynamically added content
    function refresh() {
        init();
    }
    
    // Listen for AJAX page loads
    document.addEventListener('ajaxPageLoaded', refresh);
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Public API
    window.LazyLoad = {
        init: init,
        refresh: refresh,
        loadImage: loadImage,
        loadContent: loadContent
    };
})();