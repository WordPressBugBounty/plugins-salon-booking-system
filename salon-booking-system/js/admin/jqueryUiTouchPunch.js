/*!
 * Touch bridge for jQuery UI mouse (list-table sortable).
 * Replaces brittle legacy touch-punch behaviour (ontouchend-only detect +
 * deprecated initMouseEvent) with MouseEvent + maxTouchPoints detection.
 */
(function ($) {
    "use strict";

    if (!$ || !$.ui || !$.ui.mouse) {
        return;
    }

    var mouseProto = $.ui.mouse.prototype;
    if (mouseProto._slnTouchBridgeApplied) {
        return;
    }

    var hasTouch =
        "ontouchend" in document ||
        (typeof navigator !== "undefined" &&
            navigator.maxTouchPoints &&
            navigator.maxTouchPoints > 0) ||
        (typeof window.matchMedia === "function" &&
            window.matchMedia("(pointer: coarse)").matches);

    if (!hasTouch) {
        return;
    }

    var touchHandled = false;
    var hadLegacyPunch = typeof mouseProto._touchStart === "function";

    function simulateMouseEvent(event, type) {
        if (
            !event.originalEvent ||
            !event.originalEvent.changedTouches ||
            !event.originalEvent.changedTouches.length
        ) {
            return;
        }
        if (
            event.originalEvent.touches &&
            event.originalEvent.touches.length > 1
        ) {
            return;
        }

        event.preventDefault();

        var touch = event.originalEvent.changedTouches[0];
        var simulated = new MouseEvent(type, {
            bubbles: true,
            cancelable: true,
            view: window,
            detail: 1,
            screenX: touch.screenX,
            screenY: touch.screenY,
            clientX: touch.clientX,
            clientY: touch.clientY,
            ctrlKey: false,
            altKey: false,
            shiftKey: false,
            metaKey: false,
            button: 0,
            relatedTarget: null,
        });
        event.target.dispatchEvent(simulated);
    }

    // Always install modern handlers (overrides legacy touch-punch methods).
    mouseProto._touchStart = function (event) {
        var self = this;
        if (
            touchHandled ||
            !event.originalEvent.changedTouches ||
            !event.originalEvent.changedTouches.length ||
            !self._mouseCapture(event.originalEvent.changedTouches[0])
        ) {
            return;
        }
        touchHandled = true;
        self._touchMoved = false;
        simulateMouseEvent(event, "mouseover");
        simulateMouseEvent(event, "mousemove");
        simulateMouseEvent(event, "mousedown");
    };

    mouseProto._touchMove = function (event) {
        if (!touchHandled) {
            return;
        }
        this._touchMoved = true;
        simulateMouseEvent(event, "mousemove");
    };

    mouseProto._touchEnd = function (event) {
        if (!touchHandled) {
            return;
        }
        simulateMouseEvent(event, "mouseup");
        simulateMouseEvent(event, "mouseout");
        if (!this._touchMoved) {
            simulateMouseEvent(event, "click");
        }
        touchHandled = false;
    };

    // Legacy punch already bound touch* on _mouseInit — only wrap when needed.
    if (!hadLegacyPunch) {
        var _mouseInit = mouseProto._mouseInit;
        var _mouseDestroy = mouseProto._mouseDestroy;

        mouseProto._mouseInit = function () {
            var self = this;
            self.element.on({
                touchstart: $.proxy(self, "_touchStart"),
                touchmove: $.proxy(self, "_touchMove"),
                touchend: $.proxy(self, "_touchEnd"),
            });
            _mouseInit.call(self);
        };

        mouseProto._mouseDestroy = function () {
            var self = this;
            self.element.off({
                touchstart: $.proxy(self, "_touchStart"),
                touchmove: $.proxy(self, "_touchMove"),
                touchend: $.proxy(self, "_touchEnd"),
            });
            _mouseDestroy.call(self);
        };
    }

    mouseProto._slnTouchBridgeApplied = true;
})(jQuery);
