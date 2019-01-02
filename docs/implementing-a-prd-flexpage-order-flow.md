# Implementing a PRD FlexPage Order Flow

1. [Request API credentials from PRD](#request-api-credentials-from-prd)
2. [Confirm the PRD app version and Organization ID for your project](#confirm-the-prd-app-version-and-organization-id-for-your-project)
3. [Install the Haven PRD Framework plugin](#install-the-haven-prd-framework-plugin)
4. [Install the Haven Entitlement manager plugin](#install-the-haven-entitlement-manager-plugin)
5. [Configure the Haven Entitlement Manager Plugin](#configure-the-haven-entitlement-manager-plugin)
6. [Configure the Haven PRD Framework Plugin](#configure-the-haven-prd-framework-plugin)
7. [Add required theme template files to root of theme folder](#add-required-theme-template-files-to-root-of-theme-folder)
8. [Create Wordpress "Pages" for each of the theme template files](#create-wordpress-"pages"-for-each-of-the-theme-template-files)
9. [Work with your client and PRD to develop a list of product codes](#work-with-your-client-and-prd-to-develop-a-list-of-product-codes)
10. [Does your product list contain any club or membership products?](#does-your-product-list-contain-any-club-or-membership-products)
11. [You can now set up your Products Codes in the PRD Framework plugin](#you-can-now-set-up-your-products-codes-in-the-prd-framework-plugin)
12. [Build your FlexPages](#build-your-flexpages)
13. [Add your Confirmation Email posts](#add-your-confirmation-email-posts)
14. [Add your Confirmation Page posts](#add-your-confirmation-page-posts)
15. [Debug the order process](#debug-the-order-process)
16. [Debug User Entitlements](#debug-user-entitlements)


### Request API credentials from PRD

- Request Gatekeeper API credentials for all sites
- Request Special Programs E-Commerce API and Manage Special Programs API credentials if you are implementing Shopp, Event Espresso, PRD Payments Terminal, or CSN style order flows; any implementation that involves using PRD to simply process credit card payments.
- Request Customer Update API credentials if you need customer data modified in Haven to be forwarded to PRD. 

### Confirm the PRD app version and Organization ID for your project

- The app version for all of our sites so far has been 2.20
- Some sample Org IDs for various clients so far: BEL (Belvoir - UHN), ACR (Ceramic Arts Network), PRP (Prime - I Like Crochet), CSN (Countryside Network)

### Install the Haven PRD Framework plugin

- The plugin currently requires PHP 7+

### Install the Haven Entitlement manager plugin

- There is currently no automated database creation, unless you run the conversion script found in the plugin folder. You can run the conversion script without doing any conversion however, and the database tables will be created. If you'd like to create the tables manually, use the SQL statements below.

```
CREATE TABLE IF NOT EXISTS wp_mequoda_entitlements (
	id INT(11) NOT NULL AUTO_INCREMENT,
	user_id INT(11) NOT NULL,
	pub_id varchar(8) NOT NULL,
	channel varchar(8) NOT NULL DEFAULT 'web',
	issue_id INT(11) NOT NULL DEFAULT 0,
	expires INT(11) NOT NULL DEFAULT 0,
	parent_id INT(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (id),
	KEY user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS wp_mequoda_entitlements_refreshed (
	user_id int(11) NOT NULL,
	refreshed int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (user_id),
	UNIQUE KEY user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
```

### Configure the Haven Entitlement Manager Plugin

- Check the PRD Gatekeeper box in the External Entitlement Database section
- Select a Renewal Frequency from the drop-down list. 14 days is a good starting point, but up to client.
- Hold off on adding Composite Products, we'll discuss that below

### Configure the Haven PRD Framework Plugin

- Add your App Version and Org ID values.
- Add your API keys in the appropriate boxes
- More info about setting specifics can be found in the Haven PRD Framework page under Plugins
- Hold off on adding Product Codes, we'll discuss that below


### Add required theme template files to root of theme folder

From the within the plugin folder copy the following files from the /theme-files directory to the root of the site's theme folder.

- page-offsite-order-process.php
- page-offsite-order-confirm.php

### Create Wordpress "Pages" for each of the theme template files

Offsite Order Processing Page
- Title: Offsite Order Processing
- Slug: order-processing
- Template: Page Offsite Order Process

Offsite Order Confirmation page
- Title: Offsite Order Confirmation
- Slug: order-confirmation
- Template: Page Offsite Order Confirmation

### Work with your client and PRD to develop a list of product codes

- Decide on your Haven Pub ID values for each publication. These are created in the Haven Publication Manager (Haven Pubs) plugin.
- If possible, try to create Haven Pub IDs that match the Product Codes being used by PRD; these are generally two letter codes.
- Try not to create overly long Pub IDs. Two characters is ideal, and there's really no reason to ever exceed 3 characters. Ever.
- PRD should be able to provide you with a list of their Product Codes, sorted by Program.
- Internally, PRD divides their products into four Programs: Subscriptions (SU), Catalog (CA), Continuity (CN), and Special Programs (SP). 
- Special Programs products will also include Secondary Product Codes.
- All Products will include Access Levels. PRD has a history of naming access levels with no consistency. We want to change that. At the beginning of every project, insist that PRD use the following access level designations COMBO (for all-access combo purchases), DIGITAL (for international or digital-only combo purchases), PRINT, TABLET, WEB. The default for all products that have only a single online access level should be WEB. This applies to clubs and other types of memberships as well. If it is necessary to shorten these names, we would prefer to reduce them to a single character like this: C, D, P, T, W.
- Example of "Launch Product" doc that we've been using to store an update this information is here: CAN Launch Product Doc
- Once you have your products created and your Pub IDs defined, set them up in Haven Pubs

### Does your product list contain any club or membership products?

- These are considered "Composite Products" and are simply a consilidation of various entitlements under a single purchase.
- Composite Products are managed in the Haven Entitlement Manager
- You will have had to set up your publications first (see previous step)
- Add a Product ID (akin to Pub ID) and Product Name
- Once created, you can select the specific entitlements granted to members of your club.

### You can now set up your Products Codes in the PRD Framework plugin

- Specifics can be found under the PRD Framework plugin page, but essentially you will add the PRD Product code (and possible the Secondary Code) for every single PRD product that can possible be purchased, and attache each of those products to a Haven Publication or Composite Product.
- Once attached, the Gatekeeper will be able to attach specific PRD Product Codes with Haven Publications

### Build your FlexPages

- Under the Confirmation/Error tab, be sure that the Custom Redirect URLs fields are completed using the site URL and the slugs you used for your Offsite Order Processing Page.
- Send Test Mode purchases to the dev server, production purchases to the production server.
- Beware of complexities in the Test Mode process, discussed in more detail on the PRD FlexPages development page.

### Add your Confirmation Email posts

- Create individual posts in the Confirmation Emails section of Haven (meq_confirm_email custom post type)
- Create one post for each combination of Pub ID and Access Level that may be purchased.
- The slugs chould follow this format: "purchase-confirmation-[ pub_id ]-[ access level ]", so the slug for a combo purchase of the CM publication would be purchase-confirmation-cm-combo.
- Accepted access level values are combo, digital, web, tablet, print. 
- Special cases can be configured in the offsite order processing page if necessary.
- If a specific offer, tied to a unique keycode, requires a special confirmation email, putting the keycode in the slug will give it priority over the rules defined above. The slug in this case would read purchase-confirmation-[ keycode ], ie: purchase-confirmation-CMAARE

### Add your Confirmation Page posts

- Create individual posts in the User Content section of Haven (uc custom post type) 
- Create one post for each combination of Pub ID and Access Level that may be purchased.
- The slugs chould follow this format: "order-confirmation-[ pub_id ]-[ access level ]", so the slug for a combo purchase of the CM publication would be order-confirmation-cm-combo.
- Accepted access level values are combo, digital, web, tablet, print. 
- Special cases can be configured in the offsite order confirmation page if necessary.
- If a specific offer, tied to a unique keycode, requires a special confirmation page, putting the keycode in the slug will give it priority over the rules defined above. The slug in this case would read order-confirmation-[ keycode ], ie: order-confirmation-CMAARE

### Debug the order process

- Place FlexPage orders on PRD in Test mode
- Did your entitlements get recorded correctly?
- Did your confirmation email get sent correctly?
- If you created anew user, did you get the welcome email?
- Was your confirmation page displayed correctly?
- Call up the user's history in the PRD Terminal, has the purchase been recorded properly at PRD?

### Debug User Entitlements

- Review the entitlements in the wp_mequoda_entitlements database.
- Does the account page display the proper entitlements for your user?
- Does the My Library page display the proper entitlements for your user?
- Can your user access the products they are entitled to?
