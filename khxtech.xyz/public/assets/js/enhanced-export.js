// تحسينات الأداء المضافة
const DOMCache = new Map();
function cachedQuerySelector(selector) {
    if (!DOMCache.has(selector)) {
        DOMCache.set(selector, document.querySelector(selector));
    }
    return DOMCache.get(selector);
}
/**
 * Enhanced Export and Printing Functions
 * China Office Accounting System
 * Supports PDF, Excel, and enhanced printing with progress indicators
 */

class ExportManager {
    constructor() {
        this.exportInProgress = false;
        this.progressModal = null;
        this.isRTL = this.detectLanguage();
        this.texts = this.loadTexts();
        this.init();
    }

    init() {
        // Initialize export functionality
        this.createProgressModal();
        this.bindEvents();
        this.initPrintStyles();
    }

    /**
     * Detect current language/direction
     */
    detectLanguage() {
        return document.documentElement.dir === 'rtl' || 
               document.body.dir === 'rtl' || 
               document.documentElement.lang === 'ar' ||
               document.body.lang === 'ar';
    }

    /**
     * Load text translations based on language
     */
    loadTexts() {
        if (this.isRTL) {
            return {
                // Arabic texts
                exporting: 'جاري التصدير...',
                pleaseWait: 'يرجى الانتظار...',
                loading: 'جاري التحميل...',
                printPreview: 'معاينة الطباعة',
                print: 'طباعة',
                close: 'إغلاق',
                exportSuccessful: 'تم التصدير بنجاح',
                exportFailed: 'فشل التصدير',
                exportError: 'خطأ في التصدير',
                processingData: 'جاري معالجة البيانات...',
                generatingFile: 'جاري إنشاء الملف...',
                creatingPdf: 'جاري إنشاء ملف PDF...',
                creatingExcel: 'جاري إنشاء ملف Excel...',
                downloadingFile: 'جاري تحميل الملف...',
                tableNotFound: 'لم يتم العثور على جدول للتصدير',
                exportLibraryFailed: 'فشل في تحميل مكتبة التصدير',
                loadingExportLibrary: 'جاري تحميل مكتبة التصدير...',
                clientExportSuccess: 'تم التصدير من جانب العميل بنجاح',
                fallbackClientExport: 'جاري التحويل إلى التصدير من جانب العميل...',
                networkError: 'حدث خطأ في الشبكة',
                serverError: 'حدث خطأ في الخادم أثناء التصدير',
                printError: 'حدث خطأ أثناء الطباعة: ',
                noExportType: 'لم يتم تحديد نوع التصدير أو الرابط'
            };
        } else {
            return {
                // English texts
                exporting: 'Exporting...',
                pleaseWait: 'Please wait...',
                loading: 'Loading...',
                printPreview: 'Print Preview',
                print: 'Print',
                close: 'Close',
                exportSuccessful: 'Export completed successfully',
                exportFailed: 'Export failed',
                exportError: 'Export Error',
                processingData: 'Processing data...',
                generatingFile: 'Generating file...',
                creatingPdf: 'Creating PDF file...',
                creatingExcel: 'Creating Excel file...',
                downloadingFile: 'Downloading file...',
                tableNotFound: 'No table found to export',
                exportLibraryFailed: 'Failed to load export library',
                loadingExportLibrary: 'Loading export library...',
                clientExportSuccess: 'Client-side export completed successfully',
                fallbackClientExport: 'Falling back to client-side export...',
                networkError: 'Network error occurred',
                serverError: 'Server error occurred during export',
                printError: 'Print error: ',
                noExportType: 'Export type or URL not specified'
            };
        }
    }

    /**
     * Create progress modal for export operations
     */
    createProgressModal() {
        if (document.getElementById('export-progress-modal')) return;

        const modalHTML = `
        <div class="modal fade" id="export-progress-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">
                            <i class="bi bi-download me-2"></i>
                            <span id="export-title">${this.texts.exporting}</span>
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="export-progress-bar"></div>
                        </div>
                        <p class="mb-0" id="export-status">${this.texts.pleaseWait}</p>
                        <div class="spinner-border spinner-border-sm mt-2" role="status">
                            <span class="visually-hidden">${this.texts.loading}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.progressModal = new bootstrap.Modal(document.getElementById('export-progress-modal'));
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Enhanced print buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-print, [data-action="print"]')) {
                e.preventDefault();
                this.handlePrint(e.target);
            }

            if (e.target.matches('.btn-export, [data-action="export"]')) {
                e.preventDefault();
                this.handleExport(e.target);
            }

            if (e.target.matches('.btn-export-pdf, [data-action="export-pdf"]')) {
                e.preventDefault();
                this.handlePdfExport(e.target);
            }

            if (e.target.matches('.btn-export-excel, [data-action="export-excel"]')) {
                e.preventDefault();
                this.handleExcelExport(e.target);
            }
        });

        // Print preview functionality
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                this.enhancedPrint();
            }
        });
    }

    /**
     * Initialize print styles
     */
    initPrintStyles() {
        // Ensure print.css is loaded
        if (!document.querySelector('link[href*="print.css"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/assets/css/print.css';
            link.media = 'print';
            document.head.appendChild(link);
        }

        // Add print-specific classes to body
        document.body.classList.add('print-ready');
    }

    /**
     * Enhanced print function with preview option
     */
    async enhancedPrint(options = {}) {
        const {
            showPreview = false,
            orientation = 'portrait',
            paperSize = 'A4',
            margins = 'default'
        } = options;

        try {
            // Show print preview if requested
            if (showPreview) {
                this.showPrintPreview();
                return;
            }

            // Prepare document for printing
            this.preparePrintDocument(orientation, paperSize, margins);

            // Add print-specific styles
            document.body.classList.add('printing');

            // Small delay to ensure styles are applied
            await new Promise(resolve => setTimeout(resolve, 100));

            // Print the document
            window.print();

        } catch (error) {
            console.error('Print error:', error);
            this.showError(this.texts.printError + error.message);
        } finally {
            // Clean up
            document.body.classList.remove('printing');
        }
    }

    /**
     * Prepare document for printing
     */
    preparePrintDocument(orientation, paperSize, margins) {
        // Set page orientation
        const style = document.createElement('style');
        style.id = 'temp-print-style';
        style./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = `
            @page {
                size: ${paperSize} ${orientation};
                margin: ${this.getMarginValue(margins)};
            }
            
            @media print {
                body { 
                    print-color-adjust: exact !important;
                    -webkit-print-color-adjust: exact !important;
                }
            }
        `;
        document.head.appendChild(style);

        // Remove after printing
        setTimeout(() => {
            const tempStyle = document.getElementById('temp-print-style');
            if (tempStyle) tempStyle.remove();
        }, 1000);
    }

    /**
     * Get margin value based on option
     */
    getMarginValue(margins) {
        const marginOptions = {
            'none': '0',
            'minimal': '10mm',
            'default': '15mm',
            'normal': '20mm',
            'large': '25mm'
        };
        return marginOptions[margins] || marginOptions['default'];
    }

    /**
     * Show print preview modal
     */
    showPrintPreview() {
        const previewModal = this.createPrintPreviewModal();
        const modal = new bootstrap.Modal(previewModal);
        modal.show();
    }

    /**
     * Create print preview modal
     */
    createPrintPreviewModal() {
        // Remove existing preview modal
        const existing = document.getElementById('print-preview-modal');
        if (existing) existing.remove();

        const content = document.documentElement.outerHTML;

        const modalHTML = `
        <div class="modal fade" id="print-preview-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-eye me-2"></i>${this.texts.printPreview}
                        </h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportManager.enhancedPrint()">
                                <i class="bi bi-printer"></i> ${this.texts.print}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                                ${this.texts.close}
                            </button>
                        </div>
                    </div>
                    <div class="modal-body p-0">
                        <div class="print-preview-container">
                            <iframe id="preview-frame" style="width: 100%; height: 70vh; border: none;">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        const modal = document.getElementById('print-preview-modal');
        const iframe = modal.querySelector('#preview-frame');
        
        // Load content into iframe
        iframe.onload = () => {
            const iframeDoc = iframe.contentDocument;
            iframeDoc.open();
            iframeDoc.write(content);
            iframeDoc.close();
            
            // Add print styles to iframe
            const printLink = iframeDoc.createElement('link');
            printLink.rel = 'stylesheet';
            printLink.href = '/assets/css/print.css';
            iframeDoc.head.appendChild(printLink);
            
            // Apply print styles
            iframeDoc.body.classList.add('print-preview');
        };

        return modal;
    }

    /**
     * Handle print button clicks
     */
    handlePrint(button) {
        const options = {
            showPreview: button.dataset.preview === 'true',
            orientation: button.dataset.orientation || 'portrait',
            paperSize: button.dataset.paperSize || 'A4',
            margins: button.dataset.margins || 'default'
        };

        this.enhancedPrint(options);
    }

    /**
     * Handle export button clicks
     */
    async handleExport(button) {
        const format = button.dataset.format || 'pdf';
        const exportType = button.dataset.exportType;
        const exportUrl = button.dataset.exportUrl;

        if (!exportUrl && !exportType) {
            this.showError(this.texts.noExportType);
            return;
        }

        try {
            if (format === 'pdf') {
                await this.handlePdfExport(button);
            } else if (format === 'excel') {
                await this.handleExcelExport(button);
            } else {
                await this.handleGenericExport(button);
            }
        } catch (error) {
            this.showError(this.texts.exportFailed + ': ' + error.message);
        }
    }

    /**
     * Handle PDF export
     */
    async handlePdfExport(button) {
        const exportUrl = this.buildExportUrl(button, 'pdf');
        
        this.showProgress('PDF ' + this.texts.exporting, this.texts.creatingPdf);
        
        try {
            await this.downloadFile(exportUrl, 'pdf');
            this.showSuccess('PDF ' + this.texts.exportSuccessful);
        } catch (error) {
            this.showError('PDF ' + this.texts.exportFailed + ': ' + error.message);
        }
    }

    /**
     * Handle Excel export
     */
    async handleExcelExport(button) {
        // Try server-side export first
        const serverExportUrl = this.buildExportUrl(button, 'excel');
        
        if (serverExportUrl && serverExportUrl !== '#') {
            this.showProgress('Excel ' + this.texts.exporting, this.texts.creatingExcel);
            
            try {
                await this.downloadFile(serverExportUrl, 'excel');
                this.showSuccess('Excel ' + this.texts.exportSuccessful);
                return;
            } catch (error) {
                console.warn(this.texts.fallbackClientExport, error);
            }
        }

        // Fallback to client-side export
        await this.clientSideExcelExport(button);
    }

    /**
     * Client-side Excel export using SheetJS
     */
    async clientSideExcelExport(button) {
        const tableId = button.dataset.tableId || this.findExportableTable();
        const filename = button.dataset.filename || 'export';

        if (!tableId) {
            throw new Error(this.texts.tableNotFound);
        }

        this.showProgress('Excel ' + this.texts.exporting, this.texts.processingData);

        try {
            // Load SheetJS if not already loaded
            await this.loadSheetJS();

            const table = document.getElementById(tableId) || document.querySelector(tableId);
            if (!table) {
                throw new Error(this.texts.tableNotFound);
            }

            // Create workbook from table
            const wb = XLSX.utils.table_to_book(table, {
                sheet: "البيانات",
                raw: true
            });

            // Enhance worksheet
            this.enhanceWorksheet(wb.Sheets["البيانات"], table);

            // Generate and download file
            const wbout = XLSX.write(wb, {
                bookType: 'xlsx',
                type: 'array'
            });

            this.downloadBlob(
                new Blob([wbout], { type: 'application/octet-stream' }),
                `${filename}_${this.getTimestamp()}.xlsx`
            );

            this.showSuccess(this.texts.clientExportSuccess);

        } catch (error) {
            throw new Error('Excel ' + this.texts.exportFailed + ': ' + error.message);
        }
    }

    /**
     * Load SheetJS library dynamically
     */
    async loadSheetJS() {
        if (typeof XLSX !== 'undefined') return;

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Enhance worksheet with formatting
     */
    enhanceWorksheet(ws, table) {
        if (!ws['!ref']) return;

        const range = XLSX.utils.decode_range(ws['!ref']);
        
        // Set column widths
        if (!ws['!cols']) ws['!cols'] = [];
        for (let C = range.s.c; C <= range.e.c; ++C) {
            ws['!cols'][C] = { wch: 15 };
        }

        // Style header row
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const headerCell = ws[XLSX.utils.encode_cell({ r: 0, c: C })];
            if (headerCell) {
                headerCell.s = {
                    font: { bold: true },
                    fill: { fgColor: { rgb: "3498DB" } },
                    border: {
                        top: { style: "thin" },
                        bottom: { style: "thin" },
                        left: { style: "thin" },
                        right: { style: "thin" }
                    }
                };
            }
        }
    }

    /**
     * Find exportable table in the document
     */
    findExportableTable() {
        const selectors = [
            '#export-table',
            '.export-table',
            '.table:not(.no-export)',
            'table:not(.no-export)'
        ];

        for (const selector of selectors) {
            const table = document.querySelector(selector);
            if (table) return selector;
        }

        return null;
    }

    /**
     * Build export URL with parameters
     */
    buildExportUrl(button, format) {
        let baseUrl = button.dataset.exportUrl;
        
        if (!baseUrl) {
            const exportType = button.dataset.exportType;
            if (exportType) {
                baseUrl = `/export/${exportType}`;
            } else {
                return null;
            }
        }

        const params = new URLSearchParams();
        params.set('format', format);

        // Add common parameters
        const dataParams = [
            'clientId', 'transactionId', 'startDate', 'endDate',
            'date', 'month', 'language', 'template'
        ];

        dataParams.forEach(param => {
            const value = button.dataset[param] || document.querySelector(`[name="${param}"]`)?.value;
            if (value) {
                params.set(this.camelToSnake(param), value);
            }
        });

        return `${baseUrl}?${params.toString()}`;
    }

    /**
     * Convert camelCase to snake_case
     */
    camelToSnake(str) {
        return str.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
    }

    /**
     * Download file from URL
     */
    async downloadFile(url, type) {
        const response = await fetch(url);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const blob = await response.blob();
        const contentDisposition = response.headers.get('Content-Disposition');
        
        let filename = `export_${this.getTimestamp()}`;
        if (contentDisposition) {
            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(contentDisposition);
            if (matches != null && matches[1]) {
                filename = matches[1].replace(/['"]/g, '');
            }
        }

        this.downloadBlob(blob, filename);
    }

    /**
     * Download blob as file
     */
    downloadBlob(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    /**
     * Handle generic export
     */
    async handleGenericExport(button) {
        const exportUrl = button.dataset.exportUrl;
        const format = button.dataset.format;
        
        this.showProgress(this.texts.exporting, `${this.texts.generatingFile} ${format.toUpperCase()}...`);
        
        try {
            await this.downloadFile(exportUrl, format);
            this.showSuccess(this.texts.exportSuccessful);
        } catch (error) {
            throw error;
        }
    }

    /**
     * Show progress modal
     */
    showProgress(title, status) {
        if (this.exportInProgress) return;

        this.exportInProgress = true;
        
        document.getElementById('export-title').textContent = title;
        document.getElementById('export-status').textContent = status;
        
        const progressBar = document.getElementById('export-progress-bar');
        progressBar.style.width = '20%';
        
        this.progressModal.show();

        // Simulate progress
        let progress = 20;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 90) progress = 90;
            progressBar.style.width = progress + '%';
        }, 500);

        // Store interval for cleanup
        this.progressInterval = interval;
    }

    /**
     * Hide progress modal
     */
    hideProgress() {
        if (!this.exportInProgress) return;

        this.exportInProgress = false;
        
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }

        const progressBar = document.getElementById('export-progress-bar');
        progressBar.style.width = '100%';
        
        setTimeout(() => {
            this.progressModal.hide();
            progressBar.style.width = '0%';
        }, 500);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        this.hideProgress();
        this.showToast(message, 'success');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.hideProgress();
        this.showToast(message, 'error');
        console.error('Export error:', message);
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Use existing toast system or create simple notification
        if (typeof showToast === 'function') {
            showToast(message, type);
            return;
        }

        // Fallback notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'info')} 
                          position-fixed top-0 end-0 m-3 fade show`;
        toast.style.zIndex = '9999';
        toast./* TODO: استخدم textContent أو insertAdjacentHTML */ innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-${type === 'error' ? 'exclamation-triangle' : (type === 'success' ? 'check-circle' : 'info-circle')} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }

    /**
     * Get current timestamp for filenames
     */
    getTimestamp() {
        const now = new Date();
        return now.getFullYear() +
               String(now.getMonth() + 1).padStart(2, '0') +
               String(now.getDate()).padStart(2, '0') +
               String(now.getHours()).padStart(2, '0') +
               String(now.getMinutes()).padStart(2, '0') +
               String(now.getSeconds()).padStart(2, '0');
    }

    /**
     * Quick export functions for backward compatibility
     */
    exportToExcel(tableId, filename) {
        const button = {
            dataset: {
                tableId: tableId,
                filename: filename,
                format: 'excel'
            }
        };
        this.handleExcelExport(button);
    }

    exportToPDF(elementId, filename) {
        const button = {
            dataset: {
                elementId: elementId,
                filename: filename,
                format: 'pdf'
            }
        };
        this.handlePdfExport(button);
    }

    printReport() {
        this.enhancedPrint();
    }
}

// Initialize export manager
let exportManager;
document.addEventListener('DOMContentLoaded', function() {
    exportManager = new ExportManager();
});

// Backward compatibility functions
window.exportToExcel = function(tableId, filename) {
    if (exportManager) {
        exportManager.exportToExcel(tableId, filename);
    } else {
        // Fallback to old method
        const table = document.getElementById(tableId);
        if (table && typeof XLSX !== 'undefined') {
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
            XLSX.writeFile(wb, filename + ".xlsx");
        }
    }
};

window.exportToPDF = function(elementId, filename) {
    if (exportManager) {
        exportManager.exportToPDF(elementId, filename);
    } else {
        // Fallback to simple print
        window.print();
    }
};

window.printReport = function() {
    if (exportManager) {
        exportManager.printReport();
    } else {
        window.print();
    }
};