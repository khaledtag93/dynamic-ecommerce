(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const banner = document.getElementById('proBanner');
    if (!banner) return;

    const navbar = document.querySelector('.navbar');
    const pageBody = document.querySelector('.page-body-wrapper');
    const closeButton = document.getElementById('bannerClose');
    const storageKey = 'majestic-pro-banner';

    let dismissed = false;
    try {
      dismissed = window.localStorage.getItem(storageKey) === 'true';
    } catch (error) {
      dismissed = false;
    }

    function syncBannerState(isDismissed) {
      banner.classList.toggle('d-none', isDismissed);
      banner.classList.toggle('d-flex', !isDismissed);

      if (!navbar || !pageBody) return;

      navbar.classList.toggle('fixed-top', isDismissed);
      navbar.classList.toggle('pt-5', !isDismissed);
      navbar.classList.toggle('mt-3', !isDismissed);
      pageBody.classList.toggle('pt-0', !isDismissed);
      pageBody.classList.toggle('proBanner-padding-top', isDismissed);
    }

    syncBannerState(dismissed);

    closeButton?.addEventListener('click', function () {
      try {
        window.localStorage.setItem(storageKey, 'true');
      } catch (error) {
        // Storage can be unavailable in private/restricted browsing; UI still closes safely.
      }

      syncBannerState(true);
    });
  }, { once: true });
})();
