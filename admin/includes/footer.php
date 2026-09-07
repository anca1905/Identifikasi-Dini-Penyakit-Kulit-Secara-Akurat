<?php
// admin/includes/footer.php
?>
        </div> <!-- End container-fluid -->
        
        <div class="mt-auto px-4 px-md-5 py-3 text-center text-muted small border-top bg-white d-none d-md-block" style="margin-top: 50px !important;">
            &copy; <?= date("Y"); ?> Sistem Pakar Kulit Dempster-Shafer Panel Administrasi. Build by Antigravity Model.
        </div>
        
    </div> <!-- End Page Content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar on Mobile
        document.addEventListener("DOMContentLoaded", function () {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarCollapse');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                });
            }
        });

        // SweetAlert Confirm Delete
        function confirmDelete(event, url, message) {
            event.preventDefault();
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: message || 'Apakah Anda yakin ingin menghapus data ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
    </script>
</body>
</html>
