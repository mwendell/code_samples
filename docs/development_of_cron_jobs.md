# Development of Cron Jobs
Michael Wendell

This page will show you the methodology we use in Haven to build cron scripts that, while secure, can also be selectively triggered in the browser for testing.

## For Developers: Coding for security while allowing an exception for support

The script is primarily secured by querying the php_sapi_name() function, which informs us which interface is being used to call the script. If the script is being called via the command-line (crons are command-line operations), then php_sapi_name() will return 'cli'. If the function returns anything else, we will, in most circumstances, not allow the function to continue.

We do need to create an exception for running some of our cron scripts from the browser when testing. We can do this by also accepting the value of a variable which is tied to a constant defined in Haven Settings; TEST_CRONS. If the constant is true, we can ignore the value of php_sapi_name(), and allow the script to continue. 

Here is the code which accomplishes this...



```php
// LOAD WORDPRESS
$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

// SET OUR $ts VARIABLE
$ts = (defined('TEST_CRONS')) ? TEST_CRONS : false;

// DECIDE IF WE CAN RUN OUR CRON
if ( (php_sapi_name() == 'cli')||($ts) ) {

	// DO WHAT WE HAVE TO DO

}
die();
```

In the code above, we first load Wordpress, which allows us to use the constants defined in Haven Settings.

Next, we use a ternary operator to set the value for $ts. If the TEST_CRONS constant is defined, we set $ts to the value of that constant. If the constant is not defined, $ts defaults to false.

Finally, the conditional that decides if the meat of our cron script will be run. In this case, we will run the script if either of two conditions evaluate to true. Either php_sap_name() must return 'cli', indicating that the script is being run via command-line, or $ts must be true, indicating that we have set the TEST_CRONS value to true in Haven Settings.

## For Support: Using Haven Settings to enable manual triggering of cron scripts

It should be noted that the crons using the code above will work just fine without Haven Settings installed, or if an older, incompatible version of Mequoda Settings is installed on that server. Nothing will be broken if TEST_CRONS does not exist (it simply defaults to false). However the system will not allow cron scripts to be triggered from the browser. That being said, there is no reason to hold back from updating Haven Settings to the latest version on every site, as the upgrade is completely benign. The first version of Haven Settings to support the TEST_CRONS constant is 5.1.8, and is in the plugin repository.

To allow your script to be triggered in a browser, visit Haven Settings and scroll down to the TEST_CRONS row (the variables are listed alphabetically). Click the button labeled TRUE and then click SAVE CHANGES.

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/development_of_cron_jobs_a.gif "Screenshot")

The system is now configure to allow all crons (well, all crons that are using this system) to be run manually from within a browser. If you are curious about what the URL you need to enter into the browser for the cron in question, you can visit the list of Manual Cronjob Links. This list will give you the proper link to enter in the browser for any Haven/Mequoda cron.

When are are finished testing, be sure to return to Haven Settings and click TEST_CRONS back to false.

## There are always exceptions

Some crons should never allow themselves to be triggered manually. In these cases, the TEST_CRONS and $ts parts of the code above should be excluded from the script at the top of your php file. One exception, for example, is the Haven Autotagger. The Autotagger runs a complex series of processes over multiple passes of the cron script, and running a single, manually triggered process would not have any valid testing purpose. Additionally, because the cron itself runs every minute, running it manually, out of sequence, is likely to corrupt any existing processes that might be running.

In this case, the code at the head of the script is simpler, the relevant part looks like this... 


```php
// DECIDE IF WE CAN RUN OUR CRON
if (php_sapi_name() == 'cli') {

	// DO WHAT WE HAVE TO DO

}
```

As you can see, there is no need for a reference to TEST_CRONS or $ts, and because we don't need the constants, Wordpress is only loaded if the php_sapi_name() conditional evaluates to true.