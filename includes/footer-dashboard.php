    </main>
</div><!-- /.d-flex -->

<!-- Footer dashboard -->
<footer class="bg-white border-top py-3 text-center text-muted small">
    <i class="bi bi-c-circle me-1"></i><?= date('Y') ?> Universidad Nacional de La Punta &mdash; Sistema de Tickets
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/dashboard.js"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
