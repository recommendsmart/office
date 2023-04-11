/**
 * @file
 * Entity Reference Tree JavaScript file.
 */

// Codes run both on normal page loads and when data is loaded by AJAX (or BigPipe!)
// @See https://www.drupal.org/docs/8/api/javascript-api/javascript-api-overview
(function($, Drupal, once) {
  Drupal.behaviors.entityReferenceTree = {
    attach: function(context, settings) {
      const entityJSTree = once('entityReferenceTree', '#entity-reference-tree-wrapper', context);
      entityJSTree.forEach(function(entityTree) {
          const treeContainer = $(entityTree);
          const fieldEditName = $("#entity-reference-tree-widget-field").val();
          const widgetElement = $("#" + fieldEditName);
          const theme = treeContainer.attr("theme");
          const dots = treeContainer.attr("dots");
          // Avoid ajax callback from running following codes again.
          if (widgetElement.length) {
            const entityType = $("#entity-reference-tree-entity-type").val();
            const bundle = $("#entity-reference-tree-entity-bundle").val();
            const token = settings["entity_tree_token_" + fieldEditName];
            const idIsString = bundle === "*";
            const limit = parseInt(settings["tree_limit_" + fieldEditName]);
            let selectedNodes;
            // Selected nodes.
            if (idIsString) {
              selectedNodes = widgetElement.val().match(/\([a-z 0-9 _]+\)/g);
            } else {
              selectedNodes = widgetElement.val().match(/\((\d+)\)/g);
            }
            let remaining;
            if (limit > 0) {
              remaining = limit + " " + Drupal.t("max");
            } else {
              // remaining = "unlimited";
              remaining = Drupal.t("unlimited");
            }
            if (selectedNodes) {
              // Pick up nodes id.
              for (let i = 0; i < selectedNodes.length; i++) {
                // Remove the round brackets.
                if (idIsString) {
                  selectedNodes[i] = selectedNodes[i].slice(
                    1,
                    selectedNodes[i].length - 1
                  );
                } else {
                  selectedNodes[i] = parseInt(
                    selectedNodes[i].slice(1, selectedNodes[i].length - 1),
                    10
                  );
                }
              }
            } else {
              selectedNodes = [];
            }
            // Populate the selected entities text.
            $("#entity-reference-tree-selected-node").val(widgetElement.val());
            $("#entity-reference-tree-selected-text").text(
              Drupal.t("Selected") + " (0 " + Drupal.t("of") + " " + remaining + "): " + widgetElement.val()
            );
            // Build the tree.
            treeContainer.jstree({
              core: {
                data: {
                  url: function(node) {
                    return Drupal.url(
                      "admin/entity_reference_tree/json/" +
                        entityType +
                        "/" +
                        bundle +
                        "?token=" +
                        token
                    );
                  },
                  data: function(node) {
                    return {
                      id: node.id,
                      text: node.text,
                      parent: node.parent
                    };
                  }
                },
                themes: {
                  dots: dots === "1",
                  name: theme
                },
                multiple: limit !== 1
              },
              checkbox: {
                three_state: false
              },
              search: {
                show_only_matches: true
              },
              conditionalselect : function (node, event) {
                if (limit > 1) {
                  return this.get_selected().length < limit || node.state.selected;
                } else {
                  // No limit.
                  return true;
                }
              },
              plugins: ["search", "changed", "checkbox", "conditionalselect"]
            });
            // Initialize the selected node.
            treeContainer.on("ready.jstree", function(e, data) {
              data.instance.select_node(selectedNodes);
              // Make modal window height scaled automatically.
              $("#entity-reference-tree-modal").dialog( "option", { height: 'auto' } );
              // Focus on the dialog so that pressing the Escape button closes
              // the appropriate dialog.
              $("#entity-reference-tree-search").focus();
            });
            // Selected event.
            treeContainer.on("changed.jstree", function(evt, data) {
              // selected node objects;
              const choosedNodes = data.selected;
              const r = [];

              for (let i = 0; i < choosedNodes.length; i++) {
                const node = data.instance.get_node(choosedNodes[i]);
                // node text escaping double quote.
                let nodeText =
                  node.text.replace(/"/g, '""') + " (" + node.id + ")";
                // Comma is a special character for autocomplete widge.
                if (
                  nodeText.indexOf(",") !== -1 ||
                  nodeText.indexOf("'") !== -1
                ) {
                  nodeText = '"' + nodeText + '"';
                }
                r.push(nodeText);
              }
              const selectedText = r.join(", ");
              $("#entity-reference-tree-selected-node").val(selectedText);
              $("#entity-reference-tree-selected-text").text(
                Drupal.t("Selected") + " (" + choosedNodes.length + " " + Drupal.t("of") + " " + remaining + "): " + selectedText
              );
            });
            // Search filter box.
            let to = false;
            $("#entity-reference-tree-search").keyup(function() {
              const searchInput = $(this);
              if (to) {
                clearTimeout(to);
              }
              to = setTimeout(function() {
                const v = searchInput.val();
                treeContainer.jstree(true).search(v);
              }, 250);
            });
          }
        });
    }
  };
})(jQuery, Drupal, once);

// Codes just run once the DOM has loaded.
// @See https://www.drupal.org/docs/8/api/javascript-api/javascript-api-overview
(function($) {
  // Search form sumbit function.
  // Argument passed from InvokeCommand defined in Drupal\entity_reference_tree\Form\SearchForm
  $.fn.entitySearchDialogAjaxCallback = function(fieldEditID, selectedEntites) {
    if ($("#" + fieldEditID).length) {
      // submitted entity ids.
      $("#" + fieldEditID).val(selectedEntites).trigger('change');
    }
  };
})(jQuery);
