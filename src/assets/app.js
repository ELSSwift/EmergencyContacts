(function () {
  'use strict';

  document.addEventListener('click', function (ev) {
    var copy = ev.target.closest('[data-copy]');
    if (copy) {
      var input = document.getElementById(copy.getAttribute('data-copy'));
      if (!input) return;
      input.select();
      var done = function () {
        var label = copy.textContent;
        copy.textContent = copy.getAttribute('data-copied') || label;
        setTimeout(function () { copy.textContent = label; }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
      } else {
        document.execCommand('copy');
        done();
      }
      return;
    }
    if (ev.target.closest('[data-print]')) {
      window.print();
    }
  });

  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      ev.preventDefault();
    }
  });
})();
