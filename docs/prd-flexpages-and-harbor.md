# PRD FlexPages and Harbor

The FlexPage system is a completely off-site purchase and fulfillment system built and hosted by PRD in Ohio. It seems to have been primarily designed for small print-only publishers whose simple websites did not have any purchase or user-account capabilities. Potential subscribers would click a link to an PRD FlexPage for a product, and would then complete the entire purchase within the context of the FlexPage. Once the purchase is complete, PRD would fulfill the subscription, and the publisher would manage subscriptions within the PRD management environment. The design of FlexPage forms, both functionally and aesthetically, is handled entirely within a web interface provided and hosted by PRD.

To tie this system in with Harbor, we have built a number of tools and scripts to extend the system. While our interface works, there are limitations and our goal for the future is to work with PRD to produce a system that interfaces more closely with external providers such as Harbor.

### Overview of the PRD FlexPage purchase process

A visitor to a Harbor website clicks a link to a subscription offer which will take the potential subscriber to a unique FlexPage on the PRD website.

If the potential subscriber is a known (and logged-in) Harbor user, a script placed in the FlexPage form by Harbor, along with querystring values in the URL will pre-fill the user’s name and email address on the form. If the known user has an PRD Customer ID we can include that number along with the customer's postal code to have PRD identify the subscriber and pre-populate the order form. 

 - **https://ssl.prdinternal.com/ecom/XYZ/app/live/subscriptions?org=XYZ&publ=AB&key_code=ABG30&type=S&uid=309437&cust=45000882408&post=04051&fn=harley&ln=quinn&em=harley%40harbor.com&testmode=Y**

The FlexPage purchase process itself is compact, and involves just a single data collection form. Submitting the form presents the user with an “are you sure?” page recapping the order details. Confirming the order will bring the user to the order confirmation screen.

On older sites that still use the deprecated AJAX FlexPage process each FlexPage confirmation screen will include an embedded AJAX jQuery script written by Harbor. If the order is successful, the script submits all of the purchase data to a PHP page on the Harbor website which processes the order. If successful, the page redirects back to the jQuery script on the PRD confirmation screen, indicating that the order was successfully processed in Harbor. The script then immediately redirects to the actual confirmation screen on the Harbor website. And the order process is complete.

Based on ideas developed in partnership with Harbor, PRD has replaced the slow and awkward AJAX process with simpler "POST Method" integration. In this method, data for successful orders is submitted via PHP $_POST to a processing page on the Harbor website. This action completely bypasses the PRD confirmation screen, which is now only ever displayed when a system error occurs at PRD, or if the external order processing page on Harbor fails catastrophically. In regular operation a customer should never see the confirmation screen during a FlexPage purchase.

In both methods, a page exists on the Harbor website which goes through the following process after each order. In one case, the process is conducted with data sent via AJAX, and in the other the data is received via $_POST, but it completes the same tasks...

- Create the user (if necessary).
- Send welcome email (if necessary).
- Record the order.
- Insert the appropriate entitlements.
- Record the transaction data.
- Send order confirmation email.

For the AJAX orders, the final step was to respond back to the originating script that the process was successful, and include an identifier for the order so that the Harbor confirmation page could display the proper confirmation screen.

For $_POST orders, the final step is to redirect to the confirmation screen, again including some identifying information which allows the confirmation screen to properly retrieve and display the purchase info.

### Developer Details

FlexPages are created on the PRD website and are accessed on the front end using a URL provided by the FlexPage Designer web interface. All FlexPages, whether built for the older AJAX system, or the newer POST method, will include a script that pre-fills the name and email information in the subscription form.

FlexPages created for Harbor's AJAX interface will also include a complex jQuery script that sends order information to the processing script on the Harbor website.

FlexPages created for the newer POST method simply need to be provided with the URL of the processing page on the Harbor website (typically "https://your-domain/order-process/"). PRD allows for two different order processing URLs to be included, one for production mode, and one for test mode. Typically we will set the production URL to our production site, and the test mode URL to our dev site, but this is not always the case (depending on what you're testing you may wish to place a test order that redirects to production for example). Naturally though, this can cause confusion.

### Understanding PRD Test Mode

It is important to understand what PRD means by test mode, as well as how and when to activate test mode. First, PRD's test mode is not permanently linked to Harbor's development servers, as it can be used with any of our servers during testing. It is best to think of it as simply an alternate, test-only copy of PRD's user and order database. While this can be extremely helpful when testing, even on production, it can introduce errors if you don't understand what's happening. If an order is placed on a FlexPage in test mode, that order only exists in the test mode database. If you attempt to look up order history for that user from the production site, that order will not exist, the user may not even exist. It is important to know if you placed the order in test mode or production mode, and if the site you are testing on is in test mode or production mode.

For example, as mentioned in the prior paragraph, you can place a test mode order and have the FlexPage redirect to the production site. This will result in the new user being created on the production site, emails sent, order processed, etc., so all of that can be tested successfully, and in fact, barring the use of a real credit card (test mode allows fake card purchases), this would be the only way to easily test these things. However the new user will not have the correct entitlements. This is because during the order process, entitlements are generated by a Gatekeeper call back to PRD. Our production site, being linked to PRD's production database, will call Gatekeeper on that database. Since the order record is stored on the test mode database, Gatekeeper will return invalid entitlements. This doesn't mean the system is failing, it just means that understanding these limitations will allow you the flexibility to test without having to take the whole system down and switching everything over to test mode.

Finally, it is important to note that when testing FlexPages, simply adding "&testmode=Y" to the end of any FlexPage URL and reloading the page will put the FlexPage into test mode. As above, this is very helpful, but be sure to check the URL once you'd been redirected back to Harbor. You may find you're on the dev server even though you jumped to the FlexPage from the production server. As mentioned, the destination is completely controlled by the POST URLs set in the FlexPage Designer. 

### FlexPage URLs

All of the FlexPage links go to the same server and page. A unique Keycode (key_code=) in the querystring identifies the specific FlexPage which will be displayed by PRD. Keycodes are similar to offers in the Harbor world. A publication may have many Keycodes depending on the specifics of the offer to be presented on that FlexPage; monthly, yearly, free trial, etc. Additionally, while each FlexPage will have a master Keycode that we use in the purchase URL to access that FlexPage, the final purchase may actually use a different Keycode depending on the specifics of the offer chosen (combo vs. print-only, for example).

When in development, the FlexPage URL will most likely include testmode=Y, which controls whether PRD uses their testmode server. When using testmode, the FlexPage will be branded TESTMODE in big red letters. Additionally, PRD will often need to be reminded to update FlexPages on their testmode server. Changes made in the FlexPage designer may not always be automatically copied to the testmode server.

The Flexpage URL is static during the entire purchase process, giving us the ability to add querystring values and retrieve them at the end of the order. One of the values we pass through, for example, is the current hasc value (hasc=), as well as the Harbor user_id.

If the FlexPage is using the deprecated AJAX method, you can include the development-only query value dev=1. If present, this controls whether the AJAX call and the redirect found on the confirmation screen will go to our development server.

### Harbor jQuery Scripts in FlexPages

There are currently two Harbor jQuery scripts embedded within FlexPages; one on the initial landing page (the purchase form), and is still valid. The second script is used on the final confirmation screen that is only loaded if a purchase is successful. This AJAX script has been deprecated although it is still in use on UHN and ICMN as of this writing.

In the PRD FlexPage Designer, the scripts are currently placed into fields found under the Analytics tab. The shorter landing page script is in the field labeled “Crazy Egg Code” and the longer order processing script is in the field labeled “Generic Conversion Success – Document Head”. These scripts must be placed in the FlexPages for all possible keycodes. Even though we may only link to, for example, the combo FlexPage, the scripts must be inserted into the print and web versions as well.

```
<script>jQuery(document).ready(function(){for(var e=!1,n=window.location.href,o=n.slice(n.indexOf("?")+1).split("&"),a=0;a<o.length;a++)hash=o[a].split("="),"em"==hash[0]&&(e=decodeURIComponent(hash[1]));e&&jQuery("#email").val(e)});</script>
```

The shorter landing page script is relatively simple and only serves to grab the user email from the querystring if it exists, and inject it into the email field on the subscription form.

The major reason we use this is to lower the risk of a user subscribing to a publication using a different email address than the one stored in Harbor. Unfortunately, this does not prevent the issue completely. We have already had problems on XYZ with subscribers using multiple email addresses, preventing their accounts on PRD and in Harbor to be linked properly.

```
<script>jQuery(document).ready(function(){for(var e={custno:"@@CUSTNO",email:"@@EMAIL",firstname:"@@FIRSTNAME",lastname:"@@LASTNAME",address1:"@@ADDRESS1",address2:"@@ADDRESS2",city:"@@CITY",state:"@@STATE",zip:"@@ZIP",country:"@@COUNTRY",copyright:"@@COPYRIGHT",currentyear:"@@CURRENTYEAR",keycode:"@@KEYCODE",suborder:"@@SUBORDER",subtype:"@@SUBTYPE",transid:"@@TRANSID",subtotal:"@@SUBTOTAL",shipping:"@@SHIPPING",tax:"@@TAX",ordertotal:"@@ORDERTOTAL",amtdue:"@@AMTDUE",hasc:"",uid:"0"},r="",t=window.location.href,a=t.slice(t.indexOf("?")+1).split("&"),s=0;s<a.length;s++)switch(hash=a[s].split("="),hash[0]){case"dev":r="dev.";break;case"mqsc":e.mqsc=hash[1];break;case"uid":e.uid=hash[1]}var n="https://"+r+"sampleurl.com/wp-content/plugins/harbor-prd/ajax-xyz.php";jQuery.ajax({url:n,type:"POST",crossDomain:!0,data:{order:e},dataType:"json",success:function(e){var t="?order="+e.user+"-"+e.order;location.href="http://"+r+"sampleurl.com/subscription-confirmation/"+t},error:function(e,r,t){alert("There was an error updating/creating the user/order data on the XYZ website.")}})});</script>
```

The confirmation page script (deprecated, but still in use on some sites) is where Harbor has done the most work to tie the two systems together. This script is comprised primarily of a background AJAX call to a processing page in Harbor which records all of the necessary user and order information.

Before the script is inserted into the confirmation page, the @@ codes within the script are replaced with actual values by PRD’s FlexPage system. This is how the AJAX script can submit the order values ot Harbor. Additionally, the script grabs the information the Harbor inserted into the URL, such as the hasc and uid values, and includes them in the AJAX submit.

When the PHP script is done processing the AJAX call, it returns a success message back to the script above. This reply includes a unique time key which identifies the user and order information. This is critical, as it allows us to definitely associate the returning user with the purchase they just placed. When a form is submitted via AJAX from an off-site source, the processing is done completely outside of the current user’s session, it’s essentially anonymous. The time key allows us to retrieve the user’s order information.

Once the script sees that the AJAX call was successful, the user is immediately redirected back to Harbor. The time key is used to retrieve the order information, which is displayed on the order confirmation page. In the event that the time key is missing or corrupted, a generic order confirmation is displayed.

### Harbor’s AJAX Processing PHP Script (Deprecated)

The script that processes the AJAX call on the Harbor website encapsulates the entire typical Harbor order flow and user creation process in one single page: /plugins/harbor-prd/ajax-flexpage.php.

The process goes something like this:
- Load Wordpress
- Load all valid Keycodes ($keycodes)
- Load AJAX POST data ($order)
- Fail if no email address in order
- Create user if email address is not in Harbor
- Fail if user could not be created.
- Generate confirm and opt-out tokens using Harbor Registration plugin
- Send welcome email using call to Harbor Registration plugin
- Update user information with $order data
- Build and send confirmation email
- Email contents are store as Harbor confirmation emails, and the slugs follow a specific naming pattern; “purchase-confirmation-< publication-parent-slug >-< channel-code(P/W/T/C) >.”
- Determine the purchase expiration date
- Insert the entitlement into Harbor.
- Insert the expiration date into Whatcounts using the Harbor Whatcounts Framework plugin
- Record the publication subscribe Transaction using the “harbor-transaction” action.
- Return the user id and time key to the AJAX script at PRD.