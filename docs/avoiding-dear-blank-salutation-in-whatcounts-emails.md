# Avoiding "Dear /<blank/>" salutation in Whatcounts emails

While most Harbor sites require a first and last name when registering, there are still many cases of subscribers getting into our database without that information, such as imported or initial user data. In these cases, we can avoid sending emails to Dear , but making a simple change to the Template file stored on the Whatcounts server.

Right now we use the following salutation in most of our emails:

```
Dear %%first%%,
```

However, Whatcounts does allow some very basic IF/THEN functionality in template files, and this could easily be used to insert a default value if a subscriber has no first name on file.

```
%%if first = "" then print "Colleague"%%
```

Reviewing the Whatcounts help files, which are not as helpful as they could be, I finally found mention in another article of an ELSE operator, so in the end, your entire salutation line would look like this:

```
Dear %%if first = "" then print "Colleague" else printdata first%%,
```

Additional Reading:

https://support.whatcounts.com/hc/en-us/articles/203967379-Basic-IF-THEN-Logic
https://support.whatcounts.com/hc/en-us/articles/203967599-Salutation-Basic
https://support.whatcounts.com/hc/en-us/articles/204667875-Salutation-First-or-Alternate-Text