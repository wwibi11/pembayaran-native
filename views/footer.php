<?php
// views/footer.php

$app_name    = function_exists('setting') ? setting('nama_madin', 'TPQ MADIN') : 'TPQ MADIN';
$app_version = '1.0.0';
$tahunAjaran = function_exists('setting') ? setting('tahun_ajaran', date('Y')) : date('Y');
?>
        </div> <!-- /#content -->

        <!-- ============================================
             FOOTER
             ============================================ -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span style="font-size: 12px;">
                        <i class="fas fa-mosque" style="color: #2c6b9e;"></i>
                        <span style="color: #1a2634; font-weight: 500;"><?= e($app_name) ?></span>
                        <span style="color: #8a94a6;">&copy; <?= date('Y'); ?></span>
                        <span style="color: #d1d5db; margin: 0 8px;">|</span>
                        <span style="color: #8a94a6; font-size: 11px;">
                            <i class="fas fa-graduation-cap"></i> TA <?= e($tahunAjaran) ?>
                        </span>
                        <span style="color: #d1d5db; margin: 0 8px;">|</span>
                        <span style="color: #8a94a6; font-size: 11px;">
                            <i class="fas fa-code"></i> v<?= e($app_version) ?>
                        </span>
                    </span>
                </div>
            </div>
        </footer>

    </div> <!-- /#content-wrapper -->
</div> <!-- /#wrapper -->

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->

<!-- jQuery -->
<script src="<?= BASE_URL ?>/vendor/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?= BASE_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- jQuery Easing -->
<script src="<?= BASE_URL ?>/vendor/jquery-easing/jquery.easing.min.js"></script>
<!-- Ruang Admin JS -->
<script src="<?= BASE_URL ?>/assets/js/ruang-admin.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<!-- ============================================ -->
<!-- SCRIPT UTAMA
     CATATAN: Dropdown user ditangani OLEH BOOTSTRAP (data-toggle).
     JANGAN tambahkan handler manual untuk #userDropdown di sini!
     ============================================ -->
<script>
$(document).ready(function() {

    // ============================================
    // 1. TOGGLE SIDEBAR (mobile)
    // ============================================
    function toggleSidebar() {
        $('.sidebar').toggleClass('show');
        $('#sidebarOverlay').toggleClass('show');
    }

    $('#sidebarToggleTop').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });

    $('#sidebarOverlay').on('click', function() {
        $('.sidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
    });

    // ============================================
    // 2. RESPONSIVE SIDEBAR (resize)
    // ============================================
    $(window).on('resize', function() {
        if ($(window).width() >= 769) {
            $('.sidebar').addClass('show');
            $('#sidebarOverlay').removeClass('show');
        } else {
            $('.sidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        }
    });

    // Init sidebar di desktop
    if ($(window).width() >= 769) {
        $('.sidebar').addClass('show');
    }

    // ============================================
    // 3. AUTO CLOSE SIDEBAR (klik di luar)
    //    ⚠️ Khusus mobile saja, dan hanya tutup SIDEBAR
    //       (bukan dropdown user)
    // ============================================
    $(document).on('click', function(e) {
        if ($(window).width() < 769) {
            // Klik di luar sidebar & bukan tombol toggle → tutup sidebar
            if (!$(e.target).closest('.sidebar').length &&
                !$(e.target).closest('#sidebarToggleTop').length) {
                $('.sidebar').removeClass('show');
                $('#sidebarOverlay').removeClass('show');
            }
        }
    });

    // ============================================
    // 4. AUTO CLOSE ALERT setelah 5 detik
    // ============================================
    setTimeout(function() {
        $('.alert-auto-close').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);

    // ============================================
    // 5. KONFIRMASI HAPUS (data-confirm)
    // ============================================
    $(document).on('click', '[data-confirm]', function(e) {
        const pesan = $(this).data('confirm') || 'Yakin ingin menghapus data ini?';
        if (!confirm(pesan)) {
            e.preventDefault();
            return false;
        }
    });

    // ============================================
    // 6. PREVIEW GAMBAR sebelum upload
    // ============================================
    $(document).on('change', 'input[type="file"][data-preview]', function() {
        const targetId = $(this).data('preview');
        const file = this.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                $('#' + targetId).attr('src', ev.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // ============================================
    // ⚠️ DROPDOWN USER — JANGAN DISENTUH!
    // ============================================
    // Bootstrap menangani #userDropdown secara otomatis lewat:
    //   <a href="#" data-toggle="dropdown">...</a>
    //   <div class="dropdown-menu">...</div>
    //
    // JANGAN tambahkan kode seperti:
    //   $('#userDropdown').on('click', ...)  ← HAPUS
    //   $(document).on('click', ... 'dropdown-menu'.removeClass('show'))  ← HAPUS
    //
    // Kalau Anda tambahkan itu, dropdown akan bentrok.

});
</script>

</body>
</html>