# Moodle

## Download and Configure Moodle

Your XAMPP installation will be located under the path `/opt/lampp`. Change to that directory and make a backup of the `htdocs` folder which contains your web files:

```bash
cd /opt/lampp
sudo cp -r htdocs htdocs-backup
```

Enter your `htdocs` folder and remove everything inside:

```bash
cd htdocs
sudo rm -rf *
```

Clone the `MOODLE_311_STABLE` branch into `htdocs`, this folder will also be known as your `moodle` folder:

```bash
sudo git clone -b MOODLE_311_STABLE https://github.com/moodle/moodle.git .
```

Once you have cloned all the files, you have to set up further permissions for accessing `moodle` files.

Figure out which user the `apache2` web server service is using:

```bash
ps aux | egrep '(apache|httpd)'
```

For XAMPP, it is typically `daemon`, while standalone Apache2 installations can be `www-data`. We will refer to this as the `webusername`.

Create a new user group called `webadmin` and include into it your user as well as the `webusername`.

```bash
addgroup webadmin
adduser <webusername> webadmin
adduser <username> webadmin
```

Go back to your `/opt/lampp` directory and make a new folder called `moodledata`. This is required for Moodle to function:

```bash
cd /opt/lampp
sudo mkdir moodledata
```

Set permissions to allow all `webadmin` group users to read, write and execute files under the `htdocs` and `moodledata` folders. Other users will only have read access:

```bash
sudo chown -R <webusername>:webadmin /opt/lampp/htdocs
sudo chown -R <webusername>:webadmin /opt/lampp/moodledata
sudo chmod -R 0775 /opt/lampp/htdocs
sudo chmod -R 0775 /opt/lampp/moodledata
```

Reboot WSL and log back in for permissions to take effect.

```bash
exit
```

```powershell
wsl --terminate <instanceName>
wsl -d <instanceName>
```

---

## Install Moodle

If Moodle has been configured correctly, accessing `http://localhost` should redirect you to an installation page. Follow the prompts.

These are the default settings if you have followed this guide completely:

```
Moodle Directory:   /opt/lampp/htdocs
Data Directory:     /opt/lampp/moodledata
Database Driver:    MariaDB (functionally similar to MySQL)
Database Host:      localhost
Database Name:      moodle
Database User:      moodle
Database Password:  moodle
Tables Prefix:      mdl_
Database Port:      <blank>
Unix Socket:        <blank>
```

Moodle will then begin all compatibility checks and install all core plugins. Keep following the prompts to perform the General and Admin User setups.

When installation completes successfully, you should see the Moodle Dashboard.

---

## Additional Developer Settings

Moodle can optionally output detailed technical information in the footer of each page.

You may configure this to your liking under `Site Administration > Development > Debugging`.

Here are some suggested changes:

```
Debug Messages:           DEVELOPER
Display Debug Messages:   Yes
Performance Info:         Yes
Show Page Information:    Yes
```

---

## Next: Install Peerassess Plugin

Click [here](/docs/plugin.md).
