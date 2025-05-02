v0.0.147 05/01/2025
* Style: Updated CSS files with improved styling for subscription management pages
* Style: Updated version cache busting for better user experience 
* Feature: Enhanced template files for subscription management
* UI: Improved order line items display in subscription templates
* UX: Enhanced subscription management interface with better user interaction
* Fix: Improved responsive design for subscription-related pages

v0.0.146 04/30/2025
* Feature: Added detailed debugging logs to edit-details JavaScript for improved troubleshooting
* Refactor: Optimized subscription product update handling and API interactions
* Style: Updated subscription edit details UI templates and styling
* Improvement: Enhanced product relationship tracking with better logging
* Performance: Streamlined API interactions for better performance
* Fix: Improved product mapping between WooCommerce and BOCS products
* Fix: Added null checks to prevent PHP notices in updater class

v0.0.145 04/29/2025
* Fix: Improved payment method handling and API integration
* Fix: Enhanced payment method selection and updated script version
* Style: Refined button and modal styles for better visual consistency
* Feature: Enhanced delivery address form with country-specific states
* Feature: Improved payment token handling and management
* Refactor: Optimized customer subscription lookup by using externalSourceId
* Feature: Added debug sync endpoint and improved payment method handling
* Feature: Added payment intent synchronization between Stripe and BOCS API
* Fix: Resolved issue with default payment method selection
* Refactor: Enhanced error handling in API communication
* UI: Improved responsive design for subscription management screens

v0.0.144 04/28/2025
* Feature: Improved Stripe payment method handling for subscriptions
* Fix: Enhanced subscription management interface and early renewal functionality
* Chore: Removed Sentry SDK and updated autoloader
* Refactor: Removed Sentry integration and frontend initialization
* Feature: Enhanced empty product handling and improved Stripe initialization

v0.0.143 04/27/2025
* Style: Improved subscription status badge styling with modern design
* Fix: Added null checks to prevent PHP notices in updater class
* Docs: Updated changelog release dates for consistency

v0.0.142 04/26/2025
* Feature: Implemented reusable order line items component for consistent display
* Feature: Enhanced subscription interface with dynamic order line items integration
* Refactor: Improved account templates and subscription display for better user experience
* Refactor: Optimized API handling and helper classes for better performance
* Fix: Fixed handling of product data in subscription details
* UI: Enhanced subscription management interface with improved styling

v0.0.141 04/25/2025
* Feature: Enhanced subscription management UI with improved interaction
* Style: Standardized button hover effects across all components
* Fix: Prevented paused subscriptions from incorrectly showing as upcoming
* Fix: Updated frontend template links and references
* Chore: Organized commits into logical feature groups

v0.0.140 04/24/2025
* Feature: Added developer mode with cache management tooling
* Feature: Implemented product mapping and enhanced admin settings panel
* Feature: Improved user account management and subscription handling
* Feature: Enhanced subscription management interface with better UI/UX
* Feature: Improved edit details page with better product selection experience
* Refactor: Enhanced AJAX handlers and request management
* Fix: Updated email footer template for better compatibility
* Chore: Organized code structure and improved documentation

v0.0.139 04/23/2025
* Feature: Added frequency discount display and improved data handling
* Fix: Implemented API request loop detection and error handling
* Style: Improved button loading states and subscription display 
* Style: Updated color scheme and button styling
* Refactor: Removed temporary button fix scripts and updated subscription display
* Feature: Added admin email preview functionality and improved email templates
* Feature: Added Sentry integration for error tracking
* Fix: Improved myaccount page templates
* Chore: Updated license information
* Chore: Removed unused files

v0.0.138 04/22/2025
* Feature: Add subscription details editing interface with custom product selection
* Feature: Enhance subscription management with improved UI and API handling
* Feature: Implement box switching functionality with full account system integration
* Feature: Add edit details page with product modification capabilities for custom boxes
* UX: Improve subscription accordion interface with better layout and interactions
* UX: Enhance button event handling with improved documentation
* Refactor: Improve account management system with optimized class structure
* UI: Update subscription styles for a more intuitive user experience
* Performance: Optimize API request handling for subscription management
* Integration: Fully integrate subscription details editing with account system

v0.0.137 04/10/2025
* Payments: Improved payment method handling with better empty state support
* Payments: Enhanced error feedback for Stripe integration
* UI/UX: Standardized color scheme and improved responsive design
* UI/UX: Added common CSS file with consistent variables across templates
* UI/UX: Enhanced product dialog layout with improved interactivity
* UI/UX: Improved modal sizing and positioning for better usability
* Performance: Added debug logging for payment processing to improve troubleshooting

v0.0.136 04/09/2025
* Core: Reduced verbose debug logging in Bocs_WooCommerce class
* Emails: Improved email handling for new customers with better targeting
* Performance: Optimized API communication and reduced redundant logging
* UX: Simplified checkout process with cleaner console output

v0.0.135 04/08/2025
* Cart: Enhanced stock validation with improved UI feedback and controls
* Cart: Added comprehensive product availability checks and status updates
* Cart: Implemented dynamic widget loading and initialization
* Checkout: Improved account creation and registration UX
* Checkout: Enhanced registration settings handling and descriptions
* Core: Improved plugin structure and hook organization
* Core: Optimized admin instance handling for better performance

v0.0.134 04/07/2025
* Cart: Improved product validation and error handling in add-to-cart process
* Checkout: Enhanced account creation workflow with improved UI and user guidance
* Emails: Improved renewal order email variable declarations and added custom styling
* API: Enhanced communication with custom headers and better error handling
* Authentication: Improved API error handling and response parsing for user data
* UI/UX: Added clear messaging for account creation during checkout
* Templates: Added custom checkout form template for better subscription handling
* Version: Updated JavaScript and plugin version numbers

v0.0.133 04/04/2025
* Checkout System: Enhanced subscription display with recurring totals and improved formatting
* Cart Functionality: Improved add-to-cart flow and payment method integration
* Admin Interface: Updated administrative dashboard and improved shortcode functionality
* Templates: Added new checkout templates for better subscription information display
* User Experience: Enhanced frontend display of subscription frequencies and pricing

v0.0.132 04/03/2025
* Account Management: Added account creation for BOCS subscription customers and updated user roles
* Order Processing: Enhanced synchronization with BOCS API and improved status handling
* API Integration: Improved error handling and enhanced REST API functionality
* Email System: Updated templates, added notifications, and improved delivery for subscription events
* Payment Processing: Enhanced Stripe integration with better UI and error handling
* Code Quality: Cleaned up initialization code and centralized WooCommerce integration

v0.0.131 04/01/2025
* Improved payment method management with improved UI
* Fixed email delivery reliability with multiple fallback methods
* Improved subscription display and core plugin hooks
* Enhanced customer details editing interface
* Improved email templates for subscription status changes 
* Fixed standardization of email class registration with WooCommerce
* Enhanced payment method handling and related emails
* Added comprehensive error handling for payment processing

v0.0.130 03/31/2025
* Enhanced subscription pause email handling with improved error recovery
* Added new email templates for frequency updates and subscription changes
* Improved box update email system with better customer email extraction and multiple delivery methods
* Enhanced subscription frequency update handling with email notifications
* Improved plugin update system for more reliable updates
* Updated checkout process and enhanced AJAX handling
* Enhanced admin interface and utility functions
* Improved order processing and account management functionality
* Added comprehensive debugging and logging for email delivery
* Enhanced email notification system for failed payments and subscription changes
* Fixed email sending with better logging and fallback methods
* Improved nonce handling and error logging for box update emails
* Added robust exception handling to prevent 500 errors
* Fixed JavaScript errors in update functions
* Added subscription update verification and detailed logging
* Improved BOCS ID extraction from subscription data
* Enhanced API request debugging and product retrieval
* Added emergency mail testing with multiple fallback methods
* Prevented duplicate subscription creation during payment trigger and manual status edits
* Enhanced order status updates and payment process notes

v0.0.129 03/28/2025
* Implemented comprehensive email system improvements with new templates and notifications
* Added new email templates for subscription status changes, renewals, and payment retries
* Enhanced payment methods handling with robust error handling
* Improved subscription management UI and functionality
* Added test tools for upcoming renewal emails in admin area
* Refactored email system using singleton pattern for better performance
* Fixed various email template compatibility issues
* Added new customer welcome email and subscription confirmation templates
* Improved error handling and logging throughout the system

v0.0.128 03/27/2025
* Added total field from metadata to API requests to fix subscription pricing issues
* Fixed discrepancies in subscription totals when updating or switching subscriptions
* Implemented Switch Bocs functionality with direct API integration
* Enhanced subscription management UI with improved modals and animations
* Added ability to update frequency and products for current subscription box
* Fixed product selection handling for both fixed and custom box types
* Resolved discount calculation issues with proper type comparisons
* Ensured all metadata values use correct formats for API requirements
* Improved error handling and success messaging throughout subscription forms
* Fixed multiple JavaScript syntax errors in subscription management
* Optimized page handling to refresh instead of redirect after subscription updates
* Implemented modern bocs.io branding and enhanced UI/UX elements

v0.0.127 03/25/2025
* improve auto_add_bocs_keys implementation and add page load hook

v0.0.126 03/24/2025
* Rename child/sibling order to renewal order
* Syntax error at bocs checkout js

v0.0.125 03/24/2025 
* Product Update 
* Frequency Update
* Get Stripe keys

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