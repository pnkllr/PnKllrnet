  </div>
  <div class="footer">
    © <?= date('Y') ?> <?= htmlspecialchars(getenv('APP_NAME') ?: 'PnKllrnet') ?> • Built for Twitch tools • <span class="small">ENV: <?= htmlspecialchars(getenv('APP_ENV') ?: 'production') ?></span>
  </div>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/admin-filters.js"></script>
</body>
</html>
