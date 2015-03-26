/*! Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

(function(Icinga, $) {

    "use strict";

    var activeMenuId;

    var activeHoverTimeout;
    var activeHoverItem;
    var activeHoverMenu;

    Icinga.Behaviors = Icinga.Behaviors || {};

    var Navigation = function (icinga) {
        Icinga.EventListener.call(this, icinga);
        this.on('click', '#menu a', this.linkClicked, this);
        this.on('click', '#menu tr[href]', this.linkClicked, this);
        this.on('mouseenter', 'li.dropdown', this.dropdownHover, this);
        this.on('mouseleave', 'li.dropdown', this.dropdownLeave, this);
        this.on('mouseenter', '#menu > nav > ul > li', this.menuTitleHovered, this);
        this.on('mouseleave', '#sidebar', this.leaveSidebar, this);
        this.on('rendered', this.onRendered);

        // show dropdown content when tabbing through the menu to support keyboard navigation
        this.on('focusin', 'ul.tabs > li.dropdown a.dropdown-toggle', this.dropdownFocus, this);

        // hide the dropdown content when tabbing away from a list element, to ensure
        // that it always closes when no longer focused
        this.on('focusout', 'ul.tabs li.hover ul.dropdown-menu li', this.dropdownFocusOut, this);
        this.on('focusout', 'ul.tabs > li.dropdown a.dropdown-toggle', this.dropdownFocusOut, this);
    };
    Navigation.prototype = new Icinga.EventListener();

    Navigation.prototype.dropdownFocus = function() {
        $(this).closest('li').addClass('hover');
    };

    Navigation.prototype.dropdownFocusOut = function(evt) {
        var self = this;
        if (! evt.relatedTarget || ! $(evt.relatedTarget).closest('ul.dropdown-menu').length) {

            // Close the toolbar after a small timeout to not interrupt the focus transition that
            // is currently going on
            activeHoverTimeout = setTimeout(function() {
                activeHoverItem = null;
                $(self).closest('li.hover').removeClass('hover');
            }, 500);
        } else {
            activeHoverItem = icinga.utils.getDomPath(evt.relatedTarget);
            activeHoverMenu = icinga.utils.getDomPath($(self).closest('li.hover')[0]);
        }
    };

    Navigation.prototype.onRendered = function(evt) {
        // get original source element of the rendered-event
        var el = evt.target;
        if (activeMenuId) {
            // restore old menu state
            $('#menu li.active', el).removeClass('active');
            var $selectedMenu = $('#' + activeMenuId).addClass('active');
            var $outerMenu = $selectedMenu.parent().closest('li');
            if ($outerMenu.size()) {
                $outerMenu.addClass('active');
            }

            /*
              Recreate the html content of the menu item to force the browser to update the layout, or else
              the link would only be visible as active after another click or page reload in Gecko and WebKit.

              fixes #7897
            */
            $selectedMenu.html($selectedMenu.html());

        } else {
            // store menu state
            var $menus = $('#menu li.active', el);
            if ($menus.size()) {
                activeMenuId = $menus[0].id;
                $menus.find('li.active').first().each(function () {
                    activeMenuId = this.id;
                });
            }
        }

        if (activeHoverItem && activeHoverMenu) {
            $(icinga.utils.getElementByDomPath(activeHoverItem)).focus();
            $(icinga.utils.getElementByDomPath(activeHoverMenu)).addClass('hover');
        }
        if (activeHoverTimeout) {
            clearTimeout(activeHoverTimeout);
            activeHoverTimeout = 0;
        }
    };

    Navigation.prototype.linkClicked = function(event) {
        var $a = $(this);
        var href = $a.attr('href');
        var $li;
        var icinga = event.data.self.icinga;

        if (href.match(/#/)) {
            // ...it may be a menu section without a dedicated link.
            // Switch the active menu item:
            $li = $a.closest('li');
            $('#menu .active').removeClass('active');
            $li.addClass('active');
            activeMenuId = $($li).attr('id');
            if ($li.hasClass('hover')) {
                $li.removeClass('hover');
            }
            if (href === '#') {
                // Allow to access dropdown menu by keyboard
                if ($a.hasClass('dropdown-toggle')) {
                    $a.closest('li').toggleClass('hover');
                }
                return;
            }
        } else {
            activeMenuId = $(event.target).closest('li').attr('id');
        }
        // update target url of the menu container to the clicked link
        var $menu = $('#menu');
        var menuDataUrl = icinga.utils.parseUrl($menu.data('icinga-url'));
        menuDataUrl = icinga.utils.addUrlParams(menuDataUrl.path, { url: href });
        $menu.data('icinga-url', menuDataUrl);
    };

    Navigation.prototype.setActiveByUrl = function(url) {
        this.resetActive();
        this.setActive($('#menu [href="' + url + '"]'));
    }

    /**
     * Change the active menu element
     *
     * @param $el   {jQuery}    A selector pointing to the active element
     */
    Navigation.prototype.setActive = function($el) {

        $el.closest('li').addClass('active');
        $el.parents('li').addClass('active');
        activeMenuId = $el.closest('li').attr('id');
    };

    Navigation.prototype.resetActive = function() {
        $('#menu .active').removeClass('active');
        activeMenuId = null;
    };

    Navigation.prototype.menuTitleHovered = function(event) {
        var $li = $(this),
            delay = 800,
            self = event.data.self;

        if ($li.hasClass('active')) {
            $li.siblings().removeClass('hover');
            return;
        }
        if ($li.children('ul').children('li').length === 0) {
            return;
        }
        if ($('#menu').scrollTop() > 0) {
            return;
        }

        if ($('#layout').hasClass('hoveredmenu')) {
            delay = 0;
        }

        setTimeout(function () {
            try {
                if (!$li.is('li:hover')) {
                    return;
                }
                if ($li.hasClass('active')) {
                    return;
                }
            } catch(e) { /* Bypass because if IE8 */ }

            $li.siblings().each(function () {
                var $sibling = $(this);
                try {
                    if ($sibling.is('li:hover')) {
                        return;
                    }
                } catch(e) { /* Bypass because if IE8 */ };
                if ($sibling.hasClass('hover')) {
                    $sibling.removeClass('hover');
                }
            });
            self.hoverElement($li);
        }, delay);
    };

    Navigation.prototype.leaveSidebar = function (event) {
        var $sidebar = $(this),
            $li = $sidebar.find('li.hover'),
            self = event.data.self;
        if (! $li.length) {
            $('#layout').removeClass('hoveredmenu');
            return;
        }

        setTimeout(function () {
            try {
                if ($li.is('li:hover') || $sidebar.is('sidebar:hover')) {
                    return;
                }
            } catch(e) { /* Bypass because if IE8 */ };
            $li.removeClass('hover');
            $('#layout').removeClass('hoveredmenu');
        }, 500);
    };

    Navigation.prototype.hoverElement = function ($li)  {
        $('#layout').addClass('hoveredmenu');
        $li.addClass('hover');
    };

    Navigation.prototype.dropdownHover = function () {
        $(this).addClass('hover');
    };

    Navigation.prototype.dropdownLeave = function (event) {
        var $li = $(this),
            self = event.data.self;
        setTimeout(function () {
            // TODO: make this behave well together with keyboard navigation
            try {
                if (!$li.is('li:hover') /*&& ! $li.find('a:focus')*/) {
                    $li.removeClass('hover');
                }
            } catch(e) { /* Bypass because if IE8 */ }
        }, 300);
    };
    Icinga.Behaviors.Navigation = Navigation;

}) (Icinga, jQuery);
