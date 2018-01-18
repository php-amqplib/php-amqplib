$.browser.chrome = /chrome/.test(navigator.userAgent.toLowerCase());
$.browser.ipad   = /ipad/.test(navigator.userAgent.toLowerCase());

/**
 * Initializes page contents for progressive enhancement.
 */
function initializeContents()
{
    // hide all more buttons because they are not needed with JS
    $(".element a.more").hide();

    $(".clickable.class,.clickable.interface,.clickable.trait").click(function() {
        document.location = $("a.more", this).attr('href');
    });

    // change the cursor to a pointer to make it more explicit that this it clickable
    // do a background color change on hover to emphasize the clickability eveb more
    // we do not use CSS for this because when JS is disabled this behaviour does not
    // apply and we do not want the hover
    $(".element.method,.element.function,.element.class.clickable,.element.interface.clickable,.element.trait.clickable,.element.property.clickable")
        .css("cursor", "pointer")
        .hover(function() {
            $(this).css('backgroundColor', '#F8FDF6')
        }, function(){
            $(this).css('backgroundColor', 'white')}
        );

    $("ul.side-nav.nav.nav-list li.nav-header").contents()
        .filter(function(){return this.nodeType == 3 && $.trim($(this).text()).length > 0})
        .wrap('<span class="side-nav-header" />');

    $("ul.side-nav.nav.nav-list li.nav-header span.side-nav-header")
        .css("cursor", "pointer");

    // do not show tooltips on iPad; it will cause the user having to click twice
    if (!$.browser.ipad) {
        $('.btn-group.visibility,.btn-group.view,.btn-group.type-filter,.icon-custom')
            .tooltip({'placement':'bottom'});
        $('.element').tooltip({'placement':'left'});
    }

    $('.btn-group.visibility,.btn-group.view,.btn-group.type-filter')
        .show()
        .css('display', 'inline-block')
        .find('button')
        .find('i').click(function(){ $(this).parent().click(); });

    // set the events for the visibility buttons and enable by default.
    function toggleVisibility(event)
    {
        // because the active class is toggled _after_ this event we toggle it for the duration of this event. This
        // will make the next piece of code generic
        if (event) {
            $(this).toggleClass('active');
        }

        $('.element.public,.side-nav li.public').toggle($('.visibility button.public').hasClass('active'));
        $('.element.protected,.side-nav li.protected').toggle($('.visibility button.protected').hasClass('active'));
        $('.element.private,.side-nav li.private').toggle($('.visibility button.private').hasClass('active'));
        $('.element.public.inherited,.side-nav li.public.inherited').toggle(
            $('.visibility button.public').hasClass('active') && $('.visibility button.inherited').hasClass('active')
        );
        $('.element.protected.inherited,.side-nav li.protected.inherited').toggle(
            $('.visibility button.protected').hasClass('active') && $('.visibility button.inherited').hasClass('active')
        );
        $('.element.private.inherited,.side-nav li.private.inherited').toggle(
            $('.visibility button.private').hasClass('active') && $('.visibility button.inherited').hasClass('active')
        );

        // and untoggle the active class again so that bootstrap's default handling keeps working
        if (event) {
            $(this).toggleClass('active');
        }
    }
    $('.visibility button.public').on("click", toggleVisibility);
    $('.visibility button.protected').on("click", toggleVisibility);
    $('.visibility button.private').on("click", toggleVisibility);
    $('.visibility button.inherited').on("click", toggleVisibility);
    toggleVisibility();

    $('.type-filter button.critical').click(function() {
        packageContentDivs = $('.package-contents');
        packageContentDivs.show();
        $('tr.critical').toggle($(this).hasClass('active'));
        packageContentDivs.each(function() {
            var rowCount = $(this).find('tbody tr:visible').length;

            $(this).find('.badge-info').html(rowCount);
            $(this).toggle(rowCount > 0);
        });
    });
    $('.type-filter button.error').click(function(){
        packageContentDivs = $('.package-contents');
        packageContentDivs.show();
        $('tr.error').toggle($(this).hasClass('active'));
        packageContentDivs.each(function() {
            var rowCount = $(this).find('tbody tr:visible').length;

            $(this).find('.badge-info').html(rowCount);
            $(this).toggle(rowCount > 0);
        });
    });
    $('.type-filter button.notice').click(function(){
        packageContentDivs = $('.package-contents');
        packageContentDivs.show();
        $('tr.notice').toggle($(this).hasClass('active'));
        packageContentDivs.each(function() {
            var rowCount = $(this).find('tbody tr:visible').length;

            $(this).find('.badge-info').html(rowCount);
            $(this).toggle(rowCount > 0);
        });
    });

    $('.view button.details').click(function(){
        $('.side-nav li.view-simple').removeClass('view-simple');
    }).button('toggle').click();

    $('.view button.simple').click(function(){
        $('.side-nav li').addClass('view-simple');
    });
    
    $('ul.side-nav.nav.nav-list li.nav-header span.side-nav-header').click(function(){
        $(this).siblings('ul').collapse('toggle');
    });

// sorting example
//    $('ol li').sort(
//        function(a, b) { return a.innerHTML.toLowerCase() > b.innerHTML.toLowerCase() ? 1 : -1; }
//    ).appendTo('ol');
}

$(document).ready(function() {
    prettyPrint();

    initializeContents();

    // do not show tooltips on iPad; it will cause the user having to click twice
    if(!$.browser.ipad) {
        $(".side-nav a").tooltip({'placement': 'top'});
    }

    // chrome cannot deal with certain situations; warn the user about reduced features
    if ($.browser.chrome && (window.location.protocol == 'file:')) {
        $("body > .container").prepend(
            '<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a>' +
            'You are using Google Chrome in a local environment; AJAX interaction has been ' +
            'disabled because Chrome cannot <a href="http://code.google.com/p/chromium/issues/detail?id=40787">' +
            'retrieve files using Ajax</a>.</div>'
        );
    }

    $('ul.nav-namespaces li a, ul.nav-packages li a').click(function(){
        // Google Chrome does not do Ajax locally
        if ($.browser.chrome && (window.location.protocol == 'file:'))
        {
            return true;
        }

        $(this).parents('.side-nav').find('.active').removeClass('active');
        $(this).parent().addClass('active');
        $('div.namespace-contents').load(
            this.href + ' div.namespace-contents', function(){
                initializeContents();
                $(window).scrollTop($('div.namespace-contents').position().top);
            }
        );
        $('div.package-contents').load(
            this.href + ' div.package-contents', function(){
                initializeContents();
                $(window).scrollTop($('div.package-contents').position().top);
            }
        );

        return false;
    });

    function filterPath(string)
    {
        return string
            .replace(/^\//, '')
            .replace(/(index|default).[a-zA-Z]{3,4}$/, '')
            .replace(/\/$/, '');
    }

    var locationPath = filterPath(location.pathname);

    // the ipad already smoothly scrolls and does not detect the scrollable
    // element if top=0; as such we disable this behaviour for the iPad
    if (!$.browser.ipad) {
        $('a[href*=#]').each(function ()
        {
            var thisPath = filterPath(this.pathname) || locationPath;
            if (locationPath == thisPath && (location.hostname == this.hostname || !this.hostname) && this.hash.replace(/#/, ''))
            {
                var target = decodeURIComponent(this.hash.replace(/#/,''));
                // note: I'm using attribute selector, because id selector can't match elements with '$' 
                var $target = $('[id="'+target+'"]');

                if ($target.length > 0)
                {
                    $(this).click(function (event)
                    {
                        var scrollElem = scrollableElement('html', 'body');
                        var targetOffset = $target.offset().top;

                        event.preventDefault();
                        $(scrollElem).animate({scrollTop:targetOffset}, 400, function ()
                        {
                            location.hash = target;
                        });
                    });
                }
            }
        });
    }

    // use the first element that is "scrollable"
    function scrollableElement(els)
    {
        for (var i = 0, argLength = arguments.length; i < argLength; i++)
        {
            var el = arguments[i], $scrollElement = $(el);
            if ($scrollElement.scrollTop() > 0)
            {
                return el;
            }
            else
            {
                $scrollElement.scrollTop(1);
                var isScrollable = $scrollElement.scrollTop() > 0;
                $scrollElement.scrollTop(0);
                if (isScrollable)
                {
                    return el;
                }
            }
        }
        return [];
    }

    // Hide API Documentation menu if it's empty
    $('.nav .dropdown a[href=#api]').next().filter(function(el) {
        if ($(el).children().length == 0) {
            return true;
        }
    }).parent().hide();
});
