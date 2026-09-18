(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    body.classList.add('transition', 'duration-300', 'ease-out');
    requestAnimationFrame(function () {
      body.classList.remove('opacity-0', 'translate-y-1');
    });

    body.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (!link || link.hasAttribute('data-no-transition')) return;
      var url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin ||
          (url.pathname === window.location.pathname && url.hash) ||
          link.target === '_blank' ||
          event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      body.classList.add('opacity-0', 'translate-y-1');
      setTimeout(function () { window.location.href = link.href; }, 140);
    });
  });
})();
