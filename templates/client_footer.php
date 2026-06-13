</div>
</main>

<!-- Footer -->
<footer class="client-footer">
    <div class="container">
        <p class="mb-1">🏢
            <?php echo APP_NAME; ?>
        </p>
        <small>©
            <?php echo date('Y'); ?> All rights reserved. | <a href="../catalog.php">Browse Properties</a>
        </small>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>

<?php if (isset($extra_js))
    echo $extra_js; ?>
</body>

</html>