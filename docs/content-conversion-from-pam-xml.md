# Content Conversion - US PAM XML

### Download, Extract and Prepare Data Files

XYZ has been sending most of the US issues as ZIP files with the word "crops" in the title. These files will include the images for the issue, and should be a few hundred megabytes in size.

Download and extract the latest file to a local directory, I'll refer to this as your extract folder.

Create folder for the XML for this issue, we'll call this your xml folder. When naming your xml folder, use only alphanumeric characters, as this will make your life easier later.

Copy all of the PAM XML files to xml folder; there will be one XML file for each article. You do not need the other XML files, the ones with PSV in the name.

Create a folder for the images for this issue, the name here is less important. The images you extracted will be within a crops folder, inside the folder for each article. The goal here is to pull all of the images into one directory. however before you do this, there is an issue to be aware of. Some issues may contain multiple, different images which all use the same name! I believe this happens when the designer simply pasted an image into the original page-layout program, and the program has to create a name for the image. The duplicates will all have the word "embed" in the name.

> #### Dealing with Duplicate Image Names
>
> If you search your extract folder for image files with the word "embed" in the name, you will find any duplicates that exist. That being said, it is best to rename ALL FILES with the word "embed" in the image name, since those file names might already exist elsewhere on the server, even if not in this specific issue. The fix for this is relatively simple, just rename all "embed" images so they include the article code* at the beginning of the name, followed by an underscore.
> 
> \* The _article code_ is the base name of the XML file that contains data for that article. It is also used as the name of the folder in the original zip file that contained the XML and images for this article.
>
> For example, *01.13.02_embed.png* would become *CLX120118WELLhallmark_lo_01.13.02_embed.png*. If we follow this consistent format it will be easy to make sure that the names are changed in the database later. You will probably only run into two or three "embed" images.

Now that we've renamed the images, we have to make sure those names are used in the database as well. We haven't imported anything into the database yet, so we can easily search the XML files and update the image names now. Open your text editor, and with any luck it has a 'search all files in folder' capability. Search all of the files in your xml folder for the word "embed". If any instances are found you will need to to change the names here as well. If you had to change any file names, then you should definitely find some instances of that name in the XML. Simply change the filenames using the same pattern you used above; filename becomes article-code_filename. Save your changes and you're done.

Now, move your entire xml folder to the server, placing your new folder inside the /plugins/haven-helpers/ folder on the XYZ production server.

### Preparing Articles for Import

Run **/wp-content/plugins/xyz-import-xml.php?folder=<your_xml_folder_name>**

- This script creates entries in the _temp_xyz_content_, and _temp_xyz_media tables_. The first table includes the article content, and the second table is all media references within those articles.
- The script assumes that your folder is within the /plugins/haven/helpers/ folder, so be sure that's where it is.
- If your xml folder name is aphanumeric, as specified above, you're good to go just putting it in there. If you used any characters that are not simply letters or numbers, then you'll need to urlencode the name. Here's a link to a site that will urlencode things: https://www.urlencoder.org/.
- Once the process is complete, you can delete your xml folder from the production server.
- The volume number is rarely set in the XML files, but can be determined by subtracting 1977 from the current year. The script will do this automatically if it is missing. However if the issue number is missing you can add it in the XML, or even easier, just add **&num=<issue_number>** to the URL above.  

### Prepare Images for Import

Copy all images to /wp-content/uploads/pre-import-images/xyz-us-images/<your_image_folder_name>

Run /wp-content/plugins/xyz-media-tools.php?action=insert_files&folder=<your_image_folder_name>

- This script will create entries in the temp_xyz_files table for each file contained within the temporary folder.

Update the file_id and filename columns of the temp_xyz_media table with the following query:

```
UPDATE temp_xyz_media m JOIN temp_xyz_files f ON (m.image = f.filename)
SET m.file_id = f.id, m.filename = f.filename
WHERE (m.file_id IS NULL) AND (m.filename IS NULL);
```

Run **/wp-content/plugins/xyz-media-tools.php?action=update_media_ids**

- This script will update the temp_xyz_files table with all of the media_ids used for each image.

### Importing Articles and Creating Haven Pubs Issues

Run **/wp-content/plugins/xyz-create-tocs.php?volume=<volume>&number=<number>**

- This script will insert the articles into Haven, and once all of the articles are in place, it will create the TOCs that tie them together into an issue in Haven Pubs.
- After running this script, always review Haven pubs to be sure that your issue was created properly, and that all articles and the TOC are in DRAFT mode.

### Import Images into Wordpress

Run **/wp-content/plugins/xyz-attach.php?vol=<volume>&num=<number>**

- This script imports the images for your issue into Wordpress, creating all necessary images sizes and creating the attachment posts. It also updates the appropriate temp tables with post_id information for these images, so that the correlation tool can work properly.
- Because of the load this puts on the server and the possibility of timeouts, this script processes only five images at a time. **Run the script repeatedly until it has processed all of the images in your new issue.**

### Correlate Images and Format Articles

The actual import is now complete and the process moves to image correlation and article formatting.