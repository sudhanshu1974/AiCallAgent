# Real-Time AI Voice Agent

This is a pure real-time AI voice agent that uses PHP, Gemini, and Twilio.

## File Structure

*   `index.php`: Displays the conversation logs from the `leads` table.
*   `twilio_webhook.php`: Handles the initial incoming voice call from Twilio.
*   `handle_response.php`: Processes the user's speech input, calls the Gemini API, and logs the conversation.
*   `config.php`: Contains the configuration for the application.
*   `config.example.php`: An example configuration file.
*   `log/`: A directory that contains the activity and error logs.

## Setup

1.  **Install dependencies:**

    ```bash
    composer install
    ```

2.  **Configure the application:**

    Create a `config.php` file by copying the `config.example.php` file:

    ```bash
    cp config.example.php config.php
    ```

    Open `config.php` and replace the placeholder values for `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_PHONE_NUMBER`, `GEMINI_API_KEY`, and `BASE_URL` with your actual credentials and URL. You can also set these values as environment variables.

    **Note on `BASE_URL` and local development:** If you are developing locally, you will need to use a tool like `ngrok` to expose your local server to the internet. Your `BASE_URL` should be the public URL provided by `ngrok` (e.g., `https://your-ngrok-subdomain.ngrok.io`).

3.  **Run the application:**

    You can run this application on a local server or deploy it to a web server. To test it locally, you can use a tool like ngrok to expose your local server to the internet.

4.  **Configure Twilio:**

    In your Twilio account, create a new phone number and configure its voice webhook to point to the URL of your `twilio_webhook.php` file.


