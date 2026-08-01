</main>

<footer class="footer-siniyat mt-4 py-3 text-center">
    <div class="container-fluid">
        <small class="text-muted">
            &copy; <?= date('Y') ?> <?= APP_NAME_FR ?> &mdash; 
            <span data-i18n="footer.rights">Tous droits réservés</span>
            &mdash; Année scolaire <?= APP_YEAR ?>
        </small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/assets/js/lang.js"></script>
<script src="/assets/js/idle-timer.js"></script>
<script src="/assets/js/app.js"></script>
<?php if (isset($extraScripts)): ?>
<?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
