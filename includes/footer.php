<?php
// includes/footer.php - Global Scripts & Footer
$baseUrl = Auth::getBaseUrl();
$cacheBuster = time();
?>
  <!-- Shared Global JS -->
  <script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= $cacheBuster ?>"></script>
</body>
</html>
