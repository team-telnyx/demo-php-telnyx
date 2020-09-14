<div align="center">

# Telnyx-PHP Available Number Searching

![Telnyx](../logo-dark.png)

Sample Console App demonstrating Telnyx-PHP Available number Searching

</div>

## Documentation & Tutorial

The full documentation and tutorial is available on [developers.telnyx.com](https://developers.telnyx.com)

## Pre-Reqs

You will need to set up:

* [Telnyx Account](https://telnyx.com/sign-up?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)
* [PHP & Composer](https://developers.telnyx.com/docs/v2/development/dev-env-setup?lang=php&utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) installed

## What you can do

* Search by areacode or NPA and get a list of response

## Usage

The following environmental variables need to be set

| Variable               | Description                                                                                                                                              |
|:-----------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TELNYX_API_KEY`       | Your [Telnyx API Key](https://portal.telnyx.com/#/app/api-keys?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)              |
| `TELNYX_PUBLIC_KEY`    | Your [Telnyx Public Key](https://portal.telnyx.com/#/app/account/public-key?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) |
| `TELNYX_APP_PORT`      | **Defaults to `8000`** The port the app will be served (_if applicable_)                                                                                                  |

### .env file

This app uses the excellent [phpenv](https://github.com/vlucas/phpdotenv) package to manage environment variables.

Make a copy of [`.env.sample`](./.env.sample) and save as `.env` and update the variables to match your creds.

```
TELNYX_API_KEY=
TELNYX_PUBLIC_KEY=
TENYX_APP_PORT=8000
```

### Install

Run the following commands to get started

```
$ git clone https://github.com/d-telnyx/demo-php-telnyx.git
$ cd console-number-search-and-order
$ composer install
```

### Run

Run the PHP script `php app.php` from the command line and answer the prompts to search and order

```
$ php app.php
Which NPA (area code) would you like to search?: 828
(y/n) Would you like to order +18289293882?
n
Ok, not ordering
(y/n) Would you like to order +18285208845?
y
Order for phone number: +18285208845 is pending
Phone Number: +18285208845 with id: 1461090961252681720 status is: active
Array
(
    [1] => stdClass Object
        (
            [orderId] => a0afc4d3-8d27-4f94-a385-649af4e0e7d4
            [phoneNumber] => +18285208845
            [phoneNumberStatus] => active
            [phoneNumberId] => 1461090961252681720
        )

)
```