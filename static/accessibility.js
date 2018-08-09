$(function() {
    function setAccessibility(val) {
        if (val) {
            $(":root").addClass("accessible");
        } else {
            $(":root").removeClass("accessible");
        }
        localStorage.setItem("accessibilityMode", val)
    }

    $("#accessibilityMode").change(function () {
        setAccessibility(this.checked);
    });

    var initVal = localStorage.getItem("accessibilityMode") == 'true';
    setAccessibility(initVal);
    $("#accessibilityMode").prop("checked", initVal);
});
