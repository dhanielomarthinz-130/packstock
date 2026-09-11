<?php
// includes/footer.php - Global Scripts & Footer
$baseUrl = Auth::getBaseUrl();
$cacheBuster = time();
?>
  <!--
    Shared Global JS.
    Halaman admin dan operator sudah memuat app.js sendiri lebih dulu (sebelum
    admin.js / operator.js yang bergantung padanya), lalu menandainya lewat
    $appJsAlreadyLoaded. Tanpa penjaga ini app.js termuat dua kali dan browser
    melempar "Identifier 'App' has already been declared".
  -->
<?php if (empty($appJsAlreadyLoaded)): ?>
  <script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= $cacheBuster ?>"></script>
<?php endif; ?>
</body>
</html>
