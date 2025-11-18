// Helper function to decode HTML entities using the browser's DOM


// Function that was likely intended to be named 'decodeHtml' or similar, but
// is not used inside n8nDashboard. Retained for completeness.
function decodeHTML(str) {
  if (str) {
    const doc = new DOMParser().parseFromString(str, "text/html");
    return doc.documentElement.textContent;
  }
}

// Unused function, but retained for completeness.
function reattachDrupalBehaviors(context) {
  if (
    typeof Drupal !== "undefined" &&
    typeof Drupal.attachBehaviors === "function"
  ) {
    // Implementation was empty in the original code, keeping it that way.
  } else {
    console.error("Drupal.attachBehaviors not available");
  }
}

// --- Alpine.js Component Start ---
function n8nDashboard() {
  return {
    // --- State Variables ---
    workflows: [], // n8n workflows fetched from API
    installs: [], // Installations data from /rest/installations
    clients: [], // Users/groups data from /rest/user/groups
    dashboardWorkflows: [], // Assuming this was meant for filtered/display workflows
    draggedWorkflow: null, // Workflow currently being dragged
    dragOverClientId: null, // Client ID being dragged over
    isDragging: false, // Flag for drag state
    // --- Loading/Error State ---
    isLoading: true, // Primary loading flag: Start as TRUE
        error: null,
    // --- Methods ---
    /**
     * Lifecycle method to fetch all necessary data on component initialization.
     */
    async fetchData() {


      // 1. Fetch Installations
      try {
        const installResponse = await fetch("/rest/installations");
        if (!installResponse.ok)
          throw new Error(`HTTP error! Status: ${installResponse.status}`);
        const installData = await installResponse.json();
        this.installs = installData || [];
      } catch (e) {
        console.error(e);
        this.error = `Failed to fetch installations: ${e.message}`;
      }

      // 2. Fetch n8n Workflows
      const url =
        "/fetch/n8n?url=https://n8n.digitaldecibels.com&apikey=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiMTRkODNjZS1lZTViLTRhYTktYWFiMi03MmJhZTExZmE3OWUiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzYzNDAxMDA3fQ.LouJnqhRZQfDKcEEc6K_NzpGzt8jP-fbaqEnTnY9VnQ&endpoint=/api/v1/workflows";
      try {
        const response = await fetch(url);
        if (!response.ok)
          throw new Error(`HTTP error! Status: ${response.status}`);
        const data = await response.json();
        this.workflows = data.data || [];

        // Re-attach Drupal behaviors and AJAX links after DOM update
        setTimeout(() => {
          const container = this.$refs.workflowsTable; // Assumes a ref exists in HTML
          if (
            typeof Drupal !== "undefined" &&
            Drupal.attachBehaviors
          ) {
            Drupal.attachBehaviors(container, drupalSettings);
          }
          if (typeof Drupal !== "undefined" && Drupal.ajax) {
            const ajaxLinks = container.querySelectorAll(
              "a.use-ajax, button.use-ajax"
            );
            ajaxLinks.forEach((element) => {
              if (
                !element.hasAttribute(
                  "data-drupal-ajax-processed"
                )
              ) {
                Drupal.ajax({
                  element: element,
                  url:
                    element.getAttribute("href") ||
                    element.getAttribute("data-dialog-url"),
                  dialogType: element.getAttribute(
                    "data-dialog-type"
                  ),
                  dialog: JSON.parse(
                    element.getAttribute("data-dialog-options") ||
                      "{}"
                  ),
                });
              }
            });
          }
        }, 100);
      } catch (e) {
        console.error(e);
        this.error = `Failed to fetch workflows: ${e.message}`;
      } finally {
        // Keep `isLoading` separate from the clients' loading flag
      }

      // 3. Fetch Users/Groups/Client Workflows
      try {
        const userRes = await fetch("/rest/user/groups");
        const userData = await userRes.json();
        let updatedClients = userData.map((u) => ({
          ...u,
          groups: [],
          workflows: [],
        }));
        for (let i = 0; i < updatedClients.length; i++) {
          const client = updatedClients[i];
          // Fetch groups and workflows in parallel
          const [groupsRes, workflowsRes] = await Promise.all([
            fetch(`/rest/group/users/${client.id}`),
            fetch(`/rest/group/workflows/${client.id}`),
          ]);
          const [groups, workflows] = await Promise.all([
            groupsRes.json(),
            workflowsRes.json(),
          ]);
          updatedClients[i] = {
            ...client,
            groups,
            workflows,
          };
        }
        // Assign the final array
        this.clients = updatedClients;
      } catch (err) {
        this.error = "Unable to load dashboards";
        console.error(err);
      } finally {
          this.isLoading = false;
      }
    },

    /**
     * Adds a mapped workflow to a specific client's workflow list.
     * @param {Object} mappedWorkflow - The workflow object to add.
     * @param {Object} client - The client object to add the workflow to.
     */
    addToDashboard(mappedWorkflow, client) {
      console.log("Mapped Workflow received:", mappedWorkflow);
      // Check for duplicates using the client's internal ID name (`field_id`)
      const exists = client.workflows.some(
        (w) => w.field_id === mappedWorkflow.field_id
      );
      if (exists) {
        alert("Workflow already assigned.");
        return;
      }
      client.workflows.push(mappedWorkflow);

      // Reset drag state variables
      this.dragOverClientId = null;
      // Assuming `window.dragState` is managed externally for the drag operation
       this.isDragging  = false;
    },

    /**
     * Removes a workflow from a client's workflow list.
     * @param {Object} client - The client object the workflow belongs to.
     * @param {Object} workflow - The workflow object to remove.
     */
    removeWorkflow(client, workflow) {
      const index = client.workflows.indexOf(workflow);
      if (index > -1) {
        client.workflows.splice(index, 1);
      }
    },
  };


}


