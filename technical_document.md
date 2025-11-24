# System Workflow Documentation

This document outlines the technical workflow of the AI Sales calling system, detailing the process from call initiation to conversational AI response.

### 1. Call Initiation
- **Trigger:** The process begins when a user clicks the "Call Lead" button on the `lead_details.php` page.
- **Action:** A JavaScript `fetch` request is sent to `make_call.php` with the lead's phone number, `leadsid`, and a generated call script.
- **Execution:** `make_call.php` uses the Twilio PHP SDK to create an outbound phone call.
- **Webhook:** During call creation, Twilio is configured with a webhook URL pointing to `process_industry.php`. This URL includes the `leadsid` and `callscript` as query parameters, linking the live call to the specific lead in the database.

### 2. Initial Interaction (TTS & STT)
- **Entry Point:** When the lead answers, Twilio makes an HTTP request to `process_industry.php`.
- **TwiML Generation:** This script generates a TwiML (Twilio Markup Language) response to control the call.
- **TTS (Text-to-Speech):** It uses the `<Say>` verb to convert the initial script text into speech, using the `Polly.Joanna` voice for a natural-sounding interaction.
- **STT (Speech-to-Text):** It then uses the `<Gather>` verb to listen for and transcribe the lead's spoken response. The `speechModel` is set to `phone_call` and `enhanced` is `true` for optimized accuracy over telephone audio.
- **Routing:** The `action` attribute of `<Gather>` directs Twilio to send the transcribed text to `handle_response.php` for the next step.

### 3. AI Response Generation
- **Input:** `handle_response.php` receives the transcribed text from Twilio in the `SpeechResult` POST parameter.
- **Prompting:** It constructs a prompt for the AI model using the user's speech.
- **AI Call:** The script calls the `callGemini` function, which makes a secure cURL request to the Google Gemini API (`gemini-flash-latest` model).
- **Output:** The `callGemini` function returns the AI-generated text response.

### 4. Conversational Loop
- **Logging:** `handle_response.php` logs the user's speech and the AI's response in the `Call_Details` table, creating a record of the conversation.
- **TwiML Generation:** It generates a new TwiML response to continue the conversation.
- **TTS:** The AI's text response is converted to speech using the `<Say>` verb.
- **STT:** The `<Gather>` verb is used again to capture the lead's next response.
- **Looping:** The `action` attribute for this `<Gather>` verb points back to `handle_response.php`, creating a continuous loop for the duration of the conversation.

### 5. Call Termination
- The call ends when either the lead hangs up or a TwiML response explicitly includes the `<Hangup>` verb. The current logic primarily relies on the lead ending the call.

### Summary of Technologies
- **Telephony Provider:** Twilio Voice API
- **Backend Logic:** PHP
- **Database:** SQL Server
- **Text-to-Speech (TTS):** Twilio `<Say>` verb with Amazon Polly voice.
- **Speech-to-Text (STT):** Twilio `<Gather>` verb.
- **AI Language Model:** Google Gemini API.