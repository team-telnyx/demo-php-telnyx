# Speech Assistant with Telnyx Voice and the OpenAI Realtime API (PHP)

This application demonstrates how to use **PHP**, [Telnyx Voice](https://developers.telnyx.com/docs/voice/programmable-voice/get-started) with [TeXML](https://developers.telnyx.com/docs/voice/programmable-voice/texml-setup), and [OpenAI's Realtime API](https://platform.openai.com/docs/) to make a phone call and interact with an AI Assistant.

The application opens WebSocket connections with both the OpenAI Realtime API and Telnyx, sending voice audio between them to enable a two-way conversation.

This application uses the following Telnyx products in conjunction with OpenAI's Realtime API:

- **Voice** (TeXML, WebRTC)
- **Phone Numbers**

## Prerequisites

To use the app, you will need:

- **A Telnyx account.** You can [sign up for free](https://telnyx.com/sign-up).
- **PHP 7.4 or higher.** Download from [here](https://www.php.net/downloads.php).
- **Composer** installed on your system. Instructions [here](https://getcomposer.org/download/).
- **A Telnyx number with _Voice_ capabilities.** You can find [a walkthrough to purchase a phone number here](https://support.telnyx.com/en/articles/4380325-search-and-buy-numbers).
- **An OpenAI account and an OpenAI API Key.** You can [sign up here](https://platform.openai.com/).
- **Access to the OpenAI Realtime API.**

## Local Setup Overview

This project can be summarized by the following steps:

1. **Set up port forwarding or a tunneling service** to expose your local server to the internet. This is necessary for Telnyx to send webhook events to your application.

   - **Recommended:** Use a tunneling provider like [Ngrok](https://ngrok.com/), [Expose](https://expose.dev/), or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/run-tunnel/).
   - **Not recommended:** Manually port forward by configuring your router settings, as this may expose your network to security risks.

2. **Set up your Telnyx account** with a procured [phone number that has Voice capabilities](https://support.telnyx.com/en/articles/4380325-search-and-buy-numbers).

3. **Configure the `.env` file** with your OpenAI API key and any other necessary environment variables.

4. **Install the PHP dependencies** using Composer.

5. **Run the PHP application.**

### Open an Ngrok Tunnel

To make your local server accessible to Telnyx, you can use Ngrok to create a secure tunnel to your local machine.

#### Install Ngrok

Download and install Ngrok from [the official website](https://ngrok.com/download).

#### Run Ngrok

Open a terminal and run the following command to start Ngrok on port `8000`:

```bash
ngrok http 8000
```

You should see output similar to:

```
ngrok by @inconshreveable

Session Status                online
Account                       Your Name (Plan: Free)
Version                       2.x.x
Region                        United States (us)
Web Interface                 http://127.0.0.1:4040
Forwarding                    http://abcd1234.ngrok.io -> localhost:8000
Forwarding                    https://abcd1234.ngrok.io -> localhost:8000

Connections                   ttl     opn     rt1.   rt5     p50     p90
                              0       0       0.00   0.00    0.00    0.00
```

Copy the `Forwarding` URL that starts with `https://`. You'll need this URL to configure your Telnyx webhook.

### Configure Telnyx Webhook

1. **Access TeXML Applications:**

   Log in to the [Telnyx Mission Control Portal](https://portal.telnyx.com/) and navigate to **"Call Control"** > **"TeXML"**.

2. **Edit Your TeXML Application:**

   Click the edit symbol [✎] next to your TeXML application.

3. **Set the Webhook URL:**

   In the **"App Info"** section, find **"Send a webhook to the URL: (Required)"** and paste your Ngrok forwarding URL, adding `/inbound` at the end. For example:

   ```
   https://abcd1234.ngrok.io/inbound
   ```

   > **Ensure that the HTTP method is set to POST.**

4. **Save Changes:**

   Click **"Save"** to update your application settings.

### Update the `.env` File

1. **Rename `.env.sample` to `.env`:**

   In your project directory, rename the `.env.sample` file to `.env`.

2. **Add Your OpenAI API Key:**

   Open the `.env` file and add your OpenAI API key:

   ```
   OPENAI_API_KEY=your_openai_api_key_here
   ```

3. **Set the Port (Optional):**

   If you wish to use a different port, you can set it in the `.env` file:

   ```
   PORT=8000
   ```

### Install Dependencies

Ensure that you have **PHP** and **Composer** installed.

1. **Install Composer Dependencies:**

   In the project directory, run:

   ```bash
   composer install
   ```

   This will install all required PHP libraries as specified in `composer.json`.

2. **Create `texml.xml` File:**

   Place your `texml.xml` file in the project directory. This file contains TeXML instructions for handling inbound calls.

### Run the PHP Application

Start the server by running:

```bash
php server.php
```

You should see output indicating that both the HTTP server and WebSocket server are running:

```
HTTP server running at http://0.0.0.0:8000
WebSocket server running at ws://0.0.0.0:8001/media-stream
```

> **Note:** The WebSocket server runs on port `8001` (which is `PORT + 1`). Ensure that this port is available.

## Test the App

With Ngrok running and Telnyx configured, you can now test the application:

1. **Make a Call:**

   Dial the Telnyx phone number you configured. The call should be forwarded to your application.

2. **Interact with the AI Assistant:**

   You should hear the AI assistant answer the call and be able to have a conversation.

## Troubleshooting

- **Ngrok URL Changes:**

  Remember that the Ngrok URL changes every time you restart it (unless you're using a paid plan with a custom domain). Update the Webhook URL in the Telnyx portal whenever the Ngrok URL changes.

- **Firewall Settings:**

  Ensure that your system's firewall allows incoming connections on the ports you're using (default `8000` and `8001`).

- **Dependencies Not Installed:**

  If you encounter errors related to missing PHP libraries, make sure you've run `composer install` successfully.

- **OpenAI API Access:**

  Verify that your OpenAI API key is correct and that your account has access to the Realtime API.

## Additional Notes

- **Security:**

  - Keep your `.env` file secure and never commit it to version control.
  - Be cautious when exposing ports and running servers on your local machine.

- **PHP Version:**

  - Ensure you're running PHP 7.4 or higher to maintain compatibility with the libraries used.

- **Composer Dependencies:**

  - The application relies on several PHP libraries managed via Composer:
    - `react/http`
    - `react/socket`
    - `react/event-loop`
    - `ratchet/pawl`
    - `ratchet/rfc6455`
    - `vlucas/phpdotenv`

- **OpenAI Realtime API Documentation:**

  - Familiarize yourself with the [OpenAI Realtime API documentation](https://platform.openai.com/docs/) for detailed information on the API endpoints and parameters used.

- **Telnyx Documentation:**

  - Review the [Telnyx Voice API documentation](https://developers.telnyx.com/docs/voice/programmable-voice/get-started) and [TeXML documentation](https://developers.telnyx.com/docs/voice/programmable-voice/texml-setup) for more details on configuring your Telnyx applications.

## Conclusion

You now have a PHP application that integrates Telnyx Voice services with the OpenAI Realtime API to create a conversational AI assistant over the phone. Feel free to customize the assistant's behavior by modifying the `SYSTEM_MESSAGE` in your code or adjusting other parameters.

If you have any questions or need further assistance, don't hesitate to reach out!
