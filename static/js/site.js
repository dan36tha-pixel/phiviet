/**
 * site.js — tương tác cho bản tĩnh (GitHub Pages).
 * 1. Cổng xác nhận 18+ (localStorage, vì không có PHP session/cookie).
 * 2. Tìm kiếm / lọc chuyên mục / sắp xếp trên trang chủ.
 */
(function () {
  'use strict';

  /* ---------------- 1. Cổng 18+ ---------------- */
  var gate = document.getElementById('ageGate');
  if (gate) {
    var KEY = 'sv_age_ok';
    var accepted = false;
    try {
      accepted = window.localStorage.getItem(KEY) === '1';
    } catch (e) {
      accepted = false;
    }

    if (!accepted) gate.hidden = false;

    var yes = gate.querySelector('[data-age-yes]');
    var no = gate.querySelector('[data-age-no]');

    if (yes) {
      yes.addEventListener('click', function () {
        try {
          window.localStorage.setItem(KEY, '1');
        } catch (e) {
          /* trình duyệt chặn localStorage -> chỉ ẩn trong phiên này */
        }
        gate.hidden = true;
      });
    }
    if (no) {
      no.addEventListener('click', function () {
        window.location.href = 'https://www.google.com/';
      });
    }
  }

  /* ---------------- 2. Tìm kiếm / lọc / sắp xếp ---------------- */
  var grid = document.querySelector('[data-grid]');
  if (!grid) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
  var chips = Array.prototype.slice.call(document.querySelectorAll('[data-cat]'));
  var orders = Array.prototype.slice.call(document.querySelectorAll('[data-order]'));
  var countEl = document.querySelector('[data-count]');
  var emptyEl = document.querySelector('[data-empty]');
  var form = document.querySelector('[data-search-form]');
  var input = form ? form.querySelector('input[name="q"]') : null;

  var state = { q: '', cat: '', order: 'new' };

  // Bỏ dấu tiếng Việt để tìm không cần gõ dấu
  function norm(s) {
    return String(s || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .replace(/Đ/g, 'D')
      .toLowerCase()
      .trim();
  }

  // Đọc ?q= và ?category= từ URL (để link chia sẻ vẫn đúng)
  try {
    var params = new URLSearchParams(window.location.search);
    state.q = params.get('q') || '';
    state.cat = params.get('category') || '';
  } catch (e) {
    /* trình duyệt cũ: bỏ qua */
  }
  if (input) input.value = state.q;

  function syncUrl() {
    if (!window.history || !window.history.replaceState) return;
    var params = new URLSearchParams();
    if (state.q) params.set('q', state.q);
    if (state.cat) params.set('category', state.cat);
    var qs = params.toString();
    window.history.replaceState(null, '', qs ? '?' + qs : window.location.pathname);
  }

  function applyOrder() {
    var sorted = cards.slice().sort(function (a, b) {
      var ia = parseInt(a.getAttribute('data-idx'), 10) || 0;
      var ib = parseInt(b.getAttribute('data-idx'), 10) || 0;
      return state.order === 'old' ? ia - ib : ib - ia;
    });
    var frag = document.createDocumentFragment();
    sorted.forEach(function (c) {
      frag.appendChild(c);
    });
    grid.appendChild(frag);
  }

  function render() {
    var q = norm(state.q);
    var shown = 0;

    cards.forEach(function (card) {
      var haystack = norm(card.getAttribute('data-title') + ' ' + card.getAttribute('data-desc'));
      var matchQ = q === '' || haystack.indexOf(q) !== -1;
      var matchCat = state.cat === '' || card.getAttribute('data-cat') === state.cat;
      var visible = matchQ && matchCat;
      card.hidden = !visible;
      if (visible) shown++;
    });

    applyOrder();

    chips.forEach(function (chip) {
      chip.classList.toggle('active', chip.getAttribute('data-cat') === state.cat);
    });
    orders.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-order') === state.order);
    });

    if (countEl) {
      countEl.textContent =
        shown === 0 ? '' : 'Đang hiển thị ' + shown + '/' + cards.length + ' video';
    }
    if (emptyEl) emptyEl.hidden = shown !== 0;
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      state.cat = chip.getAttribute('data-cat') || '';
      syncUrl();
      render();
    });
  });

  orders.forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.order = btn.getAttribute('data-order') === 'old' ? 'old' : 'new';
      render();
    });
  });

  if (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault(); // lọc ngay tại trang, không tải lại
      state.q = input ? input.value : '';
      syncUrl();
      render();
    });
  }

  if (input) {
    input.addEventListener('input', function () {
      state.q = input.value;
      render();
    });
  }

  render();
})();
