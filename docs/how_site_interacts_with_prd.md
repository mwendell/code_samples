# How the site interacts with PRD

We have two plugins that interact directly with PRD; Harbor Webhooks and Harbor PRD API Gateway. This document briefly explains what they do and how to use them.

### Harbor Webhooks

Harbor Webhooks processes new orders only. It isn't really a plugin, since it's not actually an installed WP plugin, it's just located in the plugins folder (this was a choice made by the initial developer on the plugin, we should have put it elsewhere). Nevertheless, it consists of a single PHP page in the /plugins/harbor-webhooks/prd-order-confirmation/ folder that listens for what is essentially a form submission from PRD, and then processes the $_REQUEST array (typically sent in the querystring). The PHP includes some sample arrays in URL format for testing.

The submission URL is **https://www.site.com/wp-content/plugins/harbor-webhooks/prd-order-confirmation/**

Once data is received, the page:

- Creates a new user if necessary
- Uses Harbor Registration to generate confirm and op-out tokens if new user.
- Uses Harbor Registration to send a welcome email if new user.
- Update existing user first name and last name if needed.
- Update user data on Whatcounts using Harbor Whatcounts Framework
- Save backup order data to wp_usermeta table
- Send order confirmation email
- Determines the subscription expiration date
- Record the entitlement data in Harbor using Harbor Entitlement Manager
- Record expiration dates at Whatcounts using Harbor Whatcounts Framework
- Record purchase transaction using Harbor Transactions

### Harbor PRD API Gateway

Harbor PRD API Gateway plugin is a very small plugin that makes outbound requests to the PRD API to confirm or update user entitlements. It exists to allow the Harbor Entitlements Manager plugin to remain agnostic in regard to fulfillment APIs such as PRD, SFG, or any provider we may work with in the future. When the get_entitlements() function is called, it simply requests entitlement information from PRD, translates that into the Entitlements Manager's preferred format, and returns the proper array.

The Harbor Entitlements Manager determines how often the plugin is called, and handles storing the entitlement info locally. There are no settings or control panel for the plugin itself, and all credentials and publication variables are hard-coded into the PHP. The functions in this plugin should never be called directly from any place other than the Harbor Entitlements Manager.

### Testing the Harbor Webhooks Plugin

You can easily test the Webhooks plugin in Harbor by submitting data that mimic the data submitted by PRD. The URL below is a sample of this complete URL, simply copy it and change the values to represent the order you would like to place. Review the Harbor Webhooks plugin to find appropriate magid values. Additionally, there are a number of default debug emails in /prd-order-confirmation/index.php that can be activated to help with any debugging.

**https://www.site.com/wp-content/plugins/harbor-webhooks/prd-order-confirmation/index.php?prd_id=123456789&name=Testy+McTesterson&magid=31903&product_term=12&payment=29.99&source_key=I6FEVG&email=testy.mctesterson@harbor.com&harbor_id=harbortest1&unixtime=1675879747&product_id=DIGITAL**

It should be obvious, but your new user will not validate properly through the PRD API Gateway (since they only exist in Harbor). Since we typically only re-confirm entitlements every 7 to 14 days (configured in Harbor Entitlements), you should be able to test with your new user successfully for a long enough period to confirm functionality.