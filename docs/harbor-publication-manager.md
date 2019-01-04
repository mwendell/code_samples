# Harbor Publication Manager

- [Overview](#overview)
- [Glossary](#glossary)
- [Using Harbor Pubs](#using-harbor-pubs)
- [Integrating with Front End Development](#integrating-with-front-end-development)
- [Developer Notes](#developer-notes)
- [Database Structure](#database-structure)

### Overview
The Harbor Publication Manager (aka: Harbor Pubs) was primarily created to formalize a method for tying together multiple individual posts (articles) into a single issue, and associating all of these issues with a specific publication. It can handle multiple publications, place publications into specific groups (newsletters, reports, hotlines, etc.), and allows publications to exist independent of topics (categories). It also manages the creation of table-of-contents posts, previous/next article ordering within an issue, issue cover images and description, featured and highlighted articles, and the upload and management of issue PDFs.

#### Advantages of Harbor Pubs over previous methods:

- Allows all premium content to share topic categories. Previous method required the creation of duplicate topic categories, each within a parent category defining the publication. Benefits are better topic organization, ease of category selection, reduction of errors, and the ability to create topic pages with premium content from multiple publications.
- Point-and-click creation of Table of Contents pages. Previous method involved laboriously adding all table of contents information, titles, and links within the post body itself. Benefits are ease of use, error reduction, and consistency.
- Improved previous page/next page linking within premium content. Previously paging was determined by the article’s post date, meaning any change to an article that changed the date would upset the order of articles within a publication. The new system references the order of the articles array to determine the previous and next pages.
- Standardization. Previous projects have handled issue creation in a number of ways, implementing the plugin will standardize the process moving forward.

### Glossary

**Publication** – refers to a specific magazine or subscription product, online or offline, offered on a client website, such as Backyard Poultry, Water Efficiency, or Wall Street Stock Forecaster. Typically the plugin is used to manage the client’s subscription-only premium products. A publication will use Issues (groups of articles accessed via a table of contents), while some publications may simply contain individual Articles without Issues. However, a single publication cannot use both formats.

**Issue** – similar to a magazine issue, this refers to a specific monthly deliverable containing a set of articles (table of contents), as well as the metadata that helps identify the issue; name, slug, volume and issue number, cover image, etc. An issue may also include a description and a downloadable PDF.

**Article** – refers to a content post that is tied to a publication. When an article is created it should be added to a publication. This is done using the Publication select box on the post editing page, or from the quick-edit accordion on the All Posts page.

**Pub ID** – refers to the arbitrary code assigned to a publication, and should be short, simple, and unique. While it can be edited in Harbor Pubs, it should be frozen well before launch, as it may occasionally be used within the naming standard of images, PDFs, etc. Ideally all Pub IDs will follow a similar format and have the same number of characters, although this is not a requirement. Pub IDs cannot be purely numeric, and should not programmatically resolve to a number (ideally they will not contain digits at all)

### Using Harbor Pubs

Upon installation, Harbor will configure the client publications using the Edit Publications tab of the plugin.

Using the form provided, you will want to give your new publication a name, slug, and pub id, and select the active checkbox. If your new publication is going to include Issues (most do) check the Use TOC Posts box as well. You can also give your publication a short description. When creating a publication, if you do not select a parent, the new publication will become a parent, and be available in the parent dropdown when you create future publications.

When you are satisfied, click the submit button to create your new publication.

Once the publications have been created, they will be available to the client as they enter new premium content articles. Clients should select a single publication for each premium article using the publication select box on the post editing page.

When choosing topic categories for new premium content posts, the client must be sure to choose the topics listed under the topics category group, and not the daily category group.

Now that we have a bunch of articles in Harbor, let’s create an Issue. In Harbor Pubs, select the Edit Issues tab, then click the Add New Issue button to the left of your chosen publication.

Start by giving your issue a title in the field at the top of the page.

Below this is the Articles In This Issue section, which displays the table of contents (TOC). Click the buttons to the left of the article titles in the Choose Articles section to add articles to your Issue. This list consists of all articles that are assigned to your chosen publication, but which have not been listed in a previous TOC (a single article may not be used in multiple Issues, or in multiple publications). Once Articles have been added to the TOC they can be repositioned within the TOC or deleted from the TOC.

You can also add headers within the TOC using the Add Subheads field below the article list.

To the right of each article in the TOC you will see check boxes or radio buttons (depending upon configuration) indicating that an article should be Featured, Highlighted, or for some clients, Sponsored. Selecting these boxes will have different effects depending on how the TOC is displayed in the them template, but most often the Featured article will be displayed with an excerpt and photo on the TOC page, while the Highlighted articles will be displayed as links. The remainder of the articles will show up in the TOC sidebar. This is not a set rule however, and is determined by the theme designer.

Note that the TOC editing tools can be configured to display or hide Post IDs. While having the IDs visible is quite helpful during initial setup and debugging, the information may not be helpful for clients once in production. This can be adjusted under the plugin’s Manage Options tab.

The final component on the left side of the page is the Issue Description. How or if this is used will be determined by the client and the theme designer.

At the top of the right hand column you will find the Publish metabox, which allows you to save your Issue, as well as setting it to draft or publish status.

Below this is the Issue Information metabox, which is used to select the publication, set the issue date, the issue volume and the issue number. The use of volume and number is optional, and the fields can be disabled in the settings under the Manage Options tab. Note that the Issue Date, not the post’s internal date is the primary sorting key for displaying Issues.

The Issue PDF metabox allows the issues’ downloadable PDF versions to be uploaded, managed, and deleted.

Some publications may use the fields in the Masthead metabox, others may not, as determined by the theme designer and the client. If so, simply enter text into the Title and Body fields as necessary to create a single masthead item. For example Title may be Editor-In-Chief, and the Body might include the editors name, or maybe the Title says Advertising Dept. and the Body includes the phone number. Clicking Save will add items to the masthead, but be sure to Save your Issue when you’re done. Like articles in the TOC, masthead items can be moved up and down within the masthead or deleted using the buttons next to each item.

The last metabox is Featured Image and allows you to assign a standard Wordpress featured image to the Issue, most often this will be the issue cover.

### Integrating with Front End Development

There are a number of features and functions available when doing UI development for a project that uses Harbor Pubs. The most important part though, is to understand the relationship between publications, issues, and articles. The publication is the custom taxonomy that creates the individual subscription products, and Issues are the custom post types that exist within a publication, and tie all of the articles together with a table of contents.

#### Publication Functions

_Publication functions return information about all publications._

**get_pubs()** – This is the function to use when you want all of the information about all of the publications. It returns an array consisting of the pub_id, active, slug, title, term_id, description, parent, and parent_slug. When you need to display all of your publications, calling this will give you an array you can loop through, and enough information about each publication to get anything else you might need.

**get_pub_ids()** – Similar to the function above, but just an array the pub_ids, nothing else.

**get_latest_covers($pub_ids, $urls)** – if given an array of Pub IDs, will returns an array of Wordpress thumbnail ID values corresponding to the featured images for the most recent issue for each of the submitted publications. Thumbnail can then be displayed using

**wp_get_attachment_image()** – If $urls is true (it is optional, false is the default), the function returns the full URLs for the source image files, as opposed to the  IDs.

**get_latest_magazine_issue($pub_id)** – Returns ID, title, and slug for the latest issue of a specific publication.

**get_pub_slug($id)** – Returns the slug for specific publication, accepts the pub_id or term_id

**get_pub_title($pub_id)** – Returns the title for specific publication

**get_pub_parent_slug($pub_id)** – Returns the slug for the parent of a specific publication

#### Issue Functions

_Issue functions return information about a specific issue or table of contents._

**first_article_link($toc_id)** – Returns a permalink for the initial article within an issue

#### Article Functions

_Article functions return information about a specific article._

**get_pub_id($post_id)** – Returns the pub_id of this article’s publication.

**get_pub_parent($post_id)** – Returns the term_id of the parent of this article’s publication.

**get_toc($post_id)** – Returns a UL/LI string with the complete table of contents that an article resides within. Designed for use within the sidebar.

**get_toc_id($post_id)** – Returns the post_id of the issue to which this post has been assigned.

#### Display Functions

**get_prev_next($post_id)** – Returns an unformatted array consisting of only the permalinks for the previous and next articles, relative to the current article, within that issue’s table of contents.

**show_prev_next($post_id, $args)** – Returns formatted HTML for the previous/next article bar. Can be modified by submitting an array of arguments such as class_cont (container class), class_prev (class for previous link), class_next (class for next link), label_prev and label_next (labels for the previous and next links).

There are a few other functions that were created before the plugin was developed and transitioned from an earlier set of helper functions that existed in the functions.php file and used the $prefix term found in older sites, and refer to publications as magazines. All of these will remain in place, simply redirecting to, and taking output from, the current functions, however we should avoid using these functions on newer sites. Primarily these deprecated functions include get_magazine_slug($prefix), get_magazine_array(), get_magazine_prefix($post_id), get_magazine_prefixes($exclude), and get_magazine_title($prefix).

### Developer Notes

First, note that Harbor Publication Manager takes advantage of the wp_termmeta table, and term meta functions, introduced in Wordpress 4.4. For projects using earlier versions of Wordpress (such as UHN) and wp_termmeta table has been manually added to the database and get_term_meta() and update_term_meta() functions have been conditionally added to the project (these functions are not used if Wordpress includes native functions of the same name). The added table and functions work exactly like those in WP 4.4 and core upgrades should work as if they were completely native.

As of this writing (version 0.62), the plugin currently consists of a single file, harbor-publication-manager.php. The file consists of a plugin declaration comment and change log, followed by functions to register the ‘toc’ post type and the ‘publication’ taxonomy. After this, the mgPubs class is created, containing all of the functionality of the plugin, other than the helper functions. The helper functions exist at the end of the file, and outside of the harborPubs class.

Within the harborPubs class sections are delineated for activation and setup, menu and options, select issue/edit pubs, edit issue, and options.

Activation and Setup consists of the standard functions used to define the plugin, options, actions, filters, and instantiate the class.

Menu and Options is probably a misnomer, since doesn’t contain any option information at all. The section only contains the admin_menu() function that creates the dashboard menu, and the load_page() function that determines the page to display based on the menu choice.

Select Issue / Edit Pubs contains the functions that display the default issue list (Edit Issues tab), as well as everything under the Edit Publications tab.

The select_issue() function performs a query to load all of the issues, and then loops through the results to list the publications and all of the issues within each. There is also a few lines of jQuery in there to run the accordion display that hides the issues for all of the publications except for the active one.

Most of the functionality under the Edit Publications tab is handled by the edit_pubs() function. It’s a relatively simple function that loads and displays a list of publications, while also displaying a form that lets the user add and edit the publication info. The function submits to itself and validates and processes its own input. It is possible to delete a publication as well, but the publication must have no issues associated with it.

There are three small additional functions in this section. Hierarchy sort handles the parent-child sorting of the publication list displayed on the Edit Publications page. The delete_issue() and notice_delete_success() functions handle deleting issues. Display of the delete button is found within the select_issue() function.

The Edit Issue section consists of only two functions, edit_issue() which builds and displays the add/edit issue form,  and save_issue() which process the output from edit_issue(). edit_issue() takes a $post_id as input, the id of the issue (toc) post. If the input is missing or 0 the function will create a new issue.

The form will require more documentation eventually, but when working with the plugin be aware that jQuery scripts are storing data in hidden fields for the article list, featured, highlighted and sponsored articles, and the masthead. Be wary of changes that might affect the ids and classes of specific fields. Much of the validation is also performed by jQuery scripts.

The save_issue() function is very straightforward and simply sanitizes all of the input from edit_issue() and saves the information to the wp_post_meta table. It also creates a new post if necessary.

Finally the Options section of the class contains a single function, manage_options(). I’ve tried to keep this as Wordpressy as possible, using the update_option() command to handle storing the options (as opposed to $wpdb).

### Database Structure

#### Publication Data

wp_terms table
- term_id
- slug
- name

wp_term_taxonomy table
- parent
- description

wp_term_meta table
- pub_id
- sub_url (currently unused)
- active (0/1)
- has_toc (0/1)

#### Issue Data

wp_postmeta table
- toc_issue_date (unix date)
- toc_issue_volume
- toc_issue_number
- toc_masthead (serialized array)
- toc_articles (serialized array)
- toc_featured (comma-delimited list)
- toc_highlighted (comma-delimited list)
- toc_sponsored (comma-delimited list)
- toc_issue_pdf

#### Plugin Options

wp_options table
- harbor_pubs (serialized array)

  - \[featured\] radio/checkbox
  - \[sponsored\] 1/0
  - \[post_ids\] 1/0
  - \[volnum\] 1/0