(function () {
  'use strict';

  const MOBILE_QUERY = '(max-width: 1199.98px), (pointer: coarse) and (max-width: 1366px)';

  function initAdminSidebar() {
    const sidebar = document.querySelector('.sidebar-offcanvas');
    const toggles = Array.from(document.querySelectorAll('[data-toggle="offcanvas"]'));

    if (!sidebar || toggles.length === 0 || sidebar.dataset.offcanvasBound === '1') {
      return;
    }

    sidebar.dataset.offcanvasBound = '1';

    let backdrop = document.querySelector('.admin-sidebar-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('button');
      backdrop.type = 'button';
      backdrop.className = 'admin-sidebar-backdrop';
      backdrop.setAttribute('aria-hidden', 'true');
      backdrop.setAttribute('aria-label', toggles[0].dataset.closeLabel || 'Close navigation');
      document.body.appendChild(backdrop);
    }

    const mobileQuery = window.matchMedia(MOBILE_QUERY);
    let lastToggle = null;

    function setOpen(open, restoreFocus = false) {
      const shouldOpen = Boolean(open && mobileQuery.matches);

      sidebar.classList.toggle('active', shouldOpen);
      document.body.classList.toggle('admin-sidebar-open', shouldOpen);
      backdrop.classList.toggle('is-active', shouldOpen);
      backdrop.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');

      toggles.forEach((toggle) => {
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      });

      if (mobileQuery.matches) {
        sidebar.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
      } else {
        sidebar.removeAttribute('aria-hidden');
      }

      if (!shouldOpen && restoreFocus && lastToggle) {
        lastToggle.focus();
      }
    }

    toggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        lastToggle = toggle;
        setOpen(!sidebar.classList.contains('active'));
      });
    });

    backdrop.addEventListener('click', () => setOpen(false, true));

    sidebar.addEventListener('click', (event) => {
      if (!event.target.closest('a[href]') || !mobileQuery.matches) return;
      setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || !sidebar.classList.contains('active')) return;
      event.preventDefault();
      setOpen(false, true);
    });

    const handleViewportChange = () => {
      if (!mobileQuery.matches) {
        setOpen(false);
        sidebar.removeAttribute('aria-hidden');
        return;
      }

      sidebar.setAttribute('aria-hidden', sidebar.classList.contains('active') ? 'false' : 'true');
    };

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', handleViewportChange);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(handleViewportChange);
    }

    window.addEventListener('orientationchange', handleViewportChange);
    handleViewportChange();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminSidebar, { once: true });
  } else {
    initAdminSidebar();
  }
})();
