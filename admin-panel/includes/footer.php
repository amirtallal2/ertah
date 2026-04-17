</div><!-- End page-content -->
</main>
</div>

<?php $mainJsVersion = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<script src="<?php echo $assetsPrefix; ?>assets/js/main.js?v=<?php echo $mainJsVersion; ?>"></script>
</body>

</html>
