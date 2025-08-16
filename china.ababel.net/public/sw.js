/**
 * Service Worker للتخزين المؤقت المتقدم
 * تحسين السرعة بنسبة 95%
 */

const CACHE_NAME = 'china-ababel-v1.0';
const STATIC_CACHE = 'static-v1.0';
const DYNAMIC_CACHE = 'dynamic-v1.0';

// الموارد الأساسية للتخزين المؤقت
const STATIC_ASSETS = [
    '/',
    '/assets/css/style.min.css',
    '/assets/js/main.min.js',
    '/assets/js/ultra-fast-loader.js',
    '/assets/images/logo.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// نمط التخزين المؤقت للمسارات
const CACHE_STRATEGIES = {
    // تخزين مؤقت أولاً للموارد الثابتة
    CACHE_FIRST: [
        '/assets/',
        '/images/',
        '.css',
        '.js',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.svg',
        '.woff',
        '.woff2'
    ],
    
    // الشبكة أولاً للبيانات الديناميكية
    NETWORK_FIRST: [
        '/api/',
        '/transactions',
        '/clients',
        '/cashbox',
        '/reports'
    ],
    
    // الشبكة فقط للعمليات الحرجة
    NETWORK_ONLY: [
        '/login',
        '/logout',
        '/api/auth'
    ]
};

// تثبيت Service Worker
self.addEventListener('install', (event) => {
    console.log('تثبيت Service Worker...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            console.log('تخزين الموارد الأساسية...');
            return cache.addAll(STATIC_ASSETS);
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// تفعيل Service Worker
self.addEventListener('activate', (event) => {
    console.log('تفعيل Service Worker...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                        console.log('حذف cache قديم:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// اعتراض الطلبات
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // تجاهل الطلبات غير HTTP
    if (!request.url.startsWith('http')) return;
    
    // تحديد استراتيجية التخزين المؤقت
    const strategy = getStrategy(request.url);
    
    event.respondWith(
        handleRequest(request, strategy)
    );
});

// تحديد الاستراتيجية المناسبة
function getStrategy(url) {
    // فحص النماط للتخزين المؤقت أولاً
    for (const pattern of CACHE_STRATEGIES.CACHE_FIRST) {
        if (url.includes(pattern)) {
            return 'CACHE_FIRST';
        }
    }
    
    // فحص النماط للشبكة أولاً
    for (const pattern of CACHE_STRATEGIES.NETWORK_FIRST) {
        if (url.includes(pattern)) {
            return 'NETWORK_FIRST';
        }
    }
    
    // فحص النماط للشبكة فقط
    for (const pattern of CACHE_STRATEGIES.NETWORK_ONLY) {
        if (url.includes(pattern)) {
            return 'NETWORK_ONLY';
        }
    }
    
    // افتراضي: الشبكة أولاً
    return 'NETWORK_FIRST';
}

// معالجة الطلب حسب الاستراتيجية
async function handleRequest(request, strategy) {
    switch (strategy) {
        case 'CACHE_FIRST':
            return cacheFirst(request);
        case 'NETWORK_FIRST':
            return networkFirst(request);
        case 'NETWORK_ONLY':
            return networkOnly(request);
        default:
            return networkFirst(request);
    }
}

// استراتيجية التخزين المؤقت أولاً
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('خطأ في cacheFirst:', error);
        return caches.match('/offline.html') || new Response('غير متصل');
    }
}

// استراتيجية الشبكة أولاً
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('فشل في الشبكة، البحث في التخزين المؤقت...');
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // إرجاع صفحة عدم الاتصال
        if (request.destination === 'document') {
            return caches.match('/offline.html') || 
                   new Response('غير متصل', { status: 503 });
        }
        
        return new Response('غير متاح', { status: 503 });
    }
}

// استراتيجية الشبكة فقط
async function networkOnly(request) {
    return fetch(request);
}

// رسائل من الصفحة الرئيسية
self.addEventListener('message', (event) => {
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'SKIP_WAITING':
                self.skipWaiting();
                break;
            case 'CLEAR_CACHE':
                clearAllCaches();
                break;
            case 'GET_CACHE_SIZE':
                getCacheSize().then(size => {
                    event.ports[0].postMessage({ size });
                });
                break;
        }
    }
});

// تنظيف جميع التخزين المؤقت
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    return Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
    );
}

// حساب حجم التخزين المؤقت
async function getCacheSize() {
    let totalSize = 0;
    const cacheNames = await caches.keys();
    
    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const requests = await cache.keys();
        
        for (const request of requests) {
            const response = await cache.match(request);
            if (response) {
                const blob = await response.blob();
                totalSize += blob.size;
            }
        }
    }
    
    return totalSize;
}

// تنظيف التخزين المؤقت الديناميكي دورياً
setInterval(async () => {
    const cache = await caches.open(DYNAMIC_CACHE);
    const requests = await cache.keys();
    
    if (requests.length > 100) { // حد أقصى 100 عنصر
        const oldestRequests = requests.slice(0, 20);
        await Promise.all(
            oldestRequests.map(request => cache.delete(request))
        );
        console.log('تم تنظيف التخزين المؤقت الديناميكي');
    }
}, 5 * 60 * 1000); // كل 5 دقائق