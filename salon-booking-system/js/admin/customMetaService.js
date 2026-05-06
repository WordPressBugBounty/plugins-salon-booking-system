"use strict";

jQuery(function ($) {
    var url = location.search;
    if (url.indexOf("post_type=sln_service") > -1) {
        sln_initServiceManagement($);
    }
    if (url.indexOf("taxonomy=sln_service_category") > -1) {
        sln_initServiceCategoryManagement($);
    }
    if (url.indexOf("post_type=sln_attendant") > -1) {
        sln_initAttendantManagement($);
    }
    sln_dataAttendant($);
    function attendantsListSkills() {
        $(".sln-service__collapse").each(function () {
            var parent = $(this),
                trigger = $(this).next(".sln-service__collapsetrigger");
            console.log(trigger.text());
            trigger.on("click", function (e) {
                parent.toggleClass("open");
                parent.toggleClass("closed");
                $(this).toggleClass("less");
                console.log(trigger.text());
                e.preventDefault();
            });
        });
        $("#_sln_attendant_services").on("select2:opening", function (e) {
            $(this).parent().removeClass("closed").addClass("open");
        });
    }
    if ($(".sln-service__collapse").length) {
        attendantsListSkills();
    }

    if ($("#_sln_service_multiple_attendants_for_service").is(":checked")) {
        $("#_sln_service_variable_price_enabled")
            .prop("checked", 0)
            .trigger("change");
    }

    $("#_sln_service_variable_price_enabled")
        .on("change", function () {
            if (
                $("#_sln_service_multiple_attendants_for_service").is(
                    ":checked",
                )
            ) {
                $(this).prop("checked", 0);
            }
            $(this)
                .closest(".sln-variable-price")
                .find(".sln-variable-price-attendants")
                .toggleClass("hide", !$(this).is(":checked"));
        })
        .trigger("change");

    $("#_sln_service_offset_for_service")
        .on("change", function () {
            $(this)
                .closest(".row")
                .find(".sln-service-offset-interval")
                .toggleClass("hide", !$(this).prop("checked"));
        })
        .trigger("change");

    $("#_sln_service_lock_for_service")
        .on("change", function () {
            $(this)
                .closest(".row")
                .find(".sln-service-lock-interval")
                .toggleClass("hide", !$(this).prop("checked"));
        })
        .trigger("change");

    $("#_sln_service_multiple_attendants_for_service")
        .on("change", function () {
            $(this)
                .closest(".row")
                .find(".sln-multiple-count-attendants")
                .toggleClass("hide", !$(this).prop("checked"));
            if (
                $("#_sln_service_variable_price_enabled").is(":checked") &&
                $(this).is(":checked")
            ) {
                $("#_sln_service_variable_price_enabled")
                    .prop("checked", 0)
                    .trigger("change");
            }
        })
        .trigger("change");

    $("#_sln_service_duration").on("change", function () {
        if ($(this).val() === "00:00") {
            $("#_sln_service_secondary").prop("checked", true);
        }
    });
});

/**
 * WordPress admin list tables: tbody#the-list + jquery-ui-sortable (core script).
 * Sortable is bound to #the-list only; handle is a dedicated column (row actions
 * and title links stay usable — cancel + handle pattern).
 */

function sln_sortableListRowStart(ui) {
    jQuery("body").addClass("sln-is-sorting-list-row");
    ui.item.data("startindex", ui.item.index());
}

function sln_sortableListRowStop() {
    jQuery("body").removeClass("sln-is-sorting-list-row");
}

function sln_wpListTableTbody($) {
    var $tb = $("#the-list");
    if ($tb.length && $tb.is("tbody")) {
        return $tb;
    }
    return $("table.wp-list-table > tbody").first();
}

/**
 * Resolve the row being dragged. WordPress bundles jQuery UI where the sortable
 * `helper` function is often invoked as `fn.call(tbody, event)` only — no `ui`
 * argument — so `ui.item` is undefined and the clone was wrong (e.g. row actions
 * only). Prefer `ui.item` when present; otherwise `closest("tr")` from the event
 * (mousedown on .sln-list-sort-handle is inside the row).
 */
function sln_wpListTableResolveDragRow($tbody, event, ui) {
    var $row = null;
    if (ui && ui.item && ui.item.length && ui.item.parent()[0] === $tbody[0]) {
        $row = ui.item;
    } else if (event && event.target) {
        $row = jQuery(event.target).closest("tr");
        if (!$row.length || $row.parent()[0] !== $tbody[0]) {
            $row = null;
        }
    }
    return $row && $row.length ? $row : jQuery();
}

/**
 * Drag preview: full row clone inside a real <table> on body (orphan <tr> breaks layout).
 */
function sln_wpListTableRowDragHelperFromTr($tr) {
    if (!$tr || !$tr.length) {
        return jQuery("<table>", { class: "sln-list-sort-helper-table" }).append(
            jQuery("<tbody>").append(
                jQuery("<tr>").append(jQuery("<td>").text("—")),
            ),
        )[0];
    }
    var $tableOrig = $tr.closest("table.wp-list-table");
    var $origCells = $tr.children("td, th");
    var tw = $tableOrig.length ? $tableOrig.outerWidth() : $tr.outerWidth();
    var $wrap = jQuery("<table>", {
        class: "sln-list-sort-helper-table widefat",
        css: {
            "table-layout": "fixed",
            "border-collapse": "collapse",
            width: tw + "px",
        },
    });
    var $clone = $tr.clone();
    $origCells.each(function (i) {
        var w = jQuery(this).outerWidth();
        $clone.children("td, th").eq(i).css({
            width: w + "px",
            maxWidth: w + "px",
            boxSizing: "border-box",
        });
    });
    $clone.find(".row-actions").empty();
    var $cg = $tableOrig.find("colgroup").first().clone();
    if ($cg.length) {
        $wrap.prepend($cg);
    }
    $wrap.append(jQuery("<tbody></tbody>").append($clone));
    return $wrap[0];
}

function sln_wpListTableSortableStart(event, ui) {
    var $orig = ui.item;
    var rowH = Math.max(1, Math.round($orig.outerHeight()));
    if (ui.placeholder && ui.placeholder.length) {
        $orig.children("td, th").each(function (idx) {
            var w = jQuery(this).outerWidth();
            ui.placeholder.children("td, th").eq(idx).width(w);
        });
        ui.placeholder.height(rowH).css({
            maxHeight: rowH + "px",
            lineHeight: "normal",
        });
    }
    sln_sortableListRowStart(ui);
}

function sln_wpListTableSortableAjaxReorder(action, method, positionsArray) {
    return jQuery.ajax({
        type: "POST",
        url: ajaxurl,
        dataType: "json",
        data: {
            action: action,
            method: method,
            data: "positions=" + positionsArray,
        },
    });
}

function sln_wpListTableOrderedPostPairs($tbody) {
    var i = 1;
    var parts = [];
    $tbody.find("tr").each(function () {
        var id = this.id || "";
        if (id.indexOf("post-") === 0) {
            parts.push(id.slice(5) + "_" + i);
            i++;
        }
    });
    return parts;
}

function sln_wpListTableOrderedTagIds($tbody) {
    var parts = [];
    $tbody.find("tr").each(function () {
        var id = this.id || "";
        if (id.indexOf("tag-") === 0) {
            parts.push(id.slice(4));
        }
    });
    return parts;
}

/**
 * @param {jQuery} $tbody
 * @param {function(jQuery.Event, Object, jQuery)} onStopReordered — called when order changed; receives (event, ui, $tbody).
 */
function sln_wpListTableSortableAppendTarget() {
    var $w = jQuery("#wpbody-content");
    return $w.length ? $w[0] : document.body;
}

/**
 * Same breakpoint as WP admin (782px): show grip column; above that column is hidden in CSS.
 * Desktop uses full-row drag (handle false) so reorder still works without the icon.
 */
function sln_wpListTableSortableUseGripHandle() {
    if (typeof window.matchMedia !== "function") {
        return true;
    }
    return window.matchMedia("(max-width: 782px)").matches;
}

function sln_wpListTableBindSortable($tbody, onStopReordered) {
    if (!$tbody.length || !$tbody.find(".sln-list-sort-handle").length) {
        return;
    }
    if ($tbody.hasClass("ui-sortable")) {
        try {
            $tbody.sortable("destroy");
        } catch (ignore) {
            /* already destroyed or incompatible state */
        }
    }
    var $listTable = $tbody.closest("table.wp-list-table");
    var useGrip = sln_wpListTableSortableUseGripHandle();
    var cancelSel =
        "input,textarea,button,select,option,.row-actions,.check-column";
    if (!useGrip) {
        cancelSel += ",a,label,.row-title";
    }
    $tbody.sortable({
        handle: useGrip ? ".sln-list-sort-handle" : false,
        axis: "y",
        distance: 8,
        scroll: true,
        scrollSensitivity: 40,
        items: "> tr:not(.no-items):not(.inline-edit-row)",
        cancel: cancelSel,
        appendTo: sln_wpListTableSortableAppendTarget(),
        helper: function (event, ui) {
            var $row = sln_wpListTableResolveDragRow($tbody, event, ui);
            return sln_wpListTableRowDragHelperFromTr($row);
        },
        start: function (event, ui) {
            sln_wpListTableSortableStart(event, ui);
            if (ui.helper && ui.helper.length && $listTable.length) {
                ui.helper.outerWidth($listTable.outerWidth());
            }
        },
        sort: function (event, ui) {
            if (ui.helper && ui.helper.length && $listTable.length) {
                ui.helper.outerWidth($listTable.outerWidth());
            }
        },
        stop: function (event, ui) {
            sln_sortableListRowStop();
            var startIndex = ui.item.data("startindex") + 1;
            var newIndex = ui.item.index() + 1;
            if (newIndex !== startIndex) {
                onStopReordered(event, ui, $tbody);
            }
            ui.item.children("td, th").css("width", "");
        },
    });
}

function sln_initServiceManagement($) {
    var $tbody = sln_wpListTableTbody($);
    sln_wpListTableBindSortable($tbody, function (event, ui, $tb) {
        sln_wpListTableSortableAjaxReorder(
            "sln_service",
            "save_position",
            sln_wpListTableOrderedPostPairs($tb),
        ).done(function (msg) {
            if (window.console && console.log) {
                console.log(msg);
            }
        });
    });
}

function sln_initServiceCategoryManagement($) {
    var $tbody = sln_wpListTableTbody($);
    sln_wpListTableBindSortable($tbody, function (event, ui, $tb) {
        sln_wpListTableSortableAjaxReorder(
            "sln_service",
            "save_cat_position",
            sln_wpListTableOrderedTagIds($tb),
        ).done(function (msg) {
            if (window.console && console.log) {
                console.log(msg);
            }
        });
    });
}

function sln_initAttendantManagement($) {
    var $tbody = sln_wpListTableTbody($);
    sln_wpListTableBindSortable($tbody, function (event, ui, $tb) {
        sln_wpListTableSortableAjaxReorder(
            "sln_attendant",
            "save_position",
            sln_wpListTableOrderedPostPairs($tb),
        ).done(function (msg) {
            if (window.console && console.log) {
                console.log(msg);
            }
        });
    });
}

function sln_dataAttendant($) {
    $("select[data-attendant]").each(function () {
        var serviceVal = $(this).attr("data-service");
        var attendantVal = $(this).val();
        var selectHtml = "";
        if (jQuery.inArray(attendantVal, ["", "0"]) !== false) {
            selectHtml += '<option value="" selected >n.d.</option>';
        }
        $.each(servicesData[serviceVal].attendants, function (index, value) {
            selectHtml +=
                '<option value="' +
                value +
                '" ' +
                (value == attendantVal ? "selected" : "") +
                " >" +
                attendantsData[value] +
                "</option>";
        });
        $(this).html(selectHtml).trigger("change");
    });

    // Show/hide max variable duration field based on variable duration checkbox
    $('#_sln_service_variable_duration').on('change', function () {
        if ($(this).is(':checked')) {
            $('.sln-service-max-variable-duration-wrapper').removeClass('hide');
        } else {
            $('.sln-service-max-variable-duration-wrapper').addClass('hide');
        }
    });

    // Initialize on page load
    if ($('#_sln_service_variable_duration').is(':checked')) {
        $('.sln-service-max-variable-duration-wrapper').removeClass('hide');
    } else {
        $('.sln-service-max-variable-duration-wrapper').addClass('hide');
    }
}
