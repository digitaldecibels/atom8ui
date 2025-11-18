function decodeHTML(str) {

    if(str){
        const doc = new DOMParser().parseFromString(str, "text/html");
        return doc.documentElement.textContent;
      }
    }


function installationsTable() {
  return {
    installations: [],
    loading: true,
    error: null,
    async fetchData() {
      try {
        const response = await fetch("/rest/installations");

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        this.installations = data;
        this.loading = false;

        // Use regular setTimeout instead of $nextTick
        setTimeout(() => {
          const container = this.$refs.tableContainer;

          // First attach behaviors normally
          if (typeof Drupal !== "undefined" && Drupal.attachBehaviors) {
            Drupal.attachBehaviors(container, drupalSettings);
          }

          // Then specifically process AJAX links
          if (typeof Drupal !== "undefined" && Drupal.ajax) {
            const ajaxLinks = container.querySelectorAll(
              "a.use-ajax, button.use-ajax"
            );

            ajaxLinks.forEach((element) => {
              if (!element.hasAttribute("data-drupal-ajax-processed")) {
                Drupal.ajax({
                  element: element,
                  url:
                    element.getAttribute("href") ||
                    element.getAttribute("data-dialog-url"),
                  dialogType: element.getAttribute("data-dialog-type"),
                  dialog: JSON.parse(
                    element.getAttribute("data-dialog-options") || "{}"
                  ),
                });
              }
            });
          }
        }, 100);
      } catch (e) {
        console.error("Fetch error:", e);
        this.error = e.message;
        this.loading = false;
      }
    },
  };
}

