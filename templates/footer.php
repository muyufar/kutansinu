<footer class="footer mt-5 py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© 2025 Sistem Pelaporan Keuangan</span>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Update dashboard numbers
        function updateDashboardNumbers() {
            $.ajax({
                url: '/kutansinu/api/get_dashboard_data.php',
                method: 'GET',
                success: function(response) {
                    $('#total-pemasukan').text('Rp ' + response.total_pemasukan);
                    $('#total-pengeluaran').text('Rp ' + response.total_pengeluaran);
                    $('#saldo').text('Rp ' + response.saldo);
                },
                error: function() {
                    console.error('Gagal mengambil data dashboard');
                }
            });
        }

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Call dashboard update if we're on the dashboard page
        if (window.location.pathname === '/kutansinu/index.php') {
            updateDashboardNumbers();
            // Update every 5 minutes
            setInterval(updateDashboardNumbers, 300000);
        }
    </script>
</body>
</html>