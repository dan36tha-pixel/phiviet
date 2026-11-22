  </main>

  <footer class="footer">
    <!-- [SỬA] Link về trang chủ dùng ./ thay vì index.html -->
    <p>
      <a href="./" style="color:inherit; text-decoration:none;">
        <?= h(SITE_NAME) ?>
      </a>
      — góc thư giãn của người Việt. Vui lòng chỉ đăng nội dung bạn có quyền chia sẻ.
    </p>
  </footer>

  <!-- [SỬA] JavaScript redirect dự phòng: nếu vẫn còn index.html trong URL, chuyển về URL sạch -->
  <script>
  (function() {
      var path = window.location.pathname;
      if (/index\.html?$/i.test(path)) {
          var newPath = path.replace(/index\.html?$/i, '');
          if (newPath === '' || newPath.slice(-1) !== '/') {
              newPath = newPath.replace(/\/?$/, '/');
          }
          window.location.replace(newPath + window.location.search + window.location.hash);
      }
  })();
  </script>
</body>
</html>
<?php if (!defined('APP_RENDERED')) define('APP_RENDERED', true); ?>
