<!-- Footer -->
<footer class="footer mt-auto py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold"><?php echo SITE_NAME; ?></h5>
                    <p class="text-white">Connecting talented professionals with great opportunities.</p>
                </div>
                <div class="col-md-2 mb-3">
                    <h6 class="fw-bold">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/public/index.php" class="text-white">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/about.php" class="text-white">About</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/how-it-works.php" class="text-white">How It Works</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/pricing.php" class="text-white">Pricing</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-3">
                    <h6 class="fw-bold">For Talents</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/public/register.php?role=talent" class="text-white">Register</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/login.php" class="text-white">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-3">
                    <h6 class="fw-bold">For Employers</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/public/register.php?role=employer" class="text-white">Register</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/login.php" class="text-white">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-3">
                    <h6 class="fw-bold">Contact</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/public/contact.php" class="text-white">Contact Us</a></li>
                        <li class="text-white"><?php echo SITE_EMAIL; ?></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-white">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Main JS -->
    <script src="<?php echo SITE_URL; ?>/public/assets/js/main.js"></script>
    
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/public/assets/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
    <script>
        Swal.fire({
            icon: '<?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>',
            title: '<?php echo $flash['type'] === 'error' ? 'Error' : 'Success'; ?>',
            text: '<?php echo addslashes($flash['message']); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    </script>
    <?php endif; ?>
</body>
</html>