<?php

$pageScripts = isset($pageScripts) ? $pageScripts : array();

?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo htmlentities($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php endforeach; ?>
    </body>
</html>
