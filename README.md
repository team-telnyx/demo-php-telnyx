<div align="center">

# Telnyx PHP Getting Started

![Telnyx](logo-dark.png)

Sample application demonstrating PHP SDK Basics

</div>

## Documentation & Tutorial

The full documentation and tutorial is available on [developers.telnyx.com](https://developers.telnyx.com/docs/v2/development/dev-env-setup?lang=dotnet&utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)

## Pre-Reqs

You will need to set up:

* [Telnyx Account](https://telnyx.com/sign-up?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)
* [Telnyx Phone Number](https://portal.telnyx.com/#/app/numbers/my-numbers?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) enabled with:
  * [Telnyx Call Control Application](https://portal.telnyx.com/#/app/call-control/applications?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)
  * [Telnyx Outbound Voice Profile](https://portal.telnyx.com/#/app/outbound-profiles?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)
* Ability to receive webhooks (with something like [ngrok](https://developers.telnyx.com/docs/v2/development/ngrok?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link))
* [PHP & Composer](https://developers.telnyx.com/docs/v2/development/dev-env-setup?lang=php&utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) installed

## What you can do

| Example                                        | Description                                                                                                         |
|:-----------------------------------------------|:--------------------------------------------------------------------------------------------------------------------|
| [Slim Messaging](slim-messaging)               | Example working with inbound MMS & SMS messages, downloading media from inbound MMS, and uploading media to AWS S3. |
| [console-number-search](console-number-search) | Example searching Available Numbers and counting number of unique `nxx` values returned from Telnyx                 |

### Install

Run the following commands to get started

```
$ git clone https://github.com/d-telnyx/demo-php-telnyx.git
```
