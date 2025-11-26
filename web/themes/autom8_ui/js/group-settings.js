// Helper function to decode HTML entities using the browser's DOM


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
    workflows: [], // n8n workflows fetched from API
    installs: [], // Installations data from /rest/installations
    users: [],
    isLoading: true, // Primary loading flag: Start as TRUE
    error: null,
    group_workflows: [],


async fetchData() {

      // Initialize the workflows array to store results from all installations
      this.workflows = [];
      this.error = null; // Clear previous errors

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
        return; // Stop execution if installations fail to fetch
      }
      // 2. Loop through each installation to fetch n8n Workflows
      for (const install of this.installs) {
        console.log(`Fetching workflows for install: ${install.field_host}`);

        const apiKey = install.field_api_key;
        const host = install.field_host;

        // Construct the URL for the proxy
        const url = `/fetch/n8n?url=${host}&apikey=${apiKey}&endpoint=/api/v1/workflows`;

        try {
          const response = await fetch(url);
          if (!response.ok)
            throw new Error(`HTTP error! Status: ${response.status} from ${host}`);

          const data = await response.json();
          const fetchedWorkflows = data.data || [];

          const newWorkflows = fetchedWorkflows.map(workflow => ({
              ...workflow, // Keep all existing workflow properties (id, name, etc.)
              install_title: install.title, // Assuming 'title' exists on the install object
              install_host: install.field_host, // Host URL
              install_type: install.field_type || 'n8n', // Assuming 'field_type' exists (or a default)
          }));

          // Combine the new, augmented workflows into the main array.
          this.workflows = [...this.workflows, ...newWorkflows];



        } catch (e) {
          // Log an error but continue to the next installation
          console.error(`Failed to fetch workflows for ${host}:`, e);
          // Optional: Update a list of failed installations/errors if needed
        }
      }



      // get group workflows


      //   try {
      //     const response = await fetch('/rest/group/workflows/1');
      //     if (!response.ok)
      //       throw new Error(`HTTP error! Status: ${response.status}`);

      //     const groupFlows = await response.json();
      //     const fetchedgroupFlows = data.data || [];

      //     const newWorkflows = fetchedWorkflows.map(workflow => ({
      //         ...workflow, // Keep all existing workflow properties (id, name, etc.)
      //         install_title: install.title, // Assuming 'title' exists on the install object
      //         install_host: install.field_host, // Host URL
      //         install_type: install.field_type || 'n8n', // Assuming 'field_type' exists (or a default)
      //     }));

      //     // Combine the new, augmented workflows into the main array.
      //     this.workflows = [...this.workflows, ...newWorkflows];



      //   } catch (e) {
      //     // Log an error but continue to the next installation
      //     console.error(`Failed to fetch workflows for ${host}:`, e);
      //     // Optional: Update a list of failed installations/errors if needed
      //   }
      // }





      this.isLoading = false;


    },



}
}


