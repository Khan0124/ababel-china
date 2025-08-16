/**
 * Ultra Fast Resource Loader
 * تحميل ذكي وسريع للموارد
 */

class UltraFastLoader {
    constructor() {
        this.loadedResources = new Set();
        this.pendingResources = new Map();
        this.observers = new Map();
        this.init();
    }
    
    init() {
        this.setupIntersectionObserver();
        this.setupResourcePreloading();
        this.setupCriticalResourceOptimization();
        this.startPerformanceMonitoring();
    }
    
    // مراقب التقاطع للتحميل الذكي
    setupIntersectionObserver() {
        if ('IntersectionObserver' in window) {
            // مراقب للصور
            this.imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.imageObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '50px' });
            
            // مراقب للمحتوى
            this.contentObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadContent(entry.target);
                        this.contentObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '100px' });
            
            this.observeElements();
        }
    }
    
    // مراقبة العناصر القابلة للتحميل الذكي
    observeElements() {
        // الصور الذكية
        document.querySelectorAll('img[data-src]').forEach(img => {
            this.imageObserver.observe(img);
        });
        
        // المحتوى الذكي
        document.querySelectorAll('[data-lazy-load]').forEach(element => {
            this.contentObserver.observe(element);
        });
        
        // الجداول الكبيرة
        document.querySelectorAll('table.lazy-table').forEach(table => {
            this.contentObserver.observe(table);
        });
    }
    
    // تحميل الصورة مع تحسينات
    loadImage(img) {
        const src = img.dataset.src;
        if (!src) return;
        
        // إنشاء صورة جديدة للتحميل المسبق
        const newImg = new Image();
        newImg.onload = () => {
            img.src = src;
            img.classList.add('loaded');
            img.classList.remove('loading');
        };
        newImg.onerror = () => {
            img.classList.add('error');
            img.alt = 'خطأ في التحميل';
        };
        
        img.classList.add('loading');
        newImg.src = src;
    }
    
    // تحميل المحتوى الذكي
    async loadContent(element) {
        const url = element.dataset.lazyLoad;
        if (!url || this.loadedResources.has(url)) return;
        
        try {
            this.showLoading(element);
            const response = await fetch(url);
            const content = await response.text();
            
            element.innerHTML = content;
            this.loadedResources.add(url);
            this.hideLoading(element);
            
            // مراقبة العناصر الجديدة
            this.observeNewElements(element);
            
        } catch (error) {
            console.error('خطأ في تحميل المحتوى:', error);
            element.innerHTML = '<p class="error">خطأ في التحميل</p>';
        }
    }
    
    // تحميل مسبق للموارد الحرجة
    setupResourcePreloading() {
        // تحميل مسبق للصفحات المحتملة
        this.preloadCriticalPages();
        
        // تحميل مسبق للمكتبات المهمة
        this.preloadLibraries();
        
        // تحميل مسبق للبيانات المتوقعة
        this.preloadData();
    }
    
    preloadCriticalPages() {
        const criticalPages = [
            '/clients',
            '/transactions',
            '/dashboard'
        ];
        
        criticalPages.forEach(page => {
            this.preloadPage(page);
        });
    }
    
    preloadPage(url) {
        if (this.loadedResources.has(`page_${url}`)) return;
        
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
        
        this.loadedResources.add(`page_${url}`);
    }
    
    preloadLibraries() {
        const libraries = [
            'https://cdn.jsdelivr.net/npm/chart.js',
            'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'
        ];
        
        libraries.forEach(lib => {
            if (!this.loadedResources.has(lib)) {
                this.preloadScript(lib);
            }
        });
    }
    
    preloadScript(src) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'script';
        link.href = src;
        document.head.appendChild(link);
        
        this.loadedResources.add(src);
    }
    
    // تحميل البيانات المتوقعة
    preloadData() {
        // تحميل إحصائيات اليوم
        this.preloadEndpoint('/api/dashboard/stats');
        
        // تحميل أحدث المعاملات
        this.preloadEndpoint('/api/transactions/recent');
    }
    
    async preloadEndpoint(url) {
        if (this.loadedResources.has(`api_${url}`)) return;
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            // تخزين في localStorage للاستخدام السريع
            localStorage.setItem(`cache_${url}`, JSON.stringify({
                data: data,
                timestamp: Date.now(),
                ttl: 5 * 60 * 1000 // 5 دقائق
            }));
            
            this.loadedResources.add(`api_${url}`);
        } catch (error) {
            console.warn('فشل في تحميل البيانات المسبقة:', url, error);
        }
    }
    
    // تحسين الموارد الحرجة
    setupCriticalResourceOptimization() {
        // ضغط الاستجابات
        this.enableResponseCompression();
        
        // تحميل الخطوط بشكل مُحسن
        this.optimizeFontLoading();
        
        // تأخير تحميل الموارد غير الحرجة
        this.deferNonCriticalResources();
    }
    
    optimizeFontLoading() {
        // تحميل الخطوط بشكل غير متزامن
        const fontLink = document.createElement('link');
        fontLink.rel = 'preload';
        fontLink.as = 'font';
        fontLink.type = 'font/woff2';
        fontLink.crossOrigin = 'anonymous';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap';
        
        document.head.appendChild(fontLink);
    }
    
    deferNonCriticalResources() {
        // تأخير تحميل الموارد غير الحرجة
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.loadNonCriticalResources();
            }, 1000);
        });
    }
    
    loadNonCriticalResources() {
        // تحميل مكتبات الرسوم البيانية
        this.loadScript('https://cdn.jsdelivr.net/npm/chart.js');
        
        // تحميل مكتبات التصدير
        this.loadScript('/assets/js/export-enhanced.js');
        
        // تحميل أدوات إضافية
        this.loadScript('/assets/js/performance-optimizer.js');
    }
    
    loadScript(src) {
        if (this.loadedResources.has(src)) return;
        
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => {
            this.loadedResources.add(src);
        };
        
        document.body.appendChild(script);
    }
    
    // مراقبة الأداء
    startPerformanceMonitoring() {
        if ('PerformanceObserver' in window) {
            // مراقبة أوقات التحميل
            const perfObserver = new PerformanceObserver((entries) => {
                entries.getEntries().forEach(entry => {
                    if (entry.duration > 1000) { // أكثر من ثانية
                        console.warn('مورد بطيء:', entry.name, entry.duration);
                    }
                });
            });
            
            perfObserver.observe({ entryTypes: ['navigation', 'resource'] });
        }
        
        // مراقبة استخدام الذاكرة
        this.monitorMemoryUsage();
    }
    
    monitorMemoryUsage() {
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.8) {
                    console.warn('استخدام ذاكرة عالي');
                    this.cleanupMemory();
                }
            }, 30000); // كل 30 ثانية
        }
    }
    
    // تنظيف الذاكرة
    cleanupMemory() {
        // تنظيف التخزين المؤقت
        this.cleanupCache();
        
        // إزالة المراقبين غير المستخدمين
        this.cleanupObservers();
        
        // تشغيل جمع القمامة
        if ('gc' in window) {
            window.gc();
        }
    }
    
    cleanupCache() {
        const now = Date.now();
        
        // تنظيف localStorage
        for (let key in localStorage) {
            if (key.startsWith('cache_')) {
                try {
                    const cached = JSON.parse(localStorage.getItem(key));
                    if (now - cached.timestamp > cached.ttl) {
                        localStorage.removeItem(key);
                    }
                } catch (e) {
                    localStorage.removeItem(key);
                }
            }
        }
    }
    
    cleanupObservers() {
        // تنظيف المراقبين المكتملين
        this.observers.forEach((observer, key) => {
            if (!document.querySelector(`[data-observer="${key}"]`)) {
                observer.disconnect();
                this.observers.delete(key);
            }
        });
    }
    
    // عرض حالة التحميل
    showLoading(element) {
        element.innerHTML = '<div class="loading-spinner">جاري التحميل...</div>';
    }
    
    hideLoading(element) {
        const spinner = element.querySelector('.loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    }
    
    // مراقبة العناصر الجديدة
    observeNewElements(container) {
        container.querySelectorAll('img[data-src]').forEach(img => {
            this.imageObserver.observe(img);
        });
        
        container.querySelectorAll('[data-lazy-load]').forEach(element => {
            this.contentObserver.observe(element);
        });
    }
    
    // واجهة عامة للاستخدام
    static getInstance() {
        if (!UltraFastLoader.instance) {
            UltraFastLoader.instance = new UltraFastLoader();
        }
        return UltraFastLoader.instance;
    }
    
    // تحسين الصور تلقائياً
    optimizeImages() {
        document.querySelectorAll('img').forEach(img => {
            // إضافة loading lazy للصور
            if (!img.hasAttribute('loading')) {
                img.loading = 'lazy';
            }
            
            // تحسين أبعاد الصور
            if (img.naturalWidth > 800) {
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
            }
        });
    }
}

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    const loader = UltraFastLoader.getInstance();
    loader.optimizeImages();
    
    // تسجيل service worker للتخزين المؤقت المتقدم
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(console.error);
    }
});

// تصدير للاستخدام العام
window.UltraFastLoader = UltraFastLoader;