v0.0.124 03/18/2025 
* Fixes on the trigger payment
* Fixes on the null frequency on checkout

v0.0.123 03/17/2025 
* error on login fix

v0.0.122 03/14/2025
* creating of subscription fixed
* checkout order with bocs notes updates

v0.0.121 03/13/2025
* Checkout pricing mismatch
* Adding bocs/subscription details to order notes

v0.0.120 03/12/2025
* email template branding
* sending of renewal order
* sending of emails if the renewal order was updated to processing

v0.0.119 03/11/2025
* Welcome Email implemented
* Subscription Renewal Invoice implemented
* Email template override paths have been updated to use the `yourtheme/bocs-wordpress/emails/` directory instead of `yourtheme/woocommerce/emails/`
* All email template filenames now have a `bocs-` prefix to avoid conflicts with other plugins
* Added template override documentation in README.md

v0.0.118 03/10/2025
* added email templates

v0.0.117 03/07/2025
* Improve plugin duplicate detection with additional checks
* Improve duplicate plugin detection and notifications
* Fix on the error when installing any other plugins
* Fixes on the checkout when filling up any form as a guest

v0.0.116 03/04/2025
* Fix on the list of user's subscriptions
* Fix on the update plugin, dev mode should get the latest, either a release or pre-release version

v0.0.115 02/19/2025
* Added the payment methods modal
* Added Stripe Elements
* Added the Stripe PHP library
* Able to edit the payment method on the subscriptions page
* Added payment methods to the WooCommerce payment methods list
* Added payment methods to the WooCommerce Payment Tokens

v0.0.114 02/18/2025
* Added the activate subscription button
* Added modal for the activate subscription
* Added modal for the cancel subscription
* Fix the issue on the redirect after the activation or cancellation of the subscription

v0.0.113 02/16/2025
* Added the check for duplicate plugin during pre-uploading of the plugin
* Fix update account information
* Update the settings link and label

v0.0.112 02/11/2025
* Added the divider on the subscriptions page
* Added more info on the accordion header
* Moved the menu item under the orders

v0.0.111 02/11/2025
* Errors on the loading line itemms were corrected
* Background errors were corrected during the updating the address

v0.0.110 02/10/2025
* Added the address editor on the subscriptions page
* Settings minor corrections on the admin side

v0.0.109 02/06/2025
* Use github for the updater instead of the old method S3
* Added the updater for the plugin for pre-release and release for the github repository
* Added the text domain for the plugin
* Correction on the old text domain to use 'bocs-wordpress'
* Fix the current user's list of subscriptions on My Account page

v0.0.108 02/04/2025
* Added the save payment checkbox on the checkout page 
* Fixes on the frequency on the subscriptions page
* Fixes on the next payment date on the subscriptions page
* Fixes on the early renewal on the subscriptions page

v0.0.107 01/31/2025
* Added the billing and shipping address on the order
* Fixed the issue on the order creation in relation to bocsId and collectionId

v0.0.106 01/29/2025
* Cancel subscription button on the subscriptions page

v0.0.105 01/28/2025
* Pause/Skip Next Payment Date

v0.0.104 01/27/2025
* Added the edit frequency on the subscriptions page

v0.0.103 01/24/2025
* Added accordion on the subscriptions page

v0.0.102 01/22/2025
* Revert the Updater to the old working version

v0.0.101 01/22/2025
* Hide and check the create account checkbox on the checkout page
* Hide and check the save card checkbox on the checkout page

v0.0.100 01/17/2025
* Force check Save payment information to my account for future purchases when bocs product is added to the cart
* Added the updater for the plugin for pre-release and release for the github repository

v0.0.99 01/16/2025
* Added Customer WooCommerce REST API for trigger payment

v0.0.98 01/15/2025
* Added require registration for bocs subscription if not logged in

v0.0.97 01/09/2025
* Update version for the widget in whatever environment
* Fixed on the discount

v0.0.96 01/09/2025
* Update version for the widget if in developer mode

v0.0.95 01/08/2025
* Remove stripe keys from the settings page
* Changes on the developer mode

v0.0.94 01/07/2025
* Fixes on the creation of the subscription after the payment

v0.0.93 01/07/2025
* Fix on the URL for the cart page redirect

v0.0.92 01/06/2025
* Closing parenthesis of a multi-line function call must be on a line by itself

v0.0.91 01/06/2025
* Added the wp's js documentation

v0.0.90 01/06/2025
* Added the bocsCartObject's js documentation to the bocs-cart.js

v0.0.89 01/06/2025
* pull request fix for the add-to-cart.js
* add to cart fix

v0.0.88 12/10/2024
* Undefined array key "bocs-view-subscription" fix

v0.0.87 12/03/2024
* Updated the widget javascript file

v0.0.86 12/02/2024
* Fixes return output on the other API endpoints

v0.0.85 11/30/2024
* Fixes on the API endpoints related to the contacts

v0.0.84 11/29/2024
* Fixes on the API endpoints (e.g. from data to data->data)

v0.0.83 11/26/2024
* Added collection and widget list on the widget editor
* Improved documentation on the widget editor
* refactored the widget editor javascript
* added the updater for the plugin for pre-release and release

v0.0.82 11/26/2024
* Added the developer mode switch for the widget URL

v0.0.81 11/19/2024
* Added the stripe keys settings

v0.0.80 11/18/2024
* Add the updating of the next payment date on the subscription page

v0.0.79 11/15/2024
* Fix the issue on the duplicate products when the product is added to the cart
* Fix the issue Cancel, Pause, Renew subscription buttons on the subscription page

v0.0.78 11/11/2024
* Added (alpha) to the plugin name

v0.0.77 11/11/2024
* Fix on the developer mode switch as the frontend was not updating

v0.0.76 11/06/2024
* Bug fixes and added developer mode

v0.0.75 11/06/2024
* updated bocs widget javascript url's version

v0.0.74 11/05/2024
* updated to production link for the api

v0.0.73 10/16/2024
* My profile subscriptions list fix

v0.0.72 10/10/2024
* Hide old bocs page
* empty bocs headers error traps

v0.0.71 10/08/2024
* Fixed on bocs list table not showing data

v0.0.70 10/04/2024
* applied fixes on adding subscription and order on the bocs end

v0.0.69 10/03/2024
* updates on the add-to-cart.js
* added getting of the bocs id

v0.0.68 08/27/2024
* updated bocs widget javascript file
* added the widget

v0.0.67 06/24/2024
* added the bocs subscription related for the cart and checkout page

v0.0.66 06/18/2024
* Added related orders
* Added email triggers for the renewal orders
* Added the custom woocommerce rest api for email

v0.0.65 06/11/2024
* default ticked for 'Save payment information to my account for future purchases'
* add the subscriptions totals after the buttons

v0.0.64 06/10/2024
* Add renew, cancel subscription

v0.0.63 05/30/2024
* Fix on the add subscription to bocs after the payment
* Listing of the subscriptions fix (first page)

v0.0.62 05/30/2024
* Fix on the add subscription to bocs after the payment
* Listing of the subscriptions fix (first page)

v0.0.61 05/28/2024
* Removed the product type of bocs type

v0.0.60 05/24/2024
* Ajax object name fix conflict with other plugins

v0.0.57 05/14/2024
* Fix on the auto adding of the bocs keys to woocommerce site

v0.0.56 05/08/2024
* Check if WooCommerce is enabled before installing
* Add Status settings like of the WooCommerce

v0.0.55 05/02/2024
* Authorization Settings fix

v0.0.54 04/22/2024
* Onchange fix on the selections for the collections and bocs

v0.0.53 04/18/2024
* API related fixes for creating subscription

v0.0.52 04/17/2024
* fix for the admin.js related to the API

v0.0.50 04/15/2024
* updated the bocsId to id
* updated the productId to id

v0.0.49 04/15/2024
* updated the bocsId to id
* updated the collectionId to id

v0.0.48 10/26/2023
* added the method for adding and showing of the Bocs' product logs
* added the admin sidebar for the product's bocs logs

v0.0.47 10/16/2023
* added the auto add of the collections and widgets when the apps' end was updated

v0.0.46 10/06/2023
* get the list of the collections and widgets every hour
* loads the list of the collections and widgets directly

v0.0.45 10/03/2023
* readme changes

v0.0.44 10/03/2023
* correction on the edit page

v0.0.43 10/03/2023
* Added icon on the description
* Edit Page, showing the selected/saved

v0.0.42 09/29/2023
* New UI added on Gutenburg Editor

v0.0.41 09/21/2023
* Fix error when both Gutenburg and sidebar is loading

v0.0.40 09/18/2023
* Fix error when creating a customer via API

v0.0.38 09/15/2023
* Redirects to the checkout page

v0.0.37 09/14/2023
* Added support for editors using ACF

v0.0.36 09/13/2023
* Added the Fixed type

v0.0.35 08/16/2023
* Added filter on the admin users list for options bocs or wordpress or both

v0.0.34 08/11/2023
* Added Source (Wordpress or Bocs) column on Admin Users List

v0.0.33 08/04/2023
* Moved the sync logs and sync modules under settings as tabs

v0.0.32 08/01/2023
* Fixes on the sync end

v0.0.31 07/31/2023
* create/register new user log/sync

v0.0.30 07/30/2023
* log's context update to json encoded array
* added details on the table display

v0.0.29 07/27/2023
* Endpoint ID fix
* Added logs on the syncs 
* Added module and id on logs

v0.0.28 07/26/2023
* Updated the endpoint for the sync
* Removed the logs related code due to errors

v0.0.27 07/24/2023
* moved the position of the menu
* added sample error log menu and content

v0.0.26 07/24/2023
* Fix when the bocs' contact id is outdated or not on the same Bocs Account

v0.0.25 07/20/2023
* When user his own profile - trigger sync

v0.0.24 07/18/2023
* Added synced when a user is edited

v0.0.23 07/13/2023
* saving on the bocs widget editor fix

v0.0.22 07/12/2023
* added radio input on the bocs widget editor
* remove highlight to non-active option

v0.0.21 07/11/2023
* added auto add bocs keys needed

v0.0.20 07/07/2023
* added the working plugin updater

v0.0.19 07/06/2023
* fix widget for the collections list
* added wordpress updater

v0.0.18 07/03/2023
* added collections list

v0.0.17 06/29/2023
* added the list of subscriptions and showing under the menu
* added the discount upon checkout

v0.0.16 06/29/2023
* add subscription to bocs app
* add new subscription to wordpress site - tables only

v0.0.15 06/28/2023
* fixes on the cart checkout
* added logo on the menu

v0.0.14 06/27/2023
* added creation of the bocs and products if not synced on checkout
* if the order is paid or processing, it will create an order and subscription on bocs end
* fix not showing list of bocs