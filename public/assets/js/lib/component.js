(function ($) {
    "use strict";
    var Components = function() {};

    //initializing tooltip
    Components.prototype.initTooltipPlugin = function() {
        $.fn.tooltip && $('[data-toggle="tooltip"]').tooltip()
    },

    //initializing Zopim
    Components.prototype.initzpm = function() {
        if (zpm.length > 1) {
            window.$zopim||(function(d,s){var z=$zopim=function(c){z._.push(c)},$=z.s=
            d.createElement(s),e=d.getElementsByTagName(s)[0];z.set=function(o){z.set.
            _.push(o)};z._=[];z.set._=[];$.async=!0;$.setAttribute("charset","utf-8");
            $.src="//v2.zopim.com/?"+zpm;z.t=+new Date;$.
            type="text/javascript";e.parentNode.insertBefore($,e)})(document,"script");
        }
    },    

    //initializing GA
    Components.prototype.initGA = function() {
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

        ga('create', 'UA-78834277-1', 'auto');
        ga('send', 'pageview');
    },

    //initializing popover
    Components.prototype.initPopoverPlugin = function() {
        $.fn.popover && $('[data-toggle="popover"]').popover()
    },

    //initializing nav bar
    Components.prototype.initNavbar = function() {
        $('.navbar-toggle').on('click', function(event) {
            $(this).toggleClass('open');
            $('#navigation').slideToggle(400);
        });

        $('.navigation-menu>li').slice(-1).addClass('last-elements');
        $('.navigation-menu li.has-submenu a[href="#"]').on('click', function(e) {
            if ($(window).width() < 992) {
                e.preventDefault();
                $(this).parent('li').toggleClass('open').find('.submenu:first').toggleClass('open');
            }
        });
    },

    //initializing nicescroll
    Components.prototype.initNiceScrollPlugin = function() {
        //You can change the color of scroll bar here
        $.fn.niceScroll && $(".nicescroll").niceScroll({ cursorcolor: '#98a6ad', cursorwidth: '6px', cursorborderradius: '5px' });
    },

    Components.prototype.initSelectPicker = function() {
        $('select[value]').each(function () {
            $(this).val(this.getAttribute("value"));
        });
        $('.noselect2').selectpicker('destroy');

        // This is for adding 'selected="true"' on <option> of <select> tag
        var selectTags = $('.selectVal');
        if (selectTags.length > 0) {
            $.each(selectTags, function (i, el) {
                var $el = $(el);

                var optValue = $el.data('value') || [];   // This will contain all the values of select tag
                optValue.forEach(function (val) {
                    $el.find('option[value="' + val + '"]').attr('selected', true);
                });
            });
        }
    },

    Components.prototype.initDatePicker = function() {
        //initialize beautiful date picker
        $.fn.datepicker.defaults.format = "yyyy-mm-dd";
        $("input[type=date]").datepicker({
            format: 'yyyy-mm-dd'
        });
        $("input[type=date]").on('changeDate', function(ev){
            $(this).datepicker('hide');
        });
    },

    Components.prototype.initNavTabs = function() {
        // Javascript to enable link to tab
        var url = document.location.toString();
        if (url.match('#')) {
            $('.nav-tabs a[href="#' + url.split('#')[1] + '"]').tab('show');
        }

        // Change hash for page-reload
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            window.location.hash = e.target.hash;
        });
    },

    Components.prototype.convertTo = function (value, currency, places) {
        if (!places) places = 2;
        
        var ans = 1;
        switch (currency.toLowerCase()) {
            case 'inr':
                places = 3;
                ans = value * 66;
                break;

            case 'pkr':
                places = 3;
                ans = value * 104;
                break;

            case 'aud':
                places = 3;
                ans = (value * 1.3);
                break;

            case 'eur':
                places = 3;
                ans = (value * 0.9);
                break;

            case 'gbp':
                places = 3;
                ans = (value * 0.8);
                break;

        }
        var final = Math.round(ans * Math.pow(10, places)) / Math.pow(10, places);
        return final;
    },

    //initilizing
    Components.prototype.init = function() {
        var $this = this;
        this.initTooltipPlugin(),
        this.initGA(),
        this.initPopoverPlugin(),
        this.initNiceScrollPlugin(),
        this.initSelectPicker(),
        this.initDatePicker(),
        this.initNavTabs(),
        this.initNavbar();
    },

    $.Components = new Components, $.Components.Constructor = Components

}(window.jQuery)),
//initializing main application module
function($) {
    "use strict";
    $.Components.init();
}(window.jQuery);