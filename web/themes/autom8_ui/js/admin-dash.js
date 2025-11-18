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


function n8nDashboard() {
  return {
    // --- State Variables ---
    workflows: [],
    installs: [],
    clients: [],
    dashboardWorkflows: [],
    draggedWorkflow: null,
    dragOverClientId: null,
    isDragging: false,
    isLoading: true,
    error: null,

    // --- Helper Methods ---

    /**
     * Get UUID from node ID
     */
    getNodeUuid: function(nodeType, nodeId) {
      var url = '/jsonapi/node/' + nodeType + '?filter[drupal_internal__nid]=' + nodeId;

      return fetch(url, {
        credentials: 'same-origin'
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(data) {
        if (data.data && data.data[0]) {
          return data.data[0].id;
        }
        throw new Error('Node ' + nodeId + ' not found');
      });
    },

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
          const container = this.$refs.workflowsTable;
          if (typeof Drupal !== "undefined" && Drupal.attachBehaviors) {
            Drupal.attachBehaviors(container, drupalSettings);
          }
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
        console.error(e);
        this.error = `Failed to fetch workflows: ${e.message}`;
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
     * Creates the workflow node via JSON:API and adds it to the group.
     *
     * @param {Object} mappedWorkflow - The workflow object to add.
     * @param {Object} client - The client object to add the workflow to.
     */
    addToDashboard: function(mappedWorkflow, client) {
      var self = this;

      console.log("Mapped Workflow received:", mappedWorkflow);
      console.log("Client:", client);

      // Check for duplicates
      const exists = client.workflows.some(
        (w) => w.field_id === mappedWorkflow.field_id
      );
      if (exists) {
        alert("Workflow already assigned.");
        self.dragOverClientId = null;
        self.isDragging = false;
        return;
      }

      console.log(mappedWorkflow);

      // You need to specify which installation to use
      // Option 1: Use the first installation
      var installId = self.installs.length > 0 ? self.installs[0].nid : null;

      // Option 2: Or let user select, or get from mappedWorkflow if available
      // var installId = mappedWorkflow.install_id || self.installs[0].nid;

      if (!installId) {
        alert("No installation available. Please create an installation first.");
        self.dragOverClientId = null;
        self.isDragging = false;
        return;
      }

      var installationUuid;
      var csrfToken;
      var createdNodeId;

      // Step 1: Get installation UUID
      self.getNodeUuid('installation', installId)
      .then(function(uuid) {
        installationUuid = uuid;
        console.log('Got installation UUID:', installationUuid);

        // Step 2: Get CSRF token
        return fetch('/session/token', {
          credentials: 'same-origin'
        });
      })
      .then(function(response) {
        return response.text();
      })
      .then(function(token) {
        csrfToken = token;
        console.log('Got CSRF token');

        // Step 3: Create the workflow node
        var payload = {
          data: {
            type: "node--workflow",
            attributes: {
              title: mappedWorkflow.title || ("Workflow " + mappedWorkflow.field_id),
              field_id: mappedWorkflow.field_id
            },
            relationships: {
              field_install: {
                data: {
                  type: "node--installation",
                  id: installationUuid
                }
              }
            }
          }
        };

        return fetch('/jsonapi/node/workflow', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/vnd.api+json',
            'Accept': 'application/vnd.api+json',
            'X-CSRF-Token': csrfToken
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
      })
      .then(function(response) {
        return response.json().then(function(json) {
          if (!response.ok) {
            var error = json.errors && json.errors[0] ? json.errors[0].detail : 'Failed to create workflow';
            throw new Error(error);
          }
          return json;
        });
      })
      .then(function(workflowResult) {
        console.log('Workflow created:', workflowResult);
        createdNodeId = workflowResult.data.attributes.drupal_internal__nid;

        // Step 4: Add workflow to group
        return fetch('/group/' + client.id + '/add-node/' + createdNodeId, {
          credentials: 'same-origin'
        });
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        console.log('Added to group:', result);

        // Add to local client workflows array with the created node ID
        client.workflows.push({
          ...mappedWorkflow,
          nid: createdNodeId
        });

        // Reset drag state
        self.dragOverClientId = null;
        self.isDragging = false;

        alert('Workflow assigned successfully!');

        // Optionally refresh data to get the latest from server
        // self.fetchData();
      })
      .catch(function(error) {
        console.error('Error adding workflow:', error);
        alert('Failed to assign workflow: ' + error.message);

        // Reset drag state even on error
        self.dragOverClientId = null;
        self.isDragging = false;
      });
    },

    /**
     * Removes a workflow from a client's workflow list.
     * @param {Object} client - The client object the workflow belongs to.
     * @param {Object} workflow - The workflow object to remove.
     */
    removeWorkflow: function(client, workflow) {
      const index = client.workflows.indexOf(workflow);
      if (index > -1) {
        client.workflows.splice(index, 1);
      }
    }
  };
}