# How the site interacts with PRD
Michael Wendell

We have two plugins that interact directly with PRD; Haven Webhooks and Mequoda PRD API Gateway. This document briefly explains what they do and how to use them.

### Haven Webhooks

Haven Webhooks processes new orders only. It isn't really a plugin, since it's not actually an installed WP plugin, it's just located in the plugins folder (this was a choice made by the initial developer on the plugin, we should have put it elsewhere). Nevertheless, it consists of a single PHP page in the /plugins/haven-webhooks/prd-order-confirmation/ folder that listens for what is essentially a form submission from PRD, and then processes the $_REQUEST array (typically sent in the querystring). The PHP includes some sample arrays in URL format for testing.

The submission URL is *https://www.site.com/wp-content/plugins/haven-webhooks/prd-order-confirmation/*

Once data is received, the page:

- Creates a new user if necessary
- Uses Haven Registration to generate confirm and op-out tokens if new user.
- Uses Haven Registration to send a welcome email if new user.
- Update existing user first name and last name if needed.
- Update user data on Whatcounts using Mequoda Whatcounts Framework
- Save backup order data to wp_usermeta table
- Send order confirmation email
- Determines the subscription expiration date
- Record the entitlement data in Haven using Haven Entitlement Manager
- Record expiration dates at Whatcounts using Mequoda Whatcounts Framework
- Record purchase transaction using Mequoda Transactions

### Mequoda PRD API Gateway

Mequoda PRD API Gateway plugin is a very small plugin that makes outbound requests to the PRD API to confirm or update user entitlements. It exists to allow the Haven Entitlements Manager plugin to remain agnostic in regard to fulfillment APIs such as PRD, SFG, or any provider we may work with in the future. When the get_entitlements() function is called, it simply requests entitlement information from PRD, translates that into the Entitlements Manager's preferred format, and returns the proper array.

The Haven Entitlements Manager determines how often the plugin is called, and handles storing the entitlement info locally. There are no settings or control panel for the plugin itself, and all credentials and publication variables are hard-coded into the PHP. The functions in this plugin should never be called directly from any place other than the Haven Entitlements Manager.

### Testing the Haven Webhooks Plugin

You can easily test the Webhooks plugin in Haven by submitting data that mimic the data submitted by PRD. The URL below is a sample of this complete URL, simply copy it and change the values to represent the order you would like to place. Review the Haven Webhooks plugin to find appropriate magid values. Additionally, there are a number of default debug emails in /prd-order-confirmation/index.php that can be activated to help with any debugging.

*https://www.site.com/wp-content/plugins/haven-webhooks/prd-order-confirmation/index.php?prd_id=123456789&name=Testy+McTesterson&magid=31903&product_term=12&payment=29.99&source_key=I6FEVG&email=testy.mctesterson@mequoda.com&haven_id=haventest1&unixtime=1675879747&product_id=DIGITAL*

It should be obvious, but your new user will not validate properly through the PRD API Gateway (since they only exist in Haven). Since we typically only re-confirm entitlements every 7 to 14 days (configured in Haven Entitlements), you should be able to test with your new user successfully for a long enough period to confirm functionality.