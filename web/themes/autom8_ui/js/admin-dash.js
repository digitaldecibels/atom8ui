// group/1/content/add/group_membership

function decodeHTML(str) {

    if(str){
        const doc = new DOMParser().parseFromString(str, "text/html");
        return doc.documentElement.textContent;
      }
    }



function n8nDashboard() {
  return {
    workflows: [],
    installs: [],
    isLoading: false,
    error: "",

    async fetchData() {
      this.isLoading = true;
      this.error = "";
      this.workflows = [];
      this.installs = [];

      try {
        const installResponse = await fetch("/rest/installations");
        if (!installResponse.ok)
          throw new Error(`HTTP error! Status: ${installResponse.status}`);

        const installData = await installResponse.json();

        // Updated for new data structure
        this.installs = installData || [];
      } catch (e) {
        console.error(e);
        this.error = `Failed to fetch workflows: ${e.message}`;
      }

      const url =
        "/fetch/n8n?url=https://n8n.digitaldecibels.com&apikey=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiMTRkODNjZS1lZTViLTRhYTktYWFiMi03MmJhZTExZmE3OWUiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzYzNDAxMDA3fQ.LouJnqhRZQfDKcEEc6K_NzpGzt8jP-fbaqEnTnY9VnQ&endpoint=/api/v1/workflows";

      try {
        const response = await fetch(url);
        if (!response.ok)
          throw new Error(`HTTP error! Status: ${response.status}`);

        const data = await response.json();

        // Updated for new data structure
        this.workflows = data.data || [];

        // Use regular setTimeout instead of $nextTick
        setTimeout(() => {
          const container = this.$refs.workflowsTable;

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
        console.error(e);
        this.error = `Failed to fetch workflows: ${e.message}`;
      } finally {
        this.isLoading = false;
      }
    },
  };
}

// Helper function to decode HTML entities using the browser's DOM
function decodeHtml(html) {
  const txt = document.createElement("textarea");
  txt.innerHTML = html;
  return txt.value;
}

function reattachDrupalBehaviors(context) {
  if (
    typeof Drupal !== "undefined" &&
    typeof Drupal.attachBehaviors === "function"
  ) {
  } else {
    console.error("Drupal.attachBehaviors not available");
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



// user clients

function userClients() {
  return {

    clients: [],
    workflows: [],               // All available workflows
    dashboardWorkflows: [],      // Workflows dropped into dashboard
    draggedWorkflow: null,       // Holds the workflow currently being dragged
    loading: false,
    error: null,
    dragOverClientId: null,
    isDragging: false,

    // --- INIT ---
 async fetchData() {
    this.loading = true;
    this.error = null;

    // Clear array first to avoid duplicates
    this.clients = [];

try {
  const userRes = await fetch('/rest/user/groups');
  const userData = await userRes.json();

  // 1. Start building the new array (use a temporary variable)
  let updatedClients = userData.map(u => ({ ...u, groups: [], workflows: [] }));

  for (let i = 0; i < updatedClients.length; i++) {
    const client = updatedClients[i];

    // Fetch groups and workflows in parallel for efficiency
    const [groupsRes, workflowsRes] = await Promise.all([
      fetch(`/rest/group/users/${client.id}`),
      fetch(`/rest/group/workflows/${client.id}`)
    ]);

    const [groups, workflows] = await Promise.all([
      groupsRes.json(),
      workflowsRes.json()
    ]);

    // 2. Update the temporary array item
    updatedClients[i] = { ...client, groups, workflows };
  }

  // 3. Assign the complete, final array to the Alpine state *only once*
  this.clients = updatedClients;

} catch (err) {
  this.error = 'Unable to load dashboards';
  console.error(err);
}

    this.loading = false;
},


addToDashboard(mappedWorkflow, client) {
            console.log('Mapped Workflow received:', mappedWorkflow);

            // 1. Check for duplicates using the client's internal ID name (`field_id`)
            const exists = client.workflows.some(w => w.field_id === mappedWorkflow.field_id);

            if (exists) {
                alert('Workflow already assigned.');
                return;
            }





            client.workflows.push(mappedWorkflow);
            this.dragOverClientId = null;
            this.isDragging = false;

        },


  };
}



