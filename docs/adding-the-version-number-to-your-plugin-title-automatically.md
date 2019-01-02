# Adding the Version Number to your Plugin Title Automatically

It's helpful when debugging plugins for everyone to be aware of what version of your plugin someone is looking at, but in many cases that is only available by visiting the Plugins page in the Wordpress Admin and checking which version of the plugin is installed. Life would be easier if the plugin gave you the version number on the actual plugin page, wouldn't it? All of the Harbor plugins I've built, and most of the ones I work on now display the version number automatically.

First, let's take a quick look at the plugin's title definition block, as reference...

```php
/**
* Plugin Name: Haven Stock Manager
* Plugin URI: http://www.mequoda.com/
* Description: Plugin in development
* Version: 0.07
* Author: Mequoda
* Author URI: http://www.mequoda.com
*/
```

Assuming our plugin is not headless, then somewhere within the plugin we will have a page title. For many plugins this will be a hard-coded title like this..

```php
echo <h2>Haven Stock Manager</h2>;
```

You could simply add the plugin version by typing it in, but that's a bad idea as we will continually forget to update it. If that was the only option it would probably better not to include it at all. Similarly, when plugin names change, which they have been wont to do lately, we must remember to change the name in this block as well. This code helps with that too.

The first part of this will be to grab the plugin data from Wordpress, which is very simple. This code can be placed anywhere that assures that the value of $plugin_data is accessible by the time we need to display our plugin title. The simplest answer would be to drop it on the line just before your title. 

```php
$plugin_data = get_plugin_data(__FILE__, 0, 0);
```

All that's left is to display our title and version number.

```php
echo '<h2>' . $plugin_data['Title'] . ' - Version ' . $plugin_data['Version'] .'</h2>';
```

This will automatically display the plugin's title and version number, as used in the plugin's title definition block, allowing us to maintain naming consistency, and to confirm plugin versions easily right from within the plugin's settings or option page.