"use strict";

jQuery(function($) {
    $("#color-background").colorpicker({
        format: "rgb",
        customClass: "sln-colorpicker-widget",
        sliders: {
            saturation: {
                maxLeft: 160,
                maxTop: 160
            },
            hue: {
                maxTop: 160
            },
            alpha: {
                maxTop: 160
            }
        },
        colorSelectors: {
            "rgba(255,255,255,1)": "rgba(255,255,255,1)",
            "rgba(0,0,0,1)": "rgba(0,0,0,1)",
            "rgba(2,119,189,1)": "rgba(2,119,189,1)"
        }
    });
    $("#color-main").colorpicker({
        format: "rgb",
        customClass: "sln-colorpicker-widget",
        sliders: {
            saturation: {
                maxLeft: 160,
                maxTop: 160
            },
            hue: {
                maxTop: 160
            },
            alpha: {
                maxTop: 160
            }
        },
        colorSelectors: {
            "rgba(2,119,189,1)": "rgba(2,119,189,1)"
        }
    });
    $("#color-text").colorpicker({
        format: "rgb",
        customClass: "sln-colorpicker-widget",
        sliders: {
            saturation: {
                maxLeft: 160,
                maxTop: 160
            },
            hue: {
                maxTop: 160
            },
            alpha: {
                maxTop: 160
            }
        },
        colorSelectors: {
            "rgba(68,68,68,1)": "rgba(68,68,68,1)",
            "rgba(0,0,0,1)": "rgba(0,0,0,1)",
            "rgba(255,255,255,1)": "rgba(255,255,255,1)"
        }
    });

    var color_background = $("#color-background input").val(),
        color_main = $("#color-main input").val(),
        color_text = $("#color-text input").val();
    $("#color-background-a").val(color_background);
    $("#color-main-a").val(color_main);
    $("#color-text-a").val(color_text);

    var bgAlphaB = 0.5,
        bgAlphaC = 0.25,
        bgAlphaD = 0.1,
        bgVal = $("#color-background-a").val(),
        c = bgVal.slice(4).split(","),
        bgShadeB =
            "rgba(" +
            c[0] +
            "," +
            parseInt(c[1]) +
            "," +
            parseInt(c[2]) +
            "," +
            bgAlphaB +
            ")",
        bgShadeC =
            "rgba(" +
            c[0] +
            "," +
            parseInt(c[1]) +
            "," +
            parseInt(c[2]) +
            "," +
            bgAlphaC +
            ")",
        bgShadeD =
            "rgba(" +
            c[0] +
            "," +
            parseInt(c[1]) +
            "," +
            parseInt(c[2]) +
            "," +
            bgAlphaD +
            ")";
    $("#color-background-b").val(bgShadeB);
    $("#color-background-c").val(bgShadeC);
    $("#color-background-d").val(bgShadeD);
    var mainAlphaB = 0.75,
        mainAlphaC = 0.5,
        mainAlphaD = 0.25,
        mainVal = $("#color-main-a").val(),
        a = mainVal.slice(4).split(","),
        mainShadeB =
            "rgba(" +
            a[0] +
            "," +
            parseInt(a[1]) +
            "," +
            parseInt(a[2]) +
            "," +
            mainAlphaB +
            ")",
        mainShadeC =
            "rgba(" +
            a[0] +
            "," +
            parseInt(a[1]) +
            "," +
            parseInt(a[2]) +
            "," +
            mainAlphaC +
            ")",
        mainShadeD =
            "rgba(" +
            a[0] +
            "," +
            parseInt(a[1]) +
            "," +
            parseInt(a[2]) +
            "," +
            mainAlphaD +
            ")";
    $("#color-main-b").val(mainShadeB);
    $("#color-main-c").val(mainShadeC);
    $("#color-main-d").val(mainShadeD);
    var textAlphaB = 0.75,
        textAlphaC = 0.5,
        textAlphaD = 0.25,
        textAlphaE = 0.1,
        textVal = $("#color-text-a").val(),
        b = textVal.slice(4).split(","),
        textShadeB =
            "rgba(" +
            b[0] +
            "," +
            parseInt(b[1]) +
            "," +
            parseInt(b[2]) +
            "," +
            textAlphaB +
            ")",
        textShadeC =
            "rgba(" +
            b[0] +
            "," +
            parseInt(b[1]) +
            "," +
            parseInt(b[2]) +
            "," +
            textAlphaC +
            ")",
        textShadeD =
            "rgba(" +
            b[0] +
            "," +
            parseInt(b[1]) +
            "," +
            parseInt(b[2]) +
            "," +
            textAlphaD +
            ")",
        textShadeE =
            "rgba(" +
            b[0] +
            "," +
            parseInt(b[1]) +
            "," +
            parseInt(b[2]) +
            "," +
            textAlphaE +
            ")";
    $("#color-text-b").val(textShadeB);
    $("#color-text-c").val(textShadeC);
    $("#color-text-d").val(textShadeD);
    $("#color-text-e").val(textShadeE);
    $(".sln-colors-sample .wrapper").css("background-color", color_background);
    $(".sln-colors-sample h1").css("color", color_main);
    $(".sln-colors-sample button").css("background-color", color_main);
    $(".sln-colors-sample button").css("color", color_background);
    $(".sln-colors-sample input").css("border-color", color_main);
    $(".sln-colors-sample input").css("color", color_main);
    $(".sln-colors-sample input").css("background-color", color_background);
    $(".sln-colors-sample p").css("color", color_text);
    $(".sln-colors-sample p + p").css("color", color_text).css("background-color", textShadeE).css("padding", '1em');
    $(".sln-colors-sample label").css("color", mainShadeB);
    $(".sln-colors-sample small").css("color", textShadeB);

    $("#color-background")
        .colorpicker()
        .on("changeColor", function(e) {
            //$(".sln-colors-sample .wrapper")[0].style.backgroundColor = e.color;
            //$(".sln-colors-sample input")[0].style.backgroundColor = e.color;
            //$(".sln-colors-sample button")[0].style.color = e.color;
            //$("#color-background-a").val(e.color);
            var bgAlphaB = 0.5,
            bgAlphaC = 0.25,
            bgAlphaD = 0.1,
            bum = e.color;
            $(".sln-colors-sample .wrapper")[0].style.backgroundColor = e.color;
            $(".sln-colors-sample input")[0].style.backgroundColor = e.color;
            $(".sln-colors-sample button")[0].style.color = e.color;
            $("#color-background-a").val(bum);
            var bgVal = $("#color-background-a").val(),
            c = bgVal.slice(4).split(","),
            bgShadeB =
                "rgba" +
                c[0] +
                "," +
                parseInt(c[1]) +
                "," +
                parseInt(c[2]) +
                "," +
                bgAlphaB +
                ")",
            bgShadeC =
                "rgba" +
                c[0] +
                "," +
                parseInt(c[1]) +
                "," +
                parseInt(c[2]) +
                "," +
                bgAlphaC +
                ")",
            bgShadeD =
                "rgba" +
                c[0] +
                "," +
                parseInt(c[1]) +
                "," +
                parseInt(c[2]) +
                "," +
                bgAlphaD +
                ")";
            $("#color-background-b").val(bgShadeB);
            $("#color-background-c").val(bgShadeC);
            $("#color-background-d").val(bgShadeD);
        });

    $("#color-main")
        .colorpicker()
        .on("changeColor", function(e) {
            var mainAlphaB = 0.75,
                mainAlphaC = 0.5,
                mainAlphaD = 0.25,
                bum = e.color;
            $("#color-main-a").val(bum);
            var mainVal = $("#color-main-a").val(),
                a = mainVal.slice(4).split(","),
                mainShadeB =
                    "rgba" +
                    a[0] +
                    "," +
                    parseInt(a[1]) +
                    "," +
                    parseInt(a[2]) +
                    "," +
                    mainAlphaB +
                    ")",
                mainShadeC =
                    "rgba" +
                    a[0] +
                    "," +
                    parseInt(a[1]) +
                    "," +
                    parseInt(a[2]) +
                    "," +
                    mainAlphaC +
                    ")",
                mainShadeD =
                    "rgba" +
                    a[0] +
                    "," +
                    parseInt(a[1]) +
                    "," +
                    parseInt(a[2]) +
                    "," +
                    mainAlphaD +
                    ")";
            $("#color-main-b").val(mainShadeB);
            $("#color-main-c").val(mainShadeC);
            $("#color-main-d").val(mainShadeD);
            $(".sln-colors-sample h1")[0].style.color = e.color;
            $(".sln-colors-sample button")[0].style.backgroundColor = e.color;
            $(".sln-colors-sample label").css("color", mainShadeB);
            $(".sln-colors-sample input")[0].style.borderColor = e.color;
            $(".sln-colors-sample input")[0].style.color = e.color;
        });
    $("#color-text")
        .colorpicker()
        .on("changeColor", function(e) {
            var textAlphaB = 0.75,
                textAlphaC = 0.5,
                textAlphaD = 0.25,
                textAlphaE = 0.1,
                bum = e.color;
            $("#color-text-a").val(bum);
            var textVal = $("#color-text-a").val(),
                b = textVal.slice(4).split(","),
                textShadeB =
                    "rgba" +
                    b[0] +
                    "," +
                    parseInt(b[1]) +
                    "," +
                    parseInt(b[2]) +
                    "," +
                    textAlphaB +
                    ")",
                textShadeC =
                    "rgba" +
                    b[0] +
                    "," +
                    parseInt(b[1]) +
                    "," +
                    parseInt(b[2]) +
                    "," +
                    textAlphaC +
                    ")",
                textShadeD =
                    "rgba" +
                    b[0] +
                    "," +
                    parseInt(b[1]) +
                    "," +
                    parseInt(b[2]) +
                    "," +
                    textAlphaD +
                    ")",
                textShadeE =
                    "rgba" +
                    b[0] +
                    "," +
                    parseInt(b[1]) +
                    "," +
                    parseInt(b[2]) +
                    "," +
                    textAlphaE +
                    ")";
            $("#color-text-b").val(textShadeB);
            $("#color-text-c").val(textShadeC);
            $("#color-text-d").val(textShadeD);
            $("#color-text-e").val(textShadeE);
            $(".sln-colors-sample p")[0].style.color = e.color;
            $(".sln-colors-sample p + p").css("color", e.color).css("background-color", textShadeE).css("padding", '1em');
            $(".sln-colors-sample small").css("color", textShadeB);
        });
});
