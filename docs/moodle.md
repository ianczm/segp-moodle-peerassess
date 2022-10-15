# Moodle

This will guide you to install Moodle 3.11.10+.

---

<br>

## Download Moodle

Your XAMPP installation is located under the path `/opt/lampp`.

The following commands will change to this path and make a backup of `htdocs` before replacing the contents with Moodle:

```bash
cd /opt/lampp
sudo cp -r htdocs htdocs-backup
sudo rm -rf htdocs/*
sudo git clone -b MOODLE_311_STABLE https://github.com/moodle/moodle.git htdocs
```

---

<br>

## Directory Permissions

### Identify Web User

The web server interacts with the XAMPP filesystem as the webuser. Appropriate permissions have to be assigned to allow normal operations.

Figure out the webuser with:

```bash
ps aux | egrep '(apache|httpd)'
```

The output can be `www-data` or `daemon` among others, and will be referred to as `<webuser>` from now on.

<br>

### Configure `htdocs`

This directory contains source files.

- The owner should be `root` or whichever user is responsible for maintaining Moodle.
- `webuser` should be given write permissions here to save configuration files.

Set configuration:

```bash
cd /opt/lampp
sudo chown -R <owner>:<webuser> /opt/lampp/htdocs
sudo chmod -R 0775 /opt/lampp/htdocs
```

<br>

### Create `moodledata` Directory

This directory allows Moodle to cache and store data, which should be kept separate from the source files in `htdocs`.

Create `/opt/lampp/moodledata`:

```bash
cd /opt/lampp
sudo mkdir moodledata
```

Give ownership of `moodledata` to the webuser, and read+execute permissions to other users.

```bash
cd /opt/lampp
sudo chown -R <webuser>:<webuser> /opt/lampp/moodledata
sudo chmod -R 0775 /opt/lampp/moodledata
```

---

<br>

## Install Moodle

If Moodle has been configured correctly, accessing `http://localhost` should redirect you to an installation page. Follow the prompts.

These are the default settings if you have followed this guide completely:

| Settings          | Value                                   |
| ----------------- | --------------------------------------- |
| Moodle Directory  | `/opt/lampp/htdocs`                     |
| Data Directory    | `/opt/lampp/moodledata`                 |
| Database Driver   | MariaDB (functionally similar to MySQL) |
| Database Host     | localhost                               |
| Database Name     | moodle                                  |
| Database User     | moodle                                  |
| Database Password | moodle                                  |
| Tables Prefix     | mdl_                                    |
| Database Port     | `<blank>`                               |
| Unix Socket       | `<blank>`                               |

Moodle will then begin all compatibility checks and install all core plugins. Keep following the prompts to perform the General and Admin User setups.

When installation completes successfully, you should see the Moodle Dashboard.

---

<br>

## Additional Developer Settings

Moodle can optionally output detailed technical information in the footer of each page.

You may configure this to your liking under `Site Administration > Development > Debugging`.

Here are some suggested changes:

| Settings               | Value     |
| ---------------------- | --------- |
| Debug Messages         | DEVELOPER |
| Display Debug Messages | Yes       |
| Performance Info       | Yes       |
| Show Page Information  | Yes       |

---

<br>

## Next: Install Peerassess Plugin

Click [here](/docs/plugin.md).
