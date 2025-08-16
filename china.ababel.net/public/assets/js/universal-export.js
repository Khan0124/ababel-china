/**
 * Universal Export Functions - China Ababel Accounting System
 * Works for all pages with fallback support
 */

// تحديد نوع المتصفح واللغة
const isRTL = document.documentElement.dir === 'rtl' || document.documentElement.lang === 'ar';

// رسائل الخطأ والنجاح
const exportMessages = {
    ar: {
        loading: 'جاري تحميل مكتبة التصدير...',
        exporting: 'جاري التصدير...',
        success: 'تم التصدير بنجاح!',
        tableNotFound: 'الجدول غير موجود',
        xlsxNotLoaded: 'مكتبة التصدير غير محملة',
        errorOccurred: 'حدث خطأ أثناء التصدير',
        retrying: 'جاري إعادة المحاولة...',
        fallbackMode: 'استخدام الطريقة البديلة...'
    },
    en: {
        loading: 'Loading export library...',
        exporting: 'Exporting...',
        success: 'Export successful!',
        tableNotFound: 'Table not found',
        xlsxNotLoaded: 'Export library not loaded',
        errorOccurred: 'Error occurred during export',
        retrying: 'Retrying...',
        fallbackMode: 'Using fallback method...'
    }
};

const messages = exportMessages[isRTL ? 'ar' : 'en'];

// وظيفة إظهار تنبيه
function showAlert(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'info': 'alert-info',
        'warning': 'alert-warning'
    }[type] || 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // إزالة التنبيه تلقائياً بعد 3 ثوان
    setTimeout(() => {
        const alert = document.querySelector('.alert:last-of-type');
        if (alert) {
            alert.remove();
        }
    }, 3000);
}

// وظيفة تحميل مكتبة XLSX ديناميكياً
function loadXLSX() {
    return new Promise((resolve, reject) => {
        if (typeof XLSX !== 'undefined') {
            resolve(XLSX);
            return;
        }
        
        showAlert(messages.loading, 'info');
        
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = () => {
            if (typeof XLSX !== 'undefined') {
                resolve(XLSX);
            } else {
                reject(new Error('XLSX failed to load'));
            }
        };
        script.onerror = () => reject(new Error('Failed to load XLSX script'));
        document.head.appendChild(script);
    });
}

// الوظيفة الرئيسية للتصدير
async function exportToExcel(tableId, filename) {
    try {
        // فحص وجود معاملات
        if (!tableId) {
            console.error('Table ID is required for exportToExcel');
            showAlert(messages.tableNotFound, 'error');
            return;
        }
        
        // فحص وجود الجدول
        const table = document.getElementById(tableId);
        if (!table) {
            console.error('Table not found:', tableId);
            showAlert(`${messages.tableNotFound}: ${tableId}`, 'error');
            return;
        }
        
        showAlert(messages.exporting, 'info');
        
        // تحميل مكتبة XLSX
        const XLSX = await loadXLSX();
        
        // تصدير الجدول
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "البيانات");
        
        // تحديد اسم الملف
        const finalFilename = (filename || 'export') + '.xlsx';
        XLSX.writeFile(wb, finalFilename);
        
        showAlert(messages.success, 'success');
        console.log('Export successful:', finalFilename);
        
    } catch (error) {
        console.error('Export error:', error);
        showAlert(`${messages.errorOccurred}: ${error.message}`, 'error');
        
        // محاولة الطريقة البديلة
        fallbackExport(tableId, filename);
    }
}

// طريقة بديلة للتصدير باستخدام CSV
function fallbackExport(tableId, filename) {
    try {
        showAlert(messages.fallbackMode, 'warning');
        
        const table = document.getElementById(tableId);
        if (!table) return;
        
        let csv = '';
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            
            cols.forEach(col => {
                // تنظيف النص من HTML وإزالة الفراغات الزائدة
                let cellText = col.textContent || col.innerText || '';
                cellText = cellText.replace(/\s+/g, ' ').trim();
                
                // إضافة علامات اقتباس إذا احتوى النص على فاصلة
                if (cellText.includes(',')) {
                    cellText = `"${cellText}"`;
                }
                
                rowData.push(cellText);
            });
            
            csv += rowData.join(',') + '\n';
        });
        
        // إنشاء وتحميل ملف CSV
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', (filename || 'export') + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showAlert('تم التصدير كملف CSV', 'success');
        }
        
    } catch (error) {
        console.error('Fallback export error:', error);
        showAlert('فشل في التصدير البديل', 'error');
    }
}

// تحديث الوظيفة العامة
window.exportToExcel = exportToExcel;

// وظائف إضافية للطباعة
window.printReport = function() {
    window.print();
};

// تحميل المكتبة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // تحميل مكتبة XLSX مسبقاً إذا كانت غير محملة
    if (typeof XLSX === 'undefined') {
        loadXLSX().catch(err => {
            console.warn('XLSX preload failed:', err);
        });
    }
});

console.log('✅ Universal Export loaded successfully');