(function ($) {
     var shiftDown = false;
     var cacheVersion = 0;
     var shiftEventSet = false;

     var setShiftEvent = function () {
         if (shiftEventSet) {
             return;
         }
         shiftEventSet = true;
         var shiftHandler = function (e) {
             var oldShiftDown = shiftDown;
             if (e.keyCode === 16) {
                 shiftDown = (e.type === "keydown");
             } else {
                 shiftDown = Boolean(e.shiftKey);
             }
         };

         $("html")
             .bind('keydown', shiftHandler)
             .bind('keyup', shiftHandler);
     };

     var getElemIndex = function (rangeInfo, element) {
         var $element = $(element);
         var $elements = rangeInfo.$elements;
         var idx = $element.data('_sc_idx');
         if (rangeInfo.cache && idx !== undefined && $element.data('_sc_cache_version') === cacheVersion) {
             return idx;
         }
         var length = rangeInfo.$elements.length;
         for (var i = 0; i < length; i++) {
             $($elements[i])
                 .data('_sc_idx', i)
                 .data('_sc_cache_version', cacheVersion);

             if ($elements[i] === element) {
                 return i;
             }
         }
         return -1;
     };


     var onChange = function (event) {
         var rangeInfo = event.data;
         var $elements;
         /* Re-select jquery object. */
         if (!rangeInfo.cache) {
             $elements = rangeInfo.$elements = $(rangeInfo.$elements.selector);
         }
         else if (cacheVersion !== rangeInfo.cacheVersion) {
             $elements = rangeInfo.$elements = $(rangeInfo.$elements.selector);
             rangeInfo.cacheVersion = cacheVersion;
             delete rangeInfo.startElement;
             delete rangeInfo.lastElement;
         }

         $elements = rangeInfo.$elements;
         var startElement = rangeInfo.startElement;
         var lastElement = rangeInfo.lastElement;

         if (rangeInfo.inChange) {
             return true;
         }

         rangeInfo.lastElement = this;

         if (!shiftDown) {
             rangeInfo.startElement = this;
             return true;
         }

         rangeInfo.inChange = true;

         var fromIdx = getElemIndex(rangeInfo, startElement),
             toIdx = getElemIndex(rangeInfo, this),
             lastIdx = getElemIndex(rangeInfo, lastElement);

         if (lastIdx === toIdx) {
             rangeInfo.inChange = false;
             return true;
         }

         var lowerIdx = fromIdx + 1, upperIdx = toIdx;
         if (toIdx < fromIdx) {
             lowerIdx = toIdx;
             upperIdx = fromIdx;
         }

         for (var j = lowerIdx; j <= upperIdx; j++) {
             if ($elements[j] !== this) {
                 rangeInfo.click.call($elements[j], rangeInfo.status.call(startElement));
                 $($elements[j]).trigger('change');
             }
         }

         if (lastIdx === fromIdx) {
             rangeInfo.inChange = false;
             return true;
         }

         var useLoop;
         if (toIdx >= fromIdx && lastIdx > toIdx) {
             lowerIdx = toIdx;
             upperIdx = lastIdx;
             useLoop = true;
         } else if (toIdx <= fromIdx && lastIdx < toIdx) {
             lowerIdx = lastIdx;
             upperIdx = toIdx;
             useLoop = true;
         }
         if (useLoop) {
             for (var x = lowerIdx; x <= upperIdx; x++) {
                 if ($elements[x] !== this) {
                     rangeInfo.click.call($elements[x], !rangeInfo.status.call(startElement));
                     $($elements[x]).trigger('change');
                 }
             }
         }

         rangeInfo.inChange = false;
         return true;
     };

     $.fn.shiftClick = function (userOpts) {
         var opts = {
             'delegate': 'body',
             'cache': true,
             'click': function (click) { this.checked = click; },
             'status': function () { return this.checked; }
         };

         if (userOpts === 'clearCache') {
             cacheVersion++;
             return this;
         }
         if (userOpts === undefined) {
             userOpts = {};
         }

         $.extend(opts, userOpts);

         var rangeInfo = {
             '$elements': this,
             'inChange': false,
             'cacheVersion': cacheVersion,
             'click': opts.click,
             'status': opts.status,
             'cache': opts.cache
         };
         setShiftEvent();
         $(opts.delegate).delegate(this.selector,
                                   'change',
                                   rangeInfo,
                                   onChange);
         return this;
     };


})(jQuery);
