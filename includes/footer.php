</div><!-- /main-content -->

<footer class="mt-auto py-3 border-top bg-white">
    <div class="container-fluid px-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <span>&copy; <?= date('Y') ?> <?= e(APP_SHORT_NAME) ?>. All rights reserved.</span>
            <span class="mt-1 mt-md-0">v<?= e(APP_VERSION) ?></span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>

</body>
</html>
