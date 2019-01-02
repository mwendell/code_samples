# Implementing a PRD FlexPage Order Flow

1. [Request API credentials from PRD](#request-api-credentials-from-prd)
2. [Confirm the PRD app version and Organization ID for your project](#confirm-the-prd-app-version-and-organization-id-for-your-project)
3. [Install the Harbor PRD Framework plugin](#install-the-harbor-prd-framework-plugin)
4. [Install the Harbor Entitlement manager plugin](#install-the-harbor-entitlement-manager-plugin)
5. [Configure the Harbor Entitlement Manager Plugin](#configure-the-harbor-entitlement-manager-plugin)
6. [Configure the Harbor PRD Framework Plugin](#configure-the-harbor-prd-framework-plugin)
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
- Request Customer Update API credentials if you need customer data modified in Harbor to be forwarded to PRD. 

### Confirm the PRD app version and Organization ID for your project

- The app version for all of our sites so far has been 2.20
- The Organization ID will be a three letter code provided by PRD

### Install the Harbor PRD Framework plugin

- The plugin currently requires PHP 7+

### Install the Harbor Entitlement manager plugin

- There is currently no automated database creation, unless you run the conversion script found in the plugin folder. You can run the conversion script without doing any conversion however, and the database tables will be created. If you'd like to create the tables manually, use the SQL statements below.

```sql
CREATE TABLE IF NOT EXISTS wp_harbor_entitlements (
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

CREATE TABLE IF NOT EXISTS wp_harbor_entitlements_refreshed (
	user_id int(11) NOT NULL,
	refreshed int(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (user_id),
	UNIQUE KEY user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
```

### Configure the Harbor Entitlement Manager Plugin

- Check the PRD Gatekeeper box in the External Entitlement Database section
- Select a Renewal Frequency from the drop-down list. 14 days is a good starting point, but the final value will be up to the client.
- Hold off on adding Composite Products, we'll discuss that below

### Configure the Harbor PRD Framework Plugin

- Add your App Version and Org ID values.
- Add your API keys in the appropriate boxes
- More info about setting specifics can be found in the Harbor PRD Framework documentation under Plugins
- Hold off on adding Product Codes, we'll discuss that below

### Add required theme template files to root of theme folder

From the within the plugin folder, copy the following files from the /theme-files directory to the root of the site's theme folder.

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

- Decide on your Harbor Pub ID values for each publication. These are created in the Harbor Publication Manager (Harbor Pubs) plugin.
- If possible, try to create Harbor Pub IDs that match the Product Codes being used by PRD; these are generally two letter codes.
- Try not to create overly long Pub IDs. Two characters is ideal, and there's really no reason to ever exceed 3 characters. Ever.
- PRD should be able to provide you with a list of their Product Codes, sorted by Program.
- Internally, PRD divides their products into four Programs: Subscriptions (SU), Catalog (CA), Continuity (CN), and Special Programs (SP). 
- Special Programs products will also include Secondary Product Codes.
- All Products will include Access Levels. Historically these values are different for each PRD partner or client. We want to change that, and keep all future access level codes consistent. At the beginning of every project, insist that PRD use the following access level designations COMBO (for all-access combo purchases), DIGITAL (for international or digital-only combo purchases), PRINT, TABLET, WEB. The default for all products that have only a single online access level should be WEB. This applies to clubs and other types of memberships as well. If it is necessary to shorten these names, we would prefer to reduce them to a single character like this: C, D, P, T, W.
- Once you have your products created and your Pub IDs defined, set them up in Harbor Pubs

### Does your product list contain any club or membership products?

- These are considered "Composite Products" and are simply a consolidation of various entitlements under a single purchase.
- Composite Products are managed in the Harbor Entitlement Manager
- You will have had to set up your publications first (see earlier step)
- Add a Product ID (akin to Pub ID) and Product Name
- Once created, you can select the specific entitlements granted to purchasers of your new composite product.

### You can now set up your Product Codes in the PRD Framework plugin

- Specifics can be found in the PRD Framework plugin documentation, but essentially you will add the PRD Product code (and possible the Secondary Code) for every single PRD product that can possibly be purchased, and attach each of those products to a Harbor Publication or Composite Product.
- Once attached, the Gatekeeper will be able to attach specific PRD Product Codes with Harbor Publications

### Build your FlexPages

- Under the Confirmation/Error tab, be sure that the _Custom Redirect URLs_ fields are completed using the site URL and the slugs you used for your Offsite Order Processing Page.
- Send Test Mode purchases to the dev server, production purchases to the production server.
- Beware of complexities in the Test Mode process, discussed in more detail in the PRD FlexPages development document.

### Add your Confirmation Email posts

- Create individual posts in the _Confirmation Emails_ section of Harbor (confirm_email custom post type)
- Create one post for each combination of Pub ID and Access Level that may be purchased.
- The slugs chould follow this format: "purchase-confirmation-[ pub_id ]-[ access level ]", so the slug for a combo purchase of the CM publication would be purchase-confirmation-cm-combo.
- Accepted access level values are combo, digital, web, tablet, print. 
- Special cases can be configured in the offsite order processing page if necessary.
- If a specific offer, tied to a unique keycode, requires a special confirmation email, putting the keycode in the slug will give it priority over the rules defined above. The slug in this case would read purchase-confirmation-[ keycode ], ie: purchase-confirmation-CMAARE

### Add your Confirmation Page posts

- Create individual posts in the _User Content_ section of Harbor (uc custom post type) 
- Create one post for each combination of Pub ID and Access Level that may be purchased.
- The slugs chould follow this format: "order-confirmation-[ pub_id ]-[ access level ]", so the slug for a combo purchase of the CM publication would be order-confirmation-cm-combo.
- Accepted access level values are combo, digital, web, tablet, print. 
- Special cases can be configured in the offsite order confirmation page if necessary.
- If a specific offer, tied to a unique keycode, requires a special confirmation page, putting the keycode in the slug will give it priority over the rules defined above. The slug in this case would read order-confirmation-[ keycode ], ie: order-confirmation-CMAARE

### Debug the order process

- Place FlexPage orders on PRD in Test Mode
- Did your entitlements get recorded correctly?
- Did your confirmation email get sent correctly?
- If you created a new user, did you get the welcome email?
- Was your confirmation page displayed correctly?
- Call up the user's history in the PRD Terminal, has the purchase been recorded properly at PRD?

### Debug User Entitlements

- Review the entitlements in the wp_harbor_entitlements database.
- Does the account page display the proper entitlements for your user?
- Does the My Library page display the proper entitlements for your user?
- Can your user access the products they are entitled to?
