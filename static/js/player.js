/**
 * player.js — phát video HLS (m3u8) cho bản tĩnh.
 * Dùng cùng logic với watch.php: mp4 phát trực tiếp, m3u8 qua hls.js,
 * Safari thì dùng HLS sẵn của trình duyệt.
 */
(function () {
  'use strict';

  function init() {
    var videos = document.querySelectorAll('video[data-m3u8Url], video[data-m3u8url]');

    Array.prototype.forEach.call(videos, function (v) {
      var url = v.dataset.m3u8Url || v.dataset.m3u8url;
      if (!url) return;

      if (/\.mp4($|\?)/i.test(url)) {
        v.src = url;
        return;
      }

      if (window.Hls && window.Hls.isSupported()) {
        var hls = new window.Hls();
        hls.loadSource(url);
        hls.attachMedia(v);
      } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
        v.src = url;
      } else {
        var note = document.createElement('div');
        note.className = 'empty-state';
        note.textContent = 'Trình duyệt không hỗ trợ phát video này.';
        v.replaceWith(note);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
