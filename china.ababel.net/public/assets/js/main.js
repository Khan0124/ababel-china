javascript
// Auto-calculate totals
document.addEventListener('DOMContentLoaded', function() {
    // Transaction form calculations
    const goodsAmount = document.getElementById('goods_amount_rmb');
    const commission = document.getElementById('commission_rmb');
    const totalAmount = document.getElementById('total_amount_rmb');
    
    if (goodsAmount && commission && totalAmount) {
        function calculateTotal() {
            const goods = parseFloat(goodsAmount.value) || 0;
            const comm = parseFloat(commission.value) || 0;
            totalAmount.value = (goods + comm).toFixed(2);
        }
        
        goodsAmount.addEventListener('input', calculateTotal);
        commission.addEventListener('input', calculateTotal);
    }
    
    // Payment and balance calculations
    const paymentRMB = document.getElementById('payment_rmb');
    const balanceRMB = document.getElementById('balance_rmb');
    
    if (paymentRMB && balanceRMB && totalAmount) {
        paymentRMB.addEventListener('input', function() {
            const total = parseFloat(totalAmount.value) || 0;
            const payment = parseFloat(paymentRMB.value) || 0;
            balanceRMB.value = (total - payment).toFixed(2);
        });
    }
    
    // Date picker default to today
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.delete-confirm');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من الحذف؟')) {
                e.preventDefault();
            }
        });
    });
    
    // Print functionality
    window.printReport = function() {
        window.print();
    };
    
    // Export to Excel
    window.exportToExcel = function(tableId, filename) {
        const table = document.getElementById(tableId);
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
        XLSX.writeFile(wb, filename + ".xlsx");
    };
    
    // Dynamic client search
    const clientSearch = document.getElementById('client-search');
    if (clientSearch) {
        clientSearch.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#clients-table tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('.client-name').textContent.toLowerCase();
                const code = row.querySelector('.client-code').textContent.toLowerCase();
                
                if (name.includes(searchValue) || code.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Currency converter
    const currencyInputs = document.querySelectorAll('.currency-convert');
    currencyInputs.forEach(input => {
        input.addEventListener('change', function() {
            const amount = parseFloat(this.value) || 0;
            const fromCurrency = this.dataset.from;
            const toCurrency = this.dataset.to;
            const rateInput = document.getElementById(`rate_${fromCurrency}_${toCurrency}`);
            const targetInput = document.getElementById(`amount_${toCurrency}`);
            
            if (rateInput && targetInput) {
                const rate = parseFloat(rateInput.value) || 0;
                targetInput.value = (amount * rate).toFixed(2);
            }
        });
    });
    
    // Ajax form submission for transactions
    const transactionForm = document.getElementById('transaction-form');
    if (transactionForm) {
        transactionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جاري الحفظ...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/transactions?success=' + encodeURIComponent(data.message);
                } else {
                    alert('خطأ: ' + data.error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'حفظ المعاملة';
                }
            })
            .catch(error => {
                alert('حدث خطأ في الاتصال');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'حفظ المعاملة';
            });
        });
    }
});

// Chart initialization for dashboard
if (document.getElementById('cashflow-chart')) {
    const ctx = document.getElementById('cashflow-chart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: cashflowLabels, // Provided by PHP
            datasets: [{
                label: 'التدفق النقدي (RMB)',
                data: cashflowData,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
}