(function ($) {
  "use strict";

  $(document).ready(function () {
    // Table sorting functionality
    $(".sort-column").on("click", function (e) {
      e.preventDefault();

      var $this = $(this);
      var column = $this.data("column");
      var $table = $("#taxonomy-table");
      var $rows = $table.find("tbody tr").toArray();
      var ascending = $this.closest("th").hasClass("desc");

      // Update sortable classes
      $table.find("thead th, tfoot th").removeClass("sorted asc desc");

      // Update the current column's sorting state
      var $currentHeader = $this.closest("th");
      $currentHeader.addClass("sorted");

      if (ascending) {
        $currentHeader.removeClass("desc").addClass("asc");
      } else {
        $currentHeader.removeClass("asc").addClass("desc");
      }

      // Sort the rows
      $rows.sort(function (a, b) {
        var valA, valB;

        if (column === "name") {
          valA = $(a).data("name");
          valB = $(b).data("name");
        } else if (column === "post_types") {
          valA = $(a).data("post-types");
          valB = $(b).data("post-types");
        } else if (column === "type") {
          valA = $(a).data("type");
          valB = $(b).data("type");
        }

        if (valA < valB) return ascending ? -1 : 1;
        if (valA > valB) return ascending ? 1 : -1;
        return 0;
      });

      // Append sorted rows to table
      $.each($rows, function (index, row) {
        $table.find("tbody").append(row);
      });

      return false;
    });

    // System taxonomy toggle
    $("#show-system-taxonomies").on("change", function () {
      if ($(this).is(":checked")) {
        $(".system-taxonomy").show();
      } else {
        $(".system-taxonomy").hide();
      }

      updateNoItemsVisibility();
    });

    // Function to update the no-items message visibility
    function updateNoItemsVisibility() {
      var systemVisible = $("#show-system-taxonomies").is(":checked");

      var visibleRows = $("#taxonomy-table tbody tr:visible").not(
        ".no-items"
      ).length;

      if (visibleRows === 0) {
        // Show the no items message
        $(".no-items").show();

        // If there are system taxonomies but they're hidden, show the hint
        if (!systemVisible && taxonomyStats.systemCount > 0) {
          $(".hidden-system-message").show();
        } else {
          $(".hidden-system-message").hide();
        }
      } else {
        // Hide the no items message when there are visible rows
        $(".no-items").hide();
      }
    }

    // Initial setup - hide system taxonomies
    $("#show-system-taxonomies").prop("checked", false).trigger("change");

    // Initial update of no-items visibility
    updateNoItemsVisibility();
  });
})(jQuery);
