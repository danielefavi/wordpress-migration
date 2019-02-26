# Wordpress Migration: standalone wordpress migration script.

Wordpress Migration helps you to migrate your wordpress website from development to production or from two different domains.

Wordpress Migration is not a plugin and no installation needed: this is a **standalone** tool that you have to run once and then delete it.

# How it works
1) Copy your files and database from the development server to the production server.
2) Open the file **wp-migrate.php** with a text editor and in the head of the file edit the database details of your production website.
3) Upload the file **wp-migrate.php** to a secret folder in the production server.
4) Open the with the browser (EG: _http://www.mywebsite.com/secret/folder/wp-migrate.php_).
5) Fill the form: in the **FROM** field place the development URL (EG: _http://localhost/mywebsite/_) and in the **TO** field place the URL of the production (EG: _https://www.mywebsite.com_).
6) Press the button **RUN THE MIGRATION**.
7) Delete the migration file.

# Disclaimer
Note that you can find better alternatives. This is a tool used by me only (so far); there are migration plugins used by thousands of people so they have been widely tested. Use _Wordpress Migration_ at your own risk.
You can use this script if you encountered problems during the migration process using other plugins.

This is a tool I made long ago (5 years ago or more) that I used quite a lot and lately I needed it again, so I decided to publish it. Since the wordpress structure is not changed from that time this tool is still a valid alternative.
