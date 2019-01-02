# Harbor PRD Framework

- [Site Options and API Keys](#site-options-and-api-keys)
- [Debug Settings](#debug-settings)
- [PRD Product Codes](#prd-product-codes)
- [PRD Keycodes (Deprecated)](#prd-keycodes-deprecated)

The Harbor PRD Framework plugin is Harbor's interface for communication with Strategic Fulfillment Group (PRD).

## Configuring the PRD Framework

Initial setup of the PRD Framework can seem complex, but continues to get simpler as we work with PRD to integrate our two systems more closely. PRD continues to make changes to the data it provides with each purchase, and the newest POST method process is far better than the AJAX system we created initially for XYZ.

### Site Options and API Keys

Most of the information needed to configure the Site Options and various API keys will be provided by PRD and most of it will never change once the site is up and running.

The PRD Org ID is unique to the client, and this one actually may change if PRD creates a development server prior to a production server (this is different from test mode). For example on the BCR project, PRD initially set up a server called BC2, and then at production rolled out BCR. This code needed to be changed in the PRD Framework settings, as well as in every single FlexPage link on the site.

PRD App Version and Base API URL are the same for all projects and have been consistent since we started working with them.

The Terminal Payments checkbox controls whether the PRD Terminal menu item in Harbor Admin will include Terminal Payments and Terminal Refunds tabs. This feature is in limited use among Harbor clients, but allows the Customer Service Representatives to manually charge a credit card for phone orders. It is important to note that the Terminal Refunds section of this feature will only refund charges placed through the Terminal Payments tab; it cannot be used to refund orders placed through any other API.

Currently there are four possible sets of API keys that may be entered, one for each of the PRD APIs that we work with. We typically gather and enter all four of the sets, but we don't necessarily use all of them. They are:

- **Special Programs API:** Although many FlexPage purchases use what PRD calls "Special Programs", we don't actually need the Special Programs API key in most cases. This API would allow us to directly place Special Programs subscription orders, which we only do on the CSN website (Terminal Payments also uses this API).
- **Manage Special Programs API:** Similarly, the Manage Special Programs API is used to refund or modify Special Programs purchases, which again, we only do on CSN (Terminal Refunds also uses this API).
- **Gatekeeper API:**  The Gatekeeper API is ubiquitous and is used everywhere, and is often the only API key we actually need.
- **Customer Update API:**  Finally the Customer Update API is not currently used anywhere, but we are in the process of developing an interface to do so.

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/harbor-prd-framework-a.png "Screenshot")


### Debug Settings

This is where we turn on and off debugging, as well as put the site into test or production mode.

PRD's Test Mode is explained further below, but in short, placing the site into test mode will allow test orders to be placed using a fake credit card number. However, since all orders are placed on PRD's site, the important change that occurs here is that, when in test mode the Gatekeeper will pull data from PRD's test mode database. Therefore, FlexPage orders placed in test mode will appear properly in the data that is retrieved by the Gatekeeper. This primarily means that user entitlements will be set correctly.

You can also turn on or off debugging emails, and choose who they go to. This can be exteremely helpful, as the entire suite of PRD API calls have been throughly instrumented. Of course, this also means that you will be inundated with emails if you turn this on. Turning it on for production and then forgetting about it for a weekend will absolutely drown your email account, so be careful. Most processes will send an email with the intial data they recieve, with the data formatted to send to PRD, and finally with the response from PRD.

For example, when a user visits their account page, the Harbor Entitlements Manager will force-update the entitlements. This involves a call to the get_entitlements() function in the PRD framework. This function first calls the gatekeeper() function, which calls the Gatekeeper API at PRD for the user data. The gatekeeper() function sends three debug emails; the intial value that was submitted to it, the formatted PRD request, and the entire PRD response. Control then passes back to the get_entitlements() function which thankfully only sends two emails, the intial data recieved from gatekeeper(), and the formatted entitlements array as returned by get_entitlements(). 

This setting also controls whether debug emails are sent by various other PRD interfaces and scripts such as the offsite order processing script or the events order processing script.

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/harbor-prd-framework-b.png "Screenshot")

### PRD Product Codes

This section controls how we link PRD's product codes, along with their secondary codes, to our Publications. In many cases this will be a one-to-one relationship; PRD's single product code for Ceramic Recipes links directly to our Ceramic Recipes publication. However more often than not, PRD will have multiple ways of referring to a single product, and all of them must be accounted for here.

Each row consists of three PRD values, and a column indicating which Harbor Publication corresponds with those values.

Before we discuss the Product Codes (and Secondary Product Codes) we should briefly introduce PRD Programs. PRD has various program types, each have completely different ways of dealing with products in their internal system, and each having different attributes and data structures. The four programs are Subscriptions (SU), Special Programs (SP), Catalog (CA), and Continuity (CN). We have dealt with all four programs, and the differences between them, in the course of our relationship with PRD.

- Subscriptions, sometimes referred to as 'Traditional Subscription Products' are primarily print, but may also be print-centric combo products (and thus may have a digital component), and often have issue-based durations and expiration values as opposed to true expiration dates. 
- Special Programs are the newest member of the PRD family, and is where you will find the newest timed-access products such as web and tablet subscriptions. That being said, we cannot rule out seeing combo offers that include print listed as Special Programs, as well as club memberships.
- Catalog products are where we send our Shopp and Event purchases, but can also be used for digital purchases which may need entitlements, such as the books sold on the Indian Country website.
- Continuity products are similar to Catalog purchases in that they are typically one-shot publications, but in this case they can also be publications that renew yearly, such as the report products on the University Health News website.

It would be great if there were hard and fast rules that could be used to identify which products fall into which programs, but alas, there are not.

So, the takeaway here is that you may find that a single product, a magazine for example, straddles two or three programs, and will need to be entered in this table once for each program it exists in. For example, IC magazine has two distinct product codes; IC, which is part of the Subscriptions (SU) program, and ICMTA, which sits in the Special Programs (SP) bucket. In this table it would be entered on two different lines. Even in the case of CM, there would be entries for both SU and SP, although in both of these cases the Product code is the same: CM.

The secondary product code is unique to products in the Special Programs (SP) program. It will typically just echo the channel (access level) of the product; listing things such as COMBO, or WEB. In this case, you may leave this column empty. It is only needed if the secondary product code changes which publication the product code is attached to. An example of this would be the ICAN club memberships. PRD uses a single product code (IC) to refer to all three levels of club membership, and then provides a secondary product code (BASIC, SILVER, GOLD) to distinguish between them.

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/harbor-prd-framework-c.png "Screenshot")


### PRD Keycodes (Deprecated)

For older sites which still use keycodes in Harbor to differentiate between product offers, this table allows keycodes to be associated with Harbor publications. However, because a keycode is akin to a unique offer, you must also select the frequency and term of the subscription, as well as the channels associated with that offer.

For these sites, it is absolutely critical that every keycode used by PRD be entered in this table (there can be hundreds of them in some cases).

This system can introduce errors into the entitlement process (mostly due to new keycodes being created and not entered ito Harbor) and is being deprecated in favor of the more concise product code system above.  

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/harbor-prd-framework-d.png "Screenshot")

