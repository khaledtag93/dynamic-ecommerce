(function($) {
  'use strict';

  $(function() {
    var $sidebar = $('.sidebar-offcanvas');
    var $toggles = $('[data-toggle="offcanvas"]');
    var $backdrop = $('<button type="button" class="admin-sidebar-backdrop" aria-label="Close navigation"></button>').appendTo('body');

    function setOpen(open) {
      $sidebar.toggleClass('active', open);
      $('body').toggleClass('admin-sidebar-open', open);
      $backdrop.toggleClass('is-active', open).attr('aria-hidden', open ? 'false' : 'true');
      $toggles.attr('aria-expanded', open ? 'true' : 'false');
    }

    $toggles.attr('aria-expanded', $sidebar.hasClass('active') ? 'true' : 'false');

    $toggles.on('click', function() {
      setOpen(!$sidebar.hasClass('active'));
    });

    $backdrop.on('click', function() {
      setOpen(false);
    });

    $sidebar.on('click', 'a[href]', function() {
      if (window.matchMedia('(max-width: 991.98px)').matches) setOpen(false);
    });

    $(document).on('keydown', function(event) {
      if (event.key === 'Escape' && $sidebar.hasClass('active')) {
        setOpen(false);
        $toggles.first().trigger('focus');
      }
    });

    $(window).on('resize', function() {
      if (window.matchMedia('(min-width: 992px)').matches) setOpen(false);
    });
  });
})(jQuery);
