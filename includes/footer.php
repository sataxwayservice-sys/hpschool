    </div> <!-- End Main Content -->

    <?php if (isLoggedIn()): ?>
    <!-- Footer -->
    <footer class="mt-5 py-4 app-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolSettings['school_name']); ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-muted">
                        Version <?php echo APP_VERSION; ?> | Powered by <?php echo htmlspecialchars(APP_NAME); ?>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Firebase SDK (if needed) -->
    <?php if (defined('FIREBASE_API_KEY') && FIREBASE_API_KEY != 'YOUR_FIREBASE_API_KEY'): ?>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-database-compat.js"></script>
    <script>
        // Firebase configuration
        const firebaseConfig = <?php echo json_encode(getFirebaseConfig()); ?>;
        firebase.initializeApp(firebaseConfig);
    </script>
    <?php endif; ?>

    <!-- Custom JS -->
    <script>
        window.APP_URL = <?php echo json_encode(APP_URL); ?>;
    </script>
    <script src="<?php echo APP_URL; ?>/assets/js/script.js?v=<?php echo @filemtime(BASE_PATH . '/assets/js/script.js'); ?>"></script>

    <!-- Additional JS -->
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline Scripts -->
    <?php if (isset($inlineScript)): ?>
        <script>
            <?php echo $inlineScript; ?>
        </script>
    <?php endif; ?>

</body>
</html>
