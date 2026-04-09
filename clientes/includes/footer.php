</div> <!-- End container-fluid -->
    
    <!-- Footer -->
    <footer class="bg-white mt-5 py-4 border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        &copy; <?php echo date('Y'); ?> JAF Investments. Todos los derechos reservados.
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0 text-muted">
                        <i class="fas fa-phone me-2"></i> +1 (809) 555-0123
                        <i class="fas fa-envelope ms-3 me-2"></i> info@jafinvestments.com
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Confirmación de eliminación
        function confirmDelete(message) {
            return confirm(message || '¿Está seguro de que desea eliminar este elemento?');
        }
        
        // Formato de moneda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-DO', {
                style: 'currency',
                currency: 'DOP'
            }).format(amount);
        }
    </script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
</body>
</html>