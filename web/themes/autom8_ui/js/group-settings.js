// Function that was likely intended to be named 'decodeHtml' or similar, but
// is not used inside n8nDashboard. Retained for completeness.
function decodeHTML(str) {
  if (str) {
    const doc = new DOMParser().parseFromString(str, "text/html");
    return doc.documentElement.textContent;
  }
}

// --- Alpine.js Component Start ---
function groupSettings() {
  return {
    // --- State Variables ---
    workflows: [], // All n8n workflows (source list)
    dashboardWorkflows: [], // Workflows added to the dashboard (target list)
    installs: [],
    users: [],
    isLoading: true,
    error: null,
    group_workflows: [],
    searchTerm: "",
    dragging: false,
    csrfToken: null,
    groupId: null, // You'll need to pass this from your Twig template

    // Computed property for the source list filter
    get filteredWorkflows() {
      if (!this.searchTerm.trim()) {
        const dashboardIds = this.dashboardWorkflows.map((w) => w.id);
        return this.workflows.filter((w) => !dashboardIds.includes(w.id));
      }

      const searchLower = this.searchTerm.toLowerCase();
      const dashboardIds = this.dashboardWorkflows.map((w) => w.id);

      return this.workflows.filter((workflow) => {
        const matchesSearch =
          workflow.name && workflow.name.toLowerCase().includes(searchLower);
        const notInDashboard = !dashboardIds.includes(workflow.id);
        return matchesSearch && notInDashboard;
      });
    },

    // --- Core Methods ---

    async init() {
      await this.fetchCsrfToken();
      await this.fetchData();
       await this.fetchDashboardWorkflows();

      this.$nextTick(() => {
        this.initSortable();
      });
    },

    // Fetch CSRF token for authenticated requests
    async fetchCsrfToken() {
      try {
        const response = await fetch('/session/token');
        this.csrfToken = await response.text();
      } catch (e) {
        console.error('Failed to fetch CSRF token:', e);
      }
    },

    // Add this new method after fetchCsrfToken()
// Fetch existing dashboard workflows for this group
async fetchDashboardWorkflows() {
  if (!this.groupId) {
    console.warn('No group ID provided, skipping dashboard workflows fetch');
    return;
  }

  try {
    const response = await fetch(`/rest/group/workflows/${this.groupId}`);

    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const existingWorkflows = await response.json();

          console.log(existingWorkflows);

    // The REST endpoint should return workflow objects with an 'id' field
    // that matches the n8n workflow IDs
    // Example: [{id: "123", name: "My Workflow", ...}, ...]

    // Match each saved workflow with the full workflow data from this.workflows
    this.dashboardWorkflows = existingWorkflows.map(savedWorkflow => {
      // Find the full workflow object from the main workflows array
      // This ensures we have all the n8n workflow data (install_host, install_type, etc.)
      const fullWorkflow = this.workflows.find(w => w.id === savedWorkflow.id);

      // If found in workflows list, use that data
      // Otherwise, use what we have from the REST endpoint
      return fullWorkflow || savedWorkflow;
    }).filter(w => w); // Filter out any null/undefined values

    console.log('Loaded dashboard workflows:', this.dashboardWorkflows);


  } catch (e) {
    console.error('Failed to fetch dashboard workflows:', e);
    // Don't set this.error here - it's not critical, just log it
  }
},


    // Create a new workflow node via JSON:API
async createWorkflowNode(workflow) {
  if (!this.csrfToken) {
    throw new Error('CSRF token not available');
  }

  // Find the install object to get its node ID
  const install = this.installs.find(i => i.field_host === workflow.install_host);

  if (!install) {
    throw new Error('Install not found for workflow');
  }

  // Build the JSON:API payload
  const payload = {
    data: {
      type: 'node--workflow',
      attributes: {
        title: workflow.name,
        field_id: workflow.id,

        // Add any other non-reference fields here
      },
      relationships: {
        field_install: {
          data: {
            type: 'node--installation', // Adjust this to match your installation content type machine name
            id: install.uuid // Use the UUID of the installation node
          }
        }
      }
    }
  };

  try {
    const response = await fetch('/jsonapi/node/workflow', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/vnd.api+json',
        'Accept': 'application/vnd.api+json',
        'X-CSRF-Token': this.csrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });

    const json = await response.json();

    if (!response.ok) {
      const error = json.errors && json.errors[0]
        ? json.errors[0].detail
        : 'Failed to create workflow';
      throw new Error(error);
    }

    console.log('Workflow node created:', json);

    const createdNodeId = json.data.attributes.drupal_internal__nid;

    // If you have a group ID, add the node to the group
    if (this.groupId) {
      await this.addNodeToGroup(createdNodeId);
    }

    return json.data;

  } catch (e) {
    console.error('Error creating workflow node:', e);
    throw e;
  }
},

    // Add the created node to a group
    async addNodeToGroup(nodeId) {
      try {
        const response = await fetch(`/group/${this.groupId}/add-node/${nodeId}`, {
          credentials: 'same-origin'
        });

        const result = await response.json();
        console.log('Node added to group:', result);
        return result;

      } catch (e) {
        console.error('Error adding node to group:', e);
        throw e;
      }
    },

    // Handles the data addition when an item is dropped into the dashboard list
    async addToDashboard(workflowId) {
      // Prevent duplicates
      if (this.dashboardWorkflows.some((w) => w.id === workflowId)) {
        return;
      }

      // Find the full workflow object in the main list
      const workflowToAdd = this.workflows.find((w) => w.id === workflowId);

      if (workflowToAdd) {
        try {
          // Create the node in Drupal
          await this.createWorkflowNode(workflowToAdd);

          // Add to the dashboardWorkflows array (triggering Alpine to re-render)
          this.dashboardWorkflows.push(workflowToAdd);

          // Re-sort the dashboard list by name for cleanliness (optional)
          this.dashboardWorkflows.sort((a, b) => a.name.localeCompare(b.name));

        } catch (e) {
          console.error('Failed to add workflow to dashboard:', e);
          alert('Failed to add workflow: ' + e.message);
        }
      }
    },

    // Remove workflow from dashboard
    removeFromDashboard(workflowId) {
      this.dashboardWorkflows = this.dashboardWorkflows.filter(
        (w) => w.id !== workflowId
      );
    },

    // Initializes SortableJS on both containers
    initSortable() {
      const availableList = document.getElementById("available-workflows-list");
      const dashboardList = document.getElementById("dashboard-workflows-list");

      if (!availableList || !dashboardList || typeof Sortable === "undefined") {
        console.warn("SortableJS containers or library not found.");
        return;
      }

      const self = this;

      const commonGroupOptions = {
        name: "workflow-transfer",
        put: true,
      };

      // 1. Available Workflows (Source List)
      new Sortable(availableList, {
        group: commonGroupOptions,
        animation: 150,
        handle: ".drag-handle",
        sort: true,
      });

      // 2. Dashboard Workflows (Target List)
      new Sortable(dashboardList, {
        group: commonGroupOptions,
        animation: 150,
        handle: ".drag-handle",
        ghostClass: "sortable-ghost",
        sort: true,

        onAdd: async function (evt) {
          const workflowId = evt.item.dataset.workflowId;

          // Update Alpine state (this will create the node)
          await self.addToDashboard(workflowId);

          // Remove the cloned item's physical DOM element
          evt.item.remove();
        },

        onRemove: function (evt) {
          const workflowId = evt.item.dataset.workflowId;
          self.dashboardWorkflows = self.dashboardWorkflows.filter(
            (w) => w.id !== workflowId
          );
        },

        onUpdate: function (evt) {
          const [movedItem] = self.dashboardWorkflows.splice(evt.oldIndex, 1);
          self.dashboardWorkflows.splice(evt.newIndex, 0, movedItem);
        },
      });
    },

    // --- Existing fetchData Method ---
    async fetchData() {
      this.workflows = [];
      this.error = null;
      this.isLoading = true;

      try {
        const installResponse = await fetch("/rest/installations");
        if (!installResponse.ok)
          throw new Error(`HTTP error! Status: ${installResponse.status}`);
        const installData = await installResponse.json();
        this.installs = installData || [];
      } catch (e) {
        console.error(e);
        this.error = `Failed to fetch installations: ${e.message}`;
        this.isLoading = false;
        return;
      }

      for (const install of this.installs) {
        const apiKey = install.field_api_key;
        const host = install.field_host;
        const uuid = install.uuid;
        const url = `/fetch/n8n?url=${host}&apikey=${apiKey}&endpoint=/api/v1/workflows`;

        try {
          const response = await fetch(url);
          if (!response.ok)
            throw new Error(
              `HTTP error! Status: ${response.status} from ${host}`
            );

          const data = await response.json();
          const fetchedWorkflows = data.data || [];

          const newWorkflows = fetchedWorkflows.map((workflow) => ({
            ...workflow,
            install_title: install.title,
            install_host: install.field_host,
            install_type: install.field_type || "n8n",
          }));

          this.workflows = [...this.workflows, ...newWorkflows];
        } catch (e) {
          console.error(`Failed to fetch workflows for ${host}:`, e);
        }
      }

      this.isLoading = false;
    },
  };
}