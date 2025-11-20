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
function workflowSelect() {
  return {
    // --- State Variables ---
    workflows: [], // n8n workflows fetched from API
    installs: [], // Installations data from /rest/installations




    isLoading: true, // Primary loading flag: Start as TRUE
    error: null,

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

      console.log(this.installs);

      // 2. Fetch n8n Workflows
      const url =
        "/fetch/n8n?url=https://n8n.digitaldecibels.com&apikey=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiMTRkODNjZS1lZTViLTRhYTktYWFiMi03MmJhZTExZmE3OWUiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzYzNDAxMDA3fQ.LouJnqhRZQfDKcEEc6K_NzpGzt8jP-fbaqEnTnY9VnQ&endpoint=/api/v1/workflows";
      try {
        const response = await fetch(url);
        if (!response.ok)
          throw new Error(`HTTP error! Status: ${response.status}`);
        const data = await response.json();
        this.workflows = data.data || [];


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





      } catch (err) {
        this.error = "Unable to load dashboards";
        console.error(err);
      } finally {
          this.isLoading = false;
      }
    },




}
}


