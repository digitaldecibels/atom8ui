Calendly.initInlineWidget({
  url: "https://calendly.com/rick-digitaldecibels/30min?hide_event_type_details=1&hide_gdpr_banner=1",
  parentElement: document.getElementById("calendly-form"),
  prefill: {
    name: "John Doe",
    email: "john@example.com",
    customAnswers: {
      a1: "First Name last name (email address) dropping off Lot #[id] of [materials]",

      // Add more custom answers as needed (a2, a3, etc.)
    }
  },
});

function isCalendlyEvent(e) {
  return e.data.event && e.data.event.indexOf("calendly") === 0;
}

window.addEventListener("message", function (e) {
  if (isCalendlyEvent(e)) {
    var div = document.getElementById("calendly-form");
    div.style.height = e.data.payload.height;

    // capture eventt

    if (e.data.event == "calendly.event_scheduled") {
      var event_url = e.data.payload.event.uri;
      const eventId = event_url.split("/").pop();

      const myAccessToken =
        "eyJraWQiOiIxY2UxZTEzNjE3ZGNmNzY2YjNjZWJjY2Y4ZGM1YmFmYThhNjVlNjg0MDIzZjdjMzJiZTgzNDliMjM4MDEzNWI0IiwidHlwIjoiUEFUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJodHRwczovL2F1dGguY2FsZW5kbHkuY29tIiwiaWF0IjoxNzU0NDEzOTg4LCJqdGkiOiJhOTQ2NWJjNC1mYjg3LTQ3OGQtOWI3OS02Yzk4MWUyMmU4NGYiLCJ1c2VyX3V1aWQiOiI3ZTQxOWI1NC0wZjYzLTRjYWUtOGY0MS02YmZlMGEwOGQ1MmEifQ.bU6Dyu11M3qlfc3e_rBLG2iwzFVgiJ57pYn5eDPv0fNfl-IHMvIMj6h9m6cCkJ_pXvMTE1-FGjqJKgJJtA-UqA";

      // Call the function to get the event data
      getEventById(eventId, myAccessToken);
    }
  }
});

const getEventById = async (eventId, accessToken) => {
  const url = `https://api.calendly.com/scheduled_events/${eventId}`;

  try {
    const response = await fetch(url, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      // Handle non-successful HTTP responses (e.g., 404 Not Found)
      const errorData = await response.json();
      throw new Error(
        `HTTP error! Status: ${response.status} - ${errorData.message}`
      );
    }

    const eventData = await response.json();

    const startTime = eventData.resource.start_time;

    var eventID = document.getElementById("edit-scheduled-id");
    var eventDate = document.getElementById("edit-scheduled-date");
    const submitButton = document.getElementById("edit-submit");

    eventID.value = eventId;
    eventDate.value = startTime;

    // Check if the fields are populated before submitting.
    if (eventID.value && eventDate.value && submitButton) {
      // Programmatically trigger the mousedown event on the submit button.
      // This is the correct way to trigger a Drupal AJAX form submission.
      submitButton.click();
    }
  } catch (error) {
    console.error("Failed to fetch event:", error);
  }
};
