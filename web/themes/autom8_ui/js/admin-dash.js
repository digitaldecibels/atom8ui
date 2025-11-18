function n8nDashboard() {
    return {
        workflows: [],
         installs: [],
        isLoading: false,
        error: '',

        async fetchData() {
            this.isLoading = true;
            this.error = '';
            this.workflows = [];
            this.installs = [];


              try {


                const installResponse = await fetch('/rest/installations');
                if (!installResponse.ok) throw new Error(`HTTP error! Status: ${installResponse.status}`);

                const installData = await installResponse.json();



                // Updated for new data structure
                this.installs = installData || [];

            } catch (e) {
                console.error(e);
                this.error = `Failed to fetch workflows: ${e.message}`;
            }



                const url = '/fetch/n8n?url=https://n8n.digitaldecibels.com&apikey=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiMTRkODNjZS1lZTViLTRhYTktYWFiMi03MmJhZTExZmE3OWUiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzYzNDAxMDA3fQ.LouJnqhRZQfDKcEEc6K_NzpGzt8jP-fbaqEnTnY9VnQ&endpoint=/api/v1/workflows';

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

                const data = await response.json();



                // Updated for new data structure
                this.workflows = data.data || [];


                        // Use regular setTimeout instead of $nextTick
        setTimeout(() => {


            const container = this.$refs.workflowsTable;

            // First attach behaviors normally
            if (typeof Drupal !== 'undefined' && Drupal.attachBehaviors) {
                Drupal.attachBehaviors(container, drupalSettings);
            }

            // Then specifically process AJAX links
            if (typeof Drupal !== 'undefined' && Drupal.ajax) {
                const ajaxLinks = container.querySelectorAll('a.use-ajax, button.use-ajax');


                ajaxLinks.forEach((element) => {
                    if (!element.hasAttribute('data-drupal-ajax-processed')) {
                        Drupal.ajax({
                            element: element,
                            url: element.getAttribute('href') || element.getAttribute('data-dialog-url'),
                            dialogType: element.getAttribute('data-dialog-type'),
                            dialog: JSON.parse(element.getAttribute('data-dialog-options') || '{}')
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
        }
    }
}





// Helper function to decode HTML entities using the browser's DOM
function decodeHtml(html) {
    const txt = document.createElement("textarea");
    txt.innerHTML = html;
    return txt.value;
}

function reattachDrupalBehaviors(context) {
    if (typeof Drupal !== 'undefined' && typeof Drupal.attachBehaviors === 'function') {


    } else {
        console.error('Drupal.attachBehaviors not available');
    }
}

function installationsTable() {
    return {
        installations: [],
        loading: true,
        error: null,
        async fetchData() {

    try {

        const response = await fetch('/rest/installations');


        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();


        const decodedData = data.map(installation => {
            return {
                ...installation,
                title: decodeHtml(installation.title)
            };
        });

        this.installations = decodedData;


        this.loading = false;



        // Use regular setTimeout instead of $nextTick
        setTimeout(() => {


            const container = this.$refs.tableContainer;

            // First attach behaviors normally
            if (typeof Drupal !== 'undefined' && Drupal.attachBehaviors) {
                Drupal.attachBehaviors(container, drupalSettings);
            }

            // Then specifically process AJAX links
            if (typeof Drupal !== 'undefined' && Drupal.ajax) {
                const ajaxLinks = container.querySelectorAll('a.use-ajax, button.use-ajax');


                ajaxLinks.forEach((element) => {
                    if (!element.hasAttribute('data-drupal-ajax-processed')) {
                        Drupal.ajax({
                            element: element,
                            url: element.getAttribute('href') || element.getAttribute('data-dialog-url'),
                            dialogType: element.getAttribute('data-dialog-type'),
                            dialog: JSON.parse(element.getAttribute('data-dialog-options') || '{}')
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
}


    }
}


// user clients


function userClients() {
    return {
        clients: [],
        loading: false,
        error: '',

        async fetchData() {
            this.loading = true;
            this.error = '';
            this.clients = [];

            try {
                const installResponse = await fetch('/rest/user/clients');
                if (!installResponse.ok) throw new Error(`HTTP error! Status: ${installResponse.status}`);

                const userClients = await installResponse.json();
                console.log(userClients, 'this is the original user clients');

                this.clients = userClients || [];
                this.loading = false; // Set loading to false after successful fetch and data assignment

                // 🌟 CRITICAL FIX: Use $nextTick to wait for DOM update 🌟
                this.$nextTick(() => {
                    const container = this.$refs.clientsTable;

                    // Ensure Drupal is available before trying to use its functions
                    if (typeof Drupal === 'undefined') {
                        console.warn("Drupal environment not loaded. Skipping AJAX attachment.");
                        return;
                    }

                    // 1. Attach general behaviors (e.g., for forms/fields within the table)
                    if (Drupal.attachBehaviors) {
                        // Pass drupalSettings if required by your setup
                        Drupal.attachBehaviors(container, window.drupalSettings);
                    }

                    // 2. Specifically process AJAX links for the table
                    if (Drupal.ajax) {
                        // Select only elements that haven't been processed yet
                        const ajaxLinks = container.querySelectorAll('a.use-ajax:not([data-drupal-ajax-processed]), button.use-ajax:not([data-drupal-ajax-processed])');

                        ajaxLinks.forEach((element) => {
                            // The check for 'data-drupal-ajax-processed' is already in the selector, but we keep the logic clean.
                            Drupal.ajax({
                                element: element,
                                url: element.getAttribute('href') || element.getAttribute('data-dialog-url'),
                                dialogType: element.getAttribute('data-dialog-type'),
                                dialog: JSON.parse(element.getAttribute('data-dialog-options') || '{}')
                            });
                        });
                    }
                });

            } catch (e) {
                console.error(e);
                this.error = `Failed to fetch clients: ${e.message}`; // Updated 'workflows' to 'clients' for clarity
                this.loading = false; // Set loading to false on error too
            }
        }
    }
}