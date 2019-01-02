# Content Conversion - UK PDF

### Install Tools

Install the Xpdf tools on your Mac.

- The Xpdf tools can be found here: https://www.xpdfreader.com/download.html
- You only need the tools, not the full reader. The tools are available for Windows and Linux as well, but I have not tried any of this on those platforms.
- Documentation for each of the tools can be found here: https://www.xpdfreader.com/support.html

Download the PDF of the UK issue, drop it into its own folder on your machine.

- You might want to rename it something simple without spaces, like **uk_2019_01.pdf**.
- You'll be running a lot of terminal and bash scripts; long names with cases and spaces are just harder to deal with.

### Extract Text Content

Open the Terminal and navigate to the folder with the PDF.

Run **pdftohtml** on the PDF file to create interim HTML versions of every page in the magazine.

- `pdftohtml <switches> <source_file_name> <destination_folder_name>`
- `pdftohtml -q uk_2019_01.pdf ./html`
- The **-q** switch which suppresses errors.
- The **./html** puts the results into a folder names html inside your current folder.
- This will take a little while to run.
- Your destination folder should contain an HTML file and a PNG file for every page, as well as an index.html file.
- FTP to the XYZ production website, and navigate to the /wp-content/uploads/pre-import-images/xyz-uk-pdf-html/ folder.

Create a new folder with the year and month of your new issue; ie: **/2019_01/**

Copy your newly created HTML and PNG files to this folder.

In your browser, run **xyz-import-pdf.php** to import data from the PDF pages into the database.

- **`https://club.countryliving.com/wp-content/plugins/harbor-helpers/xyz-import-pdf.php?vol=<year>&num=<month>`**
- The vol and num values must be set properly to import the data.
- The data is imported into the temp_xyz_pdf table in the database.

The next step is to denote which pages of the PDF contain content, and which contain ads; the **PDF Ads** tool allows you to do this easily.

- **`https://club.countryliving.com/wp-content/plugins/harbor-helpers/xyz-pdf-ads.php?year=<year>&month=<month>`**
- Simply step through the issue and for each page click 'SKIP' for ads, or 'KEEP' for content. You can also click 'NEXT' if you'd like to review the page later.
- The three buttons also have keyboard shortcuts; A (skip), S (keep), D (next);
- The cover, masthead, all of the special advertising sections, and TOC fall into the 'SKIP' bin.

### Create Articles
Once you've separated the wheat from the chaff, so to speak, you'll need to manually create the table of contents and import it into the **temp_xyz_pdf_toc** table. This will allow the page-by-page content in the **temp_xyz_pdf** table to be tied together into articles when we import it into Harbor.

- Open the issue PDF file and page through to the table of contents; it typically starts around page 5 or 6, and always consists of two pages.
- In Excel, open a blank spreadsheet, you'll want to enter data into columns A through F in the following order: Year, Month, Section, Page, Title, Subheadline.
- Year is always 4 digits, and month is always numeric; 1 through 12.
- Section is uppercase, and should only be one of the following: HOUSES & GARDENS, FEATURES, FOOD & DRINK, HEALTH & BEAUTY, or NEWS & VIEWS. Once in a while they will use a slightly different section title in the TOC (ie: NEWS, VIEWS & EVENTS, or BEAUTY & FASHION; but stick to the list above)
- Titles are often in uppercase in the TOC, but should be in AP Title Case in your Excel document. You can convert text to AP Title Case at http://titlecase.com. If it's easier to cut-and-paste the uppercase titles into Excel, just do that and then copy the whole column into the tool at titlecase.com, convert it, and then paste it back into Excel.

Once your Excel spreadsheet is complete, you'll need to get this data into the database. Paste the following formula into every cell in column G, the creates your SQL.

**="INSERT INTO temp_xyz_pdf_toc (volume, number, section, page, title, deck) VALUES ("&A1&", "&B1&", '"&C1&"', "&D1&", '"&SUBSTITUTE(E1,"'","\'")&"', '"&SUBSTITUTE(F1,"'","\'")&"');"**

Open your SQL program, and go to the XYZ production database.

Copy all of column G from your Excel spreadsheet. Paste these lines into the SQL editor window. Run your SQL. This will insert the TOC data into the temp_xyz_pdf_toc table.

Don't exit your SQL program yet though, you still have to link the TOC data to the imported page-by-page content. To do so, run the following query.

```sql
UPDATE temp_xyz_pdf p
	JOIN temp_xyz_pdf_toc t
		ON (p.volume = t.volume) AND (p.number = t.number) AND (p.page = t.page)
SET p.title = t.title, p.subheadline = t.deck, p.section = t.section
WHERE (p.title IS NULL) AND (p.subheadline IS NULL) AND (p.section IS NULL);
```

### Insert Articles and TOC Data into Harbor

It's time to insert the articles into Harbor, you do this by running **xyz-create-tocs-from-pdf.php** in a browser.

- **`https://club.countryliving.com/wp-content/plugins/harbor-helpers/xyz-create-tocs-from-pdf.php?volume=<volume>&number=<number>`**
- The link above will only do a test run. To do a final run, add &run=true to the end of the URL.
- This script will add the raw text for all of your articles to Harbor in draft mode, and create your TOC in Harbor Pubs, also as a draft.

Check Harbor Pubs to be sure that you only created a single copy of the TOC, and that all of your articles have been added properly. When adding many issues I noticed that occasionally caching would somehow cause the script to run a few articles a second time, and create a second TOC in Harbor Pubs. I didn't see the issue when creating a single issue, but if this happens you should be able to safely delete the duplicates.

### Extract and Upload Images

Now we have to grab the images from the PDF. So jump back into your SQL program, we need to run a query.

```
SELECT CONCAT('pdfimages -f ', page, ' - l ', page, ' -j -q \'<your_pdf_filename>\' \'', volume, '_', number, '_', IFNULL(post_id, '00000'), '_', page, '\'')
FROM temp_xyz_pdf
WHERE (volume = <volume>) AND (number = <number>) AND (skip = 0)
ORDER BY page;
```

You'll want to change <your_pdf_filename> in the query to the actual name of your PDF; ie: uk_2019_01.pdf. You'll also need to insert the proper volume (year) and number (month) in the WHERE section.

Running this query generates the bulk of a bash script that you will use to call **pdfimages** for each active page in the document. This will export all of the images we need, sorted and named according to which page they are on.

So open a new text document on your Mac, and name it **extract_images.sh**, and save it into the folder that contains your PDF file.

At the top of the document, type #!/bin/bash, and on the next line, insert the entire output of the query above. Your file will look something like this, with a line for each page:

```
#!/bin/bash
pdfimages -f 9 -l 9 -j -q 'cl_2018_12.pdf' '2018_12_27116_009'
pdfimages -f 10 -l 10 -j -q 'cl_2018_12.pdf' '2018_12_27116_010'
pdfimages -f 12 -l 12 -j -q 'cl_2018_12.pdf' '2018_12_27116_012'
pdfimages -f 14 -l 14 -j -q 'cl_2018_12.pdf' '2018_12_27116_014'
...
```

Save your file, and then jump back to your Terminal window. If you're not still in the same folder as your PDF and your new bash script, navigate back there.

Run your bash script by entering the following into the Terminal: **sh extract_images.sh**

Go get a sandwich, this is going to take a while.

When this is done, you will have a few thousand images in that folder. Run **ls** in the Terminal window and watch them scroll by.

Note that we used the -j switch in pdfimages, which attempts to export files in JPG format, but many of the files will remain in the PDF-specific PBM, PPM, and PGM formats. Generally you can just delete these as they are mostly useless for our purposes. That being said, there might be a few images in the PPM format that are worth saving. If you're feeling up to it you can view the images in Adobe Bridge or some other equivalent software and check them for yourself. I did find that sometimes these images needed to be stitched back together before they could be used. For example a full page image might be broken into ten smaller images. Anyway, it may not be worth your time, but you will need to make that judgement on a case-by-case basis. If you do find a PPM that is worth saving, be sure to save it as a JPG. Nevertheless, as a rule of thumb on newer PDF files you can probably delete 99.9% of the images that are not in JPG format.

Open your FTP program and copy all of your new JPG files to the /uploads/pre-import-images/xyz-uk-images/ folder on the XYZ production server.

### Attach Images to Articles

Now we need to get a list of all of the JPG file names to import into the database. I've done this by exporting the transfer queue from FileZilla, but it's easier to do it in the Terminal window.

In the Terminal (still in the folder that contains your PDF and all of your new JPG files) type the following command: **ls *.jpg >> jpglist.txt**

Now, back in Excel, open **jpglist.txt**. We're going to generate some more SQL.

- Your list of JPG files will be in column A, duplicate column A into column B.
- Select all of column B, and under the Data tab, click Text to Columns.
- Choose Delimited on the first page of the popup, and click Next >.
- Under Delimiters on the next page, select Other, and type an underscore into the box. Click Finish
- Now you have your filename in column A, the year in column B, the month in column C, the post_id in column D, and column E contains the rest of the parsed filename, we need to fix that last part.
- Select all of column E, and then once again click Data > Text to Columns. You're going to do the same thing as before, but this time your delimiter is a hyphen.
- You now have valid page numbers in column E.
- The data in column F is not needed. Delete column F.

Now we'll actually generate our SQL. Into every cell in the new column F, past the following formula:

**="INSERT INTO temp_xyz_pdf_img (volume, number, maybe_post_id, page, filename) VALUES ("&B1&", "&C1&", "&D1&", "&E1&", '"&A1&"');"**

Column F now contains the valid SQL that will insert the images into the temp_xyz_pdf_img table.

Copy all of this SQL into the editor window in your SQL program and run all of the queries.

**You're done. You've successfully imported all of the data and images from the PDF file into Harbor.**

### Correlate Images and Format Posts

Well... okay, you're not really done. The data and images are in Harbor, but they remain to be correlated and formatted.

The correlation tool for the UK issues can be found here.

- **https://club.countryliving.com/wp-content/plugins/harbor-helpers/xyz-correlate-uk.php**

Unlike the correlation tool for the US issues, where all of the images are imported directly into Harbor, this tool is has a two step process where the images need to be selected and imported into Harbor before they can be inserted into the posts or attached as the featured image. This is due to the higher number of useless images found in the PDF exports.

Article formatting can also involve more work than it would with the US issues, since all of the content is simply extracted from the PDF and contains no intelligence or metadata whatsoever.

### Converting Other PDF Content

The procedure described above may be modified to convert older US issues in addition to UK issues.

All relevant tables include a pub_id column which is defaulted to 'UK', and all relevant scripts have been designed to respect the pub_id value.

All of the scripts accept a querystring variable named (**pub**) which can be used to target a different Pub ID. To convert older US content, add **&pub=US** to any script value above.

