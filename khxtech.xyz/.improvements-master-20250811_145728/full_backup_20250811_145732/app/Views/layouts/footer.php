            </main>
        </div>
    </div>
    
    <footer class="footer mt-5 py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">
                &copy; <?= date('Y') ?> شركة أبابيل للتنمية - جميع الحقوق محفوظة
            </span>
        </div>
    </footer>
    
    <!-- Core JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Defer non-critical scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
    
    <!-- Performance optimization scripts -->
    <script src="/assets/js/performance-optimizer.js"></script>
    <script src="/assets/js/lazy-loading.js"></script>
    <script src="/assets/js/ajax-navigation.js"></script>
    <script src="/assets/js/navigation-improvements.js"></script>
    <script src="/assets/js/main.js"></script>
    
    <!-- Special navigation for clients page -->
    <script>
        function goToClients() {
            window.location.href = '/?route=clients';
        }
    </script>
    
    <!-- Page loader -->
    <div id="page-loader" class="page-loader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
    <script>
        const toastHTML = `
            <div class="toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-3" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(document.querySelector('.toast'));
        toast.show();
    </script>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <script>
        const toastHTML = `
            <div class="toast align-items-center text-white bg-danger border-0 position-fixed bottom-0 end-0 m-3" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(document.querySelector('.toast'));
        toast.show();
    </script>
    <?php endif; ?>
</body>
</html>