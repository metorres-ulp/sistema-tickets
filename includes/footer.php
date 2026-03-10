    </div><!-- /container -->
</main>

<!-- Footer -->
<footer class="bg-white border-top mt-5 py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span class="text-muted small">
                    <i class="bi bi-c-circle me-1"></i><?= date('Y') ?> Universidad Nacional de La Punta &mdash; Área de Comunicación
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="<?= APP_URL ?>/nuevo-requerimiento.php" class="text-decoration-none text-muted small me-3">
                    <i class="bi bi-plus-circle me-1"></i>Nuevo Requerimiento
                </a>
                <a href="<?= APP_URL ?>/buscar-ticket.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-search me-1"></i>Buscar Ticket
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
